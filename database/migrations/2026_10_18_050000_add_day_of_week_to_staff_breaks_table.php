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
        Schema::table('staff_breaks', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_breaks', 'day_of_week')) {
                $table->unsignedTinyInteger('day_of_week')
                    ->nullable()
                    ->after('vendor_id')
                    ->comment('Null = All Days (Weekly Consistent), 1=Mon ... 7=Sun (Day-Wise Different)');
                $table->index(['vendor_id', 'day_of_week', 'is_active']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_breaks', function (Blueprint $table) {
            if (Schema::hasColumn('staff_breaks', 'day_of_week')) {
                $table->dropIndex(['vendor_id', 'day_of_week', 'is_active']);
                $table->dropColumn('day_of_week');
            }
        });
    }
};
