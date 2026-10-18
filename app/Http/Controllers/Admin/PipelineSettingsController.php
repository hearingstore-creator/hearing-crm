<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\LostReason;
use App\Models\Pipeline;
use App\Models\PipelineSettingsLog;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pipeline Settings: stages (rename, colour, probability, rotting, gates, order, add, archive/restore),
 * lost reasons and the default owner. No hard delete; every change is written to the change log.
 */
class PipelineSettingsController extends Controller
{
    public function __construct(private DealService $deals)
    {
        $this->middleware('permission:pipeline_settings.manage');
    }

    public function index(): View
    {
        $pipeline = Pipeline::default();
        $stages = $pipeline->stages()->get();
        $openCounts = Deal::withoutGlobalScopes()->whereNull('deleted_at')->where('status', Deal::OPEN)
            ->selectRaw('stage_id, COUNT(*) as c')->groupBy('stage_id')->pluck('c', 'stage_id');

        return view('admin.deal.settings', [
            'pipeline'    => $pipeline,
            'stages'      => $stages->whereNull('archived_at')->values(),
            'archived'    => $stages->whereNotNull('archived_at')->values(),
            'openCounts'  => $openCounts,
            'gateFields'  => PipelineStage::GATE_FIELDS,
            'statusKeys'  => $this->statusKeys(),
            'lostReasons' => LostReason::orderBy('sort_order')->orderBy('id')->get(),
            'owners'      => $this->headOfficeUsers(),
            'holidays'    => \App\Models\OfficeHoliday::where('date', '>=', today())->orderBy('date')->get(),
            'logs'        => PipelineSettingsLog::with(['user:id,name', 'stage:id,name'])->latest('id')->limit(40)->get(),
        ]);
    }

    public function storeStage(Request $request): RedirectResponse
    {
        $pipeline = Pipeline::default();
        $data = $this->validateStage($request);
        $data['pipeline_id'] = $pipeline->id;
        $data['sort_order'] = (int) $pipeline->stages()->max('sort_order') + 10;

        $stage = PipelineStage::create($data);
        PipelineSettingsLog::record('stage_added', $stage, [], $this->loggable($stage));

        return back()->with('success', 'Stage "' . $stage->name . '" added.');
    }

    public function updateStage(Request $request, int $id): RedirectResponse
    {
        $stage = PipelineStage::findOrFail($id);
        $data = $this->validateStage($request, $stage);
        $old = $this->loggable($stage);

        $stage->update($data);
        $changes = array_diff_assoc(array_map('json_encode', $this->loggable($stage)), array_map('json_encode', $old));
        if ($changes) {
            PipelineSettingsLog::record('stage_updated', $stage, array_intersect_key($old, $changes), array_intersect_key($this->loggable($stage), $changes));
        }

        return back()->with('success', 'Stage "' . $stage->name . '" saved.');
    }

    /** Drag-sorted stage ids. The system "New" stage always stays first. */
    public function reorder(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $stages = Pipeline::default()->activeStages()->get()->keyBy('id');
        $ordered = collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $stages->has($id))
            ->sortBy(fn ($id) => $stages[$id]->is_system ? 0 : 1)->values();

        DB::transaction(function () use ($ordered, $stages) {
            foreach ($ordered as $index => $id) {
                $stages[$id]->update(['sort_order' => ($index + 1) * 10]);
            }
        });
        PipelineSettingsLog::record('stages_reordered', null, [], ['order' => $ordered->map(fn ($id) => $stages[$id]->name)->all()], $stages->first()?->pipeline_id);

