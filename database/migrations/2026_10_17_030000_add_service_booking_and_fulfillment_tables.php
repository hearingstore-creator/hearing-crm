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
        // 1. Add booking_type and appointment_service_id to service_types
        Schema::table('service_types', function (Blueprint $table) {
            if (!Schema::hasColumn('service_types', 'booking_type')) {
                $table->string('booking_type', 30)->default('either')->after('service_type_name');
            }
            if (!Schema::hasColumn('service_types', 'appointment_service_id')) {
                $table->foreignId('appointment_service_id')
                    ->nullable()
                    ->after('booking_type')
                    ->constrained('appointment_services')
                    ->nullOnDelete();
            }
        });

        // 2. Add fulfillment tracking to service_details
        Schema::table('service_details', function (Blueprint $table) {
            if (!Schema::hasColumn('service_details', 'fulfillment_status')) {
                $table->string('fulfillment_status', 30)->default('not_booked')->after('service_price');
            }
            if (!Schema::hasColumn('service_details', 'appointment_id')) {
                $table->foreignId('appointment_id')
                    ->nullable()
                    ->after('fulfillment_status')
                    ->constrained('appointments')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('service_details', 'done_by')) {
                $table->foreignId('done_by')
                    ->nullable()
                    ->after('appointment_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('service_details', 'done_at')) {
                $table->timestamp('done_at')->nullable()->after('done_by');
            }
            if (!Schema::hasColumn('service_details', 'notes')) {
                $table->text('notes')->nullable()->after('done_at');
            }
        });

        // 3. Add sale linkage to appointments
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'sale_master_id')) {
                $table->foreignId('sale_master_id')
                    ->nullable()
                    ->after('deal_id')
                    ->constrained('sale_masters')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('appointments', 'service_detail_id')) {
                $table->foreignId('service_detail_id')
                    ->nullable()
                    ->after('sale_master_id')
                    ->constrained('service_details')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('appointments', 'sale_invoice_no')) {
                $table->string('sale_invoice_no', 100)->nullable()->after('service_detail_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'sale_invoice_no')) {
                $table->dropColumn('sale_invoice_no');
            }
            if (Schema::hasColumn('appointments', 'service_detail_id')) {
                $table->dropForeign(['service_detail_id']);
                $table->dropColumn('service_detail_id');
            }
            if (Schema::hasColumn('appointments', 'sale_master_id')) {
                $table->dropForeign(['sale_master_id']);
                $table->dropColumn('sale_master_id');
            }
        });

        Schema::table('service_details', function (Blueprint $table) {
            if (Schema::hasColumn('service_details', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('service_details', 'done_at')) {
                $table->dropColumn('done_at');
            }
            if (Schema::hasColumn('service_details', 'done_by')) {
                $table->dropForeign(['done_by']);
                $table->dropColumn('done_by');
            }
            if (Schema::hasColumn('service_details', 'appointment_id')) {
                $table->dropForeign(['appointment_id']);
                $table->dropColumn('appointment_id');
            }
            if (Schema::hasColumn('service_details', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
        });

        Schema::table('service_types', function (Blueprint $table) {
            if (Schema::hasColumn('service_types', 'appointment_service_id')) {
                $table->dropForeign(['appointment_service_id']);
                $table->dropColumn('appointment_service_id');
            }
            if (Schema::hasColumn('service_types', 'booking_type')) {
                $table->dropColumn('booking_type');
            }
        });
    }
};
