<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One installation per clinic, anywhere: currency, country / phone code and time zone become settings
 * (defaults stay Bangladesh), and leads can carry marketing consent and be erased on request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'currency_code')) {
                $table->string('currency_code', 3)->default('BDT');
                $table->string('currency_symbol', 8)->default('৳');
                $table->string('country_code', 2)->default('BD');
                $table->string('phone_prefix', 5)->default('880');      // international calling code, no +
                $table->string('timezone', 64)->default('Asia/Dhaka');
                $table->boolean('marketing_needs_consent')->default(false);   // promotional SMS only to people who agreed
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'marketing_consent')) {
                $table->boolean('marketing_consent')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->string('consent_source', 60)->nullable();
                $table->timestamp('erased_at')->nullable();
            }
        });

        Schema::table('lead_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_submissions', 'consent')) {
                $table->boolean('consent')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', fn (Blueprint $t) => $t->dropColumn('consent'));
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['marketing_consent', 'consent_at', 'consent_source', 'erased_at']));
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn(['currency_code', 'currency_symbol', 'country_code', 'phone_prefix', 'timezone', 'marketing_needs_consent']));
    }
};
