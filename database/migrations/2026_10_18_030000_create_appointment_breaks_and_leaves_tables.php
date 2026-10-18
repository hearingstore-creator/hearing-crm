<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_breaks')) {
            Schema::create('staff_breaks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->comment('Null applies to all staff at the branch');
                $table->unsignedBigInteger('vendor_id')->comment('Branch ID');
                $table->string('name', 100)->default('Lunch Break');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('vendor_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['vendor_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('staff_leaves')) {
            Schema::create('staff_leaves', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('vendor_id')->nullable()->comment('Branch ID');
                $table->dateTime('start_datetime');
                $table->dateTime('end_datetime');
                $table->string('leave_type', 50)->default('full_day'); // full_day, half_day_morning, half_day_afternoon, custom_hours
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('requested'); // requested, approved, rejected, cancelled
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['user_id', 'status', 'start_datetime', 'end_datetime']);
                $table->index(['vendor_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('staff_breaks');
    }
};
