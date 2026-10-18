<?php

namespace App\Http\Controllers\Api;

use App\Classes\Helper;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AppointmentService;
use App\Models\BranchSchedule;
use App\Models\Lead;
use App\Models\Resource;
use App\Models\User;
use App\Services\AppointmentAvailabilityEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicAppointmentController extends Controller
{
    /**
     * GET /api/v1/appointments/branches
     * Public list of clinic branches available for booking.
     */
    public function branches(): JsonResponse
    {
        $branchIds = BranchSchedule::where('is_closed', 0)
            ->pluck('vendor_id')
            ->unique();

        $branches = User::where('type', 'vendor')
            ->whereIn('id', $branchIds)
            ->get(['id', 'company_name', 'name', 'address', 'mobile_no']);

        $data = $branches->map(fn($b) => [
            'id'       => $b->id,
            'name'     => $b->company_name ?: $b->name,
            'address'  => $b->address,
            'phone'    => $b->mobile_no,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/appointments/services
     * Public list of clinical services.
     */
    public function services(): JsonResponse
    {
        $services = AppointmentService::with(['serviceType' => fn($q) => $q->where('status', 1)])
            ->where('status', 1)
            ->get();

        $data = $services->map(fn($s) => [
            'id'                => $s->id,
            'name'              => $s->name,
            'description'       => $s->description,
            'duration_minutes'  => $s->duration_minutes ?: 45,
            'requires_booth'    => (bool) $s->requires_resource,
            'price'             => $s->serviceType?->price ? (float) $s->serviceType->price : 0.00,
            'color'             => $s->color_hex ?: '#2563eb',
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/appointments/availability
     * Get available 15-minute start times.
     */
    public function availability(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'branch_id'  => 'required|integer|exists:users,id',
            'service_id' => 'required|integer|exists:appointment_services,id',
            'date'       => 'required|date|after_or_equal:today',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $engine = new AppointmentAvailabilityEngine();
        $res = $engine->getAvailableTimes(
            $request->integer('branch_id'),
            $request->integer('service_id'),
            $request->input('date')
        );

        return response()->json([
            'success'        => true,
            'date'           => $res['date'],
            'is_closed'      => $res['is_closed'],
            'closure_reason' => $res['closure_reason'],
            'slots'          => $res['slots'],
        ]);
    }

    /**
     * POST /api/v1/appointments/hold
     * Temporary 10-minute hold for public website checkout.
     */
    public function hold(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'branch_id'  => 'required|integer|exists:users,id',
            'service_id' => 'required|integer|exists:appointment_services,id',
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required|string',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $branchId = $request->integer('branch_id');
        $serviceId = $request->integer('service_id');
        $date = $request->input('date');
        $startTime = $request->input('start_time');

        $engine = new AppointmentAvailabilityEngine();
        $avail = $engine->getAvailableTimes($branchId, $serviceId, $date);

        $targetSlot = collect($avail['slots'])->firstWhere('time', substr($startTime, 0, 5));
        if (!$targetSlot) {
            return response()->json([
                'success' => false,
                'message' => 'The selected slot is no longer available. Please select another time.',
            ], 409);
        }

        $service = AppointmentService::findOrFail($serviceId);
        $duration = $service->duration_minutes ?: 45;
        $endTime = Carbon::parse($date . ' ' . $startTime)->addMinutes($duration)->format('H:i:s');

        $tz = 'Asia/Dhaka';
        $token = Str::random(40);
        $expiresAt = Carbon::now($tz)->addMinutes(10);

        $hold = AppointmentHold::create([
            'token'       => $token,
            'vendor_id'   => $branchId,
            'user_id'     => $targetSlot['user_id'] ?? null,
            'resource_id' => $targetSlot['resource_id'] ?? null,
            'service_id'  => $serviceId,
            'hold_date'   => $date,
            'start_time'  => $targetSlot['time_full'] ?? ($startTime . ':00'),
            'end_time'    => $endTime,
            'expires_at'  => $expiresAt,
            'ip_address'  => $request->ip(),
        ]);

        return response()->json([
            'success'    => true,
            'hold_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'message'    => 'Slot held for 10 minutes.',
        ]);
    }

    /**
     * POST /api/v1/appointments/book
     * Complete public website booking with anti-spam suite.
     */
    public function book(Request $request): JsonResponse
    {
        // 1. Anti-spam honeypot check
        if ($request->filled('website') || $request->filled('hp_email')) {
            // Silently accept bot submissions
            return response()->json(['success' => true, 'message' => 'Appointment request received.']);
        }

        // 2. Elapsed time check (> 3 seconds)
        $elapsed = (float) $request->input('elapsed_seconds', 0);
        if ($elapsed > 0 && $elapsed < 3.0) {
            return response()->json(['success' => true, 'message' => 'Appointment request received.']);
        }

        $v = Validator::make($request->all(), [
            'name'        => 'required|string|min:2|max:191',
            'phone'       => ['required', 'string', 'regex:/^(?:\+8801|8801|01)[3-9]\d{8}$/'],
            'email'       => 'nullable|email|max:191',
            'notes'       => 'nullable|string|max:1000',
            'hold_token'  => 'required|string|size:40',
        ], [
            'phone.regex' => 'Please provide a valid Bangladeshi mobile number (e.g. 017XXXXXXXX).',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $token = $request->input('hold_token');
        $tz = 'Asia/Dhaka';
        $now = Carbon::now($tz);

        $hold = AppointmentHold::where('token', $token)
            ->where('expires_at', '>', $now)
            ->first();

        if (!$hold) {
            return response()->json([
                'success' => false,
                'message' => 'Your 10-minute hold has expired. Please select a time slot again.',
            ], 410);
        }

        // Normalize phone number
        $rawPhone = $request->input('phone');
        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (strlen($digits) > 11 && str_starts_with($digits, '88')) {
            $digits = substr($digits, 2);
        }
        $phone = $digits;

        // Find or create lead
        $lead = Lead::where('phone_number', $phone)->first();
        if (!$lead) {
            $lead = Lead::create([
                'full_name'    => $request->input('name'),
                'phone_number' => $phone,
                'email'        => $request->input('email'),
                'vendor_id'    => $hold->vendor_id,
                'lead_source'  => 'website',
                'lead_status'  => 'New Lead',
            ]);
        }

        // Build booking data
        $service = AppointmentService::findOrFail($hold->service_id);
        $duration = $service->duration_minutes ?: 45;

        $bookingData = [
            'lead_id'          => $lead->id,
            'customer_id'      => $lead->customer?->id,
            'branch_id'        => $hold->vendor_id,
            'service_id'       => $hold->service_id,
            'user_id'          => $hold->user_id,
            'resource_id'      => $hold->resource_id,
            'appointment_date' => $hold->hold_date,
            'start_time'       => $hold->start_time,
            'end_time'         => $hold->end_time,
            'duration_minutes' => $duration,
            'status'           => AppointmentStatus::BOOKED, // Website arrivals arrive as 'booked'
            'source'           => 'website',
            'hold_token'       => $token,
            'notes'            => 'Website Booking. ' . ($request->input('notes') ?? ''),
        ];

        $engine = new AppointmentAvailabilityEngine();

        try {
            $appointment = $engine->bookWithLock($bookingData);
            $appointment->notifyBooked();

            return response()->json([
                'success'        => true,
                'appointment_id' => $appointment->id,
                'message'        => 'Thank you! Your appointment request has been received. Our clinic reception will call you shortly to confirm your booking.',
                'date'           => date('d M Y', strtotime($appointment->appointment_date)),
                'time'           => date('g:i A', strtotime($appointment->start_time)),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
