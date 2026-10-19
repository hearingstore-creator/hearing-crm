# Report Menu Plan

Status: plan only, no code yet. Written 2026-10-21 after reading the Report menu, `BusinessReportController`, `ReportController`, `ReportHub` and the ledger writers. Figures were not checked against live data (the local database is empty), so verify with real numbers before trusting any total.

## Status

- **Phase 1 built (2026-10-21):** `App\Services\ProfitStatement`, `ProfitStatementController`, view `admin/report/profit-statement`, menu entry `Profit Statement` (permission `profit_statement.index`, also listed in the Reports Hub and in the Branch Manager and Accounts role templates), CSV and Excel export, shared date filter `App\Support\ReportPeriod`. Built on the recommended defaults: invoice-date basis, referral commission shown as owed, repairs included, access by permission only (branch-bound people are forced to their own branch). 193 tests pass.
- **Bug fixed after phase 1:** line discounts (`sale_details.discount`, `service_details.discount`) are percentages, not amounts; the statement now applies them as percentages (test added).
- **Phase 2 built (2026-10-21), partly:** `vendor_id` and `branch` are one filter on every report (`Helper::forceReportBranch`); branch-bound people still cannot widen it. CSV export added to Stock, Warranty Expiry, Battery Expiry, Advances and Services Status (shared `CsvDownload` and export-buttons partial); report labels in the menu now end in "Report" or "Statement"; a test checks every menu report is in the hub. **Not done:** replacing each report's own filter form with one shared filter bar (only the Profit Statement and the Purchase and Supplier reports use the shared period filter), and moving the stray reports (Ledger, Doctor statements, Deal reports) into the Report menu. 199 tests pass.
- Phases 3 and 4: not started.

## 1. Goal

A standard monthly **Profit Statement per branch**, and a Report menu where every report looks and behaves the same:

- Income by category (products, services, repairs), referral, product purchase price (cost of goods)
- Expenses by category
- Everything else (other income, returns, discounts) and the resulting profit

## 2. What exists today

| Where | Reports |
|---|---|
| Report menu | Reports Hub, Stock, Purchase & Stock, Purchase Report, Supplier Statement, Branch Monthly, Warranty Expiry, Battery Expiry, Sales Performance, Membership & Loyalty, Lead Funnel, Advances, Services Status |
| Outside the menu | Ledger (Accounts), Doctor Statement and Doctor & Referral (Doctor), Deal Reports (Leads), Repair History and Supplier Reconciliation (Repairs), SMS Analytics, Customer Ledger |
| Reports Hub | Lists most of the above in 7 groups (`ReportHub::GROUPS`); also takes labels and permissions from `config/acl.php` |

## 3. Findings

### Branch Monthly is not a reliable profit report

`BusinessReportController::branchMetrics()` computes:

- `gross_profit = sales total - sum(qty x purchase_price)` (products only)
- `net = gross_profit - expenses`, where expenses = every ledger `Expense` row except type `Purchase`

Problems:

1. **Double count.** Ledger expenses also contain `Supplier Payment` and `Doctor Payment` rows. Supplier payments are the cash side of the product cost already taken off as cost of goods. Doctor payments are the cash side of referral commission. Net profit is understated, and the amount depends on whether suppliers were paid at purchase (type `Purchase`, excluded) or later (`Supplier Payment`, included).
2. **No income categories.** One sales total only. No split by product category, brand, type or service type.
3. **Referral is not a line.** Accrued commission lives in `sale_masters.reference_amount`; cash paid out is `Doctor Payment`. Neither is shown.
4. **Returns and refunds** (`Credit Note`, `Customer Refund`, `Advance Refund`, `Cancellation Fee`) are not taken off revenue and some land in expenses.
5. **Repairs missing.** `repair_jobs` has customer charge and supplier cost fields that never reach the statement.
6. **Expenses are one number.** Ledger rows carry `income_expense_head_id`, but the report does not group by it.
7. **Services have no cost** and are mixed with products in the total.

