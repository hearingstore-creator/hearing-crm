<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\Designation;
use App\Models\AppointmentService;
use App\Models\Day;
use App\Models\ProfessionalTimeSlot;
use App\Models\ProfessionalWorkingDay;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Enum;

class ProfessionalController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:professional_list.index')->only(['index']);
        $this->middleware('permission:add_professional.create')->only(['create', 'store']);
        $this->middleware('permission:professional_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:professional_list.delete')->only(['destroy']);
    }

    public function index(): View
    {
        $data['professionals'] = User::where('type', 'professional')
            ->with('workingDays.day', 'timeSlots', 'appointmentServices', 'branch:id,name')
            ->orderBy('id', 'desc')
            ->get();

        $data['designations'] = Designation::cases();

        return view('admin.professional.index', $data);
    }

    public function create(): View
    {
        $data['designations'] = Designation::cases();
        $data['days'] = Day::orderBy('id')->get();
        $data['appointmentServices'] = AppointmentService::where('status', 1)->get();
        $data['timeSlots'] = TimeSlot::where('status', 1)->orderBy('start_time', 'asc')->get();

        return view('admin.professional.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $days = Day::pluck('id')->toArray();

        $this->validate($request, [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:6',
            'designation'  => ['required', new Enum(Designation::class)],
            'mobile_no'    => 'nullable|string|max:20',
            'status'       => 'nullable|in:0,1',
            'services'     => 'required|array|min:1',
            'services.*'   => 'exists:appointment_services,id',
            'time_slots'   => 'nullable|array',
            'time_slots.*' => 'array',
        ]);

        DB::transaction(function () use ($request, $days) {
            // Create user with type = professional
            $user = User::create([
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'type'        => 'professional',
                'designation' => $request->designation,
                'mobile_no'   => $request->mobile_no,
                'status'      => $request->status ?? 1,
                'vendor_id'   => \App\Classes\Helper::staffBranchFromRequest($request->vendor_id),
            ]);

            // Sync services
            $user->appointmentServices()->sync($request->services);

            // Save day-wise time slots
            ProfessionalTimeSlot::where('user_id', $user->id)->delete();
            if (!empty($request->time_slots)) {
                foreach ($request->time_slots as $dayId => $slotIds) {
                    if (!in_array($dayId, $days) || empty($slotIds)) continue;
                    foreach ($slotIds as $slotId) {
                        ProfessionalTimeSlot::create([
                            'user_id'     => $user->id,
                            'time_slot_id' => $slotId,
                            'day_id'      => $dayId,
                        ]);
                    }
                }
            }

            // Upsert working days
            foreach ($days as $dayId) {
                $isActive = isset($request->time_slots[$dayId]) && count($request->time_slots[$dayId]) > 0;
                ProfessionalWorkingDay::updateOrCreate(
                    ['user_id' => $user->id, 'day_id' => $dayId],
                    ['is_active' => $isActive],
                );
            }
        });

        return redirect('admin/professionals')->with('success', 'Saved Successfully');
    }

    public function edit(int $id): View
    {
        $data['professional'] = User::where('type', 'professional')
            ->with(['workingDays.day', 'timeSlots', 'appointmentServices', 'professionalTimeSlots.day'])
            ->findOrFail($id);

        $data['designations'] = Designation::cases();
        $data['days'] = Day::orderBy('id')->get();
        $data['appointmentServices'] = AppointmentService::where('status', 1)->get();
        $data['timeSlots'] = TimeSlot::where('status', 1)->orderBy('start_time', 'asc')->get();

        return view('admin.professional.edit', $data);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $professional = User::where('type', 'professional')->findOrFail($id);
        $days = Day::pluck('id')->toArray();

        $this->validate($request, [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $id,
            'designation'  => ['required', new Enum(Designation::class)],
            'mobile_no'    => 'nullable|string|max:20',
            'status'       => 'nullable|in:0,1',
            'services'     => 'required|array|min:1',
            'services.*'   => 'exists:appointment_services,id',
            'time_slots'   => 'nullable|array',
            'time_slots.*' => 'array',
        ]);

        DB::transaction(function () use ($request, $professional, $days) {
            $professional->update([
                'name'        => $request->name,
                'email'       => $request->email,
                'designation' => $request->designation,
                'mobile_no'   => $request->mobile_no,
                'status'      => $request->status ?? 1,
                'vendor_id'   => \App\Classes\Helper::staffBranchFromRequest($request->vendor_id),
            ]);

            if ($request->password) {
                $professional->password = Hash::make($request->password);
                $professional->save();
            }

            // Sync services
            $professional->appointmentServices()->sync($request->services);

            // Save day-wise time slots
            ProfessionalTimeSlot::where('user_id', $professional->id)->delete();
            if (!empty($request->time_slots)) {
                foreach ($request->time_slots as $dayId => $slotIds) {
                    if (!in_array($dayId, $days) || empty($slotIds)) continue;
                    foreach ($slotIds as $slotId) {
                        ProfessionalTimeSlot::create([
                            'user_id'     => $professional->id,
                            'time_slot_id' => $slotId,
                            'day_id'      => $dayId,
                        ]);
                    }
                }
            }

            // Upsert working days
            foreach ($days as $dayId) {
                $isActive = isset($request->time_slots[$dayId]) && count($request->time_slots[$dayId]) > 0;
                ProfessionalWorkingDay::updateOrCreate(
                    ['user_id' => $professional->id, 'day_id' => $dayId],
                    ['is_active' => $isActive],
                );
            }
        });

        return redirect('admin/professionals')->with('success', 'Updated Successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        User::where('type', 'professional')->findOrFail($id)->delete();

        return redirect('admin/professionals')->with('success', 'Deleted Successfully');
    }
}
