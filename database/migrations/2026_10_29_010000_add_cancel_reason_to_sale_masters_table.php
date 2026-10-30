<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cancelled sale is now kept (deleted_at + deleted_by mark it cancelled) instead of being
 * hard-deleted, so it can still be viewed and reprinted. This column holds the reason given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'cancel_reason')) {
                $table->string('cancel_reason', 500)->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};
