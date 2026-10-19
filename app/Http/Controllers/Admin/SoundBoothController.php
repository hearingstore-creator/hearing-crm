<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Sound booths per branch: services that need a booth (hearing tests) can only be booked where one is active. */
class SoundBoothController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:sound_booths.index')->only(['index']);
        $this->middleware('permission:sound_booths.create')->only(['store']);
        $this->middleware('permission:sound_booths.edit')->only(['update']);
        $this->middleware('permission:sound_booths.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $branches = Helper::branchOptions();
        $ownBranch = $user->branchId();

        if ($ownBranch) {
            $branches = array_intersect_key($branches, [$ownBranch => true]);
        }

        $booths = Resource::where('type', 'sound_booth')
            ->when($ownBranch, fn ($q) => $q->where('vendor_id', $ownBranch))
            ->orderBy('vendor_id')->orderBy('name')
            ->withCount('appointments')
            ->get();

        $usedIds = $this->usedIds($booths->pluck('id')->all());

        return view('admin.sound_booth.index', [
            'branches'        => $branches,
            'boothsByBranch'  => $booths->groupBy('vendor_id'),
            'usedIds'         => $usedIds,
            'selectedBranch'  => $ownBranch ?: (int) $request->query('branch_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_id' => 'required|integer',
            'name'      => 'required|string|max:100',
        ]);
        $branchId = $this->branchFor((int) $data['vendor_id']);

        if ($this->nameTaken($branchId, $data['name'])) {
            return back()->withInput()->withErrors(['name' => 'This branch already has a booth with that name.']);
        }

        Resource::create(['vendor_id' => $branchId, 'name' => trim($data['name']), 'type' => 'sound_booth', 'capacity' => 1, 'is_active' => true]);

        return redirect()->route('sound-booths.index')->with('success', 'Sound booth added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $booth = $this->boothOrFail($id);
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($this->nameTaken($booth->vendor_id, $data['name'], $booth->id)) {
            return back()->withErrors(['name' => 'This branch already has a booth with that name.']);
        }

        $booth->update(['name' => trim($data['name']), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('sound-booths.index')->with('success', 'Sound booth updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $booth = $this->boothOrFail($id);

        if ($this->usedIds([$booth->id])) {
            return redirect()->route('sound-booths.index')
                ->with('warning', 'This booth has appointments booked on it, so it cannot be deleted. Deactivate it instead.');
        }

        $booth->delete();

        return redirect()->route('sound-booths.index')->with('success', 'Sound booth deleted.');
    }

    /** Branch-bound users can only work on their own branch; others on any real branch. */
    private function branchFor(int $branchId): int
    {
        $own = auth()->user()->branchId();
        if ($own) {
            return $own;
        }
        abort_unless(Helper::branchOptions() && array_key_exists($branchId, Helper::branchOptions()), 422, 'Unknown branch.');

        return $branchId;
    }

    private function boothOrFail(int $id): Resource
    {
        $booth = Resource::where('type', 'sound_booth')->findOrFail($id);
        $own = auth()->user()->branchId();
        abort_if($own && (int) $booth->vendor_id !== $own, 404);

        return $booth;
    }

    private function nameTaken(int $branchId, string $name, ?int $exceptId = null): bool
    {
        return Resource::where('vendor_id', $branchId)->where('type', 'sound_booth')
            ->where('name', trim($name))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /** Booth ids that any appointment or slot hold refers to. */
    private function usedIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $used = DB::table('appointments')->whereIn('resource_id', $ids)->pluck('resource_id')->all();
        $used = array_merge($used, DB::table('appointment_holds')->whereIn('resource_id', $ids)->pluck('resource_id')->all());

        return array_values(array_unique($used));
    }
}
