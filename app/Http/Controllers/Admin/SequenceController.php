<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Services\SequenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Follow-up sequences (Leads → Follow-up Sequences), and starting / stopping one on a deal. */
class SequenceController extends Controller
{
    public function __construct(private SequenceService $sequences)
    {
        $this->middleware('permission:sequences.index')->only(['index']);
        $this->middleware('permission:sequences.create')->only(['create', 'store']);
        $this->middleware('permission:sequences.edit')->only(['edit', 'update', 'toggle']);
        $this->middleware('permission:sequences.delete')->only(['destroy']);
        $this->middleware('permission:deal_board.move')->only(['enroll', 'stop']);
    }

    public function index(): View
    {
        $sequences = Sequence::with('steps')->orderByDesc('is_active')->orderBy('name')->get();
        $stats = SequenceEnrollment::selectRaw('sequence_id, status, COUNT(*) as n')->groupBy('sequence_id', 'status')->get()
            ->groupBy('sequence_id')->map(fn ($rows) => $rows->pluck('n', 'status'));
        $sent = DB::table('sequence_runs as r')->join('sequence_enrollments as e', 'e.id', '=', 'r.enrollment_id')
            ->where('r.status', 'sent')->selectRaw('e.sequence_id, COUNT(*) as n')->groupBy('e.sequence_id')->pluck('n', 'sequence_id');
        // what the people in each sequence went on to do
        $won = DB::table('sequence_enrollments as e')->join('deals as d', 'd.id', '=', 'e.deal_id')->where('d.status', 'won')
            ->selectRaw('e.sequence_id, COUNT(DISTINCT e.deal_id) as n')->groupBy('e.sequence_id')->pluck('n', 'sequence_id');

        return view('admin.sequences.index', compact('sequences', 'stats', 'sent', 'won'));
    }

    public function create(): View
    {
        return view('admin.sequences.form', $this->formData(new Sequence(['trigger' => 'deal_created', 'stop_on_reply' => true, 'stop_on_appointment' => true])));
    }

    public function edit(int $id): View
    {
        return view('admin.sequences.form', $this->formData(Sequence::with('steps')->findOrFail($id)));
    }

