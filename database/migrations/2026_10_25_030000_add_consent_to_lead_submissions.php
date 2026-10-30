<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The consent tick box of the website form, kept on the inbox row (databases migrated before it was added). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_submissions', 'consent')) {
                $table->boolean('consent')->nullable();
            }
        });
    }

    public function down(): void
    {
        // dropped together with the region migration
    }
};
