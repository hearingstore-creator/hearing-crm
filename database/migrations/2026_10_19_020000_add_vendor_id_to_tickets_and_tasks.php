<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets and tasks get a branch (NULL = head office, which all existing rows are), so branch
 * staff only see their own branch's complaints once they are given Support rights.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tickets', 'tasks'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('vendor_id')->nullable()->after('customer_id')->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tickets', 'tasks'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('vendor_id');
                });
            }
        }
    }
};
