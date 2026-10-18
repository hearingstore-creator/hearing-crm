<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'lead_temperature')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('lead_temperature', 10)->nullable()->default('warm')->after('lead_status')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'lead_temperature')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['lead_temperature']);
                $table->dropColumn('lead_temperature');
            });
        }
    }
};
