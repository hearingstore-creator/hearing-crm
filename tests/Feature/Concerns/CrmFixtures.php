<?php

namespace Tests\Feature\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AclSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Small fixtures for feature tests: menus/permissions from config/acl.php, a Super Admin,
 * a branch login, and plain rows for suppliers, products, patients and doctors.
 */
trait CrmFixtures
{
    protected User $admin;
    protected User $branch;

    protected function setUpCrm(): void
    {
        if (! User::where('email', 'admin@gmail.com')->exists()) {
            $this->seed(AclSeeder::class);
        }
        $this->admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $this->branch = User::forceCreate([
            'name' => 'Test Branch', 'email' => 'branch@test.test', 'password' => bcrypt('secret-pass'),
            'type' => 'vendor', 'status' => 1,
        ]);
    }

    /** A user with a role holding exactly these permissions. */
    protected function userWithPermissions(array $permissionNames, string $type = null): User
    {
        $role = Role::create(['name' => 'Test role ' . uniqid()]);
        $role->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));
        $user = User::forceCreate([
            'name' => 'Test User', 'email' => uniqid('u') . '@test.test', 'password' => bcrypt('secret-pass'),
            'type' => $type, 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function supplier(): int
    {
        return DB::table('suppliers')->insertGetId(['supplier_name' => 'Test Supplier', 'created_at' => now()]);
    }

    protected function product(string $model = 'TEST-AID'): int
    {
        return DB::table('products')->insertGetId(['model' => $model, 'warranty_inventory_months' => 0, 'created_at' => now()]);
    }

    protected function patient(string $email = null): int
    {
        $lead = DB::table('leads')->insertGetId([
            'full_name' => 'Test Patient', 'phone_number' => '017' . random_int(10000000, 99999999), 'email' => $email, 'created_at' => now(),
        ]);

        return DB::table('customers')->insertGetId([
            'customer_uid' => 'C-T-' . $lead, 'lead_id' => $lead, 'referral_code' => 'TST' . $lead, 'created_at' => now(),
        ]);
    }

    protected function doctor(string $name = 'Dr Test'): int
    {
        return DB::table('doctors')->insertGetId(['name' => $name, 'status' => 1, 'created_at' => now()]);
    }

    /** POST a purchase as $user. $lines: [productId, batchNo, serialsText, qty] */
    protected function postPurchase(User $user, int $supplierId, array $lines, float $paid = 0)
    {
        return $this->actingAs($user)->from('/admin/purchase/create')->post('/admin/purchase', [
            'supplier_id' => $supplierId, 'payment_type_id' => $paid > 0 ? 1 : 4, 'date' => date('Y-m-d'),
            'total_amount' => 100, 'paid' => $paid, 'vat' => 0, 'discount' => 0,
            'product_id' => array_column($lines, 0), 'batch_no' => array_column($lines, 1), 'serial_no' => array_column($lines, 2),
            'qty' => array_column($lines, 3), 'purchase_price' => array_fill(0, count($lines), 100), 'mrp_price' => array_fill(0, count($lines), 200),
        ]);
    }

    /** POST a sale as $user. $lines: [productId, batchId|null, serials array|null, qty] */
    protected function postSale(User $user, int $customerId, array $lines, float $paid = 0, array $extra = [])
    {
        return $this->actingAs($user)->from('/admin/sale/create')->post('/admin/sale', array_merge([
            'customer_id' => $customerId, 'payment_type_id' => 1, 'date' => date('Y-m-d'),
            'total_amount' => 200, 'paid' => $paid, 'vat' => 0, 'total_discount' => 0,
            'product_id' => array_column($lines, 0), 'qty' => array_column($lines, 3),
            'unit_price' => array_fill(0, count($lines), 200), 'purchase_price' => array_fill(0, count($lines), 100),
            'discount' => array_fill(0, count($lines), 0),
            'batch_id_formatted' => json_encode(array_map(fn ($l) => $l[1] ?: null, $lines)),
            'serial_no_formatted' => json_encode(array_map(fn ($l) => $l[2] ?: null, $lines)),
        ], $extra));
    }

    protected function lastId(string $table): int
    {
        return (int) DB::table($table)->max('id');
    }

    /** debit - credit of live rows for an invoice in a party ledger */
    protected function partyNet(string $table, string $ref): float
    {
        return (float) DB::table($table)->where('ref_invoice_no', $ref)->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as n')->value('n');
    }

    /** income - expense of live company ledger rows for an invoice */
    protected function companyNet(string $ref): float
    {
        return (float) DB::table('ledgers')->where('ref_invoice_no', $ref)->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(income_amount),0) - COALESCE(SUM(expense_amount),0) as n')->value('n');
    }
}
