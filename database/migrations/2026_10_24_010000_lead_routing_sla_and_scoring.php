<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who gets a new lead (assignment rules), a first-contact deadline for every inbound lead with escalation,
 * and a lead score that tells the team who to call first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_assignment_rules')) {
            Schema::create('lead_assignment_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->unsignedInteger('sort_order')->default(10);
                $table->boolean('is_active')->default(true);
                $table->json('sources')->nullable();      // SourceOfEntry values; empty = any
                $table->json('inquiries')->nullable();    // InquiryType values; empty = any
                $table->json('vendor_ids')->nullable();   // the lead's branch (0 = head office); empty = any
                $table->boolean('include_manual')->default(false);   // also leads typed in by head office staff
                $table->string('method', 20)->default('round_robin');   // round_robin | least_busy | fixed
                $table->json('user_ids');                 // who can receive
                $table->unsignedBigInteger('set_vendor_id')->nullable();   // hand the deal to this branch
                $table->unsignedInteger('max_open')->nullable();           // skip people with this many open deals
                $table->unsignedInteger('reassign_after_minutes')->nullable();   // not contacted in time → next person
                $table->unsignedBigInteger('last_user_id')->nullable();
                $table->unsignedInteger('assigned_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'score')) {
                $table->unsignedTinyInteger('score')->nullable()->index();
                $table->json('score_reasons')->nullable();
                $table->timestamp('scored_at')->nullable();
            }
            if (!Schema::hasColumn('deals', 'assigned_rule_id')) {
                $table->unsignedBigInteger('assigned_rule_id')->nullable();
            }
            if (!Schema::hasColumn('deals', 'first_contact_due_at')) {
                $table->timestamp('first_contact_due_at')->nullable()->index();   // inbound leads: call by
                $table->timestamp('first_contact_at')->nullable();                // first call / message / visit done
                $table->timestamp('sla_alerted_at')->nullable();
                $table->unsignedTinyInteger('sla_reassigned')->default(0);
            }
        });

        Schema::table('pipelines', function (Blueprint $table) {
            if (!Schema::hasColumn('pipelines', 'scoring')) {
                $table->json('scoring')->nullable();   // lead score weights (defaults in config/lead_scoring.php)
            }
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', fn (Blueprint $t) => $t->dropColumn('scoring'));
        Schema::table('deals', fn (Blueprint $t) => $t->dropColumn(['score', 'score_reasons', 'scored_at', 'assigned_rule_id', 'first_contact_due_at', 'first_contact_at', 'sla_alerted_at', 'sla_reassigned']));
        Schema::dropIfExists('lead_assignment_rules');
    }
};
