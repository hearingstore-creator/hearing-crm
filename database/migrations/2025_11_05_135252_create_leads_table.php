<?php

use App\Enums\Gender;
use App\Enums\HearingLoss;
use App\Enums\InquiryType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\Relation;
use App\Enums\SourceOfEntry;
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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('lead_uid')->unique()->nullable(); // Autogenerate
            
            // Personal Information
            $table->string('full_name');
            $table->integer('age')->nullable();
            $table->enum('gender', array_column(Gender::cases(), 'value'))->nullable();
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->enum('relation', array_column(Relation::cases(), 'value'))->nullable();
            $table->string('contact_person_phone')->nullable();
            
            // Lead Details
            $table->json('inquiry_for')->nullable();
            $table->string('interest_brand_product')->nullable();
            $table->json('hearing_loss')->nullable();
            $table->decimal('approx_budget', 10, 2)->nullable();
            $table->string('ref_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('loss_reason')->nullable();
            $table->string('loss_to_whom')->nullable();


            // Source and Type
            $table->enum('source_of_entry', array_column(SourceOfEntry::cases(), 'value'))->nullable();
            $table->enum('lead_type', array_column(LeadType::cases(), 'value'))->nullable();
            
            // Lead Status
            $table->enum('lead_status', array_column(LeadStatus::cases(), 'value'))
            ->default(LeadStatus::NEW->value)->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->foreign('district_id')->references('id')->on('districts');
            $table->foreign('vendor_id')->references('id')->on('users')->onDelete('restrict')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
