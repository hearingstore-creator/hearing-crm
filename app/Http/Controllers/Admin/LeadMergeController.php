<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Super Admin tool: find and merge duplicate people (leads). */
class LeadMergeController extends Controller
{
    public function __construct(private LeadMergeService $merger)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Only Super Admin can merge people.');

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $keep = $request->filled('keep') ? $this->lead((int) $request->keep) : null;
        $merge = $request->filled('merge') ? $this->lead((int) $request->merge) : null;

        return view('admin.lead.merge', [
            'suggestions' => $this->suggestions(),
            'keep'        => $keep,
            'merge'       => $merge,
            'blocker'     => $keep && $merge ? $this->merger->blocker($keep, $merge) : null,
            'counts'      => $keep && $merge ? ['keep' => $this->merger->summary($keep), 'merge' => $this->merger->summary($merge)] : null,
        ]);
    }

    /** Person picker: name, phone or ID. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $digits = preg_replace('/\D/', '', $q);

        $people = Lead::withoutGlobalScopes()->whereNull('deleted_at')
            ->where(function ($w) use ($q, $digits) {
                $w->where('full_name', 'like', '%' . $q . '%')->orWhere('lead_uid', 'like', '%' . $q . '%');
                if (strlen($digits) >= 4) {
                    $w->orWhere('phone_number', 'like', '%' . $digits . '%')->orWhere('normalized_phone', 'like', '%' . $digits . '%');
                }
            })
            ->limit(15)->get(['id', 'full_name', 'phone_number', 'lead_uid']);

        return response()->json($people->map(fn ($l) => ['id' => $l->id, 'text' => "{$l->full_name} · {$l->phone_number} · {$l->lead_uid}"]));
    }

    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate(['keep' => ['required', 'integer'], 'merge' => ['required', 'integer', 'different:keep']]);
        $keep = $this->lead((int) $data['keep']);
        $merge = $this->lead((int) $data['merge']);

        try {
            $this->merger->merge($keep, $merge);
        } catch (\DomainException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return redirect()->route('leads.merge')->with('success', "{$merge->full_name} was merged into {$keep->full_name}.");
    }

    private function lead(int $id): Lead
    {
        return Lead::withoutGlobalScopes()->with(['customer' => fn ($q) => $q->withoutGlobalScopes()])->whereNull('deleted_at')->findOrFail($id);
    }

    /** Likely duplicates: same normalised phone, or same name (ignoring case and spaces). */
    private function suggestions(): array
    {
        $groups = [];
        $byPhone = DB::table('leads')->whereNull('deleted_at')->whereNotNull('normalized_phone')
            ->select('normalized_phone')->groupBy('normalized_phone')->havingRaw('COUNT(*) > 1')->limit(50)->pluck('normalized_phone');
        foreach ($byPhone as $phone) {
            $groups[] = ['reason' => 'Same phone', 'people' => $this->people(fn ($q) => $q->where('normalized_phone', $phone))];
        }

        $byName = DB::table('leads')->whereNull('deleted_at')
            ->selectRaw("LOWER(REPLACE(TRIM(full_name), ' ', '')) AS k")->groupBy('k')->havingRaw('COUNT(*) > 1')->limit(50)->pluck('k');
        foreach ($byName as $key) {
            $people = $this->people(fn ($q) => $q->whereRaw("LOWER(REPLACE(TRIM(full_name), ' ', '')) = ?", [$key]));
            $ids = $people->pluck('id')->sort()->values()->all();
            if (!collect($groups)->contains(fn ($g) => $g['people']->pluck('id')->sort()->values()->all() === $ids)) {
                $groups[] = ['reason' => 'Same name', 'people' => $people];
            }
        }

        return $groups;
    }

    private function people(\Closure $where)
    {
        return Lead::withoutGlobalScopes()->whereNull('deleted_at')->where($where)
            ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()])
            ->withCount(['deals' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')])
            ->orderBy('id')->get(['id', 'full_name', 'phone_number', 'lead_uid', 'vendor_id', 'created_at']);
    }
}
