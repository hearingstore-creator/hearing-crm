<?php

namespace App\Services;

use App\Classes\Helper;
use App\Enums\AppointmentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\CustomerLedger;
use App\Models\Ledger;
use App\Models\SaleMaster;
use Illuminate\Support\Facades\DB;

class CustomerAdvanceService
{
    /**
     * Record an advance payment when booking or managing an appointment.
     */
    public function recordAppointmentAdvance(
        Appointment $appointment,
        float $amount,
        string $paymentMethod = 'cash',
        ?string $reference = null,
        ?string $notes = null,
        ?int $userId = null
    ): CustomerAdvance {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Advance amount must be greater than zero.');
        }

        $userId = $userId ?? auth()->id();

        return DB::transaction(function () use ($appointment, $amount, $paymentMethod, $reference, $notes, $userId) {
            // Ensure customer exists (if appointment was booked via lead only)
            $customerId = $appointment->customer_id;
            if (!$customerId && $appointment->lead_id) {
                $lead = $appointment->lead;
                $customer = Customer::firstOrCreate(
                    ['lead_id' => $lead->id],
                    [
                        'referral_code' => Helper::generateReferralCode($lead->full_name),
                        'created_by'    => $userId,
                    ]
                );
                $customerId = $customer->id;
                $appointment->customer_id = $customerId;
            }

            if (!$customerId) {
                throw new \RuntimeException('Cannot record advance without a customer record.');
            }

            $customer = Customer::with('lead')->findOrFail($customerId);
            $receiptNo = CustomerAdvance::generateReceiptNo();
            $vendorId = $appointment->user?->branchId() ?? (auth()->check() ? auth()->user()->branchId() : null);

            // 1. Create Customer Advance record
            $advance = CustomerAdvance::create([
                'receipt_no'     => $receiptNo,
                'customer_id'    => $customerId,
                'lead_id'        => $appointment->lead_id,
                'appointment_id' => $appointment->id,
                'date'           => now()->toDateString(),
                'amount'         => $amount,
                'applied_amount' => 0,
                'refunded_amount'=> 0,
                'deduction_fee'  => 0,
                'balance'        => $amount,
                'payment_method' => $paymentMethod,
                'reference'      => $reference,
                'status'         => CustomerAdvance::STATUS_ACTIVE,
                'notes'          => $notes,
                'vendor_id'      => $vendorId,
                'created_by'     => $userId,
            ]);

            // 2. Link with appointment
            $appointment->advance_amount = $amount;
            $appointment->customer_advance_id = $advance->id;
            $appointment->save();

            // 3. Post to Customer Ledger (Credit / Liability)
            $cLedger = new CustomerLedger();
            $cLedger->customer_id = $customerId;
            $cLedger->date = now()->toDateString();
            $cLedger->details = "Advance for Appointment #{$appointment->id} [Receipt: {$receiptNo}]" . ($reference ? " (Ref: {$reference})" : '');
            $cLedger->debit = 0;
            $cLedger->credit = $amount;
            $cLedger->type = 'Receive';
            $cLedger->ref_invoice_no = $receiptNo;
            $cLedger->save();

            // 4. Post to General Ledger (Income / Cash In)
            $gLedger = new Ledger();
            $gLedger->invoice_no = 'ADV-LED-' . $receiptNo;
            $gLedger->date = now()->toDateString();
            $gLedger->details = "Advance Receipt {$receiptNo} for Appointment #{$appointment->id} - Customer: " . ($customer->lead->full_name ?? $customer->name);
            $gLedger->expense_amount = 0;
            $gLedger->income_amount = $amount;
            $gLedger->amount_type = Helper::$income;
            $gLedger->type = 'Advance Receive';
            $gLedger->ref_invoice_no = $receiptNo;
            $gLedger->vendor_id = $vendorId;
            $gLedger->save();

            // 5. Activity Log
            ActivityLog::create([
                'user_id'      => $userId,
                'subject_id'   => $advance->id,
                'subject_type' => CustomerAdvance::class,
                'action'       => 'appointment_advance_received',
                'new_values'   => [
                    'receipt_no'     => $receiptNo,
                    'amount'         => $amount,
                    'appointment_id' => $appointment->id,
                    'customer_id'    => $customerId,
                    'payment_method' => $paymentMethod,
                ],
                'ip_address'   => request()->ip() ?? '127.0.0.1',
                'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
            ]);

            return $advance;
        });
    }

    /**
     * Apply an active advance to a newly created or existing sale.
     */
    public function applyAdvanceToSale(SaleMaster $sale, CustomerAdvance $advance, float $applyAmount): void
    {
        if ($applyAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($sale, $advance, $applyAmount) {
            $lockedAdv = CustomerAdvance::where('id', $advance->id)->lockForUpdate()->firstOrFail();

            if ($lockedAdv->balance < ($applyAmount - 0.009)) {
                throw new \RuntimeException("Requested advance amount (৳{$applyAmount}) exceeds available balance (৳{$lockedAdv->balance}).");
            }

            $lockedAdv->applied_amount = round($lockedAdv->applied_amount + $applyAmount, 2);
            $lockedAdv->balance = max(0, round($lockedAdv->balance - $applyAmount, 2));
            $lockedAdv->status = $lockedAdv->balance <= 0.009 ? CustomerAdvance::STATUS_APPLIED : CustomerAdvance::STATUS_PARTIALLY_APPLIED;
            $lockedAdv->sale_master_id = $sale->id;
            $lockedAdv->save();

            $sale->applied_advance_id = $lockedAdv->id;
            $sale->applied_advance_amount = round(($sale->applied_advance_amount ?? 0) + $applyAmount, 2);
            $sale->save();

            ActivityLog::create([
                'user_id'      => auth()->id(),
                'subject_id'   => $lockedAdv->id,
                'subject_type' => CustomerAdvance::class,
                'action'       => 'advance_applied_to_sale',
                'new_values'   => [
                    'sale_id'         => $sale->id,
                    'sale_invoice_no' => $sale->sale_invoice_no,
                    'applied_amount'  => $applyAmount,
                    'remaining'       => $lockedAdv->balance,
                ],
                'ip_address'   => request()->ip() ?? '127.0.0.1',
                'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
            ]);
        });
    }

    /**
     * Give back the advance a sale used (sale deleted): the amount returns to the advance balance.
     * Must run inside the caller's transaction.
     */
    public function releaseAdvanceFromSale(SaleMaster $sale): void
    {
        $amount = round((float) ($sale->applied_advance_amount ?? 0), 2);
        if (!$sale->applied_advance_id || $amount <= 0) {
            return;
        }

        $lockedAdv = CustomerAdvance::where('id', $sale->applied_advance_id)->lockForUpdate()->first();
        if (!$lockedAdv) {
            return;
        }

        $lockedAdv->applied_amount = max(0, round($lockedAdv->applied_amount - $amount, 2));
        $lockedAdv->balance = round($lockedAdv->balance + $amount, 2);
        if (in_array($lockedAdv->status, [CustomerAdvance::STATUS_APPLIED, CustomerAdvance::STATUS_PARTIALLY_APPLIED], true)) {
            $lockedAdv->status = $lockedAdv->applied_amount <= 0.009 ? CustomerAdvance::STATUS_ACTIVE : CustomerAdvance::STATUS_PARTIALLY_APPLIED;
        }
        if ((int) $lockedAdv->sale_master_id === (int) $sale->id) {
            $lockedAdv->sale_master_id = null;
        }
        $lockedAdv->save();

        ActivityLog::create([
            'user_id'      => auth()->id(),
            'subject_id'   => $lockedAdv->id,
            'subject_type' => CustomerAdvance::class,
            'action'       => 'advance_released_from_deleted_sale',
            'new_values'   => [
                'sale_id'         => $sale->id,
                'sale_invoice_no' => $sale->sale_invoice_no,
                'released_amount' => $amount,
                'balance'         => $lockedAdv->balance,
            ],
            'ip_address'   => request()->ip() ?? '127.0.0.1',
            'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    /**
     * Process an advance refund or keep as credit.
     * Idempotent and thread-safe via DB transaction and row locking.
     */
    public function processRefund(
        CustomerAdvance $advance,
        float $refundAmount,
        float $deductionFee = 0,
        string $refundMethod = 'cash',
        ?string $reference = null,
        string $reason = '',
        string $actionType = 'refund',
        ?int $userId = null
    ): CustomerAdvance {
        $userId = $userId ?? auth()->id();

        return DB::transaction(function () use ($advance, $refundAmount, $deductionFee, $refundMethod, $reference, $reason, $actionType, $userId) {
            // Lock row for update
            $lockedAdv = CustomerAdvance::where('id', $advance->id)->lockForUpdate()->firstOrFail();

            // Idempotency check: already resolved
            if (in_array($lockedAdv->status, [CustomerAdvance::STATUS_REFUNDED, CustomerAdvance::STATUS_CREDIT_KEPT])) {
                return $lockedAdv;
            }

            $available = $lockedAdv->balance;
            $totalRequired = round($refundAmount + $deductionFee, 2);

            if ($totalRequired > ($available + 0.009)) {
                throw new \RuntimeException("Total refund and deduction (৳{$totalRequired}) cannot exceed available advance balance (৳{$available}).");
            }

            $customer = $lockedAdv->customer;
            $appointment = $lockedAdv->appointment;

            if ($actionType === 'credit_kept') {
                // Keep as general customer credit
                $lockedAdv->status = CustomerAdvance::STATUS_CREDIT_KEPT;
                $lockedAdv->refund_date = now()->toDateString();
                $lockedAdv->refund_reason = $reason ?: 'Patient unable to attend; advance retained as customer account credit.';
                $lockedAdv->refund_by = $userId;
                $lockedAdv->save();

                if ($appointment) {
                    $appointment->status = AppointmentStatus::CANCELLED_CREDIT_KEPT;
                    $appointment->save();
                }

                ActivityLog::create([
                    'user_id'      => $userId,
                    'subject_id'   => $lockedAdv->id,
                    'subject_type' => CustomerAdvance::class,
                    'action'       => 'advance_kept_as_credit',
                    'new_values'   => [
                        'advance_id'     => $lockedAdv->id,
                        'receipt_no'     => $lockedAdv->receipt_no,
                        'customer_id'    => $lockedAdv->customer_id,
                        'amount_kept'    => $lockedAdv->balance,
                        'reason'         => $reason,
                    ],
                    'ip_address'   => request()->ip() ?? '127.0.0.1',
                    'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
                ]);

                return $lockedAdv;
            }

            // Otherwise: Full or partial cash/bank refund
            $refundReceiptNo = CustomerAdvance::generateRefundReceiptNo();

            // 1. Customer Ledger Debit: remove credit
            $cLedger = new CustomerLedger();
            $cLedger->customer_id = $lockedAdv->customer_id;
            $cLedger->date = now()->toDateString();
            $feeNote = $deductionFee > 0 ? " (Fee deducted: ৳{$deductionFee})" : '';
            $cLedger->details = "Advance Refund [Receipt: {$refundReceiptNo}] against Advance #{$lockedAdv->receipt_no}{$feeNote}";
            $cLedger->debit = $totalRequired;
            $cLedger->credit = 0;
            $cLedger->type = 'Payment';
            $cLedger->ref_invoice_no = $refundReceiptNo;
            $cLedger->save();

            // 2. General Ledger Expense: cash out to customer
            if ($refundAmount > 0) {
                $expLedger = new Ledger();
                $expLedger->invoice_no = 'REF-LED-' . $refundReceiptNo;
                $expLedger->date = now()->toDateString();
                $expLedger->details = "Refund for Advance #{$lockedAdv->receipt_no} [Receipt: {$refundReceiptNo}] via {$refundMethod} - Customer: " . ($customer?->lead?->full_name ?? 'Customer');
                $expLedger->expense_amount = $refundAmount;
                $expLedger->income_amount = 0;
                $expLedger->amount_type = Helper::$expense;
                $expLedger->type = 'Advance Refund';
                $expLedger->ref_invoice_no = $refundReceiptNo;
                $expLedger->vendor_id = $lockedAdv->vendor_id;
                $expLedger->save();
            }

            // 3. General Ledger Income: deduction fee kept as revenue
            if ($deductionFee > 0) {
                $feeLedger = new Ledger();
                $feeLedger->invoice_no = 'FEE-LED-' . $refundReceiptNo;
                $feeLedger->date = now()->toDateString();
                $feeLedger->details = "Cancellation Fee from Advance #{$lockedAdv->receipt_no} - Customer: " . ($customer?->lead?->full_name ?? 'Customer');
                $feeLedger->expense_amount = 0;
                $feeLedger->income_amount = $deductionFee;
                $feeLedger->amount_type = Helper::$income;
                $feeLedger->type = 'Cancellation Fee';
                $feeLedger->ref_invoice_no = $refundReceiptNo;
                $feeLedger->vendor_id = $lockedAdv->vendor_id;
                $feeLedger->save();
            }

            // 4. Update Advance record
            $lockedAdv->refunded_amount = round($lockedAdv->refunded_amount + $refundAmount, 2);
            $lockedAdv->deduction_fee   = round($lockedAdv->deduction_fee + $deductionFee, 2);
            $lockedAdv->balance         = max(0, round($lockedAdv->balance - $totalRequired, 2));
            $lockedAdv->status          = CustomerAdvance::STATUS_REFUNDED;
            $lockedAdv->refund_date     = now()->toDateString();
            $lockedAdv->refund_method   = $refundMethod;
            $lockedAdv->refund_reference= $reference;
            $lockedAdv->refund_reason   = $reason;
            $lockedAdv->refund_by       = $userId;
            $lockedAdv->refund_receipt_no = $refundReceiptNo;
            $lockedAdv->save();

            // 5. Update Appointment status
            if ($appointment) {
                $appointment->status = AppointmentStatus::CANCELLED_REFUNDED;
                $appointment->save();
            }

            // 6. Audit Log
            ActivityLog::create([
                'user_id'      => $userId,
                'subject_id'   => $lockedAdv->id,
                'subject_type' => CustomerAdvance::class,
                'action'       => 'advance_refunded',
                'new_values'   => [
                    'advance_id'        => $lockedAdv->id,
                    'receipt_no'        => $lockedAdv->receipt_no,
                    'refund_receipt_no' => $refundReceiptNo,
                    'customer_id'       => $lockedAdv->customer_id,
                    'refund_amount'     => $refundAmount,
                    'deduction_fee'     => $deductionFee,
                    'refund_method'     => $refundMethod,
                    'reason'            => $reason,
                ],
                'ip_address'   => request()->ip() ?? '127.0.0.1',
                'user_agent'   => mb_substr((string) request()->userAgent(), 0, 255),
            ]);

            return $lockedAdv;
        });
    }
}
