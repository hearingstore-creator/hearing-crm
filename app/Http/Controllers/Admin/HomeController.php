<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Medicine;
use App\Models\Stock;
use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    protected $dashboardService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\DashboardService  $dashboardService
     * @return void
     */
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
        $this->middleware('permission:dashboard.index')->only(['dashboard']);
    }

    /**
     * Show the application dashboard.
     *
     * This method gathers various data points to display on the admin dashboard,
     * including total customers, today's sales, total medicines, and stock levels.
     * It also retrieves data for a chart displaying the best-selling medicines of the month.
     * The assembled data is then passed to the 'admin.dashboard.index' view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function dashboard(): View
    {
        $statistics = $this->dashboardService->getDashboardStatistics();

        return view('admin.dashboard.index', compact('statistics'));
    }

    /**
     * Search for medicines based on request parameters.
     *
     * This method provides a dynamic search functionality for medicines, typically used for AJAX requests.
     * It filters by manufacturer and category, and performs a text-based search on the medicine name.
     * The response is a JSON array formatted for use with select2 dropdowns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function medicineSearch(Request $request): JsonResponse
    {
        $query = Medicine::query();

        $query->when($request->manufacturer_id, function ($q) use ($request) {
            return $q->where('manufacturer_id', $request->input('manufacturer_id'));
        });

        $query->when($request->category_id, function ($q) use ($request) {
            return $q->where('category_id', $request->input('category_id'));
        });

        $data = $query->where('medicine_name', 'LIKE', '%' . $request->search . '%')->where('status', 1)->get();
        $response = [];

        foreach ($data as $val) {
            $stock = $this->medicineStock($val['id']);

            if ($request->type === 'purchase' || $stock > 0) {
                $response[] = [
                    "id" => $val['id'],
                    "text" => $val['strength'] ? $val['medicine_name'] . ' (' . $val['strength'] . ')' : $val['medicine_name'],
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * Calculate the stock for a given medicine.
     *
     * This helper method computes the current stock level for a specific medicine ID
     * by summing the 'in_qty' and subtracting the 'out_qty' from the stocks table.
     *
     * @param  int  $id
     * @return int
     */
    public function medicineStock(int $id): int
    {
        return Stock::where('medicine_id', $id)->value(DB::raw("SUM(in_qty - out_qty)")) ?? 0;
    }

    /**
     * Search for employees based on a search term.
     *
     * Similar to customerSearch, this method finds employees by name using a LIKE query.
     * It is intended for use with AJAX to populate UI elements like select2 dropdowns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function employeeSearch(Request $request): JsonResponse
    {
        $data = Employee::where('status', 1)->where('name', 'LIKE', '%' . $request->search . '%')->get();
        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => $val['name'],
            ];
        }

        return response()->json($response);
    }
}
