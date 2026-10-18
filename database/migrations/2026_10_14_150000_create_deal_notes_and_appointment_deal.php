<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6A (additive only):
 *  - deal_notes: notes about the person, written from a deal; can be pinned. Separate from activities.
 *  - appointments.deal_id: an appointment booked from a deal remembers that deal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lead_id', 'is_pinned']);
        });

        if (!Schema::hasColumn('appointments', 'deal_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreignId('deal_id')->nullable()->after('customer_id')->constrained('deals')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointments', 'deal_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('deal_id');
            });
        }
        Schema::dropIfExists('deal_notes');
    }
};
