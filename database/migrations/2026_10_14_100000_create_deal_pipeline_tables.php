<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead pipeline (Person + Deal), Phase 2.
 * Additive only: new tables + nullable columns. Membership, sales and stock tables are not touched.
 */
return new class extends Migration
{
    private const OLD_STATUSES = "'new','verified','tele_counsel_appointment','tele_counsel','store_appointment','counsel','trial','won','lost'";
    private const NEW_STATUSES = "'new','verified','tele_counsel_appointment','tele_counsel','store_appointment','counsel','trial','decision','won','lost'";

    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_owner_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('status_key', 40)->nullable()->comment('Mirrored to leads.lead_status');
            $table->string('color', 20)->default('#64748b');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->unsignedSmallInteger('rotting_days')->nullable();
            $table->json('required_fields')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false)->comment('New stage: always first, cannot be archived');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['pipeline_id', 'archived_at', 'sort_order']);
        });

        Schema::create('pipeline_settings_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pipeline_id')->nullable();
            $table->unsignedBigInteger('stage_id')->nullable();
            $table->string('action', 40);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('status', 10)->default('open')->comment('open | won | lost');
            $table->unsignedBigInteger('vendor_id')->nullable()->comment('null = Head Office');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('title', 150)->nullable();
            $table->json('inquiry_for')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('temperature', 10)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->unsignedBigInteger('lost_reason_id')->nullable();
            $table->string('lost_to', 150)->nullable();
            $table->text('lost_note')->nullable();
            $table->unsignedBigInteger('sale_master_id')->nullable();
            $table->string('source', 40)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_id', 'stage_id', 'status', 'position']);
            $table->index(['vendor_id', 'owner_id', 'status']);
        });

        Schema::create('deal_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id')->index();
            $table->unsignedBigInteger('from_vendor_id')->nullable();
            $table->unsignedBigInteger('to_vendor_id')->nullable();
            $table->unsignedBigInteger('from_owner_id')->nullable();
            $table->unsignedBigInteger('to_owner_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('transferred_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('normalized_phone', 20)->nullable()->after('phone_number')->index();
        });

        Schema::table('lead_status_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable()->after('lead_id')->index();
            $table->unsignedBigInteger('from_stage_id')->nullable()->after('to_status');
            $table->unsignedBigInteger('to_stage_id')->nullable()->after('from_stage_id');
        });

        // Additive ENUM value for the "Decision" stage (keeps old dashboards / SMS filters working)
        DB::statement("ALTER TABLE leads MODIFY lead_status ENUM(" . self::NEW_STATUSES . ") NULL DEFAULT 'new'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY from_status ENUM(" . self::NEW_STATUSES . ") NULL DEFAULT NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY to_status ENUM(" . self::NEW_STATUSES . ") NOT NULL");
    }

    public function down(): void
    {
        DB::table('leads')->where('lead_status', 'decision')->update(['lead_status' => 'trial']);
        DB::table('lead_status_histories')->where('from_status', 'decision')->update(['from_status' => 'trial']);
        DB::table('lead_status_histories')->where('to_status', 'decision')->update(['to_status' => 'trial']);
        DB::statement("ALTER TABLE leads MODIFY lead_status ENUM(" . self::OLD_STATUSES . ") NULL DEFAULT 'new'");
        DB::statement("ALTER TABLE lead_status_histories MODIFY from_status ENUM(" . self::OLD_STATUSES . ") NULL DEFAULT NULL");
        DB::statement("ALTER TABLE lead_status_histories MODIFY to_status ENUM(" . self::OLD_STATUSES . ") NOT NULL");

        Schema::table('lead_status_histories', function (Blueprint $table) {
            $table->dropIndex(['deal_id']);
            $table->dropColumn(['deal_id', 'from_stage_id', 'to_stage_id']);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['normalized_phone']);
            $table->dropColumn('normalized_phone');
        });

        Schema::dropIfExists('deal_transfers');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('lost_reasons');
        Schema::dropIfExists('pipeline_settings_logs');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
