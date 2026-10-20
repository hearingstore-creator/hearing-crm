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
        if (!Schema::hasTable('sale_referrals')) {
            Schema::create('sale_referrals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_master_id');
                $table->unsignedBigInteger('doctor_id');
                $table->unsignedBigInteger('doctor_chamber_id')->nullable();
                $table->string('role', 50)->default('doctor'); // doctor, quack, agent
                $table->decimal('commission_amount', 10, 2)->default(0.00);
                $table->string('settlement_status', 30)->default('pending'); // pending, settled, none
                $table->unsignedBigInteger('doctor_payment_id')->nullable();
                $table->dateTime('settled_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('sale_master_id')->references('id')->on('sale_masters')->onDelete('cascade');
                $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
                $table->foreign('doctor_chamber_id')->references('id')->on('doctor_chambers')->onDelete('set null');

                $table->index('doctor_payment_id');
                $table->index(['sale_master_id', 'settlement_status']);
                $table->index(['doctor_id', 'settlement_status']);
            });

            // Backfill existing referral sales into sale_referrals
            try {
                $salesWithDoctor = DB::table('sale_masters')
                    ->whereNotNull('reference_by')
                    ->whereNull('deleted_at')
                    ->select(['id', 'reference_by', 'doctor_chamber_id', 'reference_amount', 'referral_settlement_status', 'created_at', 'updated_at'])
                    ->get();

                foreach ($salesWithDoctor as $s) {
                    $doc = DB::table('doctors')->where('id', $s->reference_by)->first();
                    $role = $doc->referrer_type ?? 'doctor';
                    $status = $s->referral_settlement_status === 'settled' ? 'settled' : 'pending';

                    DB::table('sale_referrals')->insert([
                        'sale_master_id'    => $s->id,
                        'doctor_id'         => $s->reference_by,
                        'doctor_chamber_id' => $s->doctor_chamber_id,
                        'role'              => $role,
                        'commission_amount' => (float) ($s->reference_amount ?? 0),
                        'settlement_status' => $status,
                        'created_at'        => $s->created_at ?: now(),
                        'updated_at'        => $s->updated_at ?: now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Ignore backfill errors if tables empty in testing
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_referrals');
    }
};
