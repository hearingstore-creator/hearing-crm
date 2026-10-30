<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketingChannel;
use App\Http\Controllers\Controller;
use App\Models\LeadCaptureForm;
use App\Models\MarketingCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Marketing campaigns: the code goes into ad links (utm_campaign) and QR codes so leads find their campaign. */
class MarketingCampaignController extends Controller
{
    public function __construct()
    {
        // Campaigns and spend are run by head office
        $this->middleware(fn ($request, $next) => \App\Classes\Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:marketing_campaigns.index')->only(['index', 'show']);
        $this->middleware('permission:marketing_campaigns.create')->only(['store']);
        $this->middleware('permission:marketing_campaigns.edit')->only(['update']);
        $this->middleware('permission:marketing_campaigns.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $campaigns = MarketingCampaign::query()
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->channel))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $request->q . '%')->orWhere('code', 'like', '%' . $request->q . '%')))
            ->withSum('spends as spent', 'amount')
            ->orderByRaw("FIELD(status, 'active', 'paused', 'ended')")->orderByDesc('id')
            ->paginate(25)->withQueryString();

        // enquiries per campaign (all time), for a quick sense of scale
        $enquiries = DB::table('deals')->whereNull('deleted_at')->whereIn('campaign_id', $campaigns->pluck('id'))
            ->selectRaw('campaign_id, COUNT(*) as n')->groupBy('campaign_id')->pluck('n', 'campaign_id');

        return view('admin.marketing.campaigns', [
            'campaigns' => $campaigns,
            'enquiries' => $enquiries,
            'channels'  => MarketingChannel::forCampaigns(),
            'branches'  => \App\Classes\Helper::branchOptions(),
            'forms'     => LeadCaptureForm::where('is_active', true)->orderBy('name')->get(['id', 'name', 'key']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        MarketingCampaign::create($data);

        return redirect()->route('marketing.campaigns.index')->with('success', "Campaign \"{$data['name']}\" added. Use the code \"{$data['code']}\" as utm_campaign in its ad links.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $data = $this->validated($request, $campaign->id);
        $campaign->update($data);

        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign updated.');
    }

    /** A campaign with spend or leads is ended, not deleted, so past reports keep their numbers. */
    public function destroy(int $id): RedirectResponse
    {
        $campaign = MarketingCampaign::findOrFail($id);
        $used = $campaign->spends()->exists()
            || DB::table('deals')->where('campaign_id', $id)->exists()
            || DB::table('leads')->where('campaign_id', $id)->exists();
        if ($used) {
            $campaign->update(['status' => 'ended']);

            return redirect()->route('marketing.campaigns.index')->with('warning', 'This campaign has spend or leads, so it was marked Ended instead of deleted.');
        }
        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['code' => MarketingCampaign::normalizeCode($request->input('code') ?: Str::slug((string) $request->input('name'), '_'))]);

        return $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'code'       => ['required', 'string', 'max:150', 'not_regex:/[<>"\'&?#]/', Rule::unique('marketing_campaigns', 'code')->ignore($ignoreId)],
            'channel'    => ['required', Rule::in(array_map(fn ($c) => $c->value, MarketingChannel::forCampaigns()))],
            'vendor_id'  => ['nullable', 'integer', Rule::exists('users', 'id')->where('type', 'vendor')],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget'     => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'status'     => ['required', Rule::in(array_keys(MarketingCampaign::STATUSES))],
            'notes'      => ['nullable', 'string', 'max:2000'],
            'external_id' => ['nullable', 'string', 'max:60'],
        ], [
            'code.not_regex' => 'The code goes into web links, so it cannot contain < > " \' & ? or #.',
            'code.unique' => 'Another campaign already uses this code.',
        ]);
    }
}
