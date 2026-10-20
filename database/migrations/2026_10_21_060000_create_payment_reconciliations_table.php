<?php

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
        if (!Schema::hasTable('payment_reconciliations')) {
            Schema::create('payment_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->string('reconciliation_no', 50)->unique();
                $table->string('batch_no', 100)->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->string('channel', 50)->default('card'); // card, bkash, nagad, rocket, bank_transfer, other
                $table->string('pos_terminal_name', 100)->nullable();
                $table->date('settlement_date');
                $table->decimal('gross_amount', 12, 2)->default(0);
                $table->decimal('fee_amount', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->decimal('fee_rate', 5, 2)->nullable();
                $table->unsignedInteger('total_transactions')->default(1);
                $table->unsignedBigInteger('ledger_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('vendor_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('reconciled_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('ledger_id')->references('id')->on('ledgers')->onDelete('set null');
            });
        } else {
            Schema::table('payment_reconciliations', function (Blueprint $table) {
                if (!Schema::hasColumn('payment_reconciliations', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('reconciled_by');
                }
                if (!Schema::hasColumn('payment_reconciliations', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('payment_reconciliations', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->after('updated_by');
                }
            });
        }

        Schema::table('sale_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_masters', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('payment_id');
            }
            if (!Schema::hasColumn('sale_masters', 'payment_trx_no')) {
                $table->string('payment_trx_no', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('sale_masters', 'pos_terminal_name')) {
                $table->string('pos_terminal_name', 100)->nullable()->after('payment_trx_no');
            }
            if (!Schema::hasColumn('sale_masters', 'gateway_settlement_status')) {
                $table->string('gateway_settlement_status', 30)->default('not_applicable')->after('pos_terminal_name');
            }
            if (!Schema::hasColumn('sale_masters', 'gateway_charge_amount')) {
                $table->decimal('gateway_charge_amount', 10, 2)->default(0)->after('gateway_settlement_status');
            }
            if (!Schema::hasColumn('sale_masters', 'gateway_net_amount')) {
                $table->decimal('gateway_net_amount', 10, 2)->default(0)->after('gateway_charge_amount');
            }
            if (!Schema::hasColumn('sale_masters', 'gateway_settled_at')) {
                $table->dateTime('gateway_settled_at')->nullable()->after('gateway_net_amount');
            }
            if (!Schema::hasColumn('sale_masters', 'gateway_settlement_id')) {
                $table->unsignedBigInteger('gateway_settlement_id')->nullable()->after('gateway_settled_at');
                $table->foreign('gateway_settlement_id')->references('id')->on('payment_reconciliations')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            if (Schema::hasColumn('sale_masters', 'gateway_settlement_id')) {
                $table->dropForeign(['gateway_settlement_id']);
                $table->dropColumn('gateway_settlement_id');
            }
            $table->dropColumn([
                'payment_method',
                'payment_trx_no',
                'pos_terminal_name',
                'gateway_settlement_status',
                'gateway_charge_amount',
                'gateway_net_amount',
                'gateway_settled_at',
            ]);
        });

        Schema::dropIfExists('payment_reconciliations');
    }
};
