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
        // 1. Enhance proposals table
        Schema::table('proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('proposals', 'proposal_no')) {
                $table->string('proposal_no', 50)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('proposals', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('customer_id');
                $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            }
            if (!Schema::hasColumn('proposals', 'deal_id')) {
                $table->unsignedBigInteger('deal_id')->nullable()->after('lead_id');
                $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
            }
            if (!Schema::hasColumn('proposals', 'doctor_id')) {
                $table->unsignedBigInteger('doctor_id')->nullable()->after('deal_id');
                $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            }
            if (!Schema::hasColumn('proposals', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('doctor_id');
                $table->foreign('vendor_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('proposals', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('email');
            }
            if (!Schema::hasColumn('proposals', 'recipient_phone')) {
                $table->string('recipient_phone', 50)->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('proposals', 'recipient_address')) {
                $table->text('recipient_address')->nullable()->after('recipient_phone');
            }
            if (!Schema::hasColumn('proposals', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('valid_until');
            }
            if (!Schema::hasColumn('proposals', 'discount_type')) {
                $table->string('discount_type', 20)->default('fixed')->after('subtotal');
            }
            if (!Schema::hasColumn('proposals', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_type');
            }
            if (!Schema::hasColumn('proposals', 'vat_amount')) {
                $table->decimal('vat_amount', 12, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('proposals', 'grand_total')) {
                $table->decimal('grand_total', 12, 2)->default(0)->after('vat_amount');
            }
            if (!Schema::hasColumn('proposals', 'terms_and_conditions')) {
                $table->text('terms_and_conditions')->nullable()->after('content');
            }
            if (!Schema::hasColumn('proposals', 'notes')) {
                $table->text('notes')->nullable()->after('terms_and_conditions');
            }
            if (!Schema::hasColumn('proposals', 'converted_sale_id')) {
                $table->unsignedBigInteger('converted_sale_id')->nullable()->after('status');
                $table->foreign('converted_sale_id')->references('id')->on('sale_masters')->nullOnDelete();
            }
        });

        // Convert status column to varchar so any status workflow values can be stored cleanly
        try {
            DB::statement("ALTER TABLE `proposals` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'Draft'");
        } catch (\Throwable $e) {
            // Ignore if already varchar or driver doesn't support modify
        }

        // 2. Create proposal_items table
        if (!Schema::hasTable('proposal_items')) {
            Schema::create('proposal_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('proposal_id');
                $table->string('item_type', 30)->default('product')->comment('product, service, custom');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('item_name');
                $table->text('description')->nullable();
                $table->decimal('qty', 10, 2)->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total_price', 12, 2)->default(0);
                $table->integer('warranty_months')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('proposal_id')->references('id')->on('proposals')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('service_id')->references('id')->on('service_types')->nullOnDelete();
            });
        }

        // 3. Backfill proposal_no and grand_total for existing records
        $existing = DB::table('proposals')->whereNull('proposal_no')->get();
        foreach ($existing as $p) {
            $date = $p->proposal_date ? date('Ym', strtotime($p->proposal_date)) : date('Ym');
            $no = 'QUO-' . $date . '-' . str_pad($p->id, 4, '0', STR_PAD_LEFT);
            $total = (float) ($p->total_amount ?? 0);
            DB::table('proposals')->where('id', $p->id)->update([
                'proposal_no' => $no,
                'subtotal' => $total,
                'grand_total' => $total,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_items');

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['doctor_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['converted_sale_id']);

            $table->dropColumn([
                'proposal_no',
                'lead_id',
                'deal_id',
                'doctor_id',
                'vendor_id',
                'recipient_name',
                'recipient_phone',
                'recipient_address',
                'subtotal',
                'discount_type',
                'discount_amount',
                'vat_amount',
                'grand_total',
                'terms_and_conditions',
                'notes',
                'converted_sale_id',
            ]);
        });
    }
};
