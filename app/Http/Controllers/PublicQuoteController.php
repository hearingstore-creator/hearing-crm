<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\Setting;
use App\Notifications\CrmAlert;
use App\Services\CrmNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A quotation the patient opens from a private link (/q/{token}): they see it, accept or decline it,
 * and can pay through the payment link staff added. The owner is told straight away.
 */
class PublicQuoteController extends Controller
{
    public function show(string $token): View
    {
        $proposal = $this->find($token);
        if (!$proposal->viewed_at) {
            $proposal->forceFill(['viewed_at' => now()])->saveQuietly();
            $this->note($proposal, 'Quotation ' . $proposal->proposal_no . ' opened by the patient');
        }

        return view('public.quote', [
            'proposal' => $proposal->load('items'),
            'setting'  => Setting::find(1),
            'expired'  => $this->expired($proposal),
            'answered' => in_array($proposal->status, [ProposalStatus::ACCEPTED->value, ProposalStatus::DECLINED->value, ProposalStatus::CONVERTED->value], true),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $proposal = $this->find($token);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'agree' => ['accepted']],
            ['name.required' => 'Please type your name.', 'agree.accepted' => 'Please tick that you accept the quotation.']);

        return $this->answer($proposal, ProposalStatus::ACCEPTED, $data['name'], null);
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $proposal = $this->find($token);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->answer($proposal, ProposalStatus::DECLINED, $data['name'] ?? null, $data['reason'] ?? null);
    }

    private function answer(Proposal $proposal, ProposalStatus $status, ?string $name, ?string $reason): RedirectResponse
    {
        if (in_array($proposal->status, [ProposalStatus::ACCEPTED->value, ProposalStatus::DECLINED->value, ProposalStatus::CONVERTED->value], true)) {
            return back()->with('info', 'This quotation has already been answered.');
        }
        if ($this->expired($proposal)) {
            return back()->with('info', 'This quotation has expired. Please contact us for a new one.');
        }

        $proposal->forceFill([
            'status' => $status->value, 'responded_at' => now(),
            'responded_name' => $name ? mb_substr(strip_tags($name), 0, 120) : null,
            'response_note' => $reason ? mb_substr(strip_tags($reason), 0, 500) : null,
        ])->save();

        $word = $status === ProposalStatus::ACCEPTED ? 'accepted' : 'declined';
        $this->note($proposal, "Quotation {$proposal->proposal_no} {$word} online" . ($name ? " by {$name}" : '') . ($reason ? " – {$reason}" : ''));

        try {
            $notifier = app(CrmNotifier::class);
            $ownerId = $proposal->deal_id ? DB::table('deals')->where('id', $proposal->deal_id)->value('owner_id') : null;
            $notifier->send($notifier->superAdmins()->push(\App\Models\User::find($ownerId))->push(\App\Models\User::find($proposal->created_by)), new CrmAlert(
                'Quotation ' . $word,
                $proposal->recipient_display_name . ' ' . $word . ' quotation ' . $proposal->proposal_no . ' (৳' . number_format((float) ($proposal->grand_total ?: $proposal->total_amount), 2) . ').',
                url('admin/proposal/' . $proposal->id), $status === ProposalStatus::ACCEPTED ? 'fa-check-circle' : 'fa-times-circle',
                $status === ProposalStatus::ACCEPTED ? 'success' : 'warning', 'deal_assigned'
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('done', $status === ProposalStatus::ACCEPTED
            ? 'Thank you! Your acceptance has been sent. We will contact you shortly.'
            : 'Thank you for letting us know.');
    }

    private function find(string $token): Proposal
    {
        abort_unless(strlen($token) >= 32, 404);

        return Proposal::withoutGlobalScopes()->where('public_token', $token)->firstOrFail();
    }

    private function expired(Proposal $proposal): bool
    {
        return $proposal->status === ProposalStatus::EXPIRED->value || ($proposal->valid_until && $proposal->valid_until->lt(today()));
    }

    private function note(Proposal $proposal, string $text): void
    {
        if (!$proposal->deal_id) {
            return;
        }
        $deal = DB::table('deals')->where('id', $proposal->deal_id)->first(['id', 'lead_id', 'owner_id']);
        if ($deal) {
            DB::table('lead_follow_ups')->insert([
                'lead_id' => $deal->lead_id, 'deal_id' => $deal->id, 'activity_type' => 'note', 'assigned_user' => $deal->owner_id,
                'follow_up_date' => now(), 'follow_up_notes' => $text, 'status' => 'completed', 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
