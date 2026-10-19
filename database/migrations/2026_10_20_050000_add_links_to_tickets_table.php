<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional links from a ticket to the invoice and device it is about, and to the repair job opened for it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('tickets', 'sale_master_id')) {
                $table->unsignedBigInteger('sale_master_id')->nullable()->after('customer_id')->index();
            }
            if (!Schema::hasColumn('tickets', 'serial_no')) {
                $table->string('serial_no', 100)->nullable()->after('sale_master_id');
            }
            if (!Schema::hasColumn('tickets', 'repair_job_id')) {
                $table->unsignedBigInteger('repair_job_id')->nullable()->after('serial_no')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            foreach (['repair_job_id', 'serial_no', 'sale_master_id'] as $col) {
                if (Schema::hasColumn('tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