        return response()->json(['message' => 'Stage order saved.']);
    }

    /** Archive (never delete). Open deals in the stage must be moved to another stage first. */
    public function archiveStage(Request $request, int $id): RedirectResponse
    {
        $stage = PipelineStage::whereNull('archived_at')->findOrFail($id);
        if ($stage->is_system) {
            return back()->with('warning', 'The "' . $stage->name . '" stage is where every new lead starts. It cannot be archived.');
        }

        $openDeals = Deal::withoutGlobalScopes()->whereNull('deleted_at')->open()->where('stage_id', $stage->id)->get();
        $target = null;
        if ($openDeals->isNotEmpty()) {
            $targetId = $request->validate([
                'move_to' => ['required', Rule::exists('pipeline_stages', 'id')->whereNull('archived_at')->whereNot('id', $stage->id)],
            ], ['move_to.required' => 'Choose where the open deals in this stage should go.'])['move_to'];
            $target = PipelineStage::findOrFail($targetId);
        }

        DB::transaction(function () use ($stage, $openDeals, $target) {
            foreach ($openDeals as $deal) {
                $this->deals->move($deal, $target, [], 'Stage "' . $stage->name . '" archived');
            }
            $stage->update(['archived_at' => now()]);
        });
        PipelineSettingsLog::record('stage_archived', $stage, [], array_filter([
            'moved_deals' => $openDeals->count() ?: null,
            'moved_to'    => $target?->name,
        ]));

        return back()->with('success', 'Stage "' . $stage->name . '" archived' . ($target ? '; ' . $openDeals->count() . ' deal(s) moved to "' . $target->name . '".' : '.'));
    }

    public function restoreStage(int $id): RedirectResponse
    {
        $stage = PipelineStage::whereNotNull('archived_at')->findOrFail($id);
        $max = (int) PipelineStage::where('pipeline_id', $stage->pipeline_id)->whereNull('archived_at')->max('sort_order');
        $stage->update(['archived_at' => null, 'sort_order' => $max + 10]);
        PipelineSettingsLog::record('stage_restored', $stage);

        return back()->with('success', 'Stage "' . $stage->name . '" restored at the end of the board.');
    }

    public function storeReason(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('lost_reasons', 'name')]]);
        $reason = LostReason::create($data + ['sort_order' => (int) LostReason::max('sort_order') + 10, 'is_active' => true]);
        PipelineSettingsLog::record('lost_reason_added', null, [], ['name' => $reason->name], Pipeline::default()->id);

        return back()->with('success', 'Lost reason added.');
    }

    public function updateReason(Request $request, int $id): RedirectResponse
    {
        $reason = LostReason::findOrFail($id);
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:80', Rule::unique('lost_reasons', 'name')->ignore($reason->id)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $old = $reason->only(['name', 'is_active']);
        $reason->update($data);
        if ($old != $reason->only(['name', 'is_active'])) {
            PipelineSettingsLog::record('lost_reason_updated', null, $old, $reason->only(['name', 'is_active']), Pipeline::default()->id);
        }

        return back()->with('success', 'Lost reason saved.');
    }

    public function updateOwner(Request $request): RedirectResponse
    {
        $pipeline = Pipeline::default();
        $ownerId = $request->validate([
            'default_owner_id' => ['required', Rule::in($this->headOfficeUsers()->pluck('id')->all())],
        ])['default_owner_id'];

        $old = $pipeline->defaultOwner?->name;
        $pipeline->update(['default_owner_id' => (int) $ownerId]);
        PipelineSettingsLog::record('default_owner_changed', null, ['default_owner' => $old], ['default_owner' => $pipeline->fresh()->defaultOwner?->name], $pipeline->id);

        return back()->with('success', 'New deals will now be owned by ' . $pipeline->fresh()->defaultOwner?->name . '.');
    }

    /** Office hours + weekly holidays: new-lead call deadlines only count inside them. */
    public function updateHours(Request $request): RedirectResponse
    {
        $pipeline = Pipeline::default();
        $data = $request->validate([
            'office_open'       => ['required', 'date_format:H:i'],
            'office_close'      => ['required', 'date_format:H:i', 'after:office_open'],
            'weekly_holidays'   => ['nullable', 'array', 'max:6'],
            'weekly_holidays.*' => ['integer', 'between:1,7'],
        ], ['office_close.after' => 'Closing time must be after opening time.', 'weekly_holidays.max' => 'Keep at least one working day.']);
        $data['weekly_holidays'] = array_values(array_map('intval', $data['weekly_holidays'] ?? []));

        $old = $pipeline->only(['office_open', 'office_close', 'weekly_holidays']);
        $pipeline->update($data);
        PipelineSettingsLog::record('office_hours_changed', null, $old, $data, $pipeline->id);

        return back()->with('success', 'Office hours saved.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'after_or_equal:today', Rule::unique('office_holidays', 'date')],
            'holiday_name' => ['required', 'string', 'max:80'],
        ], ['holiday_date.unique' => 'This date is already a holiday.', 'holiday_date.after_or_equal' => 'Choose today or a future date.']);

        $holiday = \App\Models\OfficeHoliday::create(['date' => $data['holiday_date'], 'name' => $data['holiday_name'], 'created_by' => auth()->id()]);
        PipelineSettingsLog::record('holiday_added', null, [], ['holiday' => $holiday->name . ' (' . $holiday->date->format('d M Y') . ')'], Pipeline::default()->id);

        return back()->with('success', $holiday->name . ' added.');
    }

    public function destroyHoliday(int $id): RedirectResponse
    {
        $holiday = \App\Models\OfficeHoliday::findOrFail($id);
        PipelineSettingsLog::record('holiday_removed', null, ['holiday' => $holiday->name . ' (' . $holiday->date->format('d M Y') . ')'], [], Pipeline::default()->id);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    // ------------------------------------------------------------------

    private function validateStage(Request $request, ?PipelineStage $stage = null): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:60'],
            'color'             => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'probability'       => ['required', 'integer', 'between:0,100'],
            'rotting_days'      => ['nullable', 'integer', 'between:1,365'],
            'status_key'        => ['nullable', Rule::in(array_keys($this->statusKeys()))],
            'required_fields'   => ['nullable', 'array'],
            'required_fields.*' => [Rule::in(array_keys(PipelineStage::GATE_FIELDS))],
        ]);
        $data['required_fields'] = array_values($data['required_fields'] ?? []);

        // The New stage keeps its legacy status and never asks for anything
        if ($stage?->is_system) {
            $data['status_key'] = $stage->status_key;
            $data['required_fields'] = [];
        }

        return $data;
    }

    /** Legacy lead statuses a stage can mirror to (Won / Lost are deal results, not stages). */
    private function statusKeys(): array
    {
        return collect(LeadStatus::cases())
            ->reject(fn ($s) => in_array($s, [LeadStatus::WON, LeadStatus::LOST], true))
            ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
            ->all();
    }

    private function headOfficeUsers()
    {
        return User::where('status', 1)->whereNull('vendor_id')
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'vendor'))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function loggable(PipelineStage $stage): array
    {
        return $stage->only(['name', 'color', 'probability', 'rotting_days', 'status_key', 'required_fields']);
    }
}
