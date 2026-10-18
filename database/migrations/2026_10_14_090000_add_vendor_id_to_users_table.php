<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links staff (e.g. branch professionals) to a branch.
 * NULL = Head Office. Branch logins themselves (type = vendor) keep using their own id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'vendor_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'vendor_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('vendor_id');
            });
        }
    }
};
