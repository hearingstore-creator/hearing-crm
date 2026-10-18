<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products can be retired (Inactive) instead of deleted: inactive models are hidden from
 * purchases and proposals, while remaining stock can still be sold and history keeps its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'status')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('status')->default(1)->after('model')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'status')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
