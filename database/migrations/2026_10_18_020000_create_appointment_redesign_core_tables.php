<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Branch Schedules
        if (!Schema::hasTable('branch_schedules')) {
            Schema::create('branch_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedTinyInteger('day_of_week')->comment('ISO-8601: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun');
                $table->time('open_time')->default('09:30:00');
                $table->time('close_time')->default('19:00:00');
                $table->boolean('is_closed')->default(false);
                $table->timestamps();

                $table->unique(['vendor_id', 'day_of_week'], 'uniq_branch_day');
                $table->index(['vendor_id', 'is_closed'], 'idx_branch_sched_closed');
            });
        }

        // 2. Clinic Resources (Sound Booths, Rooms)
        if (!Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->string('name', 100);
                $table->string('type', 50)->default('sound_booth');
                $table->unsignedInteger('capacity')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['vendor_id', 'type', 'is_active'], 'idx_res_vendor_type');
            });
        }

        // 3. Staff Shifts / Roster
        if (!Schema::hasTable('staff_schedules')) {
            Schema::create('staff_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedTinyInteger('day_of_week')->comment('ISO-8601: 1=Mon ... 7=Sun');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'vendor_id', 'day_of_week', 'is_active'], 'idx_staff_sched_lookup');
            });
        }

        // 4. Staff Schedule Date Overrides
        if (!Schema::hasTable('staff_schedule_overrides')) {
            Schema::create('staff_schedule_overrides', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('vendor_id');
                $table->date('override_date');
                $table->boolean('is_day_off')->default(false);
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'vendor_id', 'override_date'], 'uniq_staff_date_override');
            });
        }

        // 5. Temporary Website Slot Holds
        if (!Schema::hasTable('appointment_holds')) {
            Schema::create('appointment_holds', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->unsignedBigInteger('service_id');
                $table->date('hold_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->timestamp('expires_at');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'hold_date', 'expires_at'], 'idx_holds_active');
            });
        }

        // 6. Appointment Audit & History
        if (!Schema::hasTable('appointment_histories')) {
            Schema::create('appointment_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_id');
                $table->string('from_status', 50);
                $table->string('to_status', 50);
                $table->date('old_date')->nullable();
                $table->date('new_date')->nullable();
                $table->time('old_start_time')->nullable();
                $table->time('new_start_time')->nullable();
                $table->unsignedBigInteger('old_user_id')->nullable();
                $table->unsignedBigInteger('new_user_id')->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('appointment_id', 'idx_hist_appt');
            });
        }

        // 7. Alter appointment_services
        Schema::table('appointment_services', function (Blueprint $table) {
            if (!Schema::hasColumn('appointment_services', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->default(45)->after('category');
            }
            if (!Schema::hasColumn('appointment_services', 'buffer_after_minutes')) {
                $table->unsignedSmallInteger('buffer_after_minutes')->default(10)->after('duration_minutes');
            }
            if (!Schema::hasColumn('appointment_services', 'requires_resource')) {
                $table->boolean('requires_resource')->default(false)->after('buffer_after_minutes');
            }
            if (!Schema::hasColumn('appointment_services', 'resource_type')) {
                $table->string('resource_type', 50)->nullable()->default('sound_booth')->after('requires_resource');
            }
            if (!Schema::hasColumn('appointment_services', 'is_online_booking_enabled')) {
                $table->boolean('is_online_booking_enabled')->default(false)->after('resource_type');
            }
            if (!Schema::hasColumn('appointment_services', 'color_hex')) {
                $table->string('color_hex', 20)->default('#4f46e5')->after('is_online_booking_enabled');
            }
        });

        // 8. Alter appointments
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('service_detail_id');
            }
            if (!Schema::hasColumn('appointments', 'resource_id')) {
                $table->unsignedBigInteger('resource_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('appointments', 'start_time')) {
                $table->time('start_time')->nullable()->after('appointment_date');
            }
            if (!Schema::hasColumn('appointments', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('appointments', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->default(45)->after('end_time');
            }
            if (!Schema::hasColumn('appointments', 'is_time_unknown')) {
                $table->boolean('is_time_unknown')->default(false)->after('duration_minutes');
            }
            if (!Schema::hasColumn('appointments', 'is_overbooked')) {
                $table->boolean('is_overbooked')->default(false)->after('status');
            }
            if (!Schema::hasColumn('appointments', 'overbook_reason')) {
                $table->text('overbook_reason')->nullable()->after('is_overbooked');
            }
            if (!Schema::hasColumn('appointments', 'needs_reschedule')) {
                $table->boolean('needs_reschedule')->default(false)->after('overbook_reason');
            }
            if (!Schema::hasColumn('appointments', 'source')) {
                $table->string('source', 30)->default('internal')->after('needs_reschedule');
            }

            // Indexes
            $table->index(['user_id', 'appointment_date', 'status', 'start_time', 'end_time'], 'idx_appt_avail_check');
            $table->index(['branch_id', 'appointment_date', 'status'], 'idx_appt_branch_date');
        });

        // 9. Alter office_holidays
        Schema::table('office_holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('office_holidays', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->comment('Null = All Branches')->after('date');
                $table->index(['vendor_id', 'date'], 'idx_holiday_lookup');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_histories');
        Schema::dropIfExists('appointment_holds');
        Schema::dropIfExists('staff_schedule_overrides');
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('branch_schedules');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appt_avail_check');
            $table->dropIndex('idx_appt_branch_date');
            $table->dropColumn([
                'branch_id', 'resource_id', 'start_time', 'end_time',
                'duration_minutes', 'is_time_unknown', 'is_overbooked',
                'overbook_reason', 'needs_reschedule', 'source'
            ]);
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropColumn([
                'duration_minutes', 'buffer_after_minutes', 'requires_resource',
                'resource_type', 'is_online_booking_enabled', 'color_hex'
            ]);
        });

        Schema::table('office_holidays', function (Blueprint $table) {
            $table->dropIndex('idx_holiday_lookup');
            $table->dropColumn('vendor_id');
        });
    }
};
