<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trials happen during the visit, so the Trial stage no longer asks for a return date
 * (staff had to invent one). The trial product is still asked for. Data only.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('pipeline_stages')->where('status_key', 'trial')->get(['id', 'required_fields']) as $stage) {
            $fields = json_decode((string) $stage->required_fields, true) ?: [];
            if (in_array('trial_return_date', $fields, true)) {
                DB::table('pipeline_stages')->where('id', $stage->id)->update([
                    'required_fields' => json_encode(array_values(array_diff($fields, ['trial_return_date']))),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Not put back: the date can be required again in Pipeline Settings if ever wanted.
    }
};
