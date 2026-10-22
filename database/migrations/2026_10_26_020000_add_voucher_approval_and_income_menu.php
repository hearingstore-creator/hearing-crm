<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debit / Credit Voucher phase 2: approval stamp on vouchers, the Credit Voucher (Income) menu,
 * and voucher edit / approve permissions. Existing roles keep working: whoever could create or
 * list expenses gets the matching new rights; approval is left for Super Admin to assign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('ledgers', 'status')) {
                $table->string('status', 20)->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('ledgers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });

        $parent = Menu::where('name', 'Accounts')->whereNull('parent_id')->first();
        $expense = $parent ? Menu::where('parent_id', $parent->id)->where('name', 'Expense')->first() : null;
        if (!$parent || !$expense) {
            return;
        }

        $income = Menu::updateOrCreate(
            ['name' => 'Income', 'parent_id' => $parent->id],
            ['label' => 'Credit Vouchers (Income)', 'route' => 'admin/income', 'icon' => null, 'order' => ($expense->order ?? 0) + 1]
        );

        $perm = fn (Menu $menu, string $prefix, string $action) => Permission::firstOrCreate([
            'menu_id' => $menu->id, 'name' => $prefix . '.' . $action, 'action' => $action,
        ]);

        foreach (['edit', 'approve'] as $action) {
            $perm($expense, 'expense', $action);
        }
        foreach (['index', 'create', 'delete', 'approve'] as $action) {
            $perm($income, 'income', $action);
        }

        $grant = function (string $has, array $give) {
            Role::whereHas('permissions', fn ($q) => $q->where('name', $has))->get()->each(function ($role) use ($give) {
                $ids = Permission::whereIn('name', $give)->pluck('id')->all();
                $role->permissions()->syncWithoutDetaching($ids);
            });
        };
        $grant('expense.index', ['income.index']);
        $grant('expense.create', ['income.create', 'expense.edit']);
        $grant('expense.delete', ['income.delete']);
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            foreach (['status', 'approved_by', 'approved_at'] as $col) {
                if (Schema::hasColumn('ledgers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
