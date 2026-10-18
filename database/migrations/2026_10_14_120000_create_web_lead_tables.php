<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 – website lead capture. New tables only; nothing existing is changed.
 *  - lead_capture_forms : one row per website / landing page that may post leads (key + HMAC secret)
 *  - lead_submissions   : raw inbox – every post is stored first, then processed on the queue
 *  - blocked_contacts   : phone / email / IP blocklist
 *  - jobs               : database queue (QUEUE_CONNECTION=database)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_capture_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('key', 40)->unique();
            $table->text('secret');                                   // encrypted at rest (model cast)
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete(); // null = Head Office
            $table->json('default_inquiry')->nullable();
            $table->unsignedSmallInteger('sla_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lead_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained('lead_capture_forms')->nullOnDelete();
            $table->string('status', 12)->default('pending')->index(); // pending|processed|duplicate|spam|failed
            $table->string('spam_reason', 100)->nullable();
            $table->string('name', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('normalized_phone', 20)->nullable()->index();
            $table->string('email', 191)->nullable();
            $table->text('message')->nullable();
            $table->json('inquiry_for')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('blocked_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                               // phone|email|ip
            $table->string('value', 191);
            $table->string('reason', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'value']);
        });

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_contacts');
        Schema::dropIfExists('lead_submissions');
        Schema::dropIfExists('lead_capture_forms');
        // jobs is left in place: other queued work may use it
    }
};
