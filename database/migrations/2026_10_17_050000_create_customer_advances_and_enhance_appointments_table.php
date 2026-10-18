<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create customer_advances table
        if (!Schema::hasTable('customer_advances')) {
            Schema::create('customer_advances', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_no', 50)->unique();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->unsignedBigInteger('appointment_id')->nullable()->index();
                $table->unsignedBigInteger('sale_master_id')->nullable()->index();
                $table->date('date');
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('applied_amount', 12, 2)->default(0);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->decimal('deduction_fee', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('payment_method', 50)->default('cash'); // cash, bkash, nagad, card, bank
                $table->string('reference', 255)->nullable();
                $table->string('status', 30)->default('active'); // active, applied, partially_applied, refunded, credit_kept
                $table->text('notes')->nullable();

                // Refund details
                $table->date('refund_date')->nullable();
                $table->string('refund_method', 50)->nullable();
                $table->string('refund_reference', 255)->nullable();
                $table->text('refund_reason')->nullable();
                $table->unsignedBigInteger('refund_by')->nullable();
                $table->string('refund_receipt_no', 50)->nullable();

                // Branch & Audit
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Enhance appointments table
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'advance_amount')) {
                $table->decimal('advance_amount', 12, 2)->default(0)->after('appointment_date');
            }
            if (!Schema::hasColumn('appointments', 'customer_advance_id')) {
                $table->unsignedBigInteger('customer_advance_id')->nullable()->after('advance_amount');
            }
        });

        // Convert appointments.status from enum to varchar(50) so new statuses work smoothly
        DB::statement("ALTER TABLE appointments MODIFY status VARCHAR(50) NOT NULL DEFAULT 'scheduled'");

        // 3. Enhance sale_masters table
        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'applied_advance_id')) {
                $table->unsignedBigInteger('applied_advance_id')->nullable()->after('paid');
            }
            if (!Schema::hasColumn('sale_masters', 'applied_advance_amount')) {
                $table->decimal('applied_advance_amount', 12, 2)->default(0)->after('applied_advance_id');
            }
        });

        // 4. Create advance.refund permission
        $apptMenu = \App\Models\Menu::where('name', 'Appointment')->first();
        if (!$apptMenu && !\App\Models\Menu::whereKey(11)->exists()) {
            return; // fresh database (menus come from AclSeeder later)
        }
        $perm = Permission::firstOrCreate(
            ['name' => 'advance.refund'],
            ['action' => 'refund', 'name' => 'advance.refund', 'menu_id' => $apptMenu?->id ?? 11]
        );

        $roles = Role::whereIn('name', ['Super Admin', 'Admin', 'Branch Manager', 'Manager'])->get();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_advances');

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'customer_advance_id')) {
                $table->dropColumn('customer_advance_id');
            }
            if (Schema::hasColumn('appointments', 'advance_amount')) {
                $table->dropColumn('advance_amount');
            }
        });

        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'applied_advance_amount')) {
                $table->dropColumn('applied_advance_amount');
            }
            if (Schema::hasColumn('sale_masters', 'applied_advance_id')) {
                $table->dropColumn('applied_advance_id');
            }
        });

        $perm = Permission::where('name', 'advance.refund')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
