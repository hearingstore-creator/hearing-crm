<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Doctor Chambers table (multiple practice chambers per doctor)
        Schema::create('doctor_chambers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->string('chamber_name');
            $table->text('address');
            $table->string('phone', 50)->nullable();
            $table->string('visiting_days', 100)->nullable();
            $table->string('visiting_time', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->index(['doctor_id', 'is_primary']);
        });

        // 2. Doctor Category-Wise Product Commissions
        Schema::create('doctor_category_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('category_id');
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('commission_value', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->unique(['doctor_id', 'category_id'], 'doc_cat_comm_unique');
        });

        // 3. Doctor Per-Service Wise Commissions
        Schema::create('doctor_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('service_type_id');
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('commission_value', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('service_type_id')->references('id')->on('service_types')->onDelete('cascade');
            $table->unique(['doctor_id', 'service_type_id'], 'doc_srv_comm_unique');
        });

        // 4. Add doctor_chamber_id to sale_masters to optionally track referral chamber
        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'doctor_chamber_id')) {
                $table->unsignedBigInteger('doctor_chamber_id')->nullable()->after('reference_by');
                $table->foreign('doctor_chamber_id')->references('id')->on('doctor_chambers')->nullOnDelete();
            }
        });

        // 5. Data Migration: Backfill existing doctor addresses into doctor_chambers
        $existingDoctors = DB::table('doctors')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->get();

        foreach ($existingDoctors as $doc) {
            DB::table('doctor_chambers')->insert([
                'doctor_id'     => $doc->id,
                'chamber_name'  => 'Main Chamber',
                'address'       => $doc->address,
                'phone'         => $doc->contact_number,
                'is_primary'    => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'doctor_chamber_id')) {
                $table->dropForeign(['doctor_chamber_id']);
                $table->dropColumn('doctor_chamber_id');
            }
        });

        Schema::dropIfExists('doctor_service_commissions');
        Schema::dropIfExists('doctor_category_commissions');
        Schema::dropIfExists('doctor_chambers');
    }
};
