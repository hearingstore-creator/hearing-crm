<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead pipeline, Phase 3: typed activities per deal.
 * Additive only: two nullable columns on lead_follow_ups (existing follow-ups keep working).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->unsignedBigInteger('deal_id')->nullable()->after('lead_id')->index();
            $table->string('activity_type', 20)->nullable()->after('deal_id')->comment('call | visit | sms | whatsapp | email | task | note');
        });

        // Existing follow-ups: type from the old mode, deal = the person's open deal (else latest deal)
        $modeToType = ['phone_call' => 'call', 'sms' => 'sms', 'email' => 'email', 'visit' => 'visit', 'other' => 'task'];
        DB::table('lead_follow_ups')->orderBy('id')->each(function ($fu) use ($modeToType) {
            $modes = json_decode((string) $fu->follow_up_mode, true) ?: [];
            $dealId = DB::table('deals')->where('lead_id', $fu->lead_id)->whereNull('deleted_at')
                ->orderByRaw("status = 'open' DESC")->orderByDesc('id')->value('id');

            DB::table('lead_follow_ups')->where('id', $fu->id)->update([
                'deal_id'       => $dealId,
                'activity_type' => $modeToType[$modes[0] ?? 'other'] ?? 'task',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            $table->dropIndex(['deal_id']);
            $table->dropColumn(['deal_id', 'activity_type']);
        });
    }
};
