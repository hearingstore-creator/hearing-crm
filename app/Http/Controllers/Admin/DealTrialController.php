<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealTrial;
use App\Services\TrialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Trials from the deal drawer: record an in-session trial, start / close a take-home trial. */
class DealTrialController extends Controller
{
    public function __construct(private TrialService $trials)
    {
        $this->middleware('permission:deal_board.move');
    }

    public function store(Request $request, int $dealId): RedirectResponse
    {
        $deal = Deal::findOrFail($dealId);   // branch scope applies
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:product'],
            'product'    => ['nullable', 'string', 'max:150'],
            'result'     => ['nullable', Rule::in(array_keys(DealTrial::RESULTS))],
            'note'       => ['nullable', 'string', 'max:1000'],
        ], ['product_id.required_without' => 'Choose the model that was tried.']);

        $trial = $this->trials->recordInSession($deal, $data['product_id'] ?? null, $data['product'] ?? null, $data['result'] ?? null, $data['note'] ?? null);
        app(\App\Services\DealService::class)->addActivity($deal, \App\Enums\ActivityType::NOTE, null,
            'Trial in session: ' . $trial->product_label . ($trial->result ? ' – ' . DealTrial::RESULTS[$trial->result] : '') . (!empty($data['note']) ? ' – ' . $data['note'] : ''));

        return $this->back($deal->id, 'Trial recorded.');
    }

    public function takeHome(Request $request, int $dealId): RedirectResponse
    {
        $deal = Deal::findOrFail($dealId);
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'serial_no'  => ['required', 'string', 'max:120'],
            'deposit'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'method'     => ['nullable', 'string', 'max:30'],
            'reference'  => ['nullable', 'string', 'max:100'],
            'return_due' => ['required', 'date', 'after_or_equal:today'],
            'note'       => ['nullable', 'string', 'max:1000'],
        ]);
        if ($deal->status !== Deal::OPEN) {
            return $this->back($deal->id, 'The deal is closed.', 'warning');
        }

        try {
            $this->trials->startTakeHome($deal, (int) $data['product_id'], $data['serial_no'], (float) ($data['deposit'] ?? 0),
                ($data['method'] ?? null) ?: 'cash', $data['reference'] ?? null, $data['return_due'], $data['note'] ?? null);
        } catch (\DomainException $e) {
            return $this->back($deal->id, $e->getMessage(), 'warning');
        }

        return $this->back($deal->id, 'Take-home trial started. The serial is held for this patient and a reminder is set for the day before it is due back.');
    }

    public function close(Request $request, int $trialId): RedirectResponse
    {
        $trial = DealTrial::findOrFail($trialId);
        Deal::findOrFail($trial->deal_id);   // branch scope applies
        $data = $request->validate([
            'outcome'   => ['required', Rule::in(['bought', 'returned'])],
            'deposit'   => ['nullable', Rule::in(['refund', 'credit'])],
            'method'    => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $data['outcome'] === 'bought'
                ? $this->trials->closeBought($trial)
                : $this->trials->closeReturned($trial, $data['deposit'] ?? 'refund', ($data['method'] ?? null) ?: 'cash', $data['reference'] ?? null, $data['note'] ?? null);
        } catch (\DomainException|\RuntimeException $e) {
            return $this->back($trial->deal_id, $e->getMessage(), 'warning');
        }

        return $this->back($trial->deal_id, $data['outcome'] === 'bought' ? 'Trial closed: bought.' : 'Trial closed: device returned and back in stock.');
    }

    private function back(int $dealId, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect(url('admin/deals?deal=' . $dealId))->with($type, $message);
    }
}
