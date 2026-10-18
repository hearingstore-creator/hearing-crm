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
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->boolean('create_follow_up')->default(false)->after('status');
            $table->integer('follow_up_days')->nullable()->after('create_follow_up');
            $table->unsignedBigInteger('follow_up_assigned_user')->nullable()->after('follow_up_days');
            $table->string('follow_up_mode')->nullable()->after('follow_up_assigned_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn(['create_follow_up', 'follow_up_days', 'follow_up_assigned_user', 'follow_up_mode']);
        });
    }
};
