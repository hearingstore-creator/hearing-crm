<?php

use App\Classes\Helper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default "Hearing Care Sales" pipeline and creates one deal per existing lead.
 * Existing lead / customer / membership rows are read, never modified (except the new normalized_phone column).
 */
return new class extends Migration
{
    /** Old lead_status => [stage name, deal status] */
    private const STATUS_MAP = [
        'new'                      => ['New', 'open'],
        'verified'                 => ['New', 'open'],
        'tele_counsel_appointment' => ['New', 'open'],
        'tele_counsel'             => ['Tele-counselled', 'open'],
        'store_appointment'        => ['Tele-counselled', 'open'],
        'counsel'                  => ['Assessment', 'open'],
        'trial'                    => ['Trial', 'open'],
        'decision'                 => ['Decision', 'open'],
        'won'                      => ['Decision', 'won'],
        'lost'                     => ['New', 'lost'],
    ];

    public function up(): void
    {
        $now = now();
        $ownerId = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'Super Admin')
            ->orderBy('role_user.user_id')
            ->value('role_user.user_id');

        $pipelineId = DB::table('pipelines')->insertGetId([
            'name' => 'Hearing Care Sales', 'is_default' => true, 'default_owner_id' => $ownerId,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $stages = [
            ['New',             'new',          '#64748b', 10, 1,  [],                                       true],
            ['Tele-counselled', 'tele_counsel', '#8b5cf6', 25, 7,  ['inquiry_for', 'note'],                  false],
            ['Assessment',      'counsel',      '#0ea5e9', 45, 7,  ['appointment_or_walkin'],                false],
            ['Trial',           'trial',        '#f59e0b', 70, 10, ['trial_product', 'trial_return_date'],  false],
            ['Decision',        'decision',     '#10b981', 80, 7,  ['value'],                                false],
        ];
        $stageIds = [];
        foreach ($stages as $i => [$name, $key, $color, $prob, $rot, $req, $system]) {
            $stageIds[$name] = DB::table('pipeline_stages')->insertGetId([
                'pipeline_id' => $pipelineId, 'name' => $name, 'status_key' => $key, 'color' => $color,
                'probability' => $prob, 'rotting_days' => $rot, 'required_fields' => json_encode($req),
                'sort_order' => ($i + 1) * 10, 'is_system' => $system, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $reasons = ['Price', 'Bought elsewhere', 'No response', 'Not ready', 'Medical referral', 'Distance', 'Other'];
        foreach ($reasons as $i => $name) {
            DB::table('lost_reasons')->insert(['name' => $name, 'sort_order' => ($i + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $otherReasonId = DB::table('lost_reasons')->where('name', 'Other')->value('id');

        $customers = DB::table('customers')->whereNull('deleted_at')->pluck('created_at', 'lead_id');
        $lastMove = DB::table('lead_status_histories')->select('lead_id', DB::raw('MAX(created_at) as moved'))->groupBy('lead_id')->pluck('moved', 'lead_id');
        $positions = [];

        DB::table('leads')->whereNull('deleted_at')->orderBy('id')->each(function ($lead) use ($pipelineId, $stageIds, $ownerId, $otherReasonId, $customers, $lastMove, &$positions) {
            [$stageName, $status] = self::STATUS_MAP[$lead->lead_status] ?? ['New', 'open'];
            if ($customers->has($lead->id)) {
                $status = 'won'; // already a customer = already bought
            }
            $stageId = $stageIds[$stageName];
            $positions[$stageId] = ($positions[$stageId] ?? 0) + 1000;

            $dealId = DB::table('deals')->insertGetId([
                'lead_id' => $lead->id, 'pipeline_id' => $pipelineId, 'stage_id' => $stageId, 'status' => $status,
                'vendor_id' => $lead->vendor_id, 'owner_id' => $lead->created_by ?: $ownerId,
                'inquiry_for' => self::inquiryJson($lead->inquiry_for), 'value' => $lead->approx_budget, 'temperature' => $lead->lead_temperature,
                'position' => $positions[$stageId], 'stage_entered_at' => $lastMove[$lead->id] ?? $lead->created_at,
                'won_at' => $status === 'won' ? ($customers[$lead->id] ?? $lead->updated_at) : null,
                'lost_at' => $status === 'lost' ? $lead->updated_at : null,
                'lost_reason_id' => $status === 'lost' ? $otherReasonId : null,
                'lost_to' => $status === 'lost' ? $lead->loss_to_whom : null,
                'lost_note' => $status === 'lost' ? $lead->loss_reason : null,
                'source' => $lead->source_of_entry, 'created_by' => $lead->created_by,
                'created_at' => $lead->created_at, 'updated_at' => $lead->updated_at,
            ]);

            DB::table('lead_status_histories')->where('lead_id', $lead->id)->update(['deal_id' => $dealId]);
            DB::table('leads')->where('id', $lead->id)->update(['normalized_phone' => Helper::normalizePhone($lead->phone_number)]);
        });
    }

    /** inquiry_for was stored both as JSON arrays and as plain strings; always return a JSON array (or null). */
    private static function inquiryJson(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true) ?? [$decoded];
        }

        return json_encode(array_values(array_filter(is_array($decoded) ? $decoded : [$raw])));
    }

    public function down(): void
    {
        DB::table('lead_status_histories')->update(['deal_id' => null, 'from_stage_id' => null, 'to_stage_id' => null]);
        DB::table('leads')->update(['normalized_phone' => null]);
        DB::table('deal_transfers')->delete();
        DB::table('deals')->delete();
        DB::table('lost_reasons')->delete();
        DB::table('pipeline_settings_logs')->delete();
        DB::table('pipeline_stages')->delete();
        DB::table('pipelines')->delete();
    }
};
