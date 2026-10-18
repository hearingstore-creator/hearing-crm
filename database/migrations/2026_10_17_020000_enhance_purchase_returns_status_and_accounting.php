<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\IncomeExpenseHead;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_returns', 'supplier_doc_no')) {
                $table->text('supplier_doc_no')->nullable()->after('supplier_memo_no');
            }
            if (!Schema::hasColumn('purchase_returns', 'supplier_doc_date')) {
                $table->date('supplier_doc_date')->nullable()->after('supplier_doc_no');
            }
            if (!Schema::hasColumn('purchase_returns', 'accepted_amount')) {
                $table->decimal('accepted_amount', 12, 2)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('purchase_returns', 'loss_amount')) {
                $table->decimal('loss_amount', 12, 2)->default(0.00)->after('accepted_amount');
            }
            if (!Schema::hasColumn('purchase_returns', 'attachment')) {
                $table->string('attachment', 255)->nullable()->after('reason');
            }
            if (!Schema::hasColumn('purchase_returns', 'remarks')) {
                $table->text('remarks')->nullable()->after('attachment');
            }
            if (!Schema::hasColumn('purchase_returns', 'confirmed_by')) {
                $table->unsignedBigInteger('confirmed_by')->nullable()->after('remarks');
                $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_returns', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            }
            if (!Schema::hasColumn('purchase_returns', 'unconfirm_reason')) {
                $table->text('unconfirm_reason')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('purchase_returns', 'unconfirmed_by')) {
                $table->unsignedBigInteger('unconfirmed_by')->nullable()->after('unconfirm_reason');
                $table->foreign('unconfirmed_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_returns', 'unconfirmed_at')) {
                $table->timestamp('unconfirmed_at')->nullable()->after('unconfirmed_by');
            }
            if (!Schema::hasColumn('purchase_returns', 'delete_reason')) {
                $table->text('delete_reason')->nullable()->after('deleted_by');
            }
        });

        // Set default status to pending for table schema
        DB::statement("ALTER TABLE purchase_returns MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'");

        // Handle existing records:
        // Check if an existing purchase return has a matching entry in supplier_ledgers.
        // If yes -> confirmed; if no -> pending.
        $existingReturns = DB::table('purchase_returns')->get();
        foreach ($existingReturns as $ret) {
            $hasLedger = DB::table('supplier_ledgers')
                ->where('ref_invoice_no', $ret->return_no)
                ->where('type', 'Debit Note')
                ->exists();

            if ($hasLedger) {
                DB::table('purchase_returns')
                    ->where('id', $ret->id)
                    ->update([
                        'status' => 'confirmed',
                        'accepted_amount' => $ret->total_amount,
                        'loss_amount' => 0.00,
                        'supplier_doc_date' => $ret->date,
                        'supplier_doc_no' => $ret->supplier_memo_no,
                        'confirmed_at' => $ret->created_at ?? now(),
                    ]);
            } else {
                DB::table('purchase_returns')
                    ->where('id', $ret->id)
                    ->update([
                        'status' => 'pending',
                        'loss_amount' => 0.00,
                    ]);
            }
        }

        // Ensure "Purchase Return Loss" expense head exists
        if (!DB::table('income_expense_heads')->where('head_name', 'Purchase Return Loss')->exists()) {
            DB::table('income_expense_heads')->insert([
                'head_type' => 'Expense',
                'head_name' => 'Purchase Return Loss',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'confirmed_by')) {
                $table->dropForeign(['confirmed_by']);
                $table->dropColumn('confirmed_by');
            }
            if (Schema::hasColumn('purchase_returns', 'unconfirmed_by')) {
                $table->dropForeign(['unconfirmed_by']);
                $table->dropColumn('unconfirmed_by');
            }
            $table->dropColumn([
                'supplier_doc_no',
                'supplier_doc_date',
                'accepted_amount',
                'loss_amount',
                'attachment',
                'remarks',
                'confirmed_at',
                'unconfirm_reason',
                'unconfirmed_at',
                'delete_reason',
            ]);
        });
    }
};
