<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a branch/vendor logo used on invoices and printouts.
 * Address, mobile_no and email already exist on users.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'logo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('logo')->nullable()->after('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'logo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('logo');
            });
        }
    }
};
