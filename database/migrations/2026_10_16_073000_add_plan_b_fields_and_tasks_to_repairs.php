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
        // 1. Additive fields to repair_jobs for Plan B
        Schema::table('repair_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('repair_jobs', 'pipeline_path')) {
                $table->string('pipeline_path', 50)->default('warranty_repair')->after('job_type');
            }
            if (!Schema::hasColumn('repair_jobs', 'dispatch_type')) {
                $table->string('dispatch_type', 30)->nullable()->after('supplier_id'); // 'courier', 'by_hand'
            }
            if (!Schema::hasColumn('repair_jobs', 'courier_name')) {
                $table->string('courier_name', 100)->nullable()->after('dispatch_type');
            }
            if (!Schema::hasColumn('repair_jobs', 'courier_tracking_no')) {
                $table->string('courier_tracking_no', 100)->nullable()->after('courier_name');
            }
            if (!Schema::hasColumn('repair_jobs', 'carrier_person_name')) {
                $table->string('carrier_person_name', 100)->nullable()->after('courier_tracking_no');
            }
            if (!Schema::hasColumn('repair_jobs', 'dispatched_at')) {
                $table->dateTime('dispatched_at')->nullable()->after('carrier_person_name');
            }
            if (!Schema::hasColumn('repair_jobs', 'confirmed_to_lab_at')) {
                $table->dateTime('confirmed_to_lab_at')->nullable()->after('dispatched_at');
            }
            if (!Schema::hasColumn('repair_jobs', 'supplier_bill_id')) {
                $table->foreignId('supplier_bill_id')->nullable()->after('supplier_paid')->constrained('purchase_masters')->nullOnDelete();
            }
            if (!Schema::hasColumn('repair_jobs', 'supplier_memo_date')) {
                $table->date('supplier_memo_date')->nullable()->after('lab_ref_no');
            }
            if (!Schema::hasColumn('repair_jobs', 'advance_receipt_no')) {
                $table->string('advance_receipt_no', 50)->nullable()->after('total_customer_paid');
            }
            if (!Schema::hasColumn('repair_jobs', 'declined_inspection_fee')) {
                $table->decimal('declined_inspection_fee', 10, 2)->default(0)->after('inspection_fee');
            }
            if (!Schema::hasColumn('repair_jobs', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('repair_jobs', 'cancellation_mode')) {
                $table->string('cancellation_mode', 30)->nullable()->after('cancellation_reason'); // 'refunded', 'customer_credit'
            }
        });

        // 2. Additive table: repair_tasks for Plan B Automatic Reminders & Tasks
        if (!Schema::hasTable('repair_tasks')) {
            Schema::create('repair_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_job_id')->constrained('repair_jobs')->cascadeOnDelete();
                $table->string('task_type', 50)->index(); // 'send_to_lab', 'follow_up_supplier', 'call_patient', 'confirm_lab', 'pickup_sms'
                $table->string('title', 255);
                $table->dateTime('due_date')->index();
                $table->boolean('is_completed')->default(false)->index();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_tasks');

        Schema::table('repair_jobs', function (Blueprint $table) {
            $cols = [
                'pipeline_path', 'dispatch_type', 'courier_name', 'courier_tracking_no',
                'carrier_person_name', 'dispatched_at', 'confirmed_to_lab_at',
                'supplier_bill_id', 'supplier_memo_date', 'advance_receipt_no',
                'declined_inspection_fee', 'cancellation_reason', 'cancellation_mode'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('repair_jobs', $col)) {
                    if ($col === 'supplier_bill_id') {
                        $table->dropForeign(['supplier_bill_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
