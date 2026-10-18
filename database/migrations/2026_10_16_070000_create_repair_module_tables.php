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
        // 1. repair_jobs
        if (!Schema::hasTable('repair_jobs')) {
            Schema::create('repair_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('job_no', 32)->unique()->index();
                $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('sale_master_id')->nullable()->constrained('sale_masters')->nullOnDelete();
                $table->foreignId('sale_detail_id')->nullable()->constrained('sale_details')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->boolean('is_third_party')->default(false);
                $table->string('custom_brand')->nullable();
                $table->string('custom_model')->nullable();
                $table->string('original_serial_no', 100)->index();
                $table->string('replaced_serial_no', 100)->nullable()->index();
                $table->string('side', 20)->default('na'); // left, right, both, na
                $table->string('job_type', 50)->default('repair'); // repair, replacement, quick_service, earmold_modification
                $table->boolean('is_quick_service')->default(false);
                $table->string('warranty_status', 50)->default('under_product_warranty'); // under_product_warranty, under_service_warranty, out_of_warranty, void, third_party
                $table->unsignedBigInteger('parent_repair_job_id')->nullable();
                $table->text('void_reason')->nullable();
                $table->foreignId('void_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reported_issues')->nullable();
                $table->text('physical_condition')->nullable();
                $table->json('accessories_included')->nullable();
                $table->string('current_status', 50)->default('received')->index(); // received, sent_to_lab, in_lab, lab_received, waiting_patient_approval, patient_approved, patient_declined, ready_for_pickup, delivered, cancelled
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('lab_ref_no', 100)->nullable();
                $table->dateTime('sent_to_lab_at')->nullable();
                $table->dateTime('lab_received_at')->nullable();
                $table->text('lab_diagnosis')->nullable();
                $table->boolean('is_chargeable')->default(false);
                $table->decimal('inspection_fee', 10, 2)->default(0);
                $table->decimal('estimate_amount', 10, 2)->default(0);
                $table->boolean('estimate_approved')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->decimal('total_customer_cost', 10, 2)->default(0);
                $table->decimal('total_customer_paid', 10, 2)->default(0);
                $table->decimal('total_supplier_cost', 10, 2)->default(0);
                $table->decimal('supplier_paid', 10, 2)->default(0);
                $table->integer('repair_warranty_months')->default(0);
                $table->date('repair_warranty_until')->nullable();
                $table->dateTime('promised_date')->nullable();
                $table->dateTime('delivered_at')->nullable();
                $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->constrained('users');
                $table->foreignId('created_by')->constrained('users');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('parent_repair_job_id')->references('id')->on('repair_jobs')->nullOnDelete();
            });
        }

        // 2. serial_chains
        if (!Schema::hasTable('serial_chains')) {
            Schema::create('serial_chains', function (Blueprint $table) {
                $table->id();
                $table->string('serial_no', 100)->index();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('original_sale_id')->nullable()->constrained('sale_masters')->nullOnDelete();
                $table->string('parent_serial_no', 100)->nullable()->index();
                $table->foreignId('replacement_job_id')->nullable()->constrained('repair_jobs')->nullOnDelete();
                $table->string('status', 50)->default('active_with_customer'); // active_with_customer, faulty_in_clinic, with_supplier_lab, credited_by_supplier, scrapped, in_stock
                $table->date('warranty_start_date')->nullable();
                $table->date('warranty_end_date')->nullable();
                $table->boolean('is_void')->default(false);
                $table->string('void_reason')->nullable();
                $table->timestamps();
            });
        }

        // 3. repair_status_logs
        if (!Schema::hasTable('repair_status_logs')) {
            Schema::create('repair_status_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_job_id')->constrained('repair_jobs')->cascadeOnDelete();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->text('remarks')->nullable();
                $table->foreignId('user_id')->constrained('users');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 4. repair_items
        if (!Schema::hasTable('repair_items')) {
            Schema::create('repair_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_job_id')->constrained('repair_jobs')->cascadeOnDelete();
                $table->string('item_type', 50)->default('service_charge'); // part, service_charge, lab_charge, inspection_fee
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('item_name', 255);
                $table->decimal('qty', 10, 2)->default(1);
                $table->decimal('customer_unit_price', 10, 2)->default(0);
                $table->decimal('customer_total', 10, 2)->default(0);
                $table->decimal('supplier_unit_cost', 10, 2)->default(0);
                $table->decimal('supplier_total', 10, 2)->default(0);
                $table->boolean('is_warranty_covered')->default(false);
                $table->timestamps();
            });
        }

        // 5. repair_attachments
        if (!Schema::hasTable('repair_attachments')) {
            Schema::create('repair_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_job_id')->constrained('repair_jobs')->cascadeOnDelete();
                $table->string('file_path', 255);
                $table->string('attachment_type', 50)->default('intake_condition'); // intake_condition, void_proof, lab_report, customer_signed_slip
                $table->string('caption', 255)->nullable();
                $table->foreignId('uploaded_by')->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_attachments');
        Schema::dropIfExists('repair_items');
        Schema::dropIfExists('repair_status_logs');
        Schema::dropIfExists('serial_chains');
        Schema::dropIfExists('repair_jobs');
    }
};
