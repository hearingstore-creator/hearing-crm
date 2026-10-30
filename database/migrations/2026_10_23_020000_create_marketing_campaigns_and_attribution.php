<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing: campaigns, their monthly spend, and where every lead came from,
 * so cost per lead, cost per sale and return on spend can be reported.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketing_campaigns')) {
            Schema::create('marketing_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 150)->unique();        // the utm_campaign value used in ad links (lower case)
                $table->string('channel', 30)->index();       // App\Enums\MarketingChannel
                $table->unsignedBigInteger('vendor_id')->nullable()->index();   // branch; null = all / head office
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('budget', 14, 2)->nullable();
                $table->string('status', 15)->default('active');   // active | paused | ended
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('marketing_spends')) {
            Schema::create('marketing_spends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('marketing_campaigns');
                $table->date('month')->index();               // first day of the month
                $table->decimal('amount', 14, 2);
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->unsignedBigInteger('ledger_id')->nullable()->index();   // the expense row in the accounts
                $table->string('source', 20)->default('manual');   // manual | meta_csv | google_csv | expense
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $touch = function (Blueprint $table, string $tableName, array $columns) {
            foreach ($columns as $name => $length) {
                if (!Schema::hasColumn($tableName, $name)) {
                    $table->string($name, $length)->nullable();
                }
            }
        };

        // First touch: how this person first reached us
        Schema::table('leads', function (Blueprint $table) use ($touch) {
            if (!Schema::hasColumn('leads', 'campaign_id')) {
                $table->unsignedBigInteger('campaign_id')->nullable()->index();
            }
            $touch($table, 'leads', ['utm_source' => 100, 'utm_medium' => 100, 'utm_campaign' => 150, 'utm_content' => 150,
                'utm_term' => 150, 'gclid' => 255, 'fbclid' => 255, 'landing_page' => 500, 'referrer_url' => 500]);
        });

        // Each enquiry (deal) keeps its own touch
        Schema::table('deals', function (Blueprint $table) use ($touch) {
            if (!Schema::hasColumn('deals', 'campaign_id')) {
                $table->unsignedBigInteger('campaign_id')->nullable()->index();
            }
            $touch($table, 'deals', ['utm_content' => 150, 'utm_term' => 150, 'gclid' => 255, 'fbclid' => 255, 'landing_page' => 500]);
        });

        Schema::table('lead_submissions', function (Blueprint $table) use ($touch) {
            $touch($table, 'lead_submissions', ['utm_content' => 150, 'utm_term' => 150, 'gclid' => 255, 'fbclid' => 255, 'referrer_url' => 500, 'landing_page' => 500]);
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', fn (Blueprint $t) => $t->dropColumn(['utm_content', 'utm_term', 'gclid', 'fbclid', 'referrer_url', 'landing_page']));
        Schema::table('deals', fn (Blueprint $t) => $t->dropColumn(['campaign_id', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'landing_page']));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['campaign_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'landing_page', 'referrer_url']));
        Schema::dropIfExists('marketing_spends');
        Schema::dropIfExists('marketing_campaigns');
    }
};
