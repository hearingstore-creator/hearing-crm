<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            // Taka one point is worth for people in this tier. NULL = use the loyalty setting's point value.
            $table->decimal('point_value', 8, 2)->nullable()->after('earn_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('membership_tiers', function (Blueprint $table) {
            $table->dropColumn('point_value');
        });
    }
};
