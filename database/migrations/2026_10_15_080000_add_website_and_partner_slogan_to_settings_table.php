<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (!Schema::hasColumn('settings', 'website_url')) {
                    $table->string('website_url')->nullable()->default('www.hearingstore.bd')->after('email');
                }
                if (!Schema::hasColumn('settings', 'partner_slogan')) {
                    $table->string('partner_slogan')->nullable()->default('An Authorized Partner of Hearing Store')->after('website_url');
                }
            });

            // Set default branding to Hearing Store
            \Illuminate\Support\Facades\DB::table('settings')->where('id', 1)->update([
                'company_name'   => 'Hearing Store',
                'website_url'    => 'www.hearingstore.bd',
                'partner_slogan' => 'An Authorized Partner of Hearing Store',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (Schema::hasColumn('settings', 'partner_slogan')) {
                    $table->dropColumn('partner_slogan');
                }
                if (Schema::hasColumn('settings', 'website_url')) {
                    $table->dropColumn('website_url');
                }
            });
        }
    }
};
