<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $this->middleware('permission:compose_&_send.create')->only(['sendCampaign', 'getRecipients']);
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

        return view('admin.sms.index', compact('stats', 'templates', 'recentLogs', 'activeCampaigns'));
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
            'sms_template_id' => 'required|exists:sms_templates,id',
        ]);

        $campaign = SmsCampaign::create([
            'name' => $request->campaign_name,
            'message_body' => $request->message_body,
            'sms_template_id' => $request->sms_template_id,
            'recipient_type' => $request->audience_type,
            'filter_criteria' => ['audience_type' => $request->audience_type],
        ]);

        $this->smsService->sendBulkCampaign($campaign);

        return redirect('admin/sms')->with('success', "Campaign sent! {$campaign->sent_count} messages delivered, {$campaign->failed_count} failed.");
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

        return view('admin.sms.analytics', compact('data', 'campaigns', 'period'));
    }
}
