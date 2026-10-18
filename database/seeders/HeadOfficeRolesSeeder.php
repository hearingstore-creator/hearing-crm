<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Head Office staff roles: Manager, Call Center, Accounts.
 *
 * Safe to run any time: a role's permission preset is applied ONLY when the role is first created,
 * so changes made later in Roles → Manage Permissions are never overwritten.
 *
 *   php artisan db:seed --class=HeadOfficeRolesSeeder
 */
class HeadOfficeRolesSeeder extends Seeder
{
    public function run(): void
    {
        $all = Permission::pluck('id', 'name');

        foreach ($this->presets($all->keys()->all()) as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $this->command?->info("Role '$roleName' already exists — permissions left unchanged.");
                continue;
            }

            $role = Role::create(['name' => $roleName]);
            $ids = collect($permissionNames)->map(fn ($n) => $all[$n] ?? null)->filter()->unique()->values()->all();
            $role->permissions()->sync($ids);
            $this->command?->info("Role '$roleName' created with " . count($ids) . ' permissions.');
        }
    }

    private function presets(array $all): array
    {
        // Manager: runs the business day-to-day. Everything except system admin, deleting records and data export/import.
        $managerExcluded = ['roles.', 'users.', 'setting.edit', 'database_backup.', 'activity_log.'];
        $manager = array_values(array_filter($all, function ($p) use ($managerExcluded) {
            foreach ($managerExcluded as $prefix) {
                if (str_starts_with($p, $prefix)) {
                    return false;
                }
            }
            return !str_ends_with($p, '.delete') && !str_ends_with($p, '.export') && !str_ends_with($p, '.import');
        }));

        $callCenter = [
            'dashboard.index', 'dashboard.daily_lookout', 'dashboard.follow_ups', 'dashboard.appointments',
            'dashboard.total_leads', 'dashboard.lead_pipeline', 'dashboard.lead_charts',
            'leads.index', 'add_lead.create', 'lead_list.index', 'lead_list.view', 'lead_list.edit',
            'deal_board.index', 'deal_board.move', 'deal_board.won', 'deal_board.lost', 'deal_board.transfer',
            'web_leads.index',
            'customer.index', 'customer_list.index',
            'appointment.index', 'add_appointment.create', 'appointment_list.index', 'appointment_list.edit', 'calendar_view.index',
            'add_task.create', 'task_list.index', 'task_list.edit',
            'support.index', 'add_ticket.create', 'ticket_list.index', 'ticket_list.edit',
            'doctor.index', 'doctor_list.index',
            // Hotline support: read-only invoices, warranty & battery (no sale create/edit/delete)
            'customer_lookup.index', 'sales.index', 'sale_list.index', 'sale_list.view',
            'report.index', 'warranty_expiry.index', 'battery_usage_expiry.index',
            // Keeps seeing leads/invoices after they are transferred to a branch (read-only)
            'vendor_list.view_all_branches',
        ];

        $accounts = [
            'dashboard.index', 'dashboard.view_all_data', 'dashboard.todays_sales', 'dashboard.total_sales',
            'dashboard.total_purchases', 'dashboard.net_balance', 'dashboard.cash_today', 'dashboard.revenue_chart',
            'dashboard.receivables_aging', 'dashboard.payment_mix', 'dashboard.expense_revenue', 'dashboard.supplier_aging',
            'sales.index', 'add_sale.create', 'sale_list.index', 'sale_list.view',
            'sale_return.index', 'sale_return.create', 'sale_return.view',
            'purchase.index', 'add_purchase.create', 'purchase_list.index', 'purchase_list.view',
            'purchase_return.index', 'purchase_return.create', 'purchase_return.view',
            'accounts.index', 'ledger.index', 'expense.index', 'expense.create',
            'income_expense_head.index', 'income_expense_head.create', 'income_expense_head.edit',
            'supplier_payment.index', 'supplier_payment.create', 'doctor_payment.index', 'doctor_payment.create',
            'supplier.index', 'supplier_list.index', 'supplier_statement.index',
            'customer.index', 'customer_list.index',
            'inventory.index', 'stock.index',
            'report.index', 'purchase_report.index', 'vendor_monthly_report.index', 'sales_performance.index',
            'membership_&_loyalty.index', 'reward_ledger.index', 'reward_wallets.index',
            'vendor_list.view_all_branches',
        ];

        return [
            'Manager'     => $manager,
            'Call Center' => array_values(array_intersect($callCenter, $all)),
            'Accounts'    => array_values(array_intersect($accounts, $all)),
        ];
    }
}
