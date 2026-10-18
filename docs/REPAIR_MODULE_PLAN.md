# Repair & Replacement Module – Final Process Architectural Plan & Gap Analysis

> **Status**: Ready for User Review & Formal Approval  
> **Document Purpose**: Gap analysis of the existing codebase, additive data model updates, text wireframes, phased implementation blueprint, and business questions.  
> **Constraint Checklist**:  
> - [x] **No Code Written Yet** (Analysis & Specification Only)  
> - [x] **100% Additive** (Zero breaking changes to existing Sales, Invoices, Ledgers, Purchases, Suppliers, or Inventory)  
> - [x] **All 9 Requirements Addressed** in deep technical and operational detail  
> - [x] **Specific Questions Listed** for user business decisions  

---

## Table of Contents
1. [Summary of Current Repair Module & Gap Analysis](#1-summary-of-current-repair-module--gap-analysis)
   - [1.1 What Already Matches](#11-what-already-matches)
   - [1.2 What Must Change](#12-what-must-change)
   - [1.3 What is Missing](#13-what-is-missing)
2. [One Pipeline for All Jobs (The 6 Stages & Automatic Path Engine)](#2-one-pipeline-for-all-jobs-the-6-stages--automatic-path-engine)
   - [2.1 The 6 Stages & Closing Exits](#21-the-6-stages--closing-exits)
   - [2.2 Automatic Path Routing Matrix](#22-automatic-path-routing-matrix)
   - [2.3 One Clear Next-Action Button per Stage](#23-one-clear-next-action-button-per-stage)
3. [Automatic Tasks & Reminders Engine](#3-automatic-tasks--reminders-engine)
4. [Warranty & Serial Number Lineage Rules](#4-warranty--serial-number-lineage-rules)
5. [Supplier Bills, BDT 0 Memos & Reconciliation View](#5-supplier-bills-bdt-0-memos--reconciliation-view)
   - [5.1 The 4 Supplier Document Scenarios](#51-the-4-supplier-document-scenarios)
   - [5.2 BDT 0 Replacement Stock In/Out Flow](#52-bdt-0-replacement-stock-inout-flow)
   - [5.3 Monthly Supplier Reconciliation View](#53-monthly-supplier-reconciliation-view)
6. [Customer Payments & Accounting Architecture](#6-customer-payments--accounting-architecture)
7. [Printable Documents Specification](#7-printable-documents-specification)
8. [Data Model Changes (Tables & Columns)](#8-data-model-changes-tables--columns)
9. [UI/UX Architecture & Text Wireframes](#9-uiux-architecture--text-wireframes)
   - [9.1 6-Stage Main Board & Task Bar](#91-6-stage-main-board--task-bar)
   - [9.2 Streamlined Receiving Wizard](#92-streamlined-receiving-wizard)
   - [9.3 Job Detail Screen with Single Next-Action Button](#93-job-detail-screen-with-single-next-action-button)
   - [9.4 Supplier Reconciliation View](#94-supplier-reconciliation-view)
10. [Phased Build Order](#10-phased-build-order)
11. [Questions for You Before Implementation](#11-questions-for-you-before-implementation)

---

## 1. Summary of Current Repair Module & Gap Analysis

An exhaustive review of the existing codebase (`repair_jobs`, `serial_chains`, `repair_status_logs`, `repair_items`, `repair_attachments`, `RepairController.php`, `WarrantyVerificationService.php`, `BusinessReportController.php`, `SupplierLedger.php`, `PurchaseMaster.php`, and associated Blade views) shows strong groundwork. However, the current system diverges in several areas from your streamlined 6-stage pipeline.

### 1.1 What Already Matches
* **Serial Number Lineage (`serial_chains`)**: Tracks parent-child serial lineage (`parent_serial_no`, `replacement_job_id`). Option A (remaining original manufacturer warranty) is already calculated and inherited. Replaced units are flagged as `retained_by_supplier`.
* **Replaced Serial Intake Protection**: The system already detects decommissioned/replaced serial numbers at intake and blocks duplicate repair creation, guiding staff to the active serial.
* **Customer & Supplier Ledger Linking**: Customer ledger debit/credit and supplier ledger entries exist for repair charges.
* **Hardware Barcode Scanning**: Global barcode scanning listener (`DNS1D` Code 128) is functional on desktop and counter scanners.
* **Intake Photos & Attachments**: `repair_attachments` table and upload modals support intake physical condition photos and void evidence.
* **Core Printable Documents**: Job Card (`print_job_card.blade.php`), Product Sticker (`print_sticker.blade.php`), Delivery Note (`print_delivery_note.blade.php`), and Replacement Certificate (`print_replacement_certificate.blade.php`) are already designed.

---

### 1.2 What Must Change
1. **Pipeline & Status Alignment (From 10 Ad-hoc Statuses &rarr; Strict 6 Stages)**:
   - *Current Code*: Uses 10 loosely organized statuses (`received`, `sent_to_lab`, `in_lab`, `lab_received`, `waiting_patient_approval`, `patient_approved`, `patient_declined`, `ready_for_pickup`, `delivered`, `cancelled`).
   - *Must Change*: Consolidate into the **exact 6 sequential stages**:
     1. `Received`
     2. `At Lab`
     3. `Waiting Approval`
     4. `In Repair`
     5. `Ready for Pickup`
     6. `Delivered`  
     Plus 2 closing exits: `Returned unrepaired` (patient declined) and `Cancelled`.
2. **Automatic Path Assignment (Eliminate Manual Staff Choices)**:
   - *Current Code*: Staff can pick warranty statuses or transition states via generic dropdowns, creating confusion for non-technical employees.
   - *Must Change*: The system **automatically assigns the fixed pipeline path at Stage 1** based on the warranty verification. Staff never pick the progression path manually.
3. **Dispatch Tracking at Stage 2 (`At Lab`)**:
   - *Current Code*: Only records a free-form lab name and date.
   - *Must Change*: Explicitly record **Courier Dispatch** (Courier Name + Tracking / Consignment Number) OR **By Hand Dispatch** (Bearer / Staff Person Name), plus a printable **Dispatch Note to Supplier**.
4. **Supplier Bill Alignment with Supplier Statements**:
   - *Current Code*: Writes generic text lines into `supplier_ledgers` without creating a linked bill that mirrors the supplier's actual cash memo.
   - *Must Change*: Implement strict matching with supplier cash memos:
     - Bill Reference = **Supplier's memo number exactly as printed**.
     - Bill Date = **Supplier's memo date**.
     - BDT 0.00 bills for warranty replacements **must appear explicitly** in supplier statements and ledgers as `0.00` lines so statements match line-by-line.
5. **Stage Progression UX (Single Next-Action Button)**:
   - *Current Code*: Multiple action buttons, dropdowns, and nested modal triggers exist simultaneously on the job detail screen.
   - *Must Change*: Display **one clear, primary next-action button** suited to the active stage (e.g., "Send to Lab", "Supplier Reported", "Patient Approved", "Receive from Lab", "Deliver & Settle").

---

### 1.3 What is Missing
1. **Automatic Tasks & Reminders Engine**:
   - Missing logic: Received before 5:00 PM &rarr; task "Send to lab today"; received after 5:00 PM &rarr; task "Send to lab tomorrow".
   - Missing logic: Next-day daily recurring reminder "Follow up supplier" while in `At Lab`. Highlighting jobs exceeding lab turnaround SLA ($N$ days).
   - Missing logic: "Call patient with estimate" task + 2-day reminder escalation.
   - Missing logic: "Confirm lab to proceed" task on the same day patient approves.
   - Missing logic: 7-day pickup reminder SMS schedule for uncollected devices.
2. **Missing Printable Documents**:
   - **Dispatch Note to Supplier** (courier or by-hand delivery challan).
   - **Standalone Patient Cost Estimate Slip** (printable & SMS-ready).
   - **Advance Payment Receipt Slip** (for advance taken at patient approval).
3. **Monthly Supplier Reconciliation View**:
   - A dedicated screen: select one supplier, select one month &rarr; lists our recorded repair bills (both BDT 0 and paid) alongside payments, enabling instant cross-checking against the supplier's physical statement.
4. **Inspection Charge Handling on Customer Decline**:
   - Formal creation of a small inspection charge bill when a patient declines a paid repair estimate, updating customer ledger and receipt printing.
5. **Per-Job Financial Margin Card**:
   - Clear on-screen financial breakdown for every job: `Patient Paid` vs. `Supplier Cost` vs. `Gross Margin (Profit/Loss)`.

---

## 2. One Pipeline for All Jobs (The 6 Stages & Automatic Path Engine)

### 2.1 The 6 Stages & Closing Exits

| # | Stage Key | Display Name | What Happens | Moves On When |
|---|---|---|---|---|
| **1** | `received` | **Received** | In-house inspection; automatic warranty check; physical condition & accessories; print receiving slip with barcode. | Sent to supplier lab, or fixed in-house. |
| **2** | `at_lab` | **At Lab** | Dispatched to supplier lab by courier (courier name + tracking #) or by hand (person's name). | Supplier reports back with findings / memo. |
| **3** | `waiting_approval` | **Waiting Approval** | Paid jobs only: supplier's cost estimate given to patient by phone/SMS. | Patient approves (&rarr; 4) or declines (&rarr; Returned unrepaired). |
| **4** | `in_repair` | **In Repair** | Clinic confirmed lab to proceed with the repair. | Repaired/replaced unit received back from lab. |
| **5** | `ready_for_pickup`| **Ready for Pickup** | Product received in clinic; supplier memo recorded; automated pickup SMS sent to patient. | Patient collects device at counter. |
| **6** | `delivered` | **Delivered** | Final payment settled (advance deducted); delivery note & warranty certificate issued. | **Closed**. |

**Closing Exits:**
* **Returned Unrepaired**: Patient declined cost estimate at Stage 3. Unit returned with optional inspection fee.
* **Cancelled**: Intake cancelled prior to lab work or dispatch.

---

### 2.2 Automatic Path Routing Matrix
Staff never choose the path manually. The system assigns the path automatically at intake:

```
[ INTAKE (Stage 1: Received) ]
       │
       ├─► Small counter fix (tubing, filter, buffing)? ──────────────► [ Stage 5: Ready ] ─► [ Stage 6: Delivered ]
       │
       ├─► Provisional Warranty? (Looks good outside, in warranty)
       │     │
       │     ▼
       │   [ Stage 2: At Lab ] ──► Lab Teardown & Inspection:
       │          │
       │          ├─► 1. Lab Repaired under Warranty (Free) ──────────► [ Stage 4: In Repair ] ─► [ Stage 5: Ready ] ─► [ Stage 6: Delivered ]
       │          │
       │          ├─► 2. Lab Replaced under Warranty (BDT 0 Memo) ────► [ Stage 5: Ready (New S/N) ] ─► [ Stage 6: Delivered ]
       │          │
       │          └─► 3. LAB DECLARED VOID (Internal Moisture/Corrosion)
       │                 │
       │                 ▼ (DYNAMIC PIPELINE DIVERSION!)
       │              [ Stage 3: Waiting Approval ] (Quoted Estimate ৳X)
       │                     │
       │                     ├─► Patient Approves ────────────────────► [ Stage 4: In Repair ] ─► [ Stage 5: Ready ] ─► [ Stage 6: Delivered ]
       │                     │
       │                     └─► Patient Declines ────────────────────► [ Exit: Returned Unrepaired ]
       │
       └─► Out of Warranty / Void at Counter / Third-Party? ──────────► [ Stage 2: At Lab ] ─► [ Stage 3: Approval ] ─► [ Stage 4 ] ─► [ Stage 5 ] ─► [ Stage 6 ]
                                                                                                    │
                                                                                                    └─► Declined ───► [ Exit: Returned Unrepaired ]
```

| Job Classification | Pipeline Sequence | Key Financial & Operational Rules |
|---|---|---|
| **In-House Repair** | **1 &rarr; 5 &rarr; 6** | Minor fixes (wax filter, tube, cleaning). No lab dispatch. Immediate or same-day delivery. |
| **Supplier Warranty Repair** | **1 &rarr; 2 &rarr; 4 &rarr; 5 &rarr; 6** | Covered 100% by manufacturer. Skips Stage 3 (no approval needed; patient cost is ৳0). No supplier bill. |
| **Supplier Warranty Replacement** | **1 &rarr; 2 &rarr; 5 &rarr; 6** | Lab replaces defective unit directly with new unit under BDT 0 cash memo. Old unit kept by supplier. |
| **Provisional Warranty &rarr; Lab Declared Void** | **1 &rarr; 2 &rarr; 3 &rarr; 4 &rarr; 5 &rarr; 6** | Lab discovers internal moisture/sweat corrosion. System dynamically diverts to Stage 3 for patient estimate approval. |
| **Paid Repair / Void at Intake / Third-Party** | **1 &rarr; 2 &rarr; 3 &rarr; 4 &rarr; 5 &rarr; 6** | Out of warranty or 3rd party. Lab inspects and quotes estimate. Patient approval mandatory at Stage 3. Advance collected. |
| **Patient Declines Estimate** | **3 &rarr; Returned Unrepaired** | Lab returns unserviced unit. Optional inspection charge billed. Job closed as unrepaired. |

---

### 2.3 One Clear Next-Action Button per Stage
To keep work frictionless for non-technical counter staff, each stage displays **one primary action button**:

* **Stage 1 (Received)**:
  - If In-House path: `[ ⚡ Complete In-House & Mark Ready ]`
  - If Lab path: `[ 📦 Dispatch to Supplier Lab ]` *(opens quick modal: Courier or By Hand)*
* **Stage 2 (At Lab)**:
  - If Warranty Repair: `[ 🔧 Lab Confirmed Repair Done ]` &rarr; moves to Stage 4 or 5
  - If Warranty Replacement: `[ 🔄 Receive Replacement Unit (BDT 0) ]` &rarr; moves to Stage 5
  - If Paid Job: `[ 📋 Log Lab Estimate & Contact Patient ]` &rarr; moves to Stage 3
* **Stage 3 (Waiting Approval)**:
  - `[ ✓ Patient Approved (Record Advance) ]` &rarr; moves to Stage 4
  - Secondary red link: `[ ✕ Patient Declined (Return Unrepaired) ]`
* **Stage 4 (In Repair)**:
  - `[ 📥 Receive Unit Back from Lab ]` &rarr; captures supplier memo # & moves to Stage 5
* **Stage 5 (Ready for Pickup)**:
  - `[ 🤝 Handover to Patient & Collect Due ]` &rarr; settles payment & moves to Stage 6
* **Stage 6 (Delivered)**:
  - Badge: `[ ✓ Job Completed & Closed ]` (Prints Delivery Note & Warranty Certificate)

---

## 3. Automatic Tasks & Reminders Engine

The system automatically generates actionable tasks for staff based on job states and timestamps:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        AUTOMATIC TASK GENERATOR                        │
├──────────────────────────┬─────────────────────────────────────────────┤
│ Trigger Event            │ Generated Task & Reminder Rule              │
├──────────────────────────┼─────────────────────────────────────────────┤
│ Job Received < 5:00 PM   │ Task: "Send to lab today" (due today 6 PM)  │
│ Job Received >= 5:00 PM  │ Task: "Send to lab tomorrow" (due next 12 PM)│
│ Dispatched to Lab        │ Daily Task: "Follow up with [Supplier] lab" │
│ Lab Delayed (> N days)   │ Highlight card RED: "[Supplier] overdue"    │
│ Moved to Waiting Approval│ Task: "Call patient with estimate (৳[Amt])" │
│ Approval Pending > 2 Days│ Urgent Reminder: "Follow up patient decision"│
│ Patient Approves         │ Same-Day Task: "Confirm lab to proceed"     │
│ Moved to Ready for Pickup│ Automated SMS dispatched immediately        │
│ Pickup Pending > 7 Days  │ Automated Reminder SMS dispatched to patient│
└──────────────────────────┴─────────────────────────────────────────────┘
```

* **Audit Trail**: Every status change writes to `repair_status_logs` recording: `from_stage`, `to_stage`, `user_id`, `created_at`, and `minutes_in_previous_stage`.
* **Stage Age**: Every card and table row displays a clean counter: e.g., `"At Lab: 3 days"` or `"Waiting Approval: 2 days"`.

---

## 4. Warranty & Serial Number Lineage Rules

### 4.1 Automatic Warranty Classification
Intake automatically checks and categorizes the unit into one of 5 strict statuses:
1. **Under Warranty**: Active product warranty found in sales history; expiration date is in the future. BDT 0 coverage.
2. **Expired**: Found in sales history, but original warranty period has ended. Paid path.
3. **No Warranty**: Product was sold with 0 warranty months. Paid path.
4. **Void**: Physically cracked, water-damaged, or tampered. Staff enters mandatory void reason + optional photo. Automatically converts to paid path.
5. **Third-Party**: Device was not purchased from our clinic. Staff enters brand, model, serial, and customer details manually. Always follows paid path.

### 4.2 Serial Number Lineage Engine
* **Replacement Lineage**: When a replacement is issued by the supplier lab, the new serial number is linked directly to the **original sales invoice** in `serial_chains`.
* **Warranty Clock Continuation**: The new replacement unit strictly inherits the **original warranty end date** (Option A). Staff can check an authorized override if the supplier explicitly granted a fresh warranty period.
* **Old Defective Unit**: The old serial is marked permanently as **`retained_by_supplier`** (Kept by Supplier). It cannot be reused, resold, or re-entered into repair intake.
* **360° Search**: Scanning or typing either the original serial or the replacement serial displays the complete lifecycle: `Original Sale Invoice` &rarr; `Repair Job #1` &rarr; `Replacement Challan` &rarr; `Active Serial`.

---

## 5. Supplier Bills, BDT 0 Memos & Reconciliation View

To ensure our accounts match the supplier’s physical statement line-by-line, the Repair module integrates directly with the supplier ledger and purchase system.

### 5.1 The 4 Supplier Document Scenarios

| Job Type | Supplier Document | System Recording & Accounting Impact |
|---|---|---|
| **Warranty Repair** | None | **No supplier bill**. System adds internal audit note: `"Warranty repair, no supplier charge"`. |
| **Warranty Replacement** | Cash memo, BDT 0 | **Supplier Bill (Payable) with BDT 0 total**: Records product + new serial, quantity 1, price ৳0.00. Appears on supplier ledger as a ৳0.00 line item so our statement matches supplier's memo list exactly. |
| **Paid Repair** | Cash memo with amount | **Supplier Bill (Payable) with Service Cost**: Records supplier memo #, date, and labor/parts cost &rarr; posted as Expense / Accounts Payable. |
| **Paid Replacement** | Cash memo with amount | **Supplier Bill (Payable) with Product & Serial**: Records product + new serial at supplier's charged exchange price. |

**Strict Accounting Rules:**
1. **Bill Reference**: Recorded as the supplier's memo/invoice number **exactly as printed on their paper document**.
2. **Bill Date**: Set to the **supplier's memo date**, not the entry date.
3. **BDT 0 Lines**: Are **never filtered or hidden** in supplier ledgers or statements. They appear as explicit `0.00` entries for full auditing.

---

### 5.2 BDT 0 Replacement Stock In/Out Flow
For warranty replacements from supplier labs:
1. **Stock Entry**: Replacement unit enters clinic inventory at `Cost = ৳0.00`, `Qty = 1`, with its new serial number, linked to the supplier's BDT 0 memo.
2. **Stock Issuance**: Unit is issued to the patient at `Price = ৳0.00`, `Qty = 1`, linked to the original purchase invoice.
3. **Net Inventory Impact**: The clinic's inventory balance remains completely balanced without phantom inventory gains or financial distortion.

---

### 5.3 Monthly Supplier Reconciliation View
A dedicated reconciliation tool designed for monthly vendor audits:
* **Inputs**: Select **Supplier** (e.g., Signia, Phonak, Starkey) + Select **Month / Year** (e.g., October 2026).
* **Display Table**:
  - Date
  - Supplier Memo # (as printed)
  - Job Card #
  - Item / Service Description
  - Serial Number (Old & New)
  - Bill Amount (Debit/Credit, including BDT 0.00 entries)
  - Payment Reference & Paid Amount
  - Running Balance
  - **Reconciliation Checkbox**: Staff can check off lines that match the supplier's paper statement.

---

## 6. Customer Payments & Accounting Architecture

### 6.1 Estimate & Advance Payment
* **Estimate**: For paid jobs, the estimate is sent via SMS and can be printed as a formal quotation.
* **Advance Payment**: When a patient approves at Stage 3, staff can take an advance (e.g., ৳1,000). The system:
  1. Issues an **Advance Payment Receipt**.
  2. Credits `customer_ledgers` and records Cash/Bank receipt.
  3. Displays on the job card: `Estimate: ৳3,500 | Advance Paid: ৳1,000 | Balance Due: ৳2,500`.

### 6.2 Inspection Charge on Decline
* If a patient declines the repair estimate at Stage 3, the clinic can optionally charge an **Inspection Fee** (e.g., ৳300, or ৳0):
  - Generates an **Inspection Charge Receipt / Bill**.
  - Posts to customer ledger as settled.
  - Job exits as **Returned Unrepaired**.

### 6.3 Final Settlement & Profit/Loss
* **At Delivery (Stage 6)**:
  $$\text{Final Customer Bill} = \text{Agreed Repair Charge} - \text{Advance Paid}$$
* **Per-Job Margin Visibility**:
  $$\text{Gross Profit} = \text{Total Customer Paid} - \text{Total Supplier Cost}$$
  Displayed prominently on the Job Detail page and financial reports.

---

## 7. Printable Documents Specification

All documents share the clinic's standard dual-header design (`document_header.blade.php`) and are available in A4, A5, and POS thermal formats:

| # | Document | Primary Format | Key Information Included |
|---|---|---|---|
| **1** | **Receiving Slip / Job Card** | A4 / A5 + 40x25mm Sticker | Patient copy with Code 128 barcode, intake checklist, physical condition notes, accessories kept, warranty status, and terms. Sticker sticks to device case. |
| **2** | **Dispatch Note to Supplier** | A4 / A5 | Supplier name, challan date, courier name + tracking # or bearer name, device details, serial number, reported defects, and requested action (Repair / Replacement). |
| **3** | **Cost Estimate Slip** | A5 / 80mm POS | Itemized parts and labor estimates, valid-until date, terms of repair, and acceptance signature line. |
| **4** | **Advance Payment Receipt** | A5 / 80mm POS | Advance receipt #, job card #, patient name, advance amount received, payment method, and remaining estimated balance due. |
| **5** | **Customer Repair Bill** | A4 / A5 | Official tax invoice at delivery: labor charges, parts replaced, total cost, advance deducted, net paid, and cashier signature. |
| **6** | **Delivery Note & Warranty Card** | A4 / A5 | Confirmation of handover, replacement serial numbers (Old S/N &rarr; New S/N), and Post-Warranty Service Guarantee Certificate (1, 3, or 6 months). |

---

## 8. Data Model Changes (Tables & Columns)

All modifications are strictly **additive** and preserve existing database integrity:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        DATA MODEL MODIFICATIONS                        │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Modify Table: repair_jobs (Add Dispatch, Routing & Task Fields)     │
│    + pipeline_path        ENUM('in_house', 'warranty_repair',          │
│                                'warranty_replacement', 'paid_repair')   │
│    + dispatch_type        ENUM('courier', 'by_hand') NULLABLE          │
│    + courier_name         VARCHAR(100) NULLABLE                        │
│    + courier_tracking_no  VARCHAR(100) NULLABLE                        │
│    + carrier_person_name  VARCHAR(100) NULLABLE                        │
│    + dispatched_at        DATETIME NULLABLE                            │
│    + confirmed_to_lab_at  DATETIME NULLABLE                            │
│    + supplier_bill_id     BIGINT UNSIGNED NULLABLE (FK -> purchase_m)  │
│    + supplier_memo_date   DATE NULLABLE                                │
│    + advance_receipt_no   VARCHAR(50) NULLABLE                         │
│    + declined_fee         DECIMAL(10,2) DEFAULT 0                      │
│    + cancellation_mode    ENUM('refunded', 'customer_credit') NULLABLE │
│                                                                        │
│ 2. Modify Table: serial_chains (Enhanced Supplier Tracking)            │
│    + status               Ensure 'retained_by_supplier' is locked      │
│    + supplier_memo_no     VARCHAR(100) NULLABLE                        │
│                                                                        │
│ 3. Create Table: repair_tasks (Task & Reminder Engine)                 │
│    + id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY   │
│    + repair_job_id        BIGINT UNSIGNED (FK -> repair_jobs)          │
│    + task_type            VARCHAR(50)                                  │
│                           ('send_to_lab', 'follow_up_supplier',        │
│                            'call_patient', 'confirm_lab', 'pickup_sms')│
│    + title                VARCHAR(255)                                 │
│    + due_date             DATETIME                                     │
│    + is_completed         BOOLEAN DEFAULT FALSE                        │
│    + completed_at         DATETIME NULLABLE                            │
│    + completed_by         BIGINT UNSIGNED NULLABLE (FK -> users)       │
│    + timestamps                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 9. UI/UX Architecture & Text Wireframes

### 9.1 6-Stage Main Board & Task Bar

```text
+----------------------------------------------------------------------------------------------------+
|  [Logo] REPAIR & REPLACEMENT HUB                     Branch: [ Dhanmondi v ]   User: Staff Sarah   |
|                                                                                                    |
|  +----------------------------------------------------------------------------------------------+  |
|  | [ 📷 SCAN BARCODE / SERIAL / INVOICE ] [ Type S/N, Phone or Job #...       ] [ LOOKUP / GO ] |  |
|  +----------------------------------------------------------------------------------------------+  |
|                                                                                                    |
|  TODAY'S ACTION TASKS:                                                                             |
|  [ ⚠️ 2 Jobs received after 5 PM: Send to lab today ]   [ 📞 3 Patients to call with estimates ]    |
|  [ ⏰ 1 Lab delayed beyond 5 days: Signia Lab ]        [ ✉️ 2 Devices ready > 7 days: Send SMS ]   |
|                                                                                                    |
|  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+
|  | 1. RECEIVED   |  | 2. AT LAB     |  | 3. APPROVAL   |  | 4. IN REPAIR  |  | 5. READY (3)  |  | 6. DELIVERED  |
|  | (2 Jobs)      |  | (5 Jobs)      |  | (2 Jobs)      |  | (4 Jobs)      |  | ৳ 3,500 Due   |  | (42 Closed)   |
|  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+
|  | #REP-0045     |  | #REP-0042     |  | #REP-0040     |  | #REP-0038     |  | #REP-0035     |  | Recent 5      |
|  | Signia Pure   |  | Phonak M50    |  | Oticon Real   |  | Signia AX     |  | Resound 7     |  | items...      |
|  | Under Warr    |  | Courier: SA   |  | Est: ৳4,000   |  | Lab Confirmed |  | Ready: 1 day  |  |               |
|  | Recv: Today   |  | At Lab: 3d [!] |  | Waiting: 1d   |  | Repairing...  |  | Due: ৳1,500   |  |               |
|  | [Dispatch >]  |  | [Supplier Rep]|  | [Call Patient]|  | [Receive Back]|  | [⚡ Handover] |  | [View Slip]   |
|  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+  +---------------+
+----------------------------------------------------------------------------------------------------+
```

---

### 9.2 Streamlined Receiving Wizard (Single Question Focus)

```text
+----------------------------------------------------------------------------------------------------+
|  NEW INTAKE WIZARD (Step 1: Identify Device & Auto-Verify)                                         |
+----------------------------------------------------------------------------------------------------+
|  Scan Serial or Invoice: [ 📷 SN-882191                               ] [ Verify ]                 |
|                                                                                                    |
|  SYSTEM VERIFICATION:                                                                              |
|  +----------------------------------------------------------------------------------------------+  |
|  | [✓ GREEN] UNDER PRODUCT WARRANTY                                                             |  |
|  | Product: Signia Motion 13 · Customer: Abdul Karim (01711-XXXXXX) · Inv #6AD201                 |  |
|  | Expiration: 14 Months Remaining (Expires 12 Dec 2027)                                        |  |
|  | Assigned Pipeline: WARRANTY REPAIR (1 -> 2 -> 4 -> 5 -> 6) [Auto-Selected]                    |  |
|  +----------------------------------------------------------------------------------------------+  |
|                                                                                                    |
|  Physical Condition Inspection:                                                                    |
|  (o) Intact & Normal Wear                                                                          |
|  ( ) Physical or Liquid Damage -> DECLARE WARRANTY VOID (Converts to Paid Pipeline + Attach Photo) |
|                                                                                                    |
|  Symptoms: [x] No Sound  [ ] Whistling  [ ] Distorted  [ ] Intermittent  [ ] Dead Shell           |
|  Accessories Kept: [x] Hearing Aid Unit (Side: Right)  [x] Case  [ ] Eartip  [ ] Batteries         |
|                                                                                                    |
|                                                                   [ CREATE JOB & PRINT SLIP > ]    |
+----------------------------------------------------------------------------------------------------+
```

---

### 9.3 Job Detail Screen with Single Next-Action Button

```text
+----------------------------------------------------------------------------------------------------+
|  JOB CARD #REP-2610-0042                                          [ PRINT JOB CARD ] [ STICKER ]   |
|  Patient: Abdul Karim (01711-XXXXXX) · Device: Signia Motion 13 · S/N: SN-88219                   |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  PIPELINE PROGRESSION (Stage 2 of 6: AT LAB):                                                      |
|  [✓ Received] ──► [* AT LAB *] ──► [ Waiting Approval ] ──► [ In Repair ] ──► [ Ready ] ──► [Done] |
|   16 Oct 11am     Dispatched 17 Oct                                                                |
|                   Courier: Sundarban (Trk: #SB-99201) | Time at Lab: 2 Days                        |
|                                                                                                    |
|  +----------------------------------------------------------------------------------------------+  |
|  | PRIMARY NEXT ACTION:                                                                         |  |
|  | [ 📦 LOG SUPPLIER REPORT / MEMO RECEIVED ]                                                   |  |
|  | (Click when lab reports back with diagnosis or replacement memo)                             |  |
|  +----------------------------------------------------------------------------------------------+  |
|                                                                                                    |
|  SUPPLIER BILL & MEMO DETAILS:                                                                     |
|  Supplier: Signia Central Lab · Bill Type: Warranty Replacement · Memo #: CH-8891 (BDT 0.00)       |
|                                                                                                    |
|  FINANCIAL PROFIT & LOSS:                                                                          |
|  Customer Charge: ৳ 0.00 | Advance: ৳ 0.00 | Supplier Cost: ৳ 0.00 | Net Profit/Loss: ৳ 0.00       |
+----------------------------------------------------------------------------------------------------+
```

---

### 9.4 Supplier Reconciliation View

```text
+----------------------------------------------------------------------------------------------------+
|  SUPPLIER RECONCILIATION AUDIT                                                                     |
|  Supplier: [ Signia Bangladesh v ]   Month: [ October 2026 v ]   [ FILTER ]   [ 🖨️ PRINT STATEMENT ]|
+----------------------------------------------------------------------------------------------------+
|  DATE        | MEMO #     | JOB CARD    | SERVICE / REPLACEMENT DETAILS        | BILL (BDT) | STATUS |
+--------------+------------+-------------+--------------------------------------+------------+--------+
| 04 Oct 2026  | CH-1042    | #REP-0031   | Amplifier Repair (Paid Job)          |   1,500.00 | [✓ OK] |
| 11 Oct 2026  | CH-1088    | #REP-0038   | Warranty Replacement S/N: SN-9901    |       0.00 | [✓ OK] |
| 16 Oct 2026  | CH-1120    | #REP-0042   | Warranty Replacement S/N: SN-9944    |       0.00 | [✓ OK] |
| 20 Oct 2026  | CH-1155    | #REP-0049   | Receiver Assembly (Paid Job)         |   2,200.00 | [✓ OK] |
+--------------+------------+-------------+--------------------------------------+------------+--------+
| TOTAL BILLS (Oct 2026): ৳ 3,700.00 (Includes 2 BDT 0 Warranty Lines)                               |
| TOTAL PAYMENTS MADE:   ৳ 3,700.00 | NET OUTSTANDING DUE: ৳ 0.00                                   |
+----------------------------------------------------------------------------------------------------+
```

---

## 10. Phased Build Order

To ensure safe, incremental delivery without interrupting live sales, inventory, or clinic operations:

### Phase 1: Pipeline Alignment & Automatic Path Engine (Smallest Useful MVP)
* Update `repair_jobs` schema with `pipeline_path`, `dispatch_type`, courier tracking, and bearer fields.
* Refactor job lifecycle into the strict 6 stages.
* Implement the Automatic Path Routing engine (staff never pick the path manually).
* Implement the Single Next-Action Button pattern across all 6 stages.
* Ensure all existing repair jobs seamlessly map to the 6 stages without data loss.

### Phase 2: Dispatch Tracking & New Printable Documents
* Add Dispatch modal (Courier Name + Tracking # vs. By-Hand Person Name).
* Create **Dispatch Note to Supplier** printable view.
* Create **Customer Cost Estimate Slip** printable & SMS view.
* Create **Advance Payment Receipt Slip** printable view.
* Implement Inspection Charge creation and receipt on patient decline.

### Phase 3: Supplier Bills, BDT 0 Memos & Reconciliation View
* Implement BDT 0.00 Supplier Bill creation for warranty replacements linked to exact supplier memo # and memo date.
* Implement BDT 0 Stock In / Stock Out linked to the original purchase invoice.
* Build the **Monthly Supplier Reconciliation View** (one supplier, one month, line-by-line statement matching).
* Add per-job Profit & Loss card (`Patient Paid - Supplier Cost = Margin`).

### Phase 4: Automatic Task & Reminder Engine
* Implement time-of-day intake logic (before 5:00 PM &rarr; today's task; after 5:00 PM &rarr; tomorrow's task).
* Build the Main Dashboard Task & Reminder Bar.
* Implement daily recurring "Follow up supplier" tasks and lab delay highlight badges.
* Schedule 7-day pickup reminder SMS for devices left uncollected in clinic.

---

## 11. Questions for You Before Implementation

To align the code exactly with your daily clinic operations, please clarify the following 3 questions:

1. **Advance Payment on Cancelled Jobs**:
   - If a customer paid an advance at Stage 3, but the job is later cancelled:
     - **Option A**: Refund the advance in cash/bank (posts debit entry, money returned to patient).
     - **Option B**: Retain as Customer Credit balance in their ledger (can be used for future battery, accessory, or hearing aid purchases).
     - **Option C**: Allow staff to choose between Refund or Store Credit upon cancellation.

2. **Lab Delayed Days Threshold**:
   - After how many days at the supplier lab should a job card be highlighted in **red** as delayed? (e.g., 5 days, 7 days, or 10 days?)

3. **Supplier Bill Table Integration**:
   - For supplier bills (both BDT 0 and paid repair bills), should they be recorded directly inside the existing `purchase_masters` table (with type `'repair_service'` / `'repair_replacement'`) so they appear natively in your existing purchase reports, or would you prefer a separate dedicated `supplier_bills` table that synchronizes with `supplier_ledgers`?
