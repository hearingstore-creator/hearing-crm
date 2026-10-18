# ARCHIVE: Repair & Replacement Module – Plan A (Legacy Model Archive)

> **Document Status**: Preserved Safety Archive  
> **Retention Policy**: To be kept active during the dual-run testing of Plan B. Can be safely deleted after Plan B is validated and approved.  
> **Target System**: Hearing Care & Clinic ERP / CRM  
> **File Location**: `docs/REPAIR_MODULE_PLAN_A_ARCHIVE.md`  

---

## 1. Plan A Architecture Overview

Plan A is the original, flexible repair management system implemented across Phases 1 through 4:
* **Lifecycle**: 10 ad-hoc statuses (`received`, `sent_to_lab`, `in_lab`, `lab_received`, `waiting_patient_approval`, `patient_approved`, `patient_declined`, `ready_for_pickup`, `delivered`, `cancelled`).
* **Intake**: 4-Step Intake Wizard (`admin/repairs/create.blade.php`) and Quick Walk-in Counter Service (`admin/repairs/quick.blade.php`).
* **RMA Defective Stock**: A dedicated RMA Supplier Stock Board (`admin/repairs/rma.blade.php`) tracking defective devices in clinic or dispatched to suppliers.
* **Accounting Sync**: Automatic posting to `CustomerLedger` (debit on chargeable jobs, credit on payment) and `SupplierLedger` (credit for lab charges and replacement memos).
* **Serial Lineage**: `serial_chains` table tracking `parent_serial_no`, `replacement_job_id`, and `retained_by_supplier`.
* **Printable Documents**: Job Card with Code 128 barcode, 40x25mm Product Thermal Sticker, Itemized Tax Invoice, Delivery Note, and Warranty Replacement Certificate.

---

## 2. Plan A Database Schema

### Table 1: `repair_jobs`
* `id`, `job_no`, `vendor_id`, `customer_id`, `sale_master_id`, `sale_detail_id`, `product_id`
* `is_third_party`, `custom_brand`, `custom_model`
* `original_serial_no`, `replaced_serial_no`
* `side`, `job_type`, `is_quick_service`, `warranty_status`, `parent_repair_job_id`
* `void_reason`, `void_by`
* `reported_issues`, `physical_condition`, `accessories_included`
* `current_status` (10 enum values)
* `supplier_id`, `lab_ref_no`, `sent_to_lab_at`, `lab_received_at`, `lab_diagnosis`
* `is_chargeable`, `inspection_fee`, `estimate_amount`, `estimate_approved`, `approved_at`
* `total_customer_cost`, `total_customer_paid`, `total_supplier_cost`, `supplier_paid`
* `repair_warranty_months`, `repair_warranty_until`, `promised_date`
* `delivered_at`, `delivered_by`, `received_by`, `created_by`, `updated_by`, `deleted_by`
* `remarks`, `timestamps`, `softDeletes`

### Table 2: `serial_chains`
* `id`, `serial_no`, `product_id`, `customer_id`, `original_sale_id`
* `parent_serial_no`, `replacement_job_id`
* `status` (`active_with_customer`, `faulty_in_clinic`, `with_supplier_lab`, `credited_by_supplier`, `retained_by_supplier`, `scrapped`)
* `warranty_start_date`, `warranty_end_date`, `is_void`, `void_reason`
* `timestamps`

### Table 3: `repair_items`
* `id`, `repair_job_id`, `item_type`, `product_id`, `item_name`, `qty`
* `customer_unit_price`, `customer_total`, `supplier_unit_cost`, `supplier_total`
* `is_warranty_covered`, `timestamps`

### Table 4: `repair_status_logs`
* `id`, `repair_job_id`, `from_status`, `to_status`, `remarks`, `user_id`, `created_at`

### Table 5: `repair_attachments`
* `id`, `repair_job_id`, `file_path`, `attachment_type`, `caption`, `uploaded_by`, `timestamps`

---

