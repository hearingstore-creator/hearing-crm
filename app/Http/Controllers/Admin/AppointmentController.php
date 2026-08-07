<?php

namespace App\Http\Controllers\Admin;

use App\Classes\SMS;
use App\Http\Controllers\Controller;
use App\Enums\AppointmentStatus;
use App\Enums\ServiceCategory;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\ProfessionalTimeSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;

class AppointmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:appointment_list.index')->only(['index']);
        $this->middleware('permission:add_appointment.create')->only(['create', 'store', 'getProfessionalsByService', 'getProfessionalSlots']);
        $this->middleware('permission:appointment_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:appointment_list.delete')->only(['destroy']);
        $this->middleware('permission:calendar_view.index')->only(['calendar', 'getCalendarData']);
    }

    public function index(Request $request): View
    {
        $query = Appointment::with(['lead', 'customer', 'user', 'service', 'professionalTimeSlot']);

        // Filter by professional
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by service
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Lead
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by Customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by Date Range
        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;

        if ($data['start_date'] && $data['end_date']) {
            $query->whereBetween('appointment_date', [$data['start_date'], $data['end_date']]);
        }

        $data['appointments'] = $query->orderBy('appointment_date', 'desc')->paginate(20);

        // For filters dropdown
        $data['professionals'] = User::where('type', 'professional')->where('status', 1)->orderBy('name')->get();
        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();
        $data['appointmentStatuses'] = AppointmentStatus::cases();

        return view('admin.appointment.index', $data);
    }

    public function create(): View
    {
        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();
        $data['professionals'] = User::where('type', 'professional')->where('status', 1)->with('timeSlots')->orderBy('name')->get();
        $data['appointmentStatuses'] = AppointmentStatus::cases();
        $data['serviceCategories'] = ServiceCategory::cases();
        $data['leads'] = Lead::select('id', 'full_name', 'phone_number')->orderBy('full_name')->get();
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();

        return view('admin.appointment.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_id'                  => 'nullable|integer|exists:leads,id',
            'customer_id'              => 'nullable|integer|exists:customers,id',
            'user_id'                  => 'required|integer|exists:users,id',
            'service_id'               => 'required|integer|exists:appointment_services,id',
            'professional_time_slot_id' => 'required|integer|exists:professional_time_slots,id',
            'appointment_date'         => 'required|date',
            'notes'                   => 'nullable|string',
        ]);

        // Ensure at least one of lead or customer is selected
        if (!$request->lead_id && !$request->customer_id) {
            return redirect()->back()->with('warning', 'Either lead or customer is required.')->withInput();
        }

        // Verify slot belongs to the selected professional
        $slotExists = ProfessionalTimeSlot::where('id', $validated['professional_time_slot_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();

        if (!$slotExists) {
            return redirect()->back()->with('warning', 'Invalid time slot for selected professional.')->withInput();
        }

        // Check slot availability
        $slotService = new SlotGeneratorService();
        $professional = User::findOrFail($validated['user_id']);

        if (!$slotService->isSlotAvailable($professional, $validated['appointment_date'], $validated['professional_time_slot_id'])) {
            return redirect()->back()->with('warning', 'This time slot is already booked.')->withInput();
        }

        $appointment = Appointment::create($validated);

        // Send SMS notification
        $phone_number = null;
        $recipient_name = null;

        if ($appointment->customer_id) {
            $customer = Customer::with('lead')->find($appointment->customer_id);
            if ($customer && $customer->lead) {
                $phone_number = $customer->lead->phone_number;
                $recipient_name = $customer->lead->full_name;
            }
        } elseif ($appointment->lead_id) {
            $lead = Lead::find($appointment->lead_id);
            if ($lead) {
                $phone_number = $lead->phone_number;
                $recipient_name = $lead->full_name;
            }
        }

        if ($phone_number) {
            $sms = new SMS();
            $slot = ProfessionalTimeSlot::find($appointment->professional_time_slot_id);
            $timeRange = $slot->timeSlot->start_time->format('g:i A') . ' - ' . $slot->timeSlot->end_time->format('g:i A');
            $appointmentDate = date('d M Y', strtotime($appointment->appointment_date));
            $serviceName = $appointment->service->name ?? 'N/A';
            $profName = $appointment->user->name ?? 'N/A';

            $messageBody = "Dear {$recipient_name},\n\nYour appointment has been scheduled!\n\nService: {$serviceName}\nProfessional: {$profName}\nDate: {$appointmentDate}\nTime: {$timeRange}\n\nBest regards,\nHearing Care Team";

            $rr = $sms->singleSms($phone_number, $messageBody, uniqid());
            Log::info("SMS sent to {$rr}");
        }

        return redirect('admin/appointments')->with('success', 'Appointment Booked Successfully');
    }

    public function edit(int $id): View
    {
        $data['appointment'] = Appointment::with(['lead', 'customer', 'user', 'service', 'professionalTimeSlot'])->findOrFail($id);
        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();
        $data['professionals'] = User::where('type', 'professional')->where('status', 1)->with('timeSlots')->orderBy('name')->get();
        $data['appointmentStatuses'] = AppointmentStatus::cases();
        $data['leads'] = Lead::select('id', 'full_name', 'phone_number')->orderBy('full_name')->get();
        $data['customers'] = Customer::with('lead')->orderBy('id', 'desc')->get();

        return view('admin.appointment.edit', $data);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'lead_id'                  => 'nullable|integer|exists:leads,id',
            'customer_id'              => 'nullable|integer|exists:customers,id',
            'user_id'                  => 'nullable|integer|exists:users,id',
            'service_id'               => 'nullable|integer|exists:appointment_services,id',
            'professional_time_slot_id' => 'nullable|integer|exists:professional_time_slots,id',
            'appointment_date'         => 'nullable|date',
            'status'                   => ['nullable', new Enum(AppointmentStatus::class)],
            'notes'                   => 'nullable|string',
        ]);

        // Check overlap if slot is being changed
        if (!empty($validated['professional_time_slot_id']) && !empty($validated['appointment_date'])) {
            $exists = Appointment::where('user_id', $validated['user_id'])
                ->where('appointment_date', $validated['appointment_date'])
                ->where('professional_time_slot_id', $validated['professional_time_slot_id'])
                ->where('id', '!=', $appointment->id)
                ->where('status', AppointmentStatus::SCHEDULED)
                ->exists();

            if ($exists) {
                return redirect()->back()->with('warning', 'This time slot is already booked.')->withInput();
            }
        }

        $appointment->update($validated);

        return redirect('admin/appointments')->with('success', 'Updated Successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        Appointment::findOrFail($id)->delete();

        return redirect('admin/appointments')->with('success', 'Deleted Successfully');
    }

    /**
     * AJAX: Get professionals offering a specific service.
     */
    public function getProfessionalsByService(int $serviceId): JsonResponse
    {
        $professionals = User::where('type', 'professional')
            ->where('status', 1)
            ->whereHas('appointmentServices', function ($q) use ($serviceId) {
                $q->where('appointment_services.id', $serviceId);
            })
            ->with('timeSlots')
            ->get(['id', 'name', 'designation', 'mobile_no']);

        return response()->json($professionals);
    }

    /**
     * AJAX: Get available slots for a professional on a date.
     */
    public function getProfessionalSlots(Request $request, int $user_id, string $date): JsonResponse
    {
        $professional = User::findOrFail($user_id);
        $slotService = new SlotGeneratorService();
        $excludeAppointmentId = $request->integer('exclude_appointment_id');
        $slots = $slotService->generate($professional, $date, $excludeAppointmentId);

        return response()->json($slots);
    }

    /**
     * AJAX: Update appointment status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $request->validate([
            'status' => ['required', new Enum(AppointmentStatus::class)],
        ]);

        $appointment->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated']);
    }

    /**
     * Calendar view page.
     */
    public function calendar(): View
    {
        $data['professionals'] = User::where('type', 'professional')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $data['timeSlots'] = TimeSlot::where('status', 1)
            ->orderBy('start_time')
            ->get();

        return view('admin.appointment.calendar', $data);
    }

    /**
     * AJAX: Get calendar data for a date range.
     */
    public function getCalendarData(Request $request): JsonResponse
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $userId = $request->input('user_id');

        if (!$start || !$end) {
            return response()->json([]);
        }

        $query = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'professionalTimeSlot.timeSlot'])
            ->whereBetween('appointment_date', [$start, $end]);

        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }

        $appointments = $query->get();

        // Summary stats — all-time counts (except today)
        $today = now()->format('Y-m-d');
        $statsQuery = Appointment::where('status', '!=', null);
        if ($userId && $userId !== 'all') {
            $statsQuery->where('user_id', $userId);
        }
        $statsBase = clone $statsQuery;
        $stats = [
            'total'     => $statsBase->count(),
            'today'     => Appointment::where('appointment_date', $today)
                ->when($userId && $userId !== 'all', fn($q) => $q->where('user_id', $userId))
                ->count(),
            'scheduled' => (clone $statsQuery)->where('status', AppointmentStatus::SCHEDULED)->count(),
            'cancelled' => (clone $statsQuery)->where('status', AppointmentStatus::CANCELLED)->count(),
            'completed' => (clone $statsQuery)->where('status', AppointmentStatus::COMPLETED)->count(),
        ];

        $data = $appointments->map(function ($a) {
            $patientName = $a->customer?->lead?->full_name ?? $a->lead?->full_name ?? 'Unknown';
            $startTime = $a->professionalTimeSlot?->timeSlot?->start_time;
            $endTime = $a->professionalTimeSlot?->timeSlot?->end_time;

            return [
                'id'           => $a->id,
                'date'         => $a->appointment_date?->format('Y-m-d'),
                'start_time'   => $startTime?->format('H:i'),
                'end_time'     => $endTime?->format('H:i'),
                'start_label'  => $startTime?->format('g:i A'),
                'end_label'    => $endTime?->format('g:i A'),
                'patient'      => $patientName,
                'service'      => $a->service?->name ?? '',
                'professional' => $a->user?->name ?? '',
                'professional_id' => $a->user_id,
                'status'       => $a->status?->value ?? '',
                'status_label' => $a->status?->label() ?? '',
                'color'        => $a->status?->colorHex() ?? '#007bff',
                'bg_color'     => $a->status?->bgColor() ?? '#e7f1ff',
                'notes'        => $a->notes ?? '',
            ];
        });

        return response()->json(['appointments' => $data->values(), 'stats' => $stats]);
    }
}
