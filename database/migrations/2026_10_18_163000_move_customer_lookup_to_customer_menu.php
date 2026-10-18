<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Menu;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $customerParent = Menu::where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        })->where(function ($q) {
            $q->where('name', 'Customer')->orWhere('name', 'Customers');
        })->first();

        $lookupMenu = Menu::where('route', 'admin/customer-lookup')
            ->orWhere('name', 'Customer Lookup')
            ->first();

        if ($customerParent && $lookupMenu) {
            $lookupMenu->update([
                'parent_id' => $customerParent->id,
                'order'     => 2,
            ]);

            // Reorder sibling items in Customer menu cleanly
            $ledger = Menu::where('parent_id', $customerParent->id)->where('name', 'Customer Ledger')->first();
            if ($ledger) {
                $ledger->update(['order' => 3]);
            }

            $payment = Menu::where('parent_id', $customerParent->id)->where('name', 'Customer Payment')->first();
            if ($payment) {
                $payment->update(['order' => 4]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $supportParent = Menu::where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        })->where(function ($q) {
            $q->where('name', 'Support')->orWhere('label', 'Support');
        })->first();

        $lookupMenu = Menu::where('route', 'admin/customer-lookup')
            ->orWhere('name', 'Customer Lookup')
            ->first();

        if ($supportParent && $lookupMenu) {
            $lookupMenu->update([
                'parent_id' => $supportParent->id,
                'order'     => 0,
            ]);
        }
    }
};
