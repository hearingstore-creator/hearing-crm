<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AppointmentController;
use App\Http\Controllers\Admin\DatabaseBackupController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerReturnController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\MembershipController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\MedicineController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\ReportController;
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

Route::get('/', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

Route::group(['namespace' => '\App\Http\Controllers\Admin', 'prefix' => 'admin', 'middleware' => 'auth'], function() {
    Route::get('/dashboard', [HomeController::class, 'dashboard']);
    Route::resource('leads', 'LeadController');
    Route::post('leads/{id}/follow-ups', [LeadController::class, 'storeFollowUp']);
    Route::put('leads/follow-ups/{id}', [LeadController::class, 'updateFollowUp']);
    Route::post('leads-import', [LeadController::class, 'importLeads']);
    Route::get('leads-export', [LeadController::class, 'exportToXlsx']);
    Route::get('lead-search', [LeadController::class, 'leadSearch'])->name('lead.search');
    Route::get('appointments/professionals-by-service/{serviceId}', [AppointmentController::class, 'getProfessionalsByService']);
    Route::get('appointments/professional-slots/{user_id}/{date}', [AppointmentController::class, 'getProfessionalSlots']);
    Route::get('appointments/calendar', [AppointmentController::class, 'calendar']);
    Route::get('appointments/calendar-data', [AppointmentController::class, 'getCalendarData']);
    Route::post('appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
    Route::resource('appointments', 'AppointmentController');
    Route::resource('professionals', 'ProfessionalController');
    Route::resource('appointment-services', 'AppointmentServiceController');
    Route::resource('time-slots', 'TimeSlotController');
    Route::resource('ticket', 'TicketController');
    Route::resource('task', 'TaskController');
    Route::resource('category', 'CategoryController');
    Route::resource('template', 'TemplateController');
    Route::resource('proposal', 'ProposalController');
    Route::resource('service-type', 'ServiceTypeController');
    Route::resource('type', 'TypeController');
    Route::resource('brand', 'BrandController');
    Route::resource('doctor', 'DoctorController');
    Route::resource('product', 'ProductController');
    Route::get('product-search', [ProductController::class, 'productSearch'])->name('product.search');
    Route::get('product-info/{id}', [ProductController::class, 'productInfo']);

    Route::resource('customers', 'CustomerController');
    Route::post('customers-import', 'CustomerController@importCustomers')->name('customers.import');
    Route::get('customer-search', [CustomerController::class, 'customerSearch'])->name('customer.search');

    Route::get('customer-list', [CustomerController::class, 'customerList'])->name('customer.list');
    Route::get('customer-ledger/create', [CustomerController::class, 'createCustomerLedger']);
    Route::post('customer-ledger', [CustomerController::class, 'customerLedger'])->name('customer.ledger');
    Route::get('customer-payment', [CustomerController::class, 'payment']);
    Route::post('customer-payment', [CustomerController::class, 'paymentStore'])->name('customer.payment');

    // Membership & Reward System Routes
    Route::prefix('membership')->group(function() {
        Route::get('tiers', [MembershipController::class, 'tiers'])->name('membership.tiers');
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
    Route::get('suppliers-payment/{id}/edit', [SupplierController::class, 'supplierPaymentEdit'])->name('suppliers.payment.edit');
    Route::put('suppliers-payment/{id}', [SupplierController::class, 'supplierPaymentUpdate'])->name('suppliers.payment.update');
    Route::delete('suppliers-payment/{id}', [SupplierController::class, 'supplierPaymentDestroy'])->name('suppliers.payment.destroy');
    Route::get('suppliers-due/{id}', [SupplierController::class, 'getSupplierDue'])->name('suppliers.due');

    Route::resource('vendors', 'VendorController');

    Route::get('doctors-payment', [SaleController::class, 'doctorPaymentIndex'])->name('doctors.payment.index');
    Route::get('doctors-payment/create', [SaleController::class, 'doctorPaymentCreate'])->name('doctors.payment.create');
    Route::post('doctors-payment', [SaleController::class, 'doctorPaymentStore'])->name('doctors.payment.store');
    Route::get('doctors-payment/{id}/edit', [SaleController::class, 'doctorPaymentEdit'])->name('doctors.payment.edit');
    Route::put('doctors-payment/{id}', [SaleController::class, 'doctorPaymentUpdate'])->name('doctors.payment.update');
    Route::delete('doctors-payment/{id}', [SaleController::class, 'doctorPaymentDestroy'])->name('doctors.payment.destroy');
    Route::get('doctors-due/{id}', [SaleController::class, 'getDoctorDue'])->name('doctors.due');
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
    Route::get('medicine-search', [HomeController::class, 'medicineSearch'])->name('medicine.search');

    Route::resource('sale', 'SaleController');
    Route::get('sale-list', [SaleController::class, 'saleList'])->name('sale.list');
    Route::get('sale-add-row-item', [SaleController::class, 'addRowItem'])->name('sale.add-row');
    Route::get('sale-add-service-row-item', [SaleController::class, 'addServiceRowItem'])->name('sale.add-service-row');
    Route::get('product-serial-numbers/{id}', [SaleController::class, 'getProductSerialNumbers'])->name('sale.product.serial');
    Route::get('customer-redeem-points/{id}', [SaleController::class, 'getCustomerRedeemPoints'])->name('sale.customer.redeem-points');
    Route::get('sale-pos-print/{id}', [SaleController::class, 'posPrint'])->name('sale.pos-print');


    Route::resource('income-expense-head', 'IncomeExpenseHeadController');
    Route::resource('income', 'IncomeController');
    Route::get('income-list', [IncomeController::class, 'incomeList'])->name('income.list');
    Route::resource('expense', 'ExpenseController');

    Route::get('employee-list', [EmployeeController::class, 'employeeList'])->name('employee.list');
    Route::get('employee-search', [HomeController::class, 'employeeSearch'])->name('employee.search');


    Route::resource('setting', 'SettingController');

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

    Route::get('report/doctor-referral', [ReportController::class, 'doctorReferralReport'])->name('report.doctor-referral');
    Route::get('report/doctor-referral/export/{type}/{format}', [ReportController::class, 'exportDoctorReferralReport'])->name('report.doctor-referral.export');

    // Activity Log
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    // SMS Module — Main page (compose, log, analytics)
    Route::get('sms', [SmsController::class, 'index'])->name('sms.index');
    Route::post('sms/recipients', [SmsController::class, 'getRecipients']);
    Route::post('sms/send', [SmsController::class, 'sendCampaign'])->name('sms.send');
    Route::get('sms-log', [SmsController::class, 'logIndex'])->name('sms.log');
    Route::get('sms-analytics', [SmsController::class, 'analytics'])->name('sms.analytics');

    // SMS Templates — Full CRUD
    Route::resource('sms-templates', SmsTemplateController::class);

    // SMS Automations — Full CRUD


    // Database Backup
    Route::get('database-backup', [DatabaseBackupController::class, 'download'])->name('database.backup');

    // Role Management
    Route::resource('roles', 'RoleController');
});

require __DIR__.'/auth.php';
