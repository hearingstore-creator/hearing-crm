<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval for large broadcasts to doctors / staff, and a place for replies that come back from the gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('sms_campaigns', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('sms_campaigns', 'approval_note')) {
                $table->string('approval_note', 255)->nullable();
            }
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sms_campaigns MODIFY status ENUM('sending','completed','failed','scheduled','pending_approval','rejected') NOT NULL DEFAULT 'sending'");
        }

        if (!Schema::hasTable('sms_inbound')) {
            Schema::create('sms_inbound', function (Blueprint $table) {
                $table->id();
                $table->string('phone_number', 30)->index();
                $table->text('message');
                $table->string('action', 20)->nullable();      // stop | null
                $table->unsignedBigInteger('sms_log_id')->nullable();   // the last message we sent to this number, if known
                $table->timestamp('received_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_inbound');
        DB::table('sms_campaigns')->whereIn('status', ['pending_approval', 'rejected'])->delete();
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sms_campaigns MODIFY status ENUM('sending','completed','failed','scheduled') NOT NULL DEFAULT 'sending'");
        }
        Schema::table('sms_campaigns', fn (Blueprint $t) => $t->dropColumn(['approved_by', 'approval_note']));
    }
};
