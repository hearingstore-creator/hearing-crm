<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Cash Book / Bank Book built from voucher entries: Credit Vouchers bring money in, Debit Vouchers
 * pay money out, grouped by the payment mode typed on the voucher. Cancelled vouchers net to zero.
 * It does not include sale receipts or other payments that carry no voucher payment mode.
 */
class VoucherBookController extends Controller
{
    public const BOOKS = [
        'cash'   => ['label' => 'Cash Book', 'methods' => ['Cash']],
        'bank'   => ['label' => 'Bank Book', 'methods' => ['Bank', 'Cheque']],
        'wallet' => ['label' => 'Mobile Wallet Book', 'methods' => ['bKash', 'Nagad', 'Rocket']],
        'card'   => ['label' => 'Card Book', 'methods' => ['Card']],
    ];

    public function __construct()
    {
        $this->middleware('permission:cash_bank_book.index');
    }

    public function index(Request $request): View
    {
        $books = self::BOOKS;
        $book = $request->input('book', 'cash');
        if (!isset($books[$book])) {
            $book = 'cash';
        }

        $from = $request->filled('from') ? $request->input('from') : now()->startOfMonth()->format('Y-m-d');
        $to = $request->filled('to') ? $request->input('to') : now()->format('Y-m-d');
        $methods = $books[$book]['methods'];

        $base = fn () => Ledger::whereIn('payment_method', $methods);

        $opening = (float) $base()->whereDate('date', '<', $from)->sum('income_amount')
            - (float) $base()->whereDate('date', '<', $from)->sum('expense_amount');

        $entries = $base()->with('head')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->orderBy('date')->orderBy('id')->get();

        $running = $opening;
        $entries->each(function ($e) use (&$running) {
            $running += (float) $e->income_amount - (float) $e->expense_amount;
            $e->running_balance = $running;
        });

        $receipts = (float) $entries->sum('income_amount');
        $payments = (float) $entries->sum('expense_amount');
        $closing = $opening + $receipts - $payments;

        return view('admin.report.voucher-book', compact(
            'books', 'book', 'from', 'to', 'entries', 'opening', 'receipts', 'payments', 'closing'
        ));
    }
}