## 3. Plan A Routes & Endpoints

```php
Route::get('repairs', [RepairController::class, 'index'])->name('repairs.index');
Route::get('repairs/create', [RepairController::class, 'create'])->name('repairs.create');
Route::get('repairs/quick', [RepairController::class, 'quickService'])->name('repairs.quick');
Route::post('repairs', [RepairController::class, 'store'])->name('repairs.store');
Route::get('repairs/{id}', [RepairController::class, 'show'])->name('repairs.show');
Route::post('repairs/{id}/status', [RepairController::class, 'updateStatus'])->name('repairs.update-status');
Route::get('repairs/verify-warranty', [RepairController::class, 'verifyWarranty'])->name('repairs.verify-warranty');
Route::get('repairs/search-purchases', [RepairController::class, 'searchPurchases'])->name('repairs.search-purchases');
Route::get('repairs/{id}/print/job-card', [RepairController::class, 'printJobCard'])->name('repairs.print.job-card');
Route::get('repairs/{id}/print/sticker', [RepairController::class, 'printSticker'])->name('repairs.print.sticker');
Route::get('repairs/{id}/print/delivery-note', [RepairController::class, 'printDeliveryNote'])->name('repairs.print.delivery-note');
Route::get('repairs/{id}/print/invoice', [RepairController::class, 'printInvoice'])->name('repairs.print.invoice');
Route::get('repairs/{id}/print/replacement-certificate', [RepairController::class, 'printReplacementCertificate'])->name('repairs.print.replacement-certificate');
Route::post('repairs/{id}/items', [RepairController::class, 'addItem'])->name('repairs.items.add');
Route::delete('repairs/{id}/items/{itemId}', [RepairController::class, 'deleteItem'])->name('repairs.items.delete');
Route::post('repairs/{id}/payment', [RepairController::class, 'collectPayment'])->name('repairs.payment.collect');
Route::post('repairs/{id}/replacement', [RepairController::class, 'authorizeReplacement'])->name('repairs.replacement.authorize');
Route::get('repairs/rma/index', [RepairController::class, 'rmaIndex'])->name('repairs.rma.index');
Route::post('repairs/rma/{id}/status', [RepairController::class, 'updateRmaStatus'])->name('repairs.rma.update-status');
Route::post('repairs/rma/{id}/delete', [RepairController::class, 'deleteRma'])->name('repairs.rma.delete');
Route::post('repairs/rma/clear-settled', [RepairController::class, 'clearSettledRma'])->name('repairs.rma.clear-settled');
Route::post('repairs/{id}/attachments', [RepairController::class, 'addAttachment'])->name('repairs.attachments.add');
Route::delete('repairs/{id}/attachments/{attId}', [RepairController::class, 'deleteAttachment'])->name('repairs.attachments.delete');
Route::post('repairs/{id}/void-warranty', [RepairController::class, 'voidWarranty'])->name('repairs.void-warranty');
Route::post('repairs/{id}/estimate', [RepairController::class, 'updateEstimate'])->name('repairs.estimate.update');
Route::post('repairs/{id}/patient-decision', [RepairController::class, 'patientDecision'])->name('repairs.patient-decision');
```

---

## 4. Why Plan A is Being Upgraded to Plan B

1. **Status Confusion**: 10 statuses allowed non-technical staff to jump out of sequence or leave jobs in ambiguous states (`lab_received` vs `waiting_patient_approval`).
2. **Missing Transit Proof**: No courier tracking number or bearer name when shipping hearing aids worth up to ৳200,000 to external labs.
3. **Supplier Reconciliation Gap**: Replacement cash memos with BDT 0 total were not logged as formal 0.00 bill lines, preventing line-by-line reconciliation against supplier paper statements.
4. **Manual Decision Fatigue**: Staff had to choose the next step manually instead of having the system automatically guide them with a single next-action button.

---

*This document is frozen as a historical safety archive. It can be safely deleted once Plan B testing is completed.*
