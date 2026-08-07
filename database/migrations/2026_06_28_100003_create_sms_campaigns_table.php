<?php

use App\Enums\SmsCampaignStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('message_body');
            $table->unsignedBigInteger('sms_template_id')->nullable();
            $table->string('recipient_type');
            $table->json('filter_criteria')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->enum('status', array_column(SmsCampaignStatus::cases(), 'value'))
                ->default(SmsCampaignStatus::SENDING->value);
            $table->timestamp('sent_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sms_template_id')->references('id')->on('sms_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaigns');
    }
};
