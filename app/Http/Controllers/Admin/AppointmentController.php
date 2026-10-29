<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\AppointmentStatus;
use App\Enums\ServiceCategory;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\ProfessionalTimeSlot;
use App\Models\Resource;
use App\Models\ServiceDetail;
use App\Models\TimeSlot;
use App\Models\User;
use App\Classes\Helper;
use App\Services\AppointmentAvailabilityEngine;
use App\Services\SlotGeneratorService;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;

class AppointmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:appointment_list.index')->only(['index', 'today']);
        $this->middleware('permission:add_appointment.create')->only(['create', 'store', 'getProfessionalsByService', 'getProfessionalSlots', 'searchPatient', 'quickLead', 'dynamicAvailability', 'quickWalkIn']);
        $this->middleware('permission:appointment_list.edit')->only(['edit', 'update', 'checkIn', 'startService', 'completeService', 'reschedule', 'updateStatus']);
        $this->middleware('permission:appointment_list.delete')->only(['destroy']);
        $this->middleware('permission:calendar_view.index')->only(['calendar', 'getCalendarData']);
    }

    public function index(Request $request): View
    {
        $query = Appointment::with(['lead', 'customer', 'user', 'service', 'professionalTimeSlot', 'saleMaster', 'serviceDetail', 'customerAdvance', 'branch', 'resource']);
        $query = DataScope::appointments($query);

        // Filter by branch (for HQ / Super Admin)
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

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
        $data['branches'] = Helper::branchOptions();
        $data['canViewAllBranches'] = auth()->check() ? auth()->user()->canViewAllBranches() : false;
        $data['professionals'] = User::where('type', 'professional')->where('status', 1)->orderBy('name')->get();
        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();
        $data['appointmentStatuses'] = AppointmentStatus::cases();

        return view('admin.appointment.index', $data);
    }

    /**
     * AJAX: Real-time search for patients (customers and leads) by phone number or name.
     */
    public function searchPatient(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $digits = preg_replace('/\D+/', '', $q);
        $phoneTail = strlen($digits) >= 5 ? substr($digits, -10) : null;

        // 1. Search Customers
        $customerQuery = Customer::with(['lead'])->where(function ($query) use ($q, $phoneTail) {
            $query->where('customer_uid', 'like', "%{$q}%")
                ->orWhere('membership_id', 'like', "%{$q}%")
                ->orWhereHas('lead', function ($lq) use ($q, $phoneTail) {
                    $lq->where('full_name', 'like', "%{$q}%");
                    if ($phoneTail) {
                        $lq->orWhere('phone_number', 'like', "%{$phoneTail}%")
                           ->orWhere('contact_person_phone', 'like', "%{$phoneTail}%");
                    }
                });
        });

        $customers = $customerQuery->limit(10)->get();
        $results = [];

        foreach ($customers as $c) {
            $lead = $c->lead;
            $results[] = [
                'type'         => 'customer',
                'id'           => $c->id,
                'customer_id'  => $c->id,
                'lead_id'      => $lead?->id,
                'name'         => $lead?->full_name ?? ('Customer #' . $c->customer_uid),
                'phone'        => $lead?->phone_number ?? 'N/A',
                'customer_uid' => $c->customer_uid,
                'badge'        => 'Customer',
                'badge_class'  => 'badge-success',
            ];
        }

        // 2. Search Leads (excluding those that are already customers)
        $customerLeadIds = $customers->pluck('lead_id')->filter()->all();
        $leadQuery = Lead::whereNotIn('id', $customerLeadIds)->where(function ($query) use ($q, $phoneTail) {
            $query->where('full_name', 'like', "%{$q}%")
                ->orWhere('lead_uid', 'like', "%{$q}%");
            if ($phoneTail) {
                $query->orWhere('phone_number', 'like', "%{$phoneTail}%")
                      ->orWhere('contact_person_phone', 'like', "%{$phoneTail}%");
            }
        });

        $leads = $leadQuery->limit(10)->get();
        foreach ($leads as $l) {
            $results[] = [
                'type'         => 'lead',
                'id'           => $l->id,
                'customer_id'  => null,
                'lead_id'      => $l->id,
                'name'         => $l->full_name,
                'phone'        => $l->phone_number,
                'customer_uid' => null,
                'badge'        => 'Lead',
                'badge_class'  => 'badge-info',
            ];
        }

        return response()->json($results);
    }

    /**
     * AJAX: Quick-create a new lead from within the booking wizard.
     */
    public function quickLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name'    => 'required|string|max:191',
            'phone_number' => 'required|string|max:30',
            'vendor_id'    => 'nullable|integer',
        ]);

        $vendorId = $validated['vendor_id'] ?: (auth()->check() ? auth()->user()->branchId() : null);

        // Check if phone already exists in leads
        $existing = Lead::where('phone_number', $validated['phone_number'])->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'is_existing' => true,
                'lead' => [
                    'id'           => $existing->id,
                    'name'         => $existing->full_name,
                    'phone'        => $existing->phone_number,
                    'customer_id'  => $existing->customer?->id,
                ],
                'message' => 'Found existing patient with this phone number.'
            ]);
        }

        $lead = Lead::create([
            'full_name'    => $validated['full_name'],
            'phone_number' => $validated['phone_number'],
            'vendor_id'    => $vendorId,
            'lead_source'  => 'reception',
            'lead_status'  => 'New Lead',
            'created_by'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'is_existing' => false,
            'lead' => [
                'id'           => $lead->id,
                'name'         => $lead->full_name,
                'phone'        => $lead->phone_number,
                'customer_id'  => null,
            ],
            'message' => 'New patient registered successfully.'
        ]);
    }

    /**
     * AJAX: Calculate dynamic available start times via the Availability Engine.
     */
    public function dynamicAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id'  => 'required|integer',
            'service_id' => 'required|integer',
            'date'       => 'required|date',
            'user_id'    => 'nullable|string',
        ]);

        $userId = $request->filled('user_id') && $request->user_id !== 'all' ? (int) $request->user_id : null;

        $engine = new AppointmentAvailabilityEngine();
        $availability = $engine->getAvailableTimes(
            $request->integer('branch_id'),
            $request->integer('service_id'),
            $request->input('date'),
            $userId
        );

        return response()->json($availability);
    }

    public function create(Request $request): View
    {
        // Branch options for branch dropdown
        $data['branches'] = Helper::branchOptions();
        $data['currentBranchId'] = Helper::currentBranchId() ?? 2; // Default to Mymensingh if HQ/Super Admin
        $data['canChangeBranch'] = auth()->user()->canViewAllBranches();

        // Clinical Services with their commercial ServiceType details
        $data['appointmentServices'] = AppointmentService::with('serviceType')
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $data['professionals'] = User::where('type', 'professional')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor_id', 'designation']);

        $data['appointmentStatuses'] = AppointmentStatus::cases();
        $data['serviceCategories'] = ServiceCategory::cases();

        // Support after-sale pre-filling:
        $data['serviceDetailId'] = $request->input('service_detail_id');
        $data['saleMasterId'] = $request->input('sale_master_id');
        $data['saleInvoiceNo'] = $request->input('sale_invoice_no');
        $data['preselectedCustomerId'] = $request->input('customer_id');
        $data['preselectedServiceId'] = $request->input('service_id');
        $data['serviceDetail'] = null;
        $data['preselectedCustomer'] = null;

        if ($data['preselectedCustomerId']) {
            $data['preselectedCustomer'] = Customer::with('lead')->find($data['preselectedCustomerId']);
        }

        if ($data['serviceDetailId']) {
            $serviceDetail = ServiceDetail::with(['serviceType.appointmentService', 'saleMaster.customer.lead'])->find($data['serviceDetailId']);
            if ($serviceDetail) {
                $data['serviceDetail'] = $serviceDetail;
                if (!$data['preselectedCustomerId'] && $serviceDetail->saleMaster?->customer_id) {
                    $data['preselectedCustomerId'] = $serviceDetail->saleMaster->customer_id;
                    $data['preselectedCustomer'] = $serviceDetail->saleMaster->customer;
                }
                if (!$data['saleMasterId']) {
                    $data['saleMasterId'] = $serviceDetail->sale_master_id;
                }
                if (!$data['saleInvoiceNo']) {
                    $data['saleInvoiceNo'] = $serviceDetail->saleMaster?->sale_invoice_no;
                }
                if (!$data['preselectedServiceId']) {
                    $data['preselectedServiceId'] = $serviceDetail->serviceType?->appointment_service_id;
                }
                if ($serviceDetail->saleMaster?->vendor_id) {
                    $data['currentBranchId'] = $serviceDetail->saleMaster->vendor_id;
                }
            }
        }

        return view('admin.appointment.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_id'           => 'nullable|integer|exists:leads,id',
            'customer_id'       => 'nullable|integer|exists:customers,id',
            'branch_id'         => 'required|integer|exists:users,id',
            'service_id'        => 'required|integer|exists:appointment_services,id',
            'user_id'           => 'required|integer|exists:users,id',
            'resource_id'       => 'nullable|integer|exists:resources,id',
            'appointment_date'  => 'required|date',
            'start_time'        => 'required|string',
            'notes'             => 'nullable|string',
            'service_detail_id' => 'nullable|integer|exists:service_details,id,deleted_at,NULL',
            'sale_master_id'    => 'nullable|integer|exists:sale_masters,id,deleted_at,NULL',
            'sale_invoice_no'   => 'nullable|string|max:100',
            'is_overbooked'     => 'nullable|boolean',
            'overbook_reason'   => 'nullable|string',
        ]);

        if (empty($validated['lead_id']) && empty($validated['customer_id'])) {
            return redirect()->back()->with('warning', 'Please select or enter a patient.')->withInput();
        }

        // Check overbooking permissions if overbook flag is set
        if (!empty($validated['is_overbooked'])) {
            $user = auth()->user();
            if (!$user->isSuperAdmin() && !$user->isVendor()) {
                return redirect()->back()->with('warning', 'Only Branch Managers and Super Admins can force an overbook.')->withInput();
            }
        }

        $service = AppointmentService::findOrFail($validated['service_id']);
        $validated['duration_minutes'] = $service->duration_minutes ?: 45;
        $validated['status'] = AppointmentStatus::SCHEDULED;
        $validated['source'] = 'internal';

        $engine = new AppointmentAvailabilityEngine();

        try {
            $appointment = $engine->bookWithLock($validated);
        } catch (\Throwable $e) {
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($e))->withInput();
        }

        // Process optional advance payment
        $advance = null;
        if (!empty($request->take_advance) && (float) $request->advance_amount > 0) {
            $advanceService = app(\App\Services\CustomerAdvanceService::class);
            $advance = $advanceService->recordAppointmentAdvance(
                $appointment,
                (float) $request->advance_amount,
                $request->advance_payment_method ?: 'cash',
                $request->advance_reference,
                $request->advance_notes
            );
        }

        // Link with sold service detail if booked from a sale
        if (!empty($validated['service_detail_id'])) {
            $sd = ServiceDetail::find($validated['service_detail_id']);
            if ($sd) {
                $sd->fulfillment_status = ServiceDetail::STATUS_BOOKED;
                $sd->appointment_id = $appointment->id;
                $sd->save();
            }
        }

        $appointment->notifyBooked();

        if (!empty($validated['sale_invoice_no'])) {
            return redirect()->route('sale.show', $validated['sale_invoice_no'])
                ->with('success', 'Appointment booked and linked to sold service successfully.');
        }

        if ($advance) {
            return redirect('admin/appointments')
                ->with('success', "Appointment booked successfully for " . date('d M Y', strtotime($appointment->appointment_date)) . " at " . date('g:i A', strtotime($appointment->start_time)) . ". Advance payment of ৳" . number_format($advance->amount, 2) . " received (Receipt #{$advance->receipt_no}).")
                ->with('receipt_url', route('appointments.advance-receipt', $appointment->id));
        }

        return redirect('admin/appointments')->with('success', "Appointment booked successfully for " . date('d M Y', strtotime($appointment->appointment_date)) . " at " . date('g:i A', strtotime($appointment->start_time)) . ".");
    }

    public function edit(int $id): View
    {
        $data['appointment'] = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'professionalTimeSlot', 'customerAdvance'])->findOrFail($id);
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
            'lead_id'                   => 'nullable|integer|exists:leads,id',
            'customer_id'               => 'nullable|integer|exists:customers,id',
            'user_id'                   => 'nullable|integer|exists:users,id',
            'service_id'                => 'nullable|integer|exists:appointment_services,id',
            'professional_time_slot_id'  => 'nullable|integer|exists:professional_time_slots,id',
            'appointment_date'          => 'nullable|date',
            'status'                    => ['nullable', new Enum(AppointmentStatus::class)],
            'notes'                     => 'nullable|string',
        ]);

        // Check overlap if slot is being changed
        if (!empty($validated['professional_time_slot_id']) && !empty($validated['appointment_date'])) {
            $exists = Appointment::where('user_id', $validated['user_id'])
                ->where('appointment_date', $validated['appointment_date'])
                ->where('professional_time_slot_id', $validated['professional_time_slot_id'])
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', [AppointmentStatus::SCHEDULED, AppointmentStatus::COMPLETED])
                ->exists();

            if ($exists) {
                return redirect()->back()->with('warning', 'This time slot is already booked.')->withInput();
            }
        }

        $appointment->update($validated);

        // If appointment was marked Completed, automatically mark linked service line as Done
        if (isset($validated['status'])) {
            $statusVal = is_object($validated['status']) ? $validated['status']->value : (string)$validated['status'];
            if ($statusVal === AppointmentStatus::COMPLETED->value) {
                $appointment->syncCompletedService();
            }
        }

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
     * Today's Front-Desk Walk-In & Service Queue Screen.
     */
    public function today(Request $request): View
    {
        $tz = config('app.timezone');
        $today = Carbon::today($tz)->format('Y-m-d');

        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : (Helper::currentBranchId() ?? 2);

        $query = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'branch', 'resource', 'customerAdvance'])
            ->where('appointment_date', $today);

        $query = DataScope::appointments($query);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $allToday = $query->orderByRaw("CASE WHEN start_time IS NULL THEN 1 ELSE 0 END, start_time ASC")->get();

        // Grouping by pipeline column
        $data['scheduled'] = $allToday->filter(function ($a) {
            $st = is_object($a->status) ? $a->status->value : (string) $a->status;
            return in_array($st, ['scheduled', 'booked', 'confirmed']) && !$a->needs_reschedule && !$a->is_time_unknown;
        });

        $data['arrived'] = $allToday->filter(function ($a) {
            $st = is_object($a->status) ? $a->status->value : (string) $a->status;
            return $st === 'arrived';
        });

        $data['inProgress'] = $allToday->filter(function ($a) {
            $st = is_object($a->status) ? $a->status->value : (string) $a->status;
            return $st === 'in_progress';
        });

        $data['completed'] = $allToday->filter(function ($a) {
            $st = is_object($a->status) ? $a->status->value : (string) $a->status;
            return $st === 'completed';
        });

        // Special queues
        $data['timeUnknown'] = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'branch'])
            ->where('is_time_unknown', 1)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $data['needsReschedule'] = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'branch'])
            ->where('needs_reschedule', 1)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        $data['todayDateFormatted'] = Carbon::today($tz)->format('l, d F Y');
        $data['branches'] = Helper::branchOptions();
        $data['currentBranchId'] = $branchId;
        $data['canViewAllBranches'] = auth()->check() ? auth()->user()->canViewAllBranches() : false;

        // Active specialists for quick walk-in
        $data['specialists'] = User::where('type', 'professional')->where('status', 1)->orderBy('name')->get();
        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();

        return view('admin.appointment.today', $data);
    }

    /**
     * Receptionist action: Mark patient as Arrived.
     */
    public function checkIn(int $id): JsonResponse|RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);
        $oldStatus = $appointment->status;
        $appointment->status = AppointmentStatus::ARRIVED;
        $appointment->save();

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
            'to_status'      => AppointmentStatus::ARRIVED->value,
            'old_date'       => $appointment->appointment_date,
            'new_date'       => $appointment->appointment_date,
            'old_start_time' => $appointment->start_time,
            'new_start_time' => $appointment->start_time,
            'old_user_id'    => $appointment->user_id,
            'new_user_id'    => $appointment->user_id,
            'reason'         => 'Patient arrived at reception and checked in',
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Patient marked as Arrived.']);
        }

        return redirect()->back()->with('success', 'Patient marked as Arrived.');
    }

    /**
     * Receptionist / Specialist action: Start consultation/test (In Progress).
     */
    public function startService(int $id): JsonResponse|RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);
        $oldStatus = $appointment->status;
        $appointment->status = AppointmentStatus::IN_PROGRESS;
        $appointment->save();

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
            'to_status'      => AppointmentStatus::IN_PROGRESS->value,
            'old_date'       => $appointment->appointment_date,
            'new_date'       => $appointment->appointment_date,
            'old_start_time' => $appointment->start_time,
            'new_start_time' => $appointment->start_time,
            'old_user_id'    => $appointment->user_id,
            'new_user_id'    => $appointment->user_id,
            'reason'         => 'Consultation/procedure started in room',
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Service marked as In Progress.']);
        }

        return redirect()->back()->with('success', 'Service marked as In Progress.');
    }

    /**
     * Mark appointment as Completed and sync sold services.
     */
    public function completeService(int $id): JsonResponse|RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);
        $oldStatus = $appointment->status;
        $appointment->status = AppointmentStatus::COMPLETED;
        $appointment->save();

        $appointment->syncCompletedService();

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
            'to_status'      => AppointmentStatus::COMPLETED->value,
            'old_date'       => $appointment->appointment_date,
            'new_date'       => $appointment->appointment_date,
            'old_start_time' => $appointment->start_time,
            'new_start_time' => $appointment->start_time,
            'old_user_id'    => $appointment->user_id,
            'new_user_id'    => $appointment->user_id,
            'reason'         => 'Appointment completed',
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Appointment completed successfully.']);
        }

        return redirect()->back()->with('success', 'Appointment completed successfully.');
    }

    /**
     * Reschedule appointment: updates time, writes audit log, and notifies via SMS.
     */
    public function reschedule(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'appointment_date' => 'required|date',
            'start_time'       => 'required|string',
            'user_id'          => 'nullable|integer|exists:users,id',
            'resource_id'      => 'nullable|integer|exists:resources,id',
            'reason'           => 'required|string|max:500',
        ]);

        $userId = $validated['user_id'] ?: $appointment->user_id;
        $branchId = $appointment->branch_id ?: Helper::currentBranchId();
        $serviceId = $appointment->service_id;
        $duration = $appointment->duration_minutes ?: 45;
        $startTime = $validated['start_time'];
        $newDate = $validated['appointment_date'];

        $engine = new AppointmentAvailabilityEngine();
        $availability = $engine->getAvailableTimes($branchId, $serviceId, $newDate, $userId);
        $foundSlot = collect($availability['slots'])->firstWhere('time', substr($startTime, 0, 5));

        if (!$foundSlot) {
            $msg = 'Selected time is not available. Please choose another time.';
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('warning', $msg);
        }

        $oldDate = $appointment->appointment_date;
        $oldStartTime = $appointment->start_time;
        $oldUserId = $appointment->user_id;
        $oldStatus = $appointment->status;

        $endTime = Carbon::parse($newDate . ' ' . $startTime)->addMinutes($duration)->format('H:i:s');

        $appointment->appointment_date = $newDate;
        $appointment->start_time = $startTime;
        $appointment->end_time = $endTime;
        $appointment->user_id = $userId;
        $appointment->resource_id = $foundSlot['resource_id'] ?: ($validated['resource_id'] ?? null);
        $appointment->is_time_unknown = 0;
        $appointment->needs_reschedule = 0;
        $appointment->status = AppointmentStatus::SCHEDULED;
        $appointment->save();

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
            'to_status'      => AppointmentStatus::SCHEDULED->value,
            'old_date'       => $oldDate,
            'new_date'       => $newDate,
            'old_start_time' => $oldStartTime,
            'new_start_time' => $startTime,
            'old_user_id'    => $oldUserId,
            'new_user_id'    => $userId,
            'reason'         => 'Rescheduled: ' . $validated['reason'],
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        $appointment->notifyRescheduled();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled to ' . date('d M Y', strtotime($newDate)) . ' at ' . date('g:i A', strtotime($startTime)) . '. Patient notified via SMS.',
            ]);
        }

        return redirect()->back()->with('success', 'Appointment rescheduled successfully. Patient notified via SMS.');
    }

    /**
     * Front-Desk Quick Walk-in Intake.
     */
    public function quickWalkIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_name' => 'required|string|max:191',
            'patient_phone'=> 'required|string|max:30',
            'branch_id'    => 'required|integer|exists:users,id',
            'service_id'   => 'required|integer|exists:appointment_services,id',
            'user_id'      => 'required|integer|exists:users,id',
            'resource_id'  => 'nullable|integer|exists:resources,id',
            'notes'        => 'nullable|string',
        ]);

        // Find or create lead
        $lead = Lead::where('phone_number', $validated['patient_phone'])->first();
        if (!$lead) {
            $lead = Lead::create([
                'full_name'    => $validated['patient_name'],
                'phone_number' => $validated['patient_phone'],
                'vendor_id'    => $validated['branch_id'],
                'lead_source'  => 'walk_in',
                'lead_status'  => 'New Lead',
                'created_by'   => auth()->id(),
            ]);
        }

        $service = AppointmentService::findOrFail($validated['service_id']);
        $duration = $service->duration_minutes ?: 45;

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $minRem = $now->minute % 15;
        if ($minRem > 0) {
            $now->subMinutes($minRem);
        }
        $startTime = $now->format('H:i:s');
        $endTime = $now->copy()->addMinutes($duration)->format('H:i:s');

        $appointment = Appointment::create([
            'lead_id'          => $lead->id,
            'customer_id'      => $lead->customer?->id,
            'branch_id'        => $validated['branch_id'],
            'service_id'       => $validated['service_id'],
            'user_id'          => $validated['user_id'],
            'resource_id'      => $validated['resource_id'] ?? null,
            'appointment_date' => Carbon::today($tz)->format('Y-m-d'),
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $duration,
            'status'           => AppointmentStatus::ARRIVED,
            'source'           => 'walk_in',
            'notes'            => 'Front-Desk Quick Walk-in. ' . ($validated['notes'] ?? ''),
            'created_by'       => auth()->id(),
        ]);

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => 'none',
            'to_status'      => AppointmentStatus::ARRIVED->value,
            'old_date'       => null,
            'new_date'       => $appointment->appointment_date,
            'old_start_time' => null,
            'new_start_time' => $appointment->start_time,
            'old_user_id'    => null,
            'new_user_id'    => $appointment->user_id,
            'reason'         => 'Quick Walk-In Registration',
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        return redirect()->route('appointments.today', ['branch_id' => $validated['branch_id']])
            ->with('success', "Walk-in patient {$lead->full_name} added to today's queue in Arrived status.");
    }

    /**
     * AJAX: Generic status update.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $request->validate([
            'status' => ['required', new Enum(AppointmentStatus::class)],
        ]);

        $oldStatus = $appointment->status;
        $appointment->update(['status' => $request->status]);

        AppointmentHistory::create([
            'appointment_id' => $appointment->id,
            'from_status'    => is_object($oldStatus) ? $oldStatus->value : (string) $oldStatus,
            'to_status'      => is_object($request->status) ? $request->status->value : (string) $request->status,
            'old_date'       => $appointment->appointment_date,
            'new_date'       => $appointment->appointment_date,
            'old_start_time' => $appointment->start_time,
            'new_start_time' => $appointment->start_time,
            'old_user_id'    => $appointment->user_id,
            'new_user_id'    => $appointment->user_id,
            'reason'         => 'Status updated via action menu',
            'changed_by'     => auth()->id(),
            'created_at'     => now(),
        ]);

        $statusVal = is_object($request->status) ? $request->status->value : (string) $request->status;
        if ($statusVal === AppointmentStatus::COMPLETED->value) {
            $appointment->syncCompletedService();
        }

        return response()->json(['message' => 'Status updated']);
    }

    /**
     * Interactive Multi-Column Calendar View.
     */
    public function calendar(): View
    {
        $data['branches'] = Helper::branchOptions();
        $data['currentBranchId'] = Helper::currentBranchId() ?? 2;
        $data['canViewAllBranches'] = auth()->check() ? auth()->user()->canViewAllBranches() : false;

        $data['professionals'] = User::where('type', 'professional')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor_id', 'designation']);

        $data['appointmentServices'] = AppointmentService::where('status', 1)->orderBy('name')->get();

        return view('admin.appointment.calendar', $data);
    }

    /**
     * AJAX: Get calendar data for date range, branch, and specialists.
     */
    public function getCalendarData(Request $request): JsonResponse
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        $userId = $request->input('user_id');
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : Helper::currentBranchId();

        if (!$start || !$end) {
            return response()->json([]);
        }

        $query = Appointment::with(['lead', 'customer.lead', 'user', 'service', 'professionalTimeSlot.timeSlot', 'branch', 'resource', 'customerAdvance'])
            ->whereBetween('appointment_date', [$start, $end]);

        $query = DataScope::appointments($query);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }

        $appointments = $query->get();

        // Roster of specialists and resources for this branch
        $specialistsQuery = User::where('type', 'professional')->where('status', 1);
        if ($branchId) {
            $specialistsQuery->where(function ($q) use ($branchId) {
                $q->where('vendor_id', $branchId)
                  ->orWhereNull('vendor_id')
                  ->orWhereHas('staffSchedules', fn($sq) => $sq->where('vendor_id', $branchId)->where('is_active', true));
            });
        }
        $branchSpecialists = $specialistsQuery->get(['id', 'name', 'designation']);

        $branchResources = Resource::where('is_active', true)
            ->when($branchId, fn($q) => $q->where('vendor_id', $branchId))
            ->get(['id', 'name', 'type']);

        // Stats
        $today = Carbon::today(config('app.timezone'))->format('Y-m-d');
        $statsQuery = Appointment::whereNotNull('status');
        if ($branchId) {
            $statsQuery->where('branch_id', $branchId);
        }
        if ($userId && $userId !== 'all') {
            $statsQuery->where('user_id', $userId);
        }
        $statsBase = clone $statsQuery;
        $stats = [
            'total'     => $statsBase->count(),
            'today'     => (clone $statsQuery)->where('appointment_date', $today)->count(),
            'scheduled' => (clone $statsQuery)->whereIn('status', [AppointmentStatus::SCHEDULED, AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED])->count(),
            'arrived'   => (clone $statsQuery)->where('status', AppointmentStatus::ARRIVED)->count(),
            'completed' => (clone $statsQuery)->where('status', AppointmentStatus::COMPLETED)->count(),
            'cancelled' => (clone $statsQuery)->whereIn('status', [AppointmentStatus::CANCELLED, AppointmentStatus::CANCELLED_REFUNDED, AppointmentStatus::CANCELLED_CREDIT_KEPT])->count(),
        ];

        $data = $appointments->map(function ($a) {
            $patient = $a->customer?->lead ?? $a->lead;
            $patientName = $patient?->full_name ?? ($a->customer ? 'Customer #'.$a->customer_id : 'Unknown');
            $patientPhone = $patient?->phone_number ?? '';

            $startTime = $a->start_time;
            $endTime = $a->end_time;
            if (!$startTime && $a->professionalTimeSlot?->timeSlot) {
                $startTime = $a->professionalTimeSlot->timeSlot->start_time?->format('H:i:s');
                $endTime = $a->professionalTimeSlot->timeSlot->end_time?->format('H:i:s');
            }

            $startHi = $startTime ? substr($startTime, 0, 5) : '';
            $endHi = $endTime ? substr($endTime, 0, 5) : '';
            $startLabel = $startTime ? date('g:i A', strtotime($startTime)) : 'Time Unknown';
            $endLabel = $endTime ? date('g:i A', strtotime($endTime)) : '';

            return [
                'id'                => $a->id,
                'date'              => $a->appointment_date?->format('Y-m-d'),
                'start_time'        => $startHi,
                'end_time'          => $endHi,
                'start_label'       => $startLabel,
                'end_label'         => $endLabel,
                'patient'           => $patientName,
                'phone'             => $patientPhone,
                'service'           => $a->service?->name ?? 'Clinical Service',
                'service_id'        => $a->service_id,
                'duration'          => $a->duration_minutes ?: 45,
                'professional'      => $a->user?->name ?? 'Unassigned',
                'professional_id'   => $a->user_id,
                'resource_id'       => $a->resource_id,
                'resource_name'     => $a->resource?->name,
                'branch_id'         => $a->branch_id,
                'branch_name'       => $a->branch?->company_name ?? $a->branch?->name,
                'status'            => is_object($a->status) ? $a->status->value : (string) $a->status,
                'status_label'      => is_object($a->status) ? $a->status->label() : ucfirst((string) $a->status),
                'color'             => is_object($a->status) ? $a->status->colorHex() : '#007bff',
                'bg_color'          => is_object($a->status) ? $a->status->bgColor() : '#eff6ff',
                'is_time_unknown'   => (bool) $a->is_time_unknown,
                'needs_reschedule'  => (bool) $a->needs_reschedule,
                'is_overbooked'     => (bool) $a->is_overbooked,
                'advance_amount'    => $a->customerAdvance?->amount ? (float) $a->customerAdvance->amount : 0,
                'receipt_url'       => $a->customerAdvance ? route('appointments.advance-receipt', $a->id) : null,
                'notes'             => $a->notes ?? '',
            ];
        });

        return response()->json([
            'appointments' => $data->values(),
            'specialists'  => $branchSpecialists,
            'resources'    => $branchResources,
            'stats'        => $stats,
        ]);
    }
}
