<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hearing-aid trials: the usual in-session trial (model tried + how it went) and the rare take-home trial
 * (serial out on loan, deposit held as a customer advance, return date). And quotes the patient can
 * open, accept or decline online, with an optional payment link.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deal_trials')) {
            Schema::create('deal_trials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deal_id')->index();
                $table->unsignedBigInteger('lead_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_label', 150);
                $table->string('kind', 15)->default('in_session');   // in_session | take_home
                $table->string('result', 30)->nullable();             // liked | thinking | price | not_suitable | other_model
                $table->string('serial_no', 120)->nullable()->index();   // take-home: the unit that left the centre
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('advance_id')->nullable();    // deposit (customer_advances)
                $table->decimal('deposit', 12, 2)->default(0);
                $table->date('return_due')->nullable();
                $table->string('status', 15)->default('done')->index();  // done (in session) | out | bought | returned
                $table->unsignedBigInteger('sale_master_id')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('overdue_alerted_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();      // who fitted it
                $table->timestamps();
            });
        }

        Schema::table('proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('proposals', 'public_token')) {
                $table->string('public_token', 64)->nullable()->unique();
                $table->string('payment_url', 500)->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->string('responded_name', 120)->nullable();
                $table->string('response_note', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('proposals', fn (Blueprint $t) => $t->dropColumn(['public_token', 'payment_url', 'viewed_at', 'responded_at', 'responded_name', 'response_note']));
        Schema::dropIfExists('deal_trials');
    }
};
