<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debit Voucher phase 3: a voucher can have several lines (one ledger row per head) that share
 * the voucher number, so the number is no longer unique per row (the app keeps it unique per
 * voucher). Also adds the Cash & Bank Book report menu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ledgers', 'voucher_no')) {
            try {
                Schema::table('ledgers', function (Blueprint $table) {
                    $table->dropUnique('ledgers_voucher_no_unique');
                });
            } catch (\Throwable $e) {
                // already dropped
            }
            try {
                Schema::table('ledgers', function (Blueprint $table) {
                    $table->index('voucher_no', 'ledgers_voucher_no_index');
                });
            } catch (\Throwable $e) {
                // already indexed
            }
        }

        $parent = Menu::where('name', 'Accounts')->whereNull('parent_id')->first();
        if (!$parent) {
            return;
        }

        $last = Menu::where('parent_id', $parent->id)->max('order');
        $book = Menu::updateOrCreate(
            ['name' => 'Cash Bank Book', 'parent_id' => $parent->id],
            ['label' => 'Cash & Bank Book', 'route' => 'admin/voucher-book', 'icon' => null, 'order' => ($last ?? 0) + 1]
        );
        $perm = Permission::firstOrCreate(['menu_id' => $book->id, 'name' => 'cash_bank_book.index', 'action' => 'index']);

        Role::whereHas('permissions', fn ($q) => $q->where('name', 'expense.index'))->get()
            ->each(fn ($role) => $role->permissions()->syncWithoutDetaching([$perm->id]));
    }

    public function down(): void
    {
        $book = Menu::where('name', 'Cash Bank Book')->first();
        if ($book) {
            Permission::where('menu_id', $book->id)->delete();
            $book->delete();
        }
    }
};
