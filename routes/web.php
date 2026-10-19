<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerReturnController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\PipelineSettingsController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\MedicineController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RepairController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Admin\SmsTemplateController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('/login');
});

// Public website lead form (open directly or as a popup via embed.js)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('f/{key}', [\App\Http\Controllers\PublicLeadFormController::class, 'show'])->name('public.form');
    Route::get('f/{key}/embed.js', [\App\Http\Controllers\PublicLeadFormController::class, 'embed'])->name('public.form.embed');
    Route::post('f/{key}', [\App\Http\Controllers\PublicLeadFormController::class, 'submit'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->name('public.form.submit');
});

Route::group(['namespace' => '\App\Http\Controllers\Admin', 'prefix' => 'admin', 'middleware' => 'auth'], function() {
    Route::get('/dashboard', [HomeController::class, 'dashboard']);
    Route::get('profile', [\App\Http\Controllers\Admin\ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile/password', [\App\Http\Controllers\Admin\ProfileController::class, 'password'])->name('profile.password');
    Route::get('reports', [\App\Http\Controllers\Admin\ReportHubController::class, 'index'])->name('reports.hub');
    Route::post('reports/star', [\App\Http\Controllers\Admin\ReportHubController::class, 'star'])->name('reports.star');
    Route::get('stock-transfers/product-stock', [\App\Http\Controllers\Admin\StockTransferController::class, 'productStock'])->name('stock-transfers.product-stock');
    Route::get('stock-transfers', [\App\Http\Controllers\Admin\StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::get('stock-transfers/create', [\App\Http\Controllers\Admin\StockTransferController::class, 'create'])->name('stock-transfers.create');
    Route::post('stock-transfers', [\App\Http\Controllers\Admin\StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::get('stock-transfers/{id}', [\App\Http\Controllers\Admin\StockTransferController::class, 'show'])->whereNumber('id')->name('stock-transfers.show');
    Route::post('stock-transfers/{id}/cancel', [\App\Http\Controllers\Admin\StockTransferController::class, 'cancel'])->whereNumber('id')->name('stock-transfers.cancel');
    Route::get('stock-adjustments/product-stock', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'productStock'])->name('stock-adjustments.product-stock');
    Route::get('stock-adjustments', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
    Route::get('stock-adjustments/create', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
    Route::post('stock-adjustments', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    Route::get('stock-adjustments/{id}', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'show'])->whereNumber('id')->name('stock-adjustments.show');
    Route::post('stock-adjustments/{id}/cancel', [\App\Http\Controllers\Admin\StockAdjustmentController::class, 'cancel'])->whereNumber('id')->name('stock-adjustments.cancel');
    Route::get('/dashboard/legacy',[HomeController::class, 'legacyDashboard'])->name('dashboard.legacy');
    Route::get('/dashboard/new', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'index'])->name('dashboard.new');
    Route::post('/dashboard/today/pin', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'pin'])->name('dashboard.today.pin');
    Route::get('/dashboard/today/tiles', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'tiles'])->name('dashboard.today.tiles');
    Route::get('/dashboard/today/block/{block}', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'block'])->name('dashboard.today.block');
    Route::get('/dashboard/today/tab/{tab}', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'tab'])->whereIn('tab', ['sales', 'care', 'pipeline', 'branches'])->name('dashboard.today.tab');
    Route::get('/dashboard/today/chart/{chart}', [\App\Http\Controllers\Admin\DashboardTodayController::class, 'chart'])->name('dashboard.today.chart');
    Route::get('/dashboard/audience/{segment}', [\App\Http\Controllers\Admin\DashboardAudienceController::class, 'export'])->name('dashboard.audience.export');
    Route::resource('leads', 'LeadController');
    // Old add/edit follow-up forms retired: activities are added from the lead profile / Deals board
    Route::patch('leads/follow-ups/{id}/complete', [LeadController::class, 'completeFollowUp']);
    Route::post('leads-import', [LeadController::class, 'importLeads']);
    Route::get('leads-export', [LeadController::class, 'exportToXlsx']);
    Route::get('lead-search', [LeadController::class, 'leadSearch'])->name('lead.search');
    Route::get('leads-merge', [\App\Http\Controllers\Admin\LeadMergeController::class, 'index'])->name('leads.merge');
    Route::get('leads-merge/search', [\App\Http\Controllers\Admin\LeadMergeController::class, 'search'])->name('leads.merge.search');
    Route::post('leads-merge', [\App\Http\Controllers\Admin\LeadMergeController::class, 'merge'])->name('leads.merge.store');

    // Deals board (Person + Deal pipeline)
    Route::get('deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('deals/column/{stage}', [DealController::class, 'column'])->name('deals.column');
    Route::get('deals/my-day', [DealController::class, 'myDay'])->name('deals.my-day');
    Route::get('deals/duplicate-check', [DealController::class, 'duplicateCheck'])->name('deals.duplicate-check');
    Route::post('deals', [DealController::class, 'store'])->name('deals.store');
    Route::post('deals/bulk', [DealController::class, 'bulk'])->name('deals.bulk');
    Route::post('deals/activities/{activity}/complete', [DealController::class, 'completeActivity'])->whereNumber('activity')->name('deals.activities.complete');
    Route::get('deals/{id}', [DealController::class, 'show'])->whereNumber('id')->name('deals.show');
    Route::post('deals/{id}/activities', [DealController::class, 'storeActivity'])->whereNumber('id')->name('deals.activities.store');
    Route::post('deals/{id}/notes', [DealController::class, 'storeNote'])->whereNumber('id')->name('deals.notes.store');
    Route::post('deals/notes/{note}/pin', [DealController::class, 'pinNote'])->whereNumber('note')->name('deals.notes.pin');
    Route::delete('deals/notes/{note}', [DealController::class, 'destroyNote'])->whereNumber('note')->name('deals.notes.destroy');
    Route::post('deals/{id}/appointments', [DealController::class, 'bookAppointment'])->whereNumber('id')->name('deals.appointments.store');
    Route::post('deals/{id}/contact', [DealController::class, 'logContact'])->whereNumber('id')->name('deals.contact');
    Route::post('deals/{id}/move', [DealController::class, 'move'])->whereNumber('id')->name('deals.move');
    Route::post('deals/{id}/won', [DealController::class, 'won'])->whereNumber('id')->name('deals.won');
    Route::post('deals/{id}/lost', [DealController::class, 'lost'])->whereNumber('id')->name('deals.lost');
    Route::post('deals/{id}/reopen', [DealController::class, 'reopen'])->whereNumber('id')->name('deals.reopen');
    Route::post('deals/{id}/transfer', [DealController::class, 'transfer'])->whereNumber('id')->name('deals.transfer');

    Route::get('pipeline-settings', [PipelineSettingsController::class, 'index'])->name('pipeline.settings');
    Route::post('pipeline-settings/stages', [PipelineSettingsController::class, 'storeStage']);
    Route::post('pipeline-settings/stages/reorder', [PipelineSettingsController::class, 'reorder']);
    Route::put('pipeline-settings/stages/{id}', [PipelineSettingsController::class, 'updateStage']);
    Route::post('pipeline-settings/stages/{id}/archive', [PipelineSettingsController::class, 'archiveStage']);
    Route::post('pipeline-settings/stages/{id}/restore', [PipelineSettingsController::class, 'restoreStage']);
    Route::post('pipeline-settings/lost-reasons', [PipelineSettingsController::class, 'storeReason']);
    Route::put('pipeline-settings/lost-reasons/{id}', [PipelineSettingsController::class, 'updateReason']);
    Route::put('pipeline-settings/default-owner', [PipelineSettingsController::class, 'updateOwner']);
    Route::put('pipeline-settings/office-hours', [PipelineSettingsController::class, 'updateHours']);
    Route::post('pipeline-settings/holidays', [PipelineSettingsController::class, 'storeHoliday']);
    Route::delete('pipeline-settings/holidays/{id}', [PipelineSettingsController::class, 'destroyHoliday']);

    // Header bell + deal reports
    Route::get('notifications/settings', [\App\Http\Controllers\Admin\NotificationController::class, 'settings'])->name('notifications.settings');
    Route::post('notifications/settings', [\App\Http\Controllers\Admin\NotificationController::class, 'saveSettings'])->name('notifications.settings.save');
    Route::get('notifications/{id}', [\App\Http\Controllers\Admin\NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('deal-reports', [\App\Http\Controllers\Admin\DealReportController::class, 'index'])->name('deal-reports.index');

    // Website leads: inbox, capture forms, blocklist
    Route::get('web-leads', [\App\Http\Controllers\Admin\WebLeadController::class, 'index'])->name('web-leads.index');
    Route::post('web-leads/{id}/retry', [\App\Http\Controllers\Admin\WebLeadController::class, 'retry'])->whereNumber('id');
    Route::post('web-leads/{id}/spam', [\App\Http\Controllers\Admin\WebLeadController::class, 'spam'])->whereNumber('id');
    Route::post('web-leads/forms', [\App\Http\Controllers\Admin\WebLeadController::class, 'storeForm']);
    Route::put('web-leads/forms/{id}', [\App\Http\Controllers\Admin\WebLeadController::class, 'updateForm'])->whereNumber('id');
    Route::post('web-leads/forms/{id}/secret', [\App\Http\Controllers\Admin\WebLeadController::class, 'regenerateSecret'])->whereNumber('id');
    Route::post('web-leads/blocklist', [\App\Http\Controllers\Admin\WebLeadController::class, 'block']);
    Route::delete('web-leads/blocklist/{id}', [\App\Http\Controllers\Admin\WebLeadController::class, 'unblock'])->whereNumber('id');
    Route::get('appointments/today', [AppointmentController::class, 'today'])->name('appointments.today');
    Route::post('appointments/quick-walk-in', [AppointmentController::class, 'quickWalkIn'])->name('appointments.quick-walk-in');
    Route::post('appointments/{id}/check-in', [AppointmentController::class, 'checkIn'])->name('appointments.check-in');
    Route::post('appointments/{id}/start-service', [AppointmentController::class, 'startService'])->name('appointments.start-service');
    Route::post('appointments/{id}/complete-service', [AppointmentController::class, 'completeService'])->name('appointments.complete-service');
    Route::post('appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::get('appointments/search-patient', [AppointmentController::class, 'searchPatient']);
    Route::post('appointments/quick-lead', [AppointmentController::class, 'quickLead']);
    Route::get('appointments/dynamic-availability', [AppointmentController::class, 'dynamicAvailability']);
    Route::get('appointments/professionals-by-service/{serviceId}', [AppointmentController::class, 'getProfessionalsByService']);
    Route::get('appointments/professional-slots/{user_id}/{date}', [AppointmentController::class, 'getProfessionalSlots']);
    Route::get('appointments/calendar', [AppointmentController::class, 'calendar']);
    Route::get('appointments/calendar-data', [AppointmentController::class, 'getCalendarData']);
    Route::post('appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
    Route::resource('appointments', 'AppointmentController');
    Route::get('roster', [\App\Http\Controllers\Admin\RosterController::class, 'index'])->name('roster.index');
    Route::post('roster/shift', [\App\Http\Controllers\Admin\RosterController::class, 'saveShift'])->name('roster.shift.save');
    Route::delete('roster/shift/{id}', [\App\Http\Controllers\Admin\RosterController::class, 'deleteShift'])->name('roster.shift.delete');
    Route::post('roster/branch-schedule', [\App\Http\Controllers\Admin\RosterController::class, 'saveBranchSchedule'])->name('roster.branch-schedule.save');
    Route::post('roster/break', [\App\Http\Controllers\Admin\RosterController::class, 'saveBreak'])->name('roster.break.save');
    Route::delete('roster/break/{id}', [\App\Http\Controllers\Admin\RosterController::class, 'deleteBreak'])->name('roster.break.delete');
    Route::post('roster/holiday', [\App\Http\Controllers\Admin\RosterController::class, 'saveStaffHoliday'])->name('roster.holiday.save');
    Route::post('roster/leave', [\App\Http\Controllers\Admin\RosterController::class, 'requestLeave'])->name('roster.leave.request');
    Route::post('roster/leave/{id}/approve', [\App\Http\Controllers\Admin\RosterController::class, 'approveLeave'])->name('roster.leave.approve');
    Route::post('roster/leave/{id}/reject', [\App\Http\Controllers\Admin\RosterController::class, 'rejectLeave'])->name('roster.leave.reject');
    Route::delete('roster/leave/{id}', [\App\Http\Controllers\Admin\RosterController::class, 'deleteLeave'])->name('roster.leave.delete');
    Route::resource('professionals', 'ProfessionalController');
    Route::resource('appointment-services', 'AppointmentServiceController');
    Route::resource('time-slots', 'TimeSlotController');
    Route::patch('ticket/{id}/status', [\App\Http\Controllers\Admin\TicketController::class, 'updateStatus']);
    Route::get('support-settings', [\App\Http\Controllers\Admin\SupportSettingsController::class, 'index'])->name('support.settings');
    Route::post('support-settings', [\App\Http\Controllers\Admin\SupportSettingsController::class, 'update'])->name('support.settings.update');
    Route::get('ticket-devices', [\App\Http\Controllers\Admin\TicketController::class, 'devices'])->name('ticket.devices');
    Route::get('ticket-patients', [\App\Http\Controllers\Admin\TicketController::class, 'patientSearch'])->name('ticket.patients');
    Route::post('ticket/{id}/assign', [\App\Http\Controllers\Admin\TicketController::class, 'assign'])->whereNumber('id')->name('ticket.assign');
    Route::post('ticket/{id}/notes', [\App\Http\Controllers\Admin\TicketController::class, 'addNote'])->whereNumber('id')->name('ticket.notes');
    Route::patch('task/{id}/status', [\App\Http\Controllers\Admin\TaskController::class, 'updateStatus']);
    Route::resource('ticket', 'TicketController');
    Route::resource('task', 'TaskController');
    Route::resource('category', 'CategoryController');
    Route::resource('template', 'TemplateController');
    Route::post('proposal/{id}/status', [\App\Http\Controllers\Admin\ProposalController::class, 'updateStatus'])->name('proposal.status');
    Route::post('proposal/{id}/convert-to-sale', [\App\Http\Controllers\Admin\ProposalController::class, 'convertToSale'])->name('proposal.convert');
    Route::post('proposal/{id}/email', [\App\Http\Controllers\Admin\ProposalController::class, 'sendEmail'])->name('proposal.email');
    Route::get('proposal/{id}/pdf', [\App\Http\Controllers\Admin\ProposalController::class, 'downloadPdf'])->name('proposal.pdf');
    Route::get('proposal-add-row-item', [\App\Http\Controllers\Admin\ProposalController::class, 'addRowItem'])->name('proposal.add-row');
    Route::resource('proposal', 'ProposalController');
    Route::resource('service-type', 'ServiceTypeController');
    Route::resource('type', 'TypeController');
    Route::resource('brand', 'BrandController');
    Route::resource('doctor', 'DoctorController');
    Route::get('doctor/{id}/commission-rules', 'DoctorController@commissionRules')->name('doctor.commission-rules');
    Route::resource('product', 'ProductController');
    Route::get('product-search', [ProductController::class, 'productSearch'])->name('product.search');
    Route::get('product-info/{id}', [ProductController::class, 'productInfo']);

    Route::resource('customers', 'CustomerController');
    Route::post('customers-import', 'CustomerController@importCustomers')->name('customers.import');
    Route::get('customer-search', [CustomerController::class, 'customerSearch'])->name('customer.search');
    // Header search (Ctrl+K): each group checks its own permission
    Route::get('global-search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'search'])->name('global.search')->middleware('throttle:120,1,global-search');
    Route::get('customer-lookup', [\App\Http\Controllers\Admin\CustomerLookupController::class, 'index'])->name('customer.lookup');

    Route::get('customer-list', [CustomerController::class, 'customerList'])->name('customer.list');
    Route::get('customer-ledger/create', [CustomerController::class, 'createCustomerLedger']);
    Route::post('customer-ledger', [CustomerController::class, 'customerLedger'])->name('customer.ledger');
    Route::get('customer-payment', [CustomerController::class, 'payment']);
    Route::post('customer-payment', [CustomerController::class, 'paymentStore'])->name('customer.payment');
    Route::post('customers/{customer}/open-deal', [CustomerController::class, 'openDeal'])->name('customers.open-deal');
    Route::post('customers/{customer}/after-sales-action', [CustomerController::class, 'recordAfterSalesAction'])->name('customers.after-sales-action');

    // Membership & Reward System Routes
    Route::prefix('membership')->group(function() {
        Route::get('tiers', [MembershipController::class, 'tiers'])->name('membership.tiers');
        Route::post('tiers', [MembershipController::class, 'updateTiers'])->name('membership.tiers.update');
        Route::put('tiers', [MembershipController::class, 'updateTiers']);

        Route::get('milestones', [MembershipController::class, 'milestones'])->name('membership.milestones');
        Route::post('milestones', [MembershipController::class, 'storeMilestone'])->name('membership.milestones.store');
        Route::put('milestones/{id}', [MembershipController::class, 'updateMilestone'])->name('membership.milestones.update');
        Route::post('milestones/{id}/toggle', [MembershipController::class, 'toggleMilestone'])->name('membership.milestones.toggle');
        Route::delete('milestones/{id}', [MembershipController::class, 'destroyMilestone'])->name('membership.milestones.destroy');

        Route::get('settings/export', [MembershipController::class, 'exportSettings'])->name('membership.settings.export');
        Route::post('settings/import', [MembershipController::class, 'previewImport'])->name('membership.settings.import');
        Route::post('settings/import/apply', [MembershipController::class, 'applyImport'])->name('membership.settings.import.apply');
        Route::get('settings', [MembershipController::class, 'loyaltySettings'])->name('membership.settings');
        Route::post('settings', [MembershipController::class, 'updateLoyaltySettings'])->name('membership.settings.update');
        Route::put('settings', [MembershipController::class, 'updateLoyaltySettings']);
        Route::get('wallets', [MembershipController::class, 'wallets'])->name('membership.wallets');
        Route::get('wallets/{id}', [MembershipController::class, 'walletDetails'])->name('membership.wallet-details');
        Route::get('reward-ledger', [MembershipController::class, 'rewardLedger'])->name('membership.reward-ledger');
        Route::get('referrals', [MembershipController::class, 'referrals'])->name('membership.referrals');
        Route::delete('referrals/{id}', [MembershipController::class, 'deleteReferral'])->name('membership.referrals.delete');
        Route::get('upgrades', [MembershipController::class, 'upgrades'])->name('membership.upgrades');
    });


    Route::resource('suppliers', 'SupplierController');
    Route::get('supplier-list', [SupplierController::class, 'supplierList'])->name('supplier.list');
    Route::get('supplier-ledger/create', [SupplierController::class, 'createSupplierLedger']);
    Route::post('supplier-ledger', [SupplierController::class, 'supplierLedger'])->name('supplier.ledger');
    Route::get('suppliers-payment', [SupplierController::class, 'supplierPaymentIndex'])->name('suppliers.payment.index');
    Route::get('suppliers-payment/create', [SupplierController::class, 'supplierPaymentCreate'])->name('suppliers.payment.create');
    Route::post('suppliers-payment', [SupplierController::class, 'supplierPaymentStore'])->name('suppliers.payment.store');
    Route::get('suppliers-payment/{id}/slip', [SupplierController::class, 'supplierPaymentSlip'])->name('suppliers.payment.slip');
    Route::get('suppliers-payment/{id}/edit', [SupplierController::class, 'supplierPaymentEdit'])->name('suppliers.payment.edit');
    Route::put('suppliers-payment/{id}', [SupplierController::class, 'supplierPaymentUpdate'])->name('suppliers.payment.update');
    Route::delete('suppliers-payment/{id}', [SupplierController::class, 'supplierPaymentDestroy'])->name('suppliers.payment.destroy');
    Route::get('suppliers-due/{id}', [SupplierController::class, 'getSupplierDue'])->name('suppliers.due');
    Route::get('suppliers/{id}/unpaid-purchases', [SupplierController::class, 'supplierUnpaidPurchases'])->name('suppliers.unpaid-purchases');

    Route::resource('vendors', 'VendorController');

    Route::get('doctors-payment', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'index'])->name('doctors.payment.index');
    Route::get('doctors-payment/create', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'create'])->name('doctors.payment.create');
    Route::post('doctors-payment', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'store'])->name('doctors.payment.store');
    Route::get('doctors-payment/{id}/slip', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'slip'])->name('doctors.payment.slip');
    Route::delete('doctors-payment/{id}', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'destroy'])->name('doctors.payment.destroy');
    Route::get('doctors-due/{id}', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'getDoctorDue'])->name('doctors.due');
    Route::get('doctors-period-unsettled', [\App\Http\Controllers\Admin\DoctorPaymentController::class, 'getPeriodUnsettled'])->name('doctors.period-unsettled');
    Route::resource('manufacturer-return', 'ManufacturerReturnController');

    Route::resource('customer-return', 'CustomerReturnController');
    Route::post('customer/add-return', [CustomerReturnController::class, 'customerAddReturn'])->name('customer.add-return');
    Route::get('customer-return-list', [CustomerReturnController::class, 'customerReturnList'])->name('customer-return.list');

    Route::resource('medicine', 'MedicineController');
    Route::get('medicine-list', [MedicineController::class, 'medicineList'])->name('medicine.list');
    Route::get('medicine-info/{id}', [MedicineController::class, 'medicineInfo']);
    Route::get('batch-wise/medicine-info/{id}', [MedicineController::class, 'batchWiseMedicineInfo']);
    Route::get('barcode-generate/{id}', [MedicineController::class, 'barcodeGenerate'])->name('medicine.barcode');


    Route::resource('purchase', 'PurchaseController');
    Route::get('purchase-list', [PurchaseController::class, 'purchaseList'])->name('purchase.list');
    Route::get('purchase-add-row-item', [PurchaseController::class, 'addRowItem'])->name('purchase.add-row');
    Route::get('purchase-returns/items/by-purchase', [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'getPurchaseItems'])->name('purchase-returns.items');
    Route::get('purchase-returns/bills/by-supplier', [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'getPurchasesBySupplier'])->name('purchase-returns.supplier-bills');
    Route::get('purchase-returns/serials/lookup', [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'lookupSerial'])->name('purchase-returns.serial-lookup');
    Route::post('purchase-returns/{id}/confirm', [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'confirm'])->name('purchase-returns.confirm');
    Route::post('purchase-returns/{id}/unconfirm', [\App\Http\Controllers\Admin\PurchaseReturnController::class, 'unconfirm'])->name('purchase-returns.unconfirm');
    Route::resource('purchase-returns', \App\Http\Controllers\Admin\PurchaseReturnController::class);
    Route::get('medicine-search', [HomeController::class, 'medicineSearch'])->name('medicine.search');

    Route::get('sales/due-list', [SaleController::class, 'dueList'])->name('sale.due-list');
    Route::get('sales/unpaid-invoices', [SaleController::class, 'unpaidInvoices'])->name('sale.unpaid-invoices');
    Route::post('sales/collect-due', [SaleController::class, 'collectDue'])->name('sale.collect-due');
    Route::get('sales/fulfillment', [SaleController::class, 'fulfillmentHub'])->name('sale.fulfillment');
    Route::post('sales/scan-lookup', [SaleController::class, 'scanLookup'])->name('sale.scan-lookup');
    Route::post('sales/{id}/update-delivery-status', [SaleController::class, 'updateDeliveryStatus'])->name('sale.update-delivery-status');
    Route::get('sales/pending-services', [SaleController::class, 'pendingServices'])->name('sales.pending-services');
    Route::post('sales/service-detail/{id}/done-now', [SaleController::class, 'markServiceDoneNow'])->name('sales.service-done-now');
    Route::post('sales/service-detail/{id}/book-later', [SaleController::class, 'markServiceBookLater'])->name('sales.service-book-later');

    Route::resource('sale', 'SaleController');
    Route::get('sale-list', [SaleController::class, 'saleList'])->name('sale.list');
    Route::get('sale-add-row-item', [SaleController::class, 'addRowItem'])->name('sale.add-row');
    Route::get('sale-add-service-row-item', [SaleController::class, 'addServiceRowItem'])->name('sale.add-service-row');
    Route::get('product-serial-numbers/{id}', [SaleController::class, 'getProductSerialNumbers'])->name('sale.product.serial');
    Route::get('customer-redeem-points/{id}', [SaleController::class, 'getCustomerRedeemPoints'])->name('sale.customer.redeem-points');
    Route::get('sale-pos-print/{id}', [SaleController::class, 'posPrint'])->name('sale.pos-print');

    // Sales Returns & Credit Notes
    Route::get('sale-returns/items/by-sale', [\App\Http\Controllers\Admin\SaleReturnController::class, 'getSaleItems'])->name('sale-returns.items');
    Route::get('sale-returns/customer-invoices', [\App\Http\Controllers\Admin\SaleReturnController::class, 'getCustomerInvoices'])->name('sale-returns.customer-invoices');
    Route::resource('sale-returns', \App\Http\Controllers\Admin\SaleReturnController::class);

    // Repair, Replacement & Warranty Module
    // Repair, Replacement & Warranty Module (Plan B with Plan A Legacy Switch)
    Route::get('repairs/history/export', [RepairController::class, 'exportHistory'])->name('repairs.history.export');
    Route::get('repairs/history', [RepairController::class, 'history'])->name('repairs.history');
    Route::get('repairs/supplier-reconciliation', [RepairController::class, 'supplierReconciliation'])->name('repairs.supplier-reconciliation');
    Route::get('repairs/verify-warranty', [RepairController::class, 'verifyWarranty'])->name('repairs.verify-warranty');
    Route::get('repairs/search-purchases', [RepairController::class, 'searchPurchases'])->name('repairs.search-purchases');
    Route::get('repairs/quick', [RepairController::class, 'quickService'])->name('repairs.quick');
    Route::get('repairs/lookup', [RepairController::class, 'create'])->name('repairs.lookup');
    Route::get('repairs/rma', [RepairController::class, 'rmaIndex'])->name('repairs.rma.index');
    Route::post('repairs/rma/{id}/update-status', [RepairController::class, 'updateRmaStatus'])->name('repairs.rma.update-status');
    Route::delete('repairs/rma/{id}', [RepairController::class, 'deleteRma'])->name('repairs.rma.destroy');
    Route::post('repairs/rma/{id}/delete', [RepairController::class, 'deleteRma'])->name('repairs.rma.delete');
    Route::post('repairs/rma/clear-settled', [RepairController::class, 'clearSettledRma'])->name('repairs.rma.clear-settled');
    
    // Printable Documents
    Route::get('repairs/{id}/print-job-card', [RepairController::class, 'printJobCard'])->name('repairs.print-job-card');
    Route::get('repairs/{id}/print-sticker', [RepairController::class, 'printSticker'])->name('repairs.print-sticker');
    Route::get('repairs/{id}/print-dispatch-note', [RepairController::class, 'printDispatchNote'])->name('repairs.print-dispatch-note');
    Route::get('repairs/{id}/print-estimate', [RepairController::class, 'printEstimate'])->name('repairs.print-estimate');
    Route::get('repairs/{id}/print-advance-receipt', [RepairController::class, 'printAdvanceReceipt'])->name('repairs.print-advance-receipt');
    Route::get('repairs/{id}/print-delivery-note', [RepairController::class, 'printDeliveryNote'])->name('repairs.print-delivery-note');
    Route::get('repairs/{id}/print-invoice', [RepairController::class, 'printInvoice'])->name('repairs.print-invoice');
    Route::get('repairs/{id}/print-replacement-certificate', [RepairController::class, 'printReplacementCertificate'])->name('repairs.print-replacement-certificate');
    
    // Plan B Actions & Task Handlers
    Route::post('repairs/{id}/dispatch-to-lab', [RepairController::class, 'dispatchToLab'])->name('repairs.dispatch-to-lab');
    Route::post('repairs/{id}/log-lab-report', [RepairController::class, 'logLabReport'])->name('repairs.log-lab-report');
    Route::post('repairs/tasks/{id}/complete', [RepairController::class, 'completeTask'])->name('repairs.tasks.complete');

    // Existing Lifecycle & Financial Handlers
    Route::post('repairs/{id}/update-status', [RepairController::class, 'updateStatus'])->name('repairs.update-status');
    Route::post('repairs/{id}/items', [RepairController::class, 'addItem'])->name('repairs.items.store');
    Route::delete('repairs/{id}/items/{itemId}', [RepairController::class, 'deleteItem'])->name('repairs.items.destroy');
    Route::post('repairs/{id}/collect-payment', [RepairController::class, 'collectPayment'])->name('repairs.collect-payment');
    Route::post('repairs/{id}/sync-lab-bill', [RepairController::class, 'syncLabBill'])->name('repairs.sync-lab-bill');
    Route::post('repairs/{id}/authorize-replacement', [RepairController::class, 'authorizeReplacement'])->name('repairs.authorize-replacement');
    Route::post('repairs/{id}/attachments', [RepairController::class, 'addAttachment'])->name('repairs.attachments.store');
    Route::delete('repairs/{id}/attachments/{attachmentId}', [RepairController::class, 'deleteAttachment'])->name('repairs.attachments.destroy');
    Route::post('repairs/{id}/void-warranty', [RepairController::class, 'voidWarranty'])->name('repairs.void-warranty');
    Route::post('repairs/{id}/update-estimate', [RepairController::class, 'updateEstimate'])->name('repairs.update-estimate');
    Route::post('repairs/{id}/patient-decision', [RepairController::class, 'patientDecision'])->name('repairs.patient-decision');
    Route::resource('repairs', RepairController::class);


    Route::resource('income-expense-head', 'IncomeExpenseHeadController');
    Route::resource('income', 'IncomeController');
    Route::get('income-list', [IncomeController::class, 'incomeList'])->name('income.list');
    Route::resource('expense', 'ExpenseController');

    Route::get('employee-list', [EmployeeController::class, 'employeeList'])->name('employee.list');
    Route::get('employee-search', [HomeController::class, 'employeeSearch'])->name('employee.search');


    Route::resource('setting', 'SettingController');

    Route::get('settings-hub', [\App\Http\Controllers\Admin\SettingsHubController::class, 'index'])->name('settings.hub');
    Route::get('activity-log/export', [\App\Http\Controllers\Admin\ActivityLogController::class, 'export'])->name('activity-log.export');
    Route::post('user/{id}/status', [\App\Http\Controllers\Admin\UserController::class, 'setStatus'])->whereNumber('id')->name('user.status');
    Route::post('user/{id}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->whereNumber('id')->name('user.reset');
    Route::resource('user', 'UserController');

    //Report
    Route::get('report/purchase', [PurchaseController::class, 'purchaseReportCreate']);
    Route::post('report/purchase', [PurchaseController::class, 'purchaseReport'])->name('purchase.report');

    Route::get('report/sale', [SaleController::class, 'saleReportCreate']);
    Route::post('report/sale', [SaleController::class, 'saleReport'])->name('sale.report');

    Route::get('report/stock', [ReportController::class, 'stockReportByProduct']);
    Route::get('report/sales-performance', [ReportController::class, 'salesPerformanceReport']);
    Route::get('report/sales-performance/export/{type}/{format}', [ReportController::class, 'exportSalesPerformanceReport'])->name('report.sales-performance.export');
    Route::get('report/membership-loyalty', [ReportController::class, 'membershipLoyaltyReport']);
    Route::get('report/membership-loyalty/export/{type}/{format}', [ReportController::class, 'exportMembershipLoyaltyReport'])->name('report.membership-loyalty.export');
    Route::get('report/lead-funnel', [ReportController::class, 'leadFunnelReport']);
    Route::get('report/lead-funnel/export/{type}/{format}', [ReportController::class, 'exportLeadFunnelReport'])->name('report.lead-funnel.export');
    Route::get('report/batch-wise', [MedicineController::class, 'batchWiseStockReport']);

    Route::get('report/ledger', [ReportController::class, 'ledgerCreate']);
    Route::post('report/ledger', [ReportController::class, 'ledgerReport'])->name('report.ledger');

    Route::get('report/warranty-expiry', [ReportController::class, 'warrantyExpiryReport']);
    Route::get('report/battery-usage-expiry', [ReportController::class, 'batteryUsageExpiryReport']);

    Route::get('report/purchase-stock', [ReportController::class, 'purchaseStockReport'])->name('report.purchase-stock');
    Route::get('report/purchase-stock/export/{type}/{format}', [ReportController::class, 'exportPurchaseStockReport'])->name('report.purchase-stock.export');

    Route::get('report/doctor-referral', [\App\Http\Controllers\Admin\BusinessReportController::class, 'doctorStatement'])->name('report.doctor-referral');
    Route::get('report/doctor-statement', [\App\Http\Controllers\Admin\BusinessReportController::class, 'doctorStatement'])->name('report.doctor-statement');

    // Business reports (statement style)
    Route::get('report/supplier-statement', [\App\Http\Controllers\Admin\BusinessReportController::class, 'supplierStatement'])->name('report.supplier-statement');
    Route::get('report/purchase-summary', [\App\Http\Controllers\Admin\BusinessReportController::class, 'purchaseReport'])->name('report.purchase-summary');
    Route::get('report/profit-statement', [\App\Http\Controllers\Admin\ProfitStatementController::class, 'index'])->name('report.profit-statement');
    Route::get('report/profit-statement/detail', [\App\Http\Controllers\Admin\ProfitStatementController::class, 'detail'])->name('report.profit-detail');
    Route::get('report/profit-statement/budget', [\App\Http\Controllers\Admin\ProfitStatementController::class, 'budget'])->name('report.profit-budget');
    Route::post('report/profit-statement/budget', [\App\Http\Controllers\Admin\ProfitStatementController::class, 'saveBudget'])->name('report.profit-budget.save');
    Route::get('report/vendor-monthly', [\App\Http\Controllers\Admin\BusinessReportController::class, 'vendorMonthly'])->name('report.vendor-monthly');
    Route::get('report/advances', [\App\Http\Controllers\Admin\ReportController::class, 'advancesReport'])->name('report.advances');
    Route::get('report/services-status', [\App\Http\Controllers\Admin\ReportController::class, 'servicesStatusReport'])->name('report.services-status');

    // Customer Advances & Refunds (Parts C & D)
    Route::post('appointments/{id}/advance', [\App\Http\Controllers\Admin\CustomerAdvanceController::class, 'recordAppointmentAdvance'])->name('appointments.record-advance');
    Route::get('appointments/{id}/advance-receipt', [\App\Http\Controllers\Admin\CustomerAdvanceController::class, 'advanceReceipt'])->name('appointments.advance-receipt');
    Route::get('customers/{id}/active-advance', [\App\Http\Controllers\Admin\CustomerAdvanceController::class, 'getActiveAdvance'])->name('advances.active');
    Route::post('advances/{id}/refund', [\App\Http\Controllers\Admin\CustomerAdvanceController::class, 'refund'])->name('advances.refund');
    Route::get('advances/{id}/refund-receipt', [\App\Http\Controllers\Admin\CustomerAdvanceController::class, 'refundReceipt'])->name('advances.refund-receipt');

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // SMS Module — Main page (compose, log, analytics)
    Route::get('sms', [SmsController::class, 'index'])->name('sms.index');
    Route::post('sms/recipients', [SmsController::class, 'getRecipients']);
    Route::post('sms/send', [SmsController::class, 'sendCampaign'])->name('sms.send');
    Route::get('sms-log', [SmsController::class, 'logIndex'])->name('sms.log');
    Route::get('sms-analytics', [SmsController::class, 'analytics'])->name('sms.analytics');
    Route::post('sms/opt-outs', [SmsController::class, 'optOutStore'])->name('sms.opt-outs.store');
    Route::delete('sms/opt-outs/{id}', [SmsController::class, 'optOutDestroy'])->name('sms.opt-outs.destroy');

    // SMS Audience Builder (AJAX)
    Route::get('sms/audience-filter-config', [SmsController::class, 'audienceFilterConfig']);
    Route::post('sms/audience-quick-counts', [SmsController::class, 'audienceQuickCounts']);
    Route::post('sms/audience-filter', [SmsController::class, 'audienceFilter']);
    Route::post('sms/filter-groups', [SmsController::class, 'saveFilterGroup']);
    Route::get('sms/filter-groups', [SmsController::class, 'getFilterGroups']);
    Route::delete('sms/filter-groups/{id}', [SmsController::class, 'deleteFilterGroup']);
    
    // SMS Automations
    Route::resource('sms-automations', App\Http\Controllers\Admin\SmsAutomationController::class)->except(['show']);
    Route::post('sms-automations/{id}/toggle', [App\Http\Controllers\Admin\SmsAutomationController::class, 'toggle']);

    // SMS Templates — Full CRUD
    Route::resource('sms-templates', SmsTemplateController::class);

    // SMS Automations — Full CRUD


    // Database Backup
    Route::get('database-backup', [DatabaseBackupController::class, 'download'])->name('database.backup');

    // Role Management
    Route::resource('roles', 'RoleController');
});

require __DIR__.'/auth.php';