    public function store(Request $request): RedirectResponse
    {
        $sequence = DB::transaction(function () use ($request) {
            $sequence = Sequence::create($this->validated($request) + ['created_by' => auth()->id()]);
            $this->saveSteps($sequence, $request);
            return $sequence;
        });

        return redirect()->route('sequences.index')->with('success', 'Sequence "' . $sequence->name . '" saved' . ($sequence->is_active ? ' and switched on.' : '. Switch it on when it is ready.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $sequence = Sequence::findOrFail($id);
        DB::transaction(function () use ($sequence, $request) {
            $sequence->update($this->validated($request) + ['updated_by' => auth()->id()]);
            $this->saveSteps($sequence, $request);
        });

        return redirect()->route('sequences.index')->with('success', 'Sequence saved. People already in it continue with the new steps.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $sequence = Sequence::findOrFail($id);
        $sequence->update(['is_active' => !$sequence->is_active]);

        return back()->with('success', '"' . $sequence->name . '" is now ' . ($sequence->is_active ? 'on' : 'off') . '. ' . ($sequence->is_active ? '' : 'People already in it stop at their next step.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $sequence = Sequence::findOrFail($id);
        foreach ($sequence->enrollments()->where('status', 'active')->get() as $e) {
            $this->sequences->stopEnrollment($e, 'Sequence deleted');
        }
        $sequence->delete();

        return redirect()->route('sequences.index')->with('success', 'Sequence deleted.');
    }

    /** Start a sequence on a deal (from the deal drawer). */
    public function enroll(Request $request, int $dealId): RedirectResponse
    {
        $data = $request->validate(['sequence_id' => ['required', 'integer', 'exists:sequences,id']]);
        $deal = Deal::findOrFail($dealId);   // branch scope applies
        $e = $this->sequences->start(Sequence::findOrFail($data['sequence_id']), $deal, auth()->id(), true);

        return redirect(url('admin/deals?deal=' . $deal->id))->with($e ? 'success' : 'warning',
            $e ? 'Follow-up sequence started.' : 'Not started: the deal is closed, the sequence has no steps, or it is already running on this deal.');
    }

    public function stop(int $enrollmentId): RedirectResponse
    {
        $e = SequenceEnrollment::with('sequence')->findOrFail($enrollmentId);
        Deal::findOrFail($e->deal_id);   // branch scope applies
        $this->sequences->stopEnrollment($e, 'Stopped by ' . auth()->user()->name);

        return redirect(url('admin/deals?deal=' . $e->deal_id))->with('success', 'Follow-up sequence stopped.');
    }

    private function formData(Sequence $sequence): array
    {
        return [
            'sequence'    => $sequence,
            'steps'       => $sequence->exists ? $sequence->steps : collect(),
            'stages'      => \App\Models\Pipeline::default()->activeStages()->get(['id', 'name']),
            'lostReasons' => \App\Models\LostReason::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'sources'     => \App\Enums\SourceOfEntry::cases(),
            'mailOn'      => !in_array(config('mail.default'), ['log', 'array'], true),
            'waOn'        => (bool) (config('connectors.meta.wa_phone_number_id') && config('connectors.meta.wa_token')),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:120'],
            'description'            => ['nullable', 'string', 'max:1000'],
            'trigger'                => ['required', Rule::in(array_keys(Sequence::TRIGGERS))],
            'trigger_sources'        => ['nullable', 'array'],
            'trigger_sources.*'      => [Rule::in(array_column(\App\Enums\SourceOfEntry::cases(), 'value'))],
            'trigger_stage_id'       => ['nullable', 'required_if:trigger,stage_entered', 'integer', 'exists:pipeline_stages,id'],
            'trigger_lost_reasons'   => ['nullable', 'array'],
            'trigger_lost_reasons.*' => ['integer', 'exists:lost_reasons,id'],
            'steps'                  => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.channel'        => ['required', Rule::in(array_keys(Sequence::CHANNELS))],
            'steps.*.delay_value'    => ['required', 'integer', 'min:0', 'max:1000'],
            'steps.*.delay_unit'     => ['required', Rule::in(['minutes', 'hours', 'days'])],
            'steps.*.subject'        => ['nullable', 'string', 'max:200'],
            'steps.*.body'           => ['nullable', 'string', 'max:2000'],
            'steps.*.wa_template'    => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'steps.*.wa_language'    => ['nullable', 'string', 'max:10'],
        ], [
            'steps.required' => 'Add at least one step.',
            'trigger_stage_id.required_if' => 'Choose the stage that starts this sequence.',
            'steps.*.wa_template.regex' => 'WhatsApp template names use small letters, numbers and underscores.',
        ]);
        foreach ($data['steps'] as $i => $s) {
            if (in_array($s['channel'], ['sms', 'email'], true) && trim((string) ($s['body'] ?? '')) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(["steps.$i.body" => 'Step ' . ($i + 1) . ' needs the message text.']);
            }
            if ($s['channel'] === 'whatsapp' && empty($s['wa_template'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(["steps.$i.wa_template" => 'Step ' . ($i + 1) . ' needs the approved WhatsApp template name.']);
            }
        }

        return [
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'trigger' => $data['trigger'],
            'trigger_sources' => $data['trigger'] === 'deal_created' ? (array_values($data['trigger_sources'] ?? []) ?: null) : null,
            'trigger_stage_id' => $data['trigger'] === 'stage_entered' ? $data['trigger_stage_id'] : null,
            'trigger_lost_reasons' => $data['trigger'] === 'deal_lost' ? (array_values(array_map('intval', $data['trigger_lost_reasons'] ?? [])) ?: null) : null,
            'stop_on_reply' => $request->boolean('stop_on_reply'),
            'stop_on_appointment' => $request->boolean('stop_on_appointment'),
            'stop_on_stage_change' => $request->boolean('stop_on_stage_change'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Replace the steps; running enrollments keep their place by step number. */
    private function saveSteps(Sequence $sequence, Request $request): void
    {
        $sequence->steps()->delete();
        foreach (array_values($request->input('steps', [])) as $i => $s) {
            $mult = ['minutes' => 1, 'hours' => 60, 'days' => 1440][$s['delay_unit']] ?? 1;
            $sequence->steps()->create([
                'position' => $i + 1, 'delay_minutes' => (int) $s['delay_value'] * $mult, 'channel' => $s['channel'],
                'subject' => $s['subject'] ?? null, 'body' => $s['body'] ?? null,
                'wa_template' => $s['wa_template'] ?? null, 'wa_language' => $s['wa_language'] ?? null,
            ]);
        }
    }
}
