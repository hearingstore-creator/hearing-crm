<?php

use App\Enums\SmsStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sms_campaign_id')->nullable();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->string('phone_number', 30);
            $table->text('message');
            $table->string('type');
            $table->enum('status', array_column(SmsStatus::cases(), 'value'))
                ->default(SmsStatus::PENDING->value);
            $table->text('gateway_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sms_campaign_id')->references('id')->on('sms_campaigns')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