### The menu is not standardised

| Area | Today | Standard to apply |
|---|---|---|
| Date choice | month picker, period presets or start/end, depending on the report | one filter bar: preset (This month, Last month, This quarter, This year, Custom) |
| Branch filter | `vendor_id` on some, `branch` on others | one name, same rule: branch-bound users forced to own branch (`Helper::forceReportBranch`) |
| Export | Excel on some, CSV only on others, none on some | CSV and Excel on every report |
| Placement | about a third of reports are outside the Report menu | every report listed in the menu and the hub |
| Names | mix of "Report", "Statement", "Summary", "Monthly" | consistent: `<Subject> report` or `<Subject> statement` |

## 4. Profit Statement design

One screen, one branch or all branches side by side (head office only), for a month or any range. Compared with the previous period.

| Line | Source |
|---|---|
| **Income** | |
| Products by category | `sale_details` (price x qty, less line discount), grouped by `products.category_id` |
| Services by service type | `service_details`, grouped by `service_type_id` |
| Repair income | `repair_jobs.total_customer_cost` for jobs delivered in the period |
| Less: returns and discounts | `sale_returns`, `sale_masters.total_discount` |
| **Net sales** | |
| **Cost of goods** | `sale_details.qty x purchase_price`, by product category |
| Repair supplier cost | `repair_jobs.total_supplier_cost` |
| **Gross profit** | |
| **Referral commission** | `sale_masters.reference_amount` for the period; paid and unpaid shown beside it |
| **Operating expenses by category** | `ledgers` with `amount_type = Expense`, grouped by `income_expense_head_id`; excludes `Supplier Payment`, `Doctor Payment`, `Purchase`, `Customer Refund`, `Advance Refund` (already counted above) |
| **Other income by category** | `ledgers` income rows with a head that are not sales receipts, advances or repair payments |
| **Net profit** | gross profit - referral - operating expenses + other income |

Rules:

- **Basis:** invoice date for sales and cost, ledger date for expenses and other income. Alternative on request: cash basis (money actually received and paid).
- **Reconciliation line** at the bottom: ties the statement back to ledger totals and lists what was excluded and why, so the figure can be audited.
- **Branch rule:** `vendor_id IS NULL` is head office; branch users see only their own branch; people with `vendor_list.view_all_branches` can compare.
- **Permission:** new menu item `Profit Statement` under Report (`profit_statement.index`), not granted to any role by default (money data).
- **Exports:** CSV and Excel, same rows as the screen.

## 5. Phases

| # | Phase | Result |
|---|---|---|
| 1 | Profit Statement service + screen + export + tests | New report, correct profit, reconciliation line |
| 2 | Standardise the menu | One filter bar and export component, one branch parameter, all reports in the menu and hub with consistent names |
| 3 | Tie-ins | Branch Monthly keeps only activity counts (leads, appointments, invoices); dashboard gross profit tile and `expense_revenue` read from the same service |
| 4 | Optional | Monthly trend (12 months), category drill-down to invoices, budget vs actual |

Each phase is shippable on its own and committed separately.

## 6. Decisions needed before phase 1

1. **Basis:** invoice date (recommended) or cash basis?
2. **Referral commission:** show as owed in the month of sale (recommended) or only when paid?
3. **Repairs:** include in the statement, or keep repair profit separate?
4. **Who sees it:** Owner and Accounts only, or branch managers for their own branch too?

## 7. Risks and checks

- Compare phase 1 output with the old Branch Monthly and the ledger for one closed month on live data before relying on it; differences should be explained by the double-count items above.
- Old sales may have `purchase_price` of 0 on lines; the statement should flag lines with no cost rather than show inflated profit (same idea as `SaleCost` shadow mode).
- Cancelled or reversed ledger rows use `X (Reversed)` types (`LedgerReversal`); the exclusion list must handle these.
- Deploy: new menu goes in `config/acl.php`; no destructive migration.
