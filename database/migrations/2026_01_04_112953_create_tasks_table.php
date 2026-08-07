<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            
            // tasks Details
            $table->string('subject');
            $table->text('description');
            
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->enum('status', array_column(TaskStatus::cases(), 'value'))
            ->default(TaskStatus::PENDING->value);
            $table->enum('priority', array_column(TaskPriority::cases(), 'value'))
            ->default(TaskPriority::LOW->value);
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('restrict')->onUpdate('cascade');
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
        Schema::dropIfExists('tasks');
    }
};
