<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Grants the new dashboard widget permissions to existing roles so nobody loses widgets
 * after the dashboard upgrade. It ONLY adds permissions (syncWithoutDetaching); nothing is removed.
 *
 * Run after AclSeeder:  php artisan db:seed --class=DashboardWidgetPermissionSeeder
 */
class DashboardWidgetPermissionSeeder extends Seeder
{
    /** Module permission => dashboard widgets it unlocks. */
    protected array $map = [
        'sale_list.index'        => ['todays_sales', 'total_sales', 'revenue_chart'],
        'lead_list.index'        => ['total_leads', 'lead_pipeline', 'lead_charts', 'daily_lookout', 'follow_ups', 'marketing_insights'],
        'customer_list.index'    => ['total_customers', 'daily_lookout', 'warranty_alerts'],
        'product.index'          => ['total_products', 'inventory'],
        'task_list.index'        => ['total_tasks', 'daily_lookout'],
        'ticket_list.index'      => ['total_tickets', 'daily_lookout'],
        'appointment_list.index' => ['appointments', 'daily_lookout'],
        'expense.index'          => ['net_balance', 'total_purchases'],
    ];

    /** Old widget set — a role that had all of these is treated as a full-dashboard role. */
    protected array $legacyFull = [
        'todays_sales', 'total_products', 'total_leads', 'total_customers', 'total_tickets',
        'total_tasks', 'appointments', 'net_balance', 'total_sales', 'total_purchases', 'performance',
    ];

    public function run(): void
    {
        $dashPerms = Permission::where('name', 'like', 'dashboard.%')->pluck('id', 'name');

        foreach (Role::with('permissions:id,name')->get() as $role) {
            if ($role->name === 'Super Admin') {
                // Super Admin bypasses checks anyway, but keep the role editor tidy
                $role->permissions()->syncWithoutDetaching($dashPerms->values()->all());
                continue;
            }

            $has = $role->permissions->pluck('name');
            if (!$has->contains('dashboard.index')) {
                continue; // role has no dashboard access — leave untouched
            }

            $grant = collect();

            $isFull = collect($this->legacyFull)->every(fn ($a) => $has->contains('dashboard.' . $a));
            if ($isFull) {
                // Full-dashboard roles (e.g. Admin) get every widget incl. view-all-data
                $grant = $dashPerms->keys();
            } else {
                foreach ($this->map as $modulePerm => $widgets) {
                    if ($has->contains($modulePerm)) {
                        foreach ($widgets as $w) {
                            $grant->push('dashboard.' . $w);
                        }
                    }
                }
            }

            $ids = $grant->unique()->map(fn ($n) => $dashPerms[$n] ?? null)->filter()->values()->all();
            $role->permissions()->syncWithoutDetaching($ids);

            $this->command?->info("  {$role->name}: +" . count($ids) . ' dashboard widget permission(s)');
        }
    }
}
