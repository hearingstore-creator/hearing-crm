# Demo data and what it found

Written 2026-10-20. The local database (`crm_local`) was filled with realistic hearing-care data by using the CRM's own screens, so every rule (validation, stock, ledgers, membership points, SMS automation) ran for real. Nothing was typed straight into business tables except reference data and a few clearly marked touch-ups listed below.

## Safety

- **No real SMS or email was sent.** The seeding server ran on its own port with the SMS gateway pointed at a local stand-in that only answers "success". The live SSL Wireless token in `.env` was never used. The CRM's SMS log shows 158 messages as "sent"; none left the computer.
- **Undo everything:** the database before any demo data is saved at `storage/app/demo/crm_local_BEFORE_demo_data.sql` (ignored by git). To go back to it, with the XAMPP MySQL tools:

  ```bash
  mysql -u root crm_local < storage/app/demo/crm_local_BEFORE_demo_data.sql
  ```

  (This replaces the whole local database. It also removes the districts and days reference rows added below, so run `database/seeders` or re-add them if you need them.)
- Demo logins (all `@zenhear.test`, one shared password) are listed in `storage/app/demo/accounts.txt`.
- The scripts that built it are in `storage/app/demo/scripts/` (`s1_setup.php` ... `s17_last.php`, plus `crawl.php`).

## What is in it

| Area | Amount |
|---|---|
| Company | Zenhear Hearing Care, three branches (Dhanmondi, Chattogram, Mymensingh) + head office |
| People | 17 logins: 7 staff with role templates, 6 professionals (audiologists etc.), branch logins |
| Stock | 28 products (Phonak, Oticon, Signia, ReSound, Widex, Starkey hearing aids by serial; Rayovac batteries; accessories), 12 purchases from 6 suppliers, 5 stock transfers, 7 adjustments |
| Patients | 38 customers and 61 leads with hearing profiles, 30 deals in the pipeline (won, lost, reopened, moved back) |
| Sales | 39 invoices (Jul to Oct 2026, about 6.0 million BDT), 42 services, delivery steps, dues collected, 1 sale return, 1 purchase return |
| Doctors | 6 ENT doctors with chambers, referral commission owed and paid |
| Appointments | 20 over the coming days: queue states, advances, reschedule, cancel, leave and Friday-closed rosters |
| Repairs | 5 jobs: lab warranty, void estimate with advance, quick counter service, replacement with RMA, third-party device |
| Support | 13 tickets (notes, assignment, solved/closed, linked to invoices and devices) and 8 tasks |
| Membership | tiers edited (Bronze to Platinum, Gold and Platinum with their own point value), 4 milestones, 3 referrals rewarded, points redeemed |
| SMS | 6 templates, automations switched on, one opt-out, 2 campaigns |
| Money | 14 expense and income heads, monthly expenses per branch, supplier and doctor payments, advances refunded and kept, a customer payment |

Touch-ups made directly in the database (not through screens), because the screens cannot do them:

- Districts (63) and weekdays were empty and were added; sound booths (2 per branch) were added; the 3 pre-existing demo referrals and 2 appointments I booked for the wrong person were corrected.
- Timestamps: records were created in one afternoon, so `created_at` was spread across Jul to Oct to match their own dates (`s15_history.php`).
- Margins: stock cost was scaled by 0.72 everywhere it is stored so the Profit Statement shows a realistic 45% gross margin (`s16_margins.php`), and head-office salary, advertising and rent were scaled down.

## Bugs found and fixed (in the working tree, tests in `DemoDataFindingsTest`)

1. **General Settings never saved.** `HelperTrait` turned every model into "fillable only the tracking columns", so models using `$guarded` (Settings, and legacy Employee, Salary, Designation models) silently ignored every field. The screen said "Updated Successfully" and changed nothing. On the live site company name, address, VAT and payment instructions may never have saved.
2. **Manual Income was saved with no amount.** The form posts `paid_amount`; the ledger helper reads `paid`. Every entry from the Income screen recorded NULL. Also now refuses an expense head, zero and text amounts.
3. **Two supplier payments in the same second crashed** (duplicate ledger number). Same fix for doctor payments and repair payments.
4. **A branch-wide break on the roster crashed** with "Undefined array key user_id".

## Gaps found, not fixed (your decision)

1. **Referrals cannot be started.** Nothing in the CRM creates a referral (no field on lead, patient or sale to say "referred by member X"). The tier "referral bonus" you edit therefore never pays out unless a row is inserted by hand. Needs a "Referred by" field.
2. **Sound booths have no screen.** They exist only through a seeder with hard-coded branch ids. A new branch has no booth, so hearing tests cannot be booked there ("No sound booth available at this branch").
3. **Reference tables were empty** (districts, weekdays) on this database and are not part of the deploy. Check the live site has them.
4. **Income has no menu or permission**, so only Super Admin can record income (camp fees, interest).
5. **Branch logins cannot use Support** (tickets, tasks), and head-office staff cannot link a branch's invoices or devices to a ticket.
6. **Front Desk / Sales template cannot take in a repair** (view only), so a counter quick service gets 403. Consider "Repairs: add and edit" for that template.
7. **Super Admin gets 403 or 404 on branch-only actions** (mark a branch service done, update a branch delivery). Branch isolation is strict; confirm that is intended.
8. **Dead routes:** the old resource routes expose `show`, `edit` and legacy modules (medicine, employee, customer return, manufacturer return) that fail with 500 when opened by URL. None is in a menu. 28 such URLs were found by opening every screen as every role.
9. After a lab replaces a unit, the separate "Authorize replacement" step errors ("serial already assigned"); the lab report already did the replacement. Confusing, not harmful.

Everything else opened without an error for all 9 logins (head office, 6 staff roles, branch login, professional): about 175 screens for Super Admin.
