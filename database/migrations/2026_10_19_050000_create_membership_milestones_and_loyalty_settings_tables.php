<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand reward_ledgers.type ENUM to include 'milestone'
        DB::statement("ALTER TABLE reward_ledgers MODIFY type ENUM('earn', 'redeem', 'referral', 'milestone') NOT NULL");

        // 2. Create membership_milestones table
        if (!Schema::hasTable('membership_milestones')) {
            Schema::create('membership_milestones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->enum('type', ['lifetime_spend', 'purchase_count']);
                $table->decimal('threshold', 12, 2);
                $table->unsignedInteger('bonus_points');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Create membership_milestone_awards table
        if (!Schema::hasTable('membership_milestone_awards')) {
            Schema::create('membership_milestone_awards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('membership_milestone_id')->constrained('membership_milestones')->cascadeOnDelete();
                $table->foreignId('reward_ledger_id')->nullable()->constrained('reward_ledgers')->nullOnDelete();
                $table->timestamp('awarded_at')->useCurrent();
                $table->timestamps();

                $table->unique(['customer_id', 'membership_milestone_id'], 'cust_milestone_unique');
            });
        }

        // 4. Create loyalty_settings table
        if (!Schema::hasTable('loyalty_settings')) {
            Schema::create('loyalty_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('point_value', 8, 2)->default(1.00);
                $table->unsignedInteger('expiry_months')->default(12);
                $table->unsignedInteger('min_redeem_points')->default(0);
                $table->decimal('max_redeem_percent', 5, 2)->default(100.00);
                $table->timestamps();
            });

            // Insert single default row
            DB::table('loyalty_settings')->insert([
                'point_value' => 1.00,
                'expiry_months' => 12,
                'min_redeem_points' => 0,
                'max_redeem_percent' => 100.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_milestone_awards');
        Schema::dropIfExists('membership_milestones');
        Schema::dropIfExists('loyalty_settings');
        DB::statement("ALTER TABLE reward_ledgers MODIFY type ENUM('earn', 'redeem', 'referral') NOT NULL");
    }
};
