<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead connectors: Meta Lead Ads, WhatsApp, Google lead forms and a general webhook feed the same inbox
 * as the website form. Every call that arrives is logged so it can be checked and processed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_submissions', 'source')) {
                $table->string('source', 30)->nullable()->index();          // null = website form
            }
            if (!Schema::hasColumn('lead_submissions', 'external_id')) {
                $table->string('external_id', 120)->nullable()->index();    // Meta leadgen id, Google lead id, WhatsApp message id …
            }
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_campaigns', 'external_id')) {
                $table->string('external_id', 60)->nullable()->index();     // the campaign id in Meta or Google Ads
            }
        });

        if (!Schema::hasTable('lead_webhook_events')) {
            Schema::create('lead_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('source', 20)->index();         // meta | whatsapp | google | webhook
                $table->string('external_id', 120)->nullable()->index();
                $table->string('status', 15)->default('received')->index();   // received | processed | duplicate | ignored | failed
                $table->string('note', 500)->nullable();
                $table->longText('payload')->nullable();
                $table->unsignedBigInteger('submission_id')->nullable();
                $table->unsignedBigInteger('form_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_webhook_events');
        Schema::table('marketing_campaigns', fn (Blueprint $t) => $t->dropColumn('external_id'));
        Schema::table('lead_submissions', fn (Blueprint $t) => $t->dropColumn(['source', 'external_id']));
    }
};
