<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebLead;
use App\Models\BlockedContact;
use App\Models\LeadCaptureForm;
use App\Models\LeadSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Website leads: Inbox of raw submissions (retry / spam), capture forms (keys + secrets) and the blocklist.
 * Head Office only. web_leads.index = inbox; web_leads.manage = forms + blocklist.
 */
class WebLeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:web_leads.index')->only(['index', 'retry', 'spam']);
        $this->middleware('permission:web_leads.manage')->only(['storeForm', 'updateForm', 'regenerateSecret', 'block', 'unblock', 'retryEvent']);
        $this->middleware(function ($request, $next) {
            abort_if(auth()->user()->branchId(), 403, 'Website leads are handled by Head Office.');
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->tab, ['forms', 'blocklist', 'connections'], true) && auth()->user()->hasAccess('web_leads.manage') ? $request->tab : 'inbox';
        $status = in_array($request->status, ['pending', 'processed', 'duplicate', 'spam', 'failed'], true) ? $request->status : null;

        $submissions = LeadSubmission::with(['form:id,name', 'lead:id,full_name,lead_uid', 'deal:id,status,stage_id', 'deal.stage:id,name,color'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->filled('source'), fn ($q) => $request->source === 'website' ? $q->whereNull('source') : $q->where('source', $request->source))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $phone = \App\Classes\Helper::normalizePhone($term);
                $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('email', 'like', "%$term%")
                    ->orWhere('utm_campaign', 'like', "%$term%")
                    ->when($phone && strlen($phone) >= 6, fn ($x) => $x->orWhere('normalized_phone', 'like', "%$phone%")));
            })
            ->latest('id')->paginate(25)->withQueryString();

        $counts = LeadSubmission::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.web-lead.index', [
            'tab'         => $tab,
            'status'      => $status,
            'submissions' => $submissions,
            'counts'      => $counts,
            'today'       => LeadSubmission::whereDate('created_at', today())->whereNotIn('status', ['spam'])->count(),
            'forms'       => $tab === 'forms' ? LeadCaptureForm::with('vendor:id,name')->withCount('submissions')->latest('id')->get() : collect(),
            'blocked'     => $tab === 'blocklist' ? BlockedContact::whereIn('type', BlockedContact::TYPES)->latest('id')->paginate(50) : collect(),
            'branches'    => \App\Classes\Helper::branchOptions(),
            'inquiries'   => InquiryType::cases(),
            'canManage'   => auth()->user()->hasAccess('web_leads.manage'),
            'endpoint'    => url('api/v1/leads'),
            'events'      => $tab === 'connections' ? \App\Models\LeadWebhookEvent::latest('id')->limit(60)->get() : collect(),
            'allForms'    => $tab === 'connections' ? LeadCaptureForm::where('is_active', true)->orderBy('name')->get(['id', 'name', 'key']) : collect(),
            'meta'        => [
                'webhook'   => url('api/v1/hooks/meta'),
                'secret'    => (bool) config('connectors.meta.app_secret'),
                'verify'    => (bool) config('connectors.meta.verify_token'),
                'token'     => (bool) config('connectors.meta.page_token'),
                'form'      => app(\App\Services\LeadConnector::class)->metaForm()?->name,
                'waForm'    => app(\App\Services\LeadConnector::class)->metaForm(true)?->name,
            ],
        ]);
    }

    /** Re-process a failed submission, or restore one that was wrongly flagged as spam. */
    public function retry(int $id): RedirectResponse
    {
        $s = LeadSubmission::whereIn('status', [LeadSubmission::FAILED, LeadSubmission::SPAM])->findOrFail($id);
        $s->update(['status' => LeadSubmission::PENDING, 'spam_reason' => null, 'error' => null]);
        ProcessWebLead::dispatch($s->id);

        return back()->with('success', 'Submission sent for processing.');
    }

    /** Process a logged connector call again (after fixing a key or the capture form). */
    public function retryEvent(int $id): RedirectResponse
    {
        $event = \App\Models\LeadWebhookEvent::whereIn('status', ['failed', 'received'])->findOrFail($id);
        $status = app(\App\Services\LeadConnector::class)->replay($event);

        return back()->with($status === 'failed' ? 'error' : 'success', $status === 'failed' ? 'Still failing: ' . $event->fresh()->note : 'Processed again: ' . $status . '.');
    }

    public function spam(int $id): RedirectResponse
    {
        LeadSubmission::whereIn('status', [LeadSubmission::PENDING, LeadSubmission::FAILED])->findOrFail($id)
            ->update(['status' => LeadSubmission::SPAM, 'spam_reason' => 'Marked by ' . auth()->user()->name]);

        return back()->with('success', 'Marked as spam.');
    }

    public function storeForm(Request $request): RedirectResponse
    {
        $secret = LeadCaptureForm::newSecret();
        $form = LeadCaptureForm::create($this->validateForm($request) + [
            'key' => LeadCaptureForm::newKey(), 'secret' => $secret, 'created_by' => auth()->id(),
        ]);

        return redirect()->route('web-leads.index', ['tab' => 'forms'])
            ->with('success', 'Form created.')->with('new_secret', ['form' => $form->name, 'key' => $form->key, 'secret' => $secret]);
    }

    public function updateForm(Request $request, int $id): RedirectResponse
    {
        LeadCaptureForm::findOrFail($id)->update($this->validateForm($request, $id));

        return back()->with('success', 'Form updated.');
    }

    /** Secrets are shown once. A lost secret is replaced; the website must then use the new one. */
    public function regenerateSecret(int $id): RedirectResponse
    {
        $form = LeadCaptureForm::findOrFail($id);
        $secret = LeadCaptureForm::newSecret();
        $form->update(['secret' => $secret]);

        return back()->with('success', 'New secret generated. The old one no longer works.')
            ->with('new_secret', ['form' => $form->name, 'key' => $form->key, 'secret' => $secret]);
    }

    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'   => ['required', Rule::in(BlockedContact::TYPES)],
            'value'  => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);
        $value = BlockedContact::normalize($data['type'], $data['value']);
        abort_unless($value, 422, 'Nothing to block.');

        BlockedContact::firstOrCreate(['type' => $data['type'], 'value' => $value],
            ['reason' => $data['reason'] ?? null, 'created_by' => auth()->id()]);

        return back()->with('success', ucfirst($data['type']) . ' blocked. Future submissions go to spam.');
    }

    public function unblock(int $id): RedirectResponse
    {
        BlockedContact::findOrFail($id)->delete();

        return back()->with('success', 'Removed from the blocklist.');
    }

    private function validateForm(Request $request, ?int $id = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('slug')) ?: null]);
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'slug'              => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9-]+$/',
                                    Rule::unique('lead_capture_forms', 'slug')->ignore($id)],
            'vendor_id'         => ['nullable', Rule::exists('users', 'id')->where('type', 'vendor')],
            'sla_minutes'       => ['required', 'integer', 'min:1', 'max:1440'],
            'default_inquiry'   => ['nullable', 'array'],
            'default_inquiry.*' => [new Enum(InquiryType::class)],
            'is_active'         => ['nullable', 'boolean'],
        ], [
            'slug.unique' => 'This short link name is already used by another form.',
            'slug.min'    => 'The short link name needs at least 3 characters.',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['default_inquiry'] = array_values($data['default_inquiry'] ?? []) ?: null;

        return $data;
    }
}
