<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional short, readable link name for hosted forms: /f/{slug} (the long key keeps working). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_capture_forms', function (Blueprint $table) {
            $table->string('slug', 40)->nullable()->unique()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('lead_capture_forms', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
