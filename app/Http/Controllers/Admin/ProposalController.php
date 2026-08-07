<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Proposal;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ProposalController extends Controller
{
    /**
     * Configure middleware to handle authorization for different proposal operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:proposals.index')->only(['index']);
        $this->middleware('permission:proposals.create')->only(['create', 'store']);
        $this->middleware('permission:proposals.edit')->only(['edit', 'update']);
        $this->middleware('permission:proposals.delete')->only(['destroy']);
    }

    /**
     * Display a listing of all proposals.
     *
     * @return View
     */
    public function index(): View
    {
        $proposals = Proposal::with(['customer', 'template'])->orderBy('id', 'desc')->get();
        return view('admin.proposal.index', compact('proposals'));
    }

    /**
     * Show the form for creating a new proposal.
     *
     * @return View
     */
    public function create(): View
    {
        $customers = Customer::with('lead')->orderBy('id', 'desc')->get();
        $templates = Template::where('status', 1)->orderBy('id', 'desc')->get();
        return view('admin.proposal.create', compact('customers', 'templates'));
    }

    /**
     * Store a newly created proposal in storage.
     *
     * @param Request $request HTTP request containing proposal data
     * @return RedirectResponse Redirects to proposal list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'send_type' => 'required|in:customer,email',
            'customer_id' => 'required_if:send_type,customer|nullable|exists:customers,id',
            'email' => 'required_if:send_type,email|nullable|email',
            'template_id' => 'nullable|exists:templates,id',
            'title' => 'required|string',
            'content' => 'nullable|string',
            'proposal_date' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $proposal = new Proposal($request->all());

        // Clear customer_id if email is provided
        if ($request->send_type === 'email') {
            $proposal->customer_id = null;
        } else {
            $proposal->email = null;
        }

        if ($proposal->save()) {
            return redirect('admin/proposal')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Display the specified proposal.
     *
     * @param int $id Proposal ID
     * @return View
     */
    public function show(int $id): View
    {
        $proposal = Proposal::with(['customer', 'template'])->findOrFail($id);
        return view('admin.proposal.show', compact('proposal'));
    }

    /**
     * Show the form for editing the specified proposal.
     *
     * @param int $id Proposal ID
     * @return View
     */
    public function edit(int $id): View
    {
        $proposal = Proposal::findOrFail($id);
        $customers = Customer::orderBy('id', 'desc')->get();
        $templates = Template::where('status', 1)->orderBy('id', 'desc')->get();
        return view('admin.proposal.edit', compact('proposal', 'customers', 'templates'));
    }

    /**
     * Update the specified proposal in storage.
     *
     * @param Request $request HTTP request containing updated proposal data
     * @param int $id Proposal ID
     * @return RedirectResponse Redirects to proposal list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'send_type' => 'required|in:customer,email',
            'customer_id' => 'required_if:send_type,customer|nullable|exists:customers,id',
            'email' => 'required_if:send_type,email|nullable|email',
            'template_id' => 'nullable|exists:templates,id',
            'title' => 'required|string',
            'content' => 'nullable|string',
            'proposal_date' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $proposal = Proposal::findOrFail($id);
        $proposal->fill($request->all());

        // Clear customer_id if email is provided
        if ($request->send_type === 'email') {
            $proposal->customer_id = null;
        } else {
            $proposal->email = null;
        }

        if ($proposal->save()) {
            return redirect('admin/proposal')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified proposal from storage.
     *
     * @param int $id Proposal ID
     * @return RedirectResponse Redirects to proposal list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $proposal = Proposal::findOrFail($id);
        $proposal->delete();

        return redirect('/admin/proposal')->with('success', 'Deleted Successfully');
    }
}
