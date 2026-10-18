<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('proposal_items') && !Schema::hasColumn('proposal_items', 'discount_percent')) {
            Schema::table('proposal_items', function (Blueprint $table) {
                $table->decimal('discount_percent', 8, 2)->default(0)->after('unit_price');
            });
        }

        if (Schema::hasTable('proposals') && !Schema::hasColumn('proposals', 'discount_percent')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->decimal('discount_percent', 8, 2)->default(0)->after('discount_amount');
            });
        }

        // Backfill discount_percent for existing proposal items
        $items = DB::table('proposal_items')->where('discount', '>', 0)->get();
        foreach ($items as $item) {
            $gross = (float) $item->qty * (float) $item->unit_price;
            $percent = $gross > 0 ? round(((float) $item->discount / $gross) * 100, 2) : 0;
            DB::table('proposal_items')->where('id', $item->id)->update(['discount_percent' => $percent]);
        }

        // Backfill discount_percent for existing proposals
        $proposals = DB::table('proposals')->where('discount_amount', '>', 0)->get();
        foreach ($proposals as $p) {
            $subtotal = (float) $p->subtotal;
            $percent = 0;
            if ($p->discount_type === 'percentage') {
                $percent = (float) $p->discount_amount;
            } elseif ($subtotal > 0) {
                $percent = round(((float) $p->discount_amount / $subtotal) * 100, 2);
            }
            DB::table('proposals')->where('id', $p->id)->update(['discount_percent' => $percent]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('proposal_items') && Schema::hasColumn('proposal_items', 'discount_percent')) {
            Schema::table('proposal_items', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }

        if (Schema::hasTable('proposals') && Schema::hasColumn('proposals', 'discount_percent')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
