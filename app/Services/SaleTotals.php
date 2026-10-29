<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\SaleMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shadow check of a new invoice's totals (audit F-02, stage 2). LOG ONLY: it never blocks or changes a sale.
 *
 * Recomputes the total the same way public/assets/sales.js does:
 *   line  = qty × price − line discount (taka)
 *   gross = max(0, Σ lines + VAT − invoice discount)
 *   total = max(0, gross − redeemed points × point value)
 * and writes a warning to the log when the saved total or discount differs by more than TOLERANCE.
 * Review those warnings before deciding whether the server should enforce its own total.
 */
class SaleTotals
{
    public const TOLERANCE = 1.00;

    /** @return array{total: float, discount: float} */
    public static function expected(Request $request, ?Customer $customer): array
    {
        $lines = 0.0;
        $lineDiscounts = 0.0;

        foreach (array_filter($request->product_id ?? []) as $k => $_) {
            $lines += (float) ($request->qty[$k] ?? 0) * (float) ($request->unit_price[$k] ?? 0) - (float) ($request->discount_taka[$k] ?? 0);
            $lineDiscounts += (float) ($request->discount_taka[$k] ?? 0);
        }
        foreach (array_filter($request->service_type_id ?? []) as $k => $_) {
            $lines += (float) ($request->service_qty[$k] ?? 0) * (float) ($request->service_price[$k] ?? 0) - (float) ($request->service_discount_taka[$k] ?? 0);
            $lineDiscounts += (float) ($request->service_discount_taka[$k] ?? 0);
        }

        $invoiceDiscount = (float) $request->invoice_discount;
        $gross = max(0, $lines + (float) $request->vat - $invoiceDiscount);
        $pointValue = $customer ? LoyaltySetting::pointValueFor($customer) : 1.0;
        $total = max(0, $gross - (float) $request->redeem_points * ($pointValue > 0 ? $pointValue : 1.0));

        return ['total' => round($total, 2), 'discount' => round($lineDiscounts + $invoiceDiscount, 2)];
    }

    /** Log a warning when the saved invoice does not match its lines. Never throws. */
    public static function logMismatch(Request $request, SaleMaster $sale): void
    {
        try {
            $expected = self::expected($request, Customer::find($sale->customer_id));
            $savedTotal = round((float) $sale->total_amount, 2);
            $savedDiscount = round((float) $sale->total_discount, 2);

            if (abs($savedTotal - $expected['total']) > self::TOLERANCE || abs($savedDiscount - $expected['discount']) > self::TOLERANCE) {
                Log::warning('Sale total differs from its lines', [
                    'sale_invoice_no'   => $sale->sale_invoice_no,
                    'user_id'           => auth()->id(),
                    'saved_total'       => $savedTotal,
                    'expected_total'    => $expected['total'],
                    'saved_discount'    => $savedDiscount,
                    'expected_discount' => $expected['discount'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::notice('Sale total check skipped: ' . $e->getMessage());
        }
    }
}
