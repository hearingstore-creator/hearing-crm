<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_return_details')) {
            Schema::table('purchase_return_details', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_return_details', 'purchase_master_id')) {
                    $table->unsignedBigInteger('purchase_master_id')->nullable()->after('purchase_return_id')->index();
                    $table->foreign('purchase_master_id')->references('id')->on('purchase_masters')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_return_details')) {
            Schema::table('purchase_return_details', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_return_details', 'purchase_master_id')) {
                    $table->dropForeign(['purchase_master_id']);
                    $table->dropColumn('purchase_master_id');
                }
            });
        }
    }
};
