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
        $orders = [
            // CRM
            1   => 1,  // Dashboard
            2   => 2,  // Leads
            38  => 3,  // Customer
            19  => 4,  // Doctor
            25  => 5,  // Vendor (Branches)
            22  => 6,  // Supplier

            // SALES & COMMERCE
            31  => 7,  // Sales
            35  => 8,  // Proposals
            28  => 9,  // Purchase

            // OPERATIONS (Grouped consecutively so only 1 OPERATIONS header appears)
            11  => 10, // Appointment
            117 => 11, // repair (Repair & Service)
            14  => 12, // Inventory
            87  => 13, // Support

            // FINANCE
            47  => 14, // Accounts
            41  => 15, // Membership

            // COMMUNICATION
            82  => 16, // SMS Centre

            // REPORTS & ADMINISTRATION
            53  => 17, // Report
            58  => 18, // Utilities
        ];

        foreach ($orders as $id => $order) {
            Menu::where('id', $id)->update(['order' => $order]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Menu::where('id', 117)->update(['order' => 13]);
        Menu::where('id', 47)->update(['order' => 13]);
    }
};
