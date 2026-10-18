<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedContact;
use App\Models\SmsCampaign;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
        $this->middleware('permission:compose_&_send.index')->only(['index', 'logIndex', 'analytics']);
        $this->middleware('permission:compose_&_send.create')->only(['sendCampaign', 'getRecipients', 'optOutStore', 'optOutDestroy']);
    }

    /**
     * Main SMS page — compose, automation, templates, log, analytics tabs
     */
    public function index(): View
    {
        $stats = [
            'total_sent' => SmsLog::count(),
            'today_sent' => SmsLog::whereDate('sent_at', today())->count(),
            'templates' => SmsTemplate::where('is_active', true)->count(),
        ];

        $templates = SmsTemplate::where('is_active', true)->orderBy('name')->get();
        $recentLogs = SmsLog::with(['campaign'])
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $activeCampaigns = SmsCampaign::orderBy('id', 'desc')
            ->where('status', 'sending')
            ->count();

        $automations = \App\Models\SmsAutomation::with('smsTemplate')->orderBy('id', 'desc')->get();
        $optOuts = BlockedContact::where('type', BlockedContact::SMS_OPT_OUT)->latest('id')->get();

        return view('admin.sms.index', compact('stats', 'templates', 'recentLogs', 'activeCampaigns', 'automations', 'optOuts'));
    }

    /** Add a number to the SMS opt-out list. */
    public function optOutStore(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:20', 'reason' => 'nullable|string|max:190']);
        $value = BlockedContact::normalize(BlockedContact::SMS_OPT_OUT, $data['phone']);
        if (!$value) {
            return redirect('admin/sms#optout-tab')->with('warning', 'Enter a valid phone number.');
        }

        BlockedContact::firstOrCreate(['type' => BlockedContact::SMS_OPT_OUT, 'value' => $value],
            ['reason' => $data['reason'] ?? null, 'created_by' => auth()->id()]);

        return redirect('admin/sms#optout-tab')->with('success', "{$value} will no longer get promotional SMS.");
    }

    public function optOutDestroy(int $id)
    {
        BlockedContact::where('type', BlockedContact::SMS_OPT_OUT)->findOrFail($id)->delete();

        return redirect('admin/sms#optout-tab')->with('success', 'Number removed from the opt-out list.');
    }

    /**
     * AJAX — get filtered recipient list by audience type
     */
    public function getRecipients(Request $request)
    {
        $audienceType = $request->input('audience_type', '');
        $recipients = $this->smsService->getRecipientsByAudience($audienceType);

        $data = $recipients->map(function ($recipient) {
            if ($recipient instanceof \App\Models\Customer) {
                $lead = $recipient->lead;
                return [
                    'id' => $recipient->id,
                    'type' => 'customer',
                    'name' => $lead->full_name ?? 'Unknown',
                    'phone' => $lead->phone_number ?? '',
                    'label' => ($lead->full_name ?? 'Unknown') . ' (' . ($lead->phone_number ?? '') . ')',
                ];
            }

            return [
                'id' => $recipient->id,
                'type' => 'lead',
                'name' => $recipient->full_name ?? 'Unknown',
                'phone' => $recipient->phone_number ?? '',
                'label' => ($recipient->full_name ?? 'Unknown') . ' (' . ($recipient->phone_number ?? '') . ')',
            ];
        });

        return response()->json([
            'count' => $data->count(),
            'recipients' => $data,
        ]);
    }

    /**
     * Send bulk campaign
     */
    public function sendCampaign(Request $request)
    {
        $this->validate($request, [
            'campaign_name' => 'required|string|max:255',
            'message_body' => 'required|string|max:1000',
            'audience_type' => 'required|string',
            'sms_template_id' => 'nullable|exists:sms_templates,id',
        ]);

        $campaign = SmsCampaign::create([
            'name' => $request->campaign_name,
            'message_body' => $request->message_body,
            'sms_template_id' => $request->sms_template_id ?: null,
            'recipient_type' => $request->audience_type,
            'filter_criteria' => $request->filter_criteria ? json_decode($request->filter_criteria, true) : ['audience_type' => $request->audience_type],
            'create_follow_up' => $request->boolean('create_follow_up'),
            'follow_up_days' => $request->follow_up_days ?: null,
            'follow_up_assigned_user' => $request->follow_up_assigned_user ?: auth()->id(),
            'follow_up_mode' => $request->follow_up_mode ?: null,
            'status' => \App\Enums\SmsCampaignStatus::SENDING->value,
        ]);

        // Sent in the background (queue worker); results appear in Send Log / Analytics
        \App\Jobs\SendSmsCampaign::dispatch($campaign->id, auth()->id());

        return redirect('admin/sms')->with('success', "Campaign \"{$campaign->name}\" is being sent in the background. Check the Send Log in a minute for results.");
    }

    /**
     * Send log — server-rendered table with search
     */
    public function logIndex(Request $request): View
    {
        $logs = SmsLog::with(['campaign'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('phone_number', 'like', '%' . $request->search . '%')
                        ->orWhere('message', 'like', '%' . $request->search . '%')
                        ->orWhere('type', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->filled('type'), function ($q) use ($request) {
                $q->where('type', $request->type);
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('sent_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('sent_at', '<=', $request->date_to);
            })
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.sms.log', compact('logs'));
    }

    /**
     * Analytics page
     */
    public function analytics(Request $request): View
    {
        $period = $request->get('period', '30_days');
        $data = $this->smsService->getAnalyticsData($period);

        $campaigns = SmsCampaign::orderBy('id', 'desc')
            ->take(10)
            ->get();

        // Follow-up stats
        $data['follow_ups_created'] = SmsLog::where('follow_up_created', true)->count();
        $data['follow_ups_pending'] = \App\Models\LeadFollowUp::where('status', 'pending')->count();
        $data['follow_ups_completed'] = \App\Models\LeadFollowUp::where('status', 'completed')->count();

        return view('admin.sms.analytics', compact('data', 'campaigns', 'period'));
    }

    // =====================================================
    // AUDIENCE BUILDER AJAX ENDPOINTS (new — no existing logic touched)
    // =====================================================

    /**
     * AJAX — Get quick filter counts for leads or customers
     */
    public function audienceQuickCounts(Request $request)
    {
        $type = $request->input('type', 'leads');
        $service = app(\App\Services\SmsAudienceFilterService::class);

        $counts = $type === 'customers'
            ? $service->getCustomerQuickFilterCounts()
            : $service->getLeadQuickFilterCounts();

        return response()->json(['counts' => $counts]);
    }

    /**
     * AJAX — Filter recipients with advanced criteria
     */
    public function audienceFilter(Request $request)
    {
        $type = $request->input('type', 'leads');
        $filters = $request->input('filters', []);
        $service = app(\App\Services\SmsAudienceFilterService::class);

        $results = $type === 'customers'
            ? $service->filterCustomers($filters)
            : $service->filterLeads($filters);

        $stats = $service->getSummaryStats($results, $type);

        // Build preview list (first 50 for the recipient table)
        $preview = $results->take(50)->map(function ($record) use ($type) {
            if ($type === 'leads') {
                return [
                    'id' => $record->id,
                    'name' => $record->full_name ?? 'Unknown',
                    'phone' => $record->phone_number ?? '',
                    'segment' => ($record->lead_type ?? '') . ' · ' . ($record->lead_status ?? ''),
                    'status' => !empty($record->phone_number) ? 'ok' : 'missing',
                ];
            }
            $lead = $record->lead;
            return [
                'id' => $record->id,
                'name' => $lead->full_name ?? 'Unknown',
                'phone' => $lead->phone_number ?? '',
                'segment' => ($record->membershipTier->name ?? 'No Tier'),
                'status' => !empty($lead->phone_number) ? 'ok' : 'missing',
            ];
        });

        return response()->json([
            'stats' => $stats,
            'preview' => $preview->values(),
            'total' => $results->count(),
        ]);
    }

    /**
     * AJAX — Save a filter group
     */
    public function saveFilterGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'recipient_type' => 'required|in:leads,customers',
            'filter_criteria' => 'required|array',
        ]);

        $group = \App\Models\SmsFilterGroup::create([
            'name' => $request->name,
            'recipient_type' => $request->recipient_type,
            'filter_criteria' => $request->filter_criteria,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'group' => $group]);
    }

    /**
     * AJAX — Get saved filter groups
     */
    public function getFilterGroups(Request $request)
    {
        $type = $request->input('type', 'leads');
        $groups = \App\Models\SmsFilterGroup::where('recipient_type', $type)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['groups' => $groups]);
    }

    /**
     * AJAX — Delete a filter group
     */
    public function deleteFilterGroup(int $id)
    {
        $group = \App\Models\SmsFilterGroup::findOrFail($id);
        $group->delete();

        return response()->json(['success' => true]);
    }

    /**
     * AJAX — Get filter configuration (categories + options) from the database
     */
    public function audienceFilterConfig(Request $request)
    {
        $type = $request->input('type', 'leads');

        if ($type === 'leads') {
            return response()->json($this->getLeadFilterConfig());
        }

        return response()->json($this->getCustomerFilterConfig());
    }

    protected function getLeadFilterConfig(): array
    {
        // Lead statuses from DB
        $statuses = \App\Models\Lead::whereNull('deleted_at')
            ->distinct()->pluck('lead_status')->filter()->values()
            ->map(fn($v) => ['v' => $v, 'l' => ucwords(str_replace('_', ' ', $v))])->toArray();

        // Inquiry options — stored as JSON array, flatten unique
        $inquiries = \App\Models\Lead::whereNull('deleted_at')
            ->pluck('inquiry_for')->filter()
            ->flatMap(fn($v) => is_array($v) ? $v : [$v])
            ->unique()->filter()->values()
            ->map(fn($v) => ['v' => $v, 'l' => ucwords(str_replace('_', ' ', $v))])->toArray();

        // Sources
        $sources = \App\Models\Lead::whereNull('deleted_at')
            ->distinct()->pluck('source_of_entry')->filter()->values()
            ->map(fn($v) => ['v' => $v, 'l' => ucwords(str_replace('_', ' ', $v))])->toArray();

        // Gender
        $genders = \App\Models\Lead::whereNull('deleted_at')
            ->distinct()->pluck('gender')->filter()->values()
            ->map(fn($v) => ['v' => $v, 'l' => ucwords($v)])->toArray();

        // Districts
        $districts = \App\Models\District::orderBy('name')
            ->pluck('name', 'id')
            ->map(fn($name, $id) => ['v' => (string) $id, 'l' => $name])->values()->toArray();

        $quickFilters = [];
        foreach ($statuses as $s) {
            $count = \App\Models\Lead::whereNull('deleted_at')->where('lead_status', $s['v'])->count();
            $quickFilters[] = ['id' => 'qf_' . $s['v'], 'label' => $s['l'], 'count' => $count, 'filters' => [['field' => 'lead_status', 'op' => 'in', 'values' => [$s['v']]]]];
        }

        return [
            'quick_filters' => $quickFilters,
            'categories' => [
                ['id' => 'lead_status',     'label' => 'Lead Status',   'color' => '#0e9a8f', 'kind' => 'chip', 'options' => $statuses],
                ['id' => 'inquiry_for',     'label' => 'Inquiry',       'color' => '#e8564d', 'kind' => 'chip', 'options' => $inquiries],
                ['id' => 'lead_age_days',   'label' => 'Lead Age',      'color' => '#2f8fd1', 'kind' => 'op',   'opType' => 'days', 'options' => []],
                ['id' => 'source_of_entry', 'label' => 'Lead Source',   'color' => '#7a6bd0', 'kind' => 'chip', 'options' => $sources],
                ['id' => 'gender',          'label' => 'Gender',        'color' => '#d1a12f', 'kind' => 'chip', 'options' => $genders],
                ['id' => 'district_id',     'label' => 'District',      'color' => '#3fae7a', 'kind' => 'chip', 'options' => $districts],
                ['id' => 'age',             'label' => 'Age (Years)',   'color' => '#d1568f', 'kind' => 'op',   'opType' => 'numeric', 'options' => []],
            ],
        ];
    }

    protected function getCustomerFilterConfig(): array
    {
        // Membership tiers
        $tiers = \App\Models\MembershipTier::orderBy('name')
            ->pluck('name', 'id')
            ->map(fn($name, $id) => ['v' => $name, 'l' => $name])->values()->toArray();

        // Product categories (from categories table)
        $prodCats = \DB::table('categories')->whereNull('deleted_at')
            ->pluck('category_name', 'id')
            ->map(fn($name, $id) => ['v' => $name, 'l' => $name])->values()->toArray();

        // Product brands
        $prodBrands = \DB::table('brands')->whereNull('deleted_at')
            ->pluck('brand_name', 'id')
            ->map(fn($name, $id) => ['v' => $name, 'l' => $name])->values()->toArray();

        // Gender (from leads table since customer links to lead)
        $genders = \App\Models\Lead::whereNull('deleted_at')
            ->distinct()->pluck('gender')->filter()->values()
            ->map(fn($v) => ['v' => $v, 'l' => ucwords($v)])->toArray();

        // Districts
        $districts = \App\Models\District::orderBy('name')
            ->pluck('name', 'id')
            ->map(fn($name, $id) => ['v' => (string) $id, 'l' => $name])->values()->toArray();

        // Quick filters for customers
        $quickFilters = [
            ['id' => 'qf_warranty30', 'label' => 'Warranty ≤ 30 Days', 'count' => '—', 'filters' => [['field' => 'warranty_days', 'op' => 'lte', 'value' => 30]]],
            ['id' => 'qf_has_appointment', 'label' => 'Has Appointment This Week', 'count' => '—', 'filters' => [['field' => 'has_appointment_this_week', 'op' => 'eq', 'value' => 1]]],
            ['id' => 'qf_has_membership', 'label' => 'Has Membership', 'count' => '—', 'filters' => [['field' => 'has_membership', 'op' => 'eq', 'value' => 1]]],
        ];

        return [
            'quick_filters' => $quickFilters,
            'categories' => [
                ['id' => 'membership_tier',   'label' => 'Membership',            'color' => '#0e9a8f', 'kind' => 'chip', 'options' => $tiers],
                ['id' => 'product_category',  'label' => 'Product Owned',         'color' => '#e8564d', 'kind' => 'chip', 'options' => $prodCats],
                ['id' => 'product_brand',     'label' => 'Product Brand',         'color' => '#e8564d', 'kind' => 'chip', 'options' => $prodBrands],
                ['id' => 'warranty_days',     'label' => 'Warranty',              'color' => '#2f8fd1', 'kind' => 'op',   'opType' => 'warranty', 'options' => []],
                ['id' => 'usage_expiry',      'label' => 'Usage Expiry',          'color' => '#7a6bd0', 'kind' => 'op',   'opType' => 'days',     'options' => []],
                ['id' => 'purchase_amount',   'label' => 'Purchase Amount (৳)',   'color' => '#e8564d', 'kind' => 'op',   'opType' => 'numeric',  'options' => []],
                ['id' => 'purchase_count',    'label' => 'Number of Purchases',   'color' => '#3fae7a', 'kind' => 'op',   'opType' => 'numeric',  'options' => []],
                ['id' => 'last_purchase_days','label' => 'Last Purchase (Days Ago)','color' => '#d1568f', 'kind' => 'op',  'opType' => 'days',     'options' => []],
                ['id' => 'customer_activity', 'label' => 'Customer Activity',     'color' => '#2f8fd1', 'kind' => 'op',   'opType' => 'days',     'options' => []],
                ['id' => 'gender',            'label' => 'Gender',                'color' => '#d1a12f', 'kind' => 'chip', 'options' => $genders],
                ['id' => 'district',          'label' => 'District',              'color' => '#2f8fd1', 'kind' => 'chip', 'options' => $districts],
                ['id' => 'age',               'label' => 'Age (Years)',           'color' => '#d1568f', 'kind' => 'op',   'opType' => 'numeric',  'options' => []],
            ],
        ];
    }
}
