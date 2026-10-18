<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Services\CustomerAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

class CustomerAdvanceController extends Controller
{
    protected CustomerAdvanceService $advanceService;

    public function __construct(CustomerAdvanceService $advanceService)
    {
        $this->advanceService = $advanceService;
    }

    /**
     * AJAX endpoint: Get active advances for a customer when creating/editing a sale.
     */
    public function getActiveAdvance(int $customerId): JsonResponse
    {
        $customer = Customer::with(['lead:id,full_name,phone_number'])->find($customerId);
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $activeAdvances = CustomerAdvance::where('customer_id', $customerId)
            ->where('status', CustomerAdvance::STATUS_ACTIVE)
            ->where('balance', '>', 0)
            ->with(['appointment'])
            ->orderBy('id', 'desc')
            ->get();

        $totalBalance = (float) $activeAdvances->sum('balance');

        return response()->json([
            'success'       => true,
            'total_balance' => $totalBalance,
            'advances'      => $activeAdvances->map(fn ($adv) => [
                'id'             => $adv->id,
                'receipt_no'     => $adv->receipt_no,
                'date'           => $adv->date?->format('d M Y'),
                'amount'         => $adv->amount,
                'balance'        => $adv->balance,
                'appointment_id' => $adv->appointment_id,
                'appointment_date' => $adv->appointment?->appointment_date?->format('d M Y'),
                'payment_method' => ucfirst($adv->payment_method),
            ]),
        ]);
    }

    /**
     * Store advance payment on an appointment.
     */
    public function recordAppointmentAdvance(Request $request, int $appointmentId)
    {
        $appointment = Appointment::with(['lead', 'customer'])->findOrFail($appointmentId);

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:1|max:9999999',
            'payment_method' => 'required|string|in:cash,bkash,nagad,card,bank',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:500',
        ]);

        try {
            $advance = $this->advanceService->recordAppointmentAdvance(
                $appointment,
                (float) $validated['amount'],
                $validated['payment_method'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success'     => true,
                    'message'     => "Advance payment of ৳{$advance->amount} recorded successfully. Receipt #{$advance->receipt_no}",
                    'receipt_url' => route('appointments.advance-receipt', $appointment->id),
                    'advance'     => $advance,
                ]);
            }

            return redirect()->back()->with('success', "Advance payment of ৳{$advance->amount} recorded successfully. Receipt #{$advance->receipt_no}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Appointment Advance Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Could not record advance payment. Please try again.'], 500);
            }
            return redirect()->back()->with('warning', 'Could not record advance payment: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Process advance refund or retain as customer credit.
     * Requires permission: advance.refund
     */
    public function refund(Request $request, int $advanceId): JsonResponse
    {
        // Enforce advance.refund permission
        if (!auth()->user()->can('advance.refund') && !Helper::hasPermission('advance.refund') && auth()->user()->type !== 'super_admin' && auth()->user()->type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have permission to refund customer advances (advance.refund).',
            ], 403);
        }

        $advance = CustomerAdvance::findOrFail($advanceId);

        $validated = $request->validate([
            'action_type'    => 'required|string|in:refund,credit_kept',
            'refund_amount'  => 'required_if:action_type,refund|numeric|min:0|max:' . $advance->balance,
            'deduction_fee'  => 'nullable|numeric|min:0|max:' . $advance->balance,
            'refund_method'  => 'required_if:action_type,refund|string|in:cash,bkash,nagad,card,bank',
            'reference'      => 'nullable|string|max:100',
            'reason'         => 'required|string|min:3|max:1000',
        ]);

        try {
            $actionType = $validated['action_type'];
            $refundAmount = $actionType === 'refund' ? (float) ($validated['refund_amount'] ?? 0) : 0;
            $deductionFee = $actionType === 'refund' ? (float) ($validated['deduction_fee'] ?? 0) : 0;

            $updated = $this->advanceService->processRefund(
                $advance,
                $refundAmount,
                $deductionFee,
                $validated['refund_method'] ?? 'cash',
                $validated['reference'] ?? null,
                $validated['reason'],
                $actionType
            );

            $msg = $actionType === 'credit_kept'
                ? "Advance balance of ৳{$advance->balance} retained as credit on customer account."
                : "Advance refund of ৳{$refundAmount} processed successfully (Fee: ৳{$deductionFee}). Receipt: {$updated->refund_receipt_no}";

            return response()->json([
                'success'           => true,
                'message'           => $msg,
                'action_type'       => $actionType,
                'refund_receipt_url'=> $actionType === 'refund' ? route('advances.refund-receipt', $updated->id) : null,
                'advance'           => $updated,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Advance Refund Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error processing refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Print Money Receipt for an appointment advance.
     */
    public function advanceReceipt(int $appointmentId): View
    {
        $appointment = Appointment::with([
            'customer.lead',
            'lead',
            'customerAdvance',
            'user',
            'service',
            'professionalTimeSlot'
        ])->findOrFail($appointmentId);

        $advance = $appointment->customerAdvance;
        if (!$advance) {
            abort(404, 'No advance payment record found for this appointment.');
        }

        $branding = Helper::invoiceBranding($appointment->user?->branchId() ?? $advance->vendor_id);

        return view('admin.appointment.advance_receipt', compact('appointment', 'advance', 'branding'));
    }

    /**
     * Print Refund Receipt for an advance refund.
     */
    public function refundReceipt(int $advanceId): View
    {
        $advance = CustomerAdvance::with([
            'customer.lead',
            'lead',
            'appointment.user',
            'appointment.service',
            'refundByUser',
            'createdByUser'
        ])->findOrFail($advanceId);

        $branding = Helper::invoiceBranding($advance->vendor_id);

        return view('admin.appointment.refund_receipt', compact('advance', 'branding'));
    }
}
