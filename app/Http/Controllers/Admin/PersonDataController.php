<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PersonData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Data requests from a person: download everything held about them, or erase them (Super Admin only). */
class PersonDataController extends Controller
{
    public function __construct(private PersonData $data)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Only Super Admin can export or erase a person\'s data.');
            return $next($request);
        });
    }

    public function export(int $id)
    {
        $payload = $this->data->export($id);
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(), 'subject_id' => $id, 'subject_type' => \App\Models\Lead::class, 'action' => 'person_data_exported', 'new_values' => [],
            'ip_address' => request()->ip() ?? '127.0.0.1', 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
        ]);

        return response()->streamDownload(fn () => print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'person-data-' . (DB::table('leads')->where('id', $id)->value('lead_uid') ?: $id) . '.json', ['Content-Type' => 'application/json']);
    }

    public function erase(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500'], 'confirm' => ['accepted']],
            ['reason.required' => 'Write why (for example: the person asked to be removed).', 'confirm.accepted' => 'Tick that you understand this cannot be undone.']);
        $this->data->erase($id, $data['reason']);

        return redirect(url('admin/leads/' . $id))->with('success', 'Personal details erased. Invoices and payments are kept without the name.');
    }
}
