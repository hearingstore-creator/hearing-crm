<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TimeSlotController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:time_slot.index')->only(['index']);
        $this->middleware('permission:time_slot.create')->only(['create', 'store']);
        $this->middleware('permission:time_slot.edit')->only(['edit', 'update']);
        $this->middleware('permission:time_slot.delete')->only(['destroy']);
    }

    public function index(): View
    {
        $data['timeSlots'] = TimeSlot::orderBy('start_time', 'asc')->get();

        return view('admin.time_slot.index', $data);
    }

    public function create(): View
    {
        return view('admin.time_slot.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'status'      => 'nullable|in:0,1',
        ]);

        TimeSlot::create([
            'start_time' => $request->start_time,
            'end_time'   => $request->end_time,
            'status'     => $request->status ?? 1,
        ]);

        return redirect('admin/time-slots')->with('success', 'Saved Successfully');
    }

    public function edit(int $id): View
    {
        $data['timeSlot'] = TimeSlot::findOrFail($id);

        return view('admin.time_slot.edit', $data);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'status'      => 'nullable|in:0,1',
        ]);

        $timeSlot = TimeSlot::findOrFail($id);
        $timeSlot->fill($request->all());
        $timeSlot->save();

        return redirect('admin/time-slots')->with('success', 'Updated Successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        TimeSlot::findOrFail($id)->delete();

        return redirect('admin/time-slots')->with('success', 'Deleted Successfully');
    }
}
