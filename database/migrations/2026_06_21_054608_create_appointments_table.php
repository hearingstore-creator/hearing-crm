<?php

use App\Enums\AppointmentStatus;
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
        Schema::dropIfExists('appointments');
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Link with Lead or Customer
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();

            // Professional (User with type = professional)
            $table->unsignedBigInteger('user_id')->nullable();

            // Appointment Service
            $table->unsignedBigInteger('service_id')->nullable();

            // Professional's specific time slot selected by patient
            $table->unsignedBigInteger('professional_time_slot_id')->nullable();

            // Date
            $table->date('appointment_date');

            $table->enum('status', array_column(AppointmentStatus::cases(), 'value'))
                ->default(AppointmentStatus::SCHEDULED->value);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Foreign keys
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('appointment_services')->nullOnDelete();
            $table->foreign('professional_time_slot_id')->references('id')->on('professional_time_slots')->nullOnDelete();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
