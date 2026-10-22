<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * More than one sales pipeline (repairs, institutional, ...). Additive only.
 * "Hearing Care Sales" stays the default; existing assignment rules and sequences are pinned to it so nothing
 * changes until someone edits them. Two example pipelines are added switched off.
 */
return new class extends Migration
{
    private const EXAMPLES = [
        'Repairs' => [
            'description' => 'Hearing aid repairs: from receiving the device to handing it back.',
            'sort_order'  => 20,
            'stages'      => [
                ['New', '#64748b', 10], ['Received', '#0ea5e9', 25], ['Diagnosed', '#6366f1', 40],
                ['Quote sent', '#f59e0b', 60], ['In repair', '#8b5cf6', 80], ['Ready for pickup', '#10b981', 95],
            ],
        ],
        'Institutional / corporate' => [
            'description' => 'Companies, hospitals, NGOs and other bulk buyers.',
            'sort_order'  => 30,
            'stages'      => [
                ['New', '#64748b', 5], ['Contacted', '#0ea5e9', 15], ['Needs assessment', '#6366f1', 30],
                ['Proposal sent', '#f59e0b', 55], ['Negotiation', '#8b5cf6', 75],
            ],
        ],
    ];

    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            if (!Schema::hasColumn('pipelines', 'description')) {
                $table->string('description', 255)->nullable()->after('name');
                $table->unsignedInteger('sort_order')->default(10)->after('is_active');
                $table->timestamp('archived_at')->nullable()->after('sort_order');
            }
        });
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deal_pipeline_id')) {
                $table->unsignedBigInteger('deal_pipeline_id')->nullable();   // last pipeline chosen on the Deals Board
            }
        });
        Schema::table('lead_assignment_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_assignment_rules', 'pipeline_ids')) {
                $table->json('pipeline_ids')->nullable()->after('vendor_ids');   // empty = any pipeline
            }
        });
        Schema::table('sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('sequences', 'trigger_pipeline_id')) {
                $table->unsignedBigInteger('trigger_pipeline_id')->nullable()->after('trigger');   // null = any pipeline
            }
        });

        $defaultId = DB::table('pipelines')->where('is_default', true)->value('id');
        if (!$defaultId) {
            return;
        }
        DB::table('pipelines')->where('id', $defaultId)->update(['sort_order' => 10]);
        DB::table('lead_assignment_rules')->whereNull('pipeline_ids')->update(['pipeline_ids' => json_encode([(int) $defaultId])]);
        DB::table('sequences')->whereNull('trigger_pipeline_id')->where('trigger', '!=', 'manual')->update(['trigger_pipeline_id' => $defaultId]);

        $default = DB::table('pipelines')->where('id', $defaultId)->first();
        foreach (self::EXAMPLES as $name => $p) {
            if (DB::table('pipelines')->where('name', $name)->exists()) {
                continue;
            }
            $id = DB::table('pipelines')->insertGetId([
                'name' => $name, 'description' => $p['description'], 'is_default' => false, 'is_active' => false,
                'sort_order' => $p['sort_order'], 'default_owner_id' => $default->default_owner_id,
                'office_open' => $default->office_open, 'office_close' => $default->office_close,
                'weekly_holidays' => $default->weekly_holidays, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($p['stages'] as $i => [$stage, $color, $probability]) {
                DB::table('pipeline_stages')->insert([
                    'pipeline_id' => $id, 'name' => $stage, 'status_key' => null, 'color' => $color, 'probability' => $probability,
                    'required_fields' => json_encode([]), 'sort_order' => ($i + 1) * 10, 'is_system' => $i === 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('pipelines')->whereIn('name', array_keys(self::EXAMPLES))->where('is_default', false)
            ->whereNotExists(fn ($q) => $q->from('deals')->whereColumn('deals.pipeline_id', 'pipelines.id'))->pluck('id');
        DB::table('pipeline_stages')->whereIn('pipeline_id', $ids)->delete();
        DB::table('pipelines')->whereIn('id', $ids)->delete();

        Schema::table('sequences', fn (Blueprint $t) => $t->dropColumn('trigger_pipeline_id'));
        Schema::table('lead_assignment_rules', fn (Blueprint $t) => $t->dropColumn('pipeline_ids'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('deal_pipeline_id'));
        Schema::table('pipelines', fn (Blueprint $t) => $t->dropColumn(['description', 'sort_order', 'archived_at']));
    }
};
