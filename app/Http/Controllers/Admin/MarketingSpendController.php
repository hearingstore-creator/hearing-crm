<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketingChannel;
use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\MarketingCampaign;
use App\Models\MarketingSpend;
use App\Services\LedgerReversal;
use App\Services\MarketingSpendImport;
use App\Services\MarketingSpendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Monthly marketing spend per campaign. Saving it also books the advertising expense (once). */
class MarketingSpendController extends Controller
{
    public function __construct(private MarketingSpendService $spends)
    {
        $this->middleware(fn ($request, $next) => \App\Classes\Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:marketing_spend.index')->only(['index']);
        $this->middleware('permission:marketing_spend.create')->only(['store', 'linkExpense']);
        $this->middleware('permission:marketing_spend.import')->only(['importPreview', 'importConfirm']);
        $this->middleware('permission:marketing_spend.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $month = $this->month($request->input('month'));
        $rows = MarketingSpend::with(['campaign', 'vendor:id,name'])
            ->whereDate('month', $month->toDateString())
            ->orderByDesc('amount')->get();

        $trend = MarketingSpend::where('month', '>=', $month->copy()->subMonths(5)->toDateString())
            ->where('month', '<=', $month->toDateString())
            ->selectRaw("DATE_FORMAT(month, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->pluck('total', 'ym');

        return view('admin.marketing.spend', [
            'month'     => $month,
            'rows'      => $rows,
            'total'     => (float) $rows->sum('amount'),
            'trend'     => $trend,
            'campaigns' => MarketingCampaign::orderByRaw("FIELD(status, 'active', 'paused', 'ended')")->orderBy('name')->get(['id', 'name', 'channel', 'status', 'vendor_id']),
            'branches'  => \App\Classes\Helper::branchOptions(),
            'unlinked'  => $this->spends->unlinkedAdvertisingExpenses(30),
            'preview'   => session('marketing_import'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'integer', 'exists:marketing_campaigns,id'],
            'month'       => ['required', 'date_format:Y-m'],
            'amount'      => ['required', 'numeric', 'min:0', 'max:999999999'],
            'vendor_id'   => ['nullable', 'integer', Rule::exists('users', 'id')->where('type', 'vendor')],
            'note'        => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->spends->save(MarketingCampaign::findOrFail($data['campaign_id']), $data['month'], (float) $data['amount'], $data['vendor_id'] ?? null, 'manual', $data['note'] ?? null);
        } catch (\DomainException $e) {
            return redirect()->back()->withInput()->with('warning', $e->getMessage());
        }

        return redirect()->route('marketing.spend.index', ['month' => $data['month']])->with('success', 'Spend saved. The advertising expense is booked in the accounts for that month.');
    }

    /** Remove a month's spend (Super Admin, with a reason): the expense row is reversed, not deleted. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $reason = LedgerReversal::reasonFrom($request);
        $spend = MarketingSpend::findOrFail($id);
        $month = Carbon::parse($spend->month)->format('Y-m');
        $this->spends->remove($spend, $reason);

        return redirect()->route('marketing.spend.index', ['month' => $month])->with('success', 'Spend removed. Its expense entry was reversed.');
    }

    /** Count an advertising expense typed in Expenses as a campaign's spend. */
    public function linkExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ledger_id'   => ['required', 'integer'],
            'campaign_id' => ['required', 'integer', 'exists:marketing_campaigns,id'],
        ]);
        $ledger = Ledger::withoutGlobalScopes()->whereNull('deleted_at')->where('amount_type', 'Expense')->findOrFail($data['ledger_id']);

        try {
            $spend = $this->spends->linkExpense(MarketingCampaign::findOrFail($data['campaign_id']), $ledger);
        } catch (\DomainException $e) {
            return redirect()->back()->with('warning', $e->getMessage());
        }

        return redirect()->route('marketing.spend.index', ['month' => Carbon::parse($spend->month)->format('Y-m')])->with('success', 'Expense counted for the campaign. No second expense was booked.');
    }

    /** Step 1: read a Meta or Google export and show what would be saved. */
    public function importPreview(Request $request, MarketingSpendImport $import): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['meta', 'google'])],
            'file'     => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'rate'     => ['nullable', 'numeric', 'min:0.0001', 'max:100000'],
        ]);

        try {
            $result = $import->parse($request->file('file')->getRealPath(), $data['platform'], (float) ($data['rate'] ?? 1));
        } catch (\DomainException $e) {
            return redirect()->back()->with('warning', $e->getMessage());
        }
        if (!$result['rows']) {
            return redirect()->back()->with('warning', 'No spend rows were found in that file.');
        }

        return redirect()->route('marketing.spend.index', ['month' => $result['rows'][0]['month']])
            ->with('marketing_import', $result + ['rate' => (float) ($data['rate'] ?? 1)]);
    }

    /** Step 2: save the previewed rows. Unknown campaigns are created (Meta → Facebook, Google → Google) when ticked. */
    public function importConfirm(Request $request): RedirectResponse
    {
        $rows = $request->validate([
            'rows'               => ['required', 'array', 'max:500'],
            'rows.*.name'        => ['required', 'string', 'max:150'],
            'rows.*.month'       => ['required', 'date_format:Y-m'],
            'rows.*.amount'      => ['required', 'numeric', 'min:0', 'max:999999999'],
            'rows.*.campaign_id' => ['nullable', 'integer', 'exists:marketing_campaigns,id'],
            'rows.*.include'     => ['nullable'],
            'platform'           => ['required', Rule::in(['meta', 'google'])],
        ]);

        $platform = $rows['platform'];
        $saved = 0;
        $created = 0;
        DB::transaction(function () use ($rows, $platform, &$saved, &$created) {
            foreach ($rows['rows'] as $row) {
                if (empty($row['include'])) {
                    continue;
                }
                $campaign = !empty($row['campaign_id']) ? MarketingCampaign::find($row['campaign_id']) : null;
                if (!$campaign) {
                    $code = MarketingCampaign::normalizeCode(Str::slug($row['name'], '_')) ?: 'campaign_' . Str::random(6);
                    $campaign = MarketingCampaign::where('code', $code)->first() ?? MarketingCampaign::create([
                        'name' => $row['name'], 'code' => $code,
                        'channel' => $platform === 'google' ? MarketingChannel::GOOGLE->value : MarketingChannel::FACEBOOK->value,
                        'status' => 'active',
                    ]);
                    $created += (int) $campaign->wasRecentlyCreated;
                }
                $this->spends->save($campaign, $row['month'], (float) $row['amount'], null, $platform . '_csv', 'Imported from ' . ucfirst($platform));
                $saved++;
            }
        });

        return redirect()->route('marketing.spend.index', ['month' => $rows['rows'][0]['month']])
            ->with('success', "Imported {$saved} month(s) of spend" . ($created ? ", {$created} new campaign(s) created" : '') . '. The expenses are booked in the accounts.');
    }

    private function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value . '-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
