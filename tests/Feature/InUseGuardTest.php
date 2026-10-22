<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Records still in use cannot be deleted; retired products and referral / expense rules (Part 2 audit, Batch 8). */
class InUseGuardTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_product_with_history_is_kept_and_unused_product_can_go(): void
    {
        $used = $this->product('USED-AID');
        $this->postPurchase($this->admin, $this->supplier(), [[$used, '', 'SN-U1', 1]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->from('/admin/product')->delete("/admin/product/$used")
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'purchases') && str_contains($w, 'Inactive'));
        $this->assertDatabaseHas('products', ['id' => $used, 'deleted_at' => null]);

        $unused = $this->product('UNUSED-AID');
        $this->actingAs($this->admin)->from('/admin/product')->delete("/admin/product/$unused")->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $unused]);
    }

    public function test_inactive_product_is_hidden_from_purchases_but_stock_still_sells(): void
    {
        $productId = $this->product('RETIRED-AID');
        $this->postPurchase($this->admin, $this->supplier(), [[$productId, '', 'SN-R1', 1]])->assertSessionHasNoErrors();
        DB::table('products')->where('id', $productId)->update(['status' => 0]);

        $ids = fn ($type) => collect($this->actingAs($this->admin)->getJson('/admin/product-search?search=RETIRED&type=' . $type)->json())->pluck('id')->all();
        $this->assertNotContains($productId, $ids('purchase'));
        $this->assertContains($productId, $ids('sale'));
    }

    public function test_catalogue_heads_and_templates_in_use_cannot_be_deleted(): void
    {
        $categoryId = DB::table('categories')->insertGetId(['category_name' => 'Used category', 'status' => 1, 'created_at' => now()]);
        $typeId = DB::table('types')->insertGetId(['type_name' => 'Used type', 'status' => 1, 'created_at' => now()]);
        $brandId = DB::table('brands')->insertGetId(['brand_name' => 'Used brand', 'status' => 1, 'created_at' => now()]);
        DB::table('products')->insert(['model' => 'X', 'category_id' => $categoryId, 'type_id' => $typeId, 'brand_id' => $brandId, 'warranty_inventory_months' => 12, 'created_at' => now()]);

        $this->actingAs($this->admin)->from('/admin/category')->delete("/admin/category/$categoryId")->assertSessionHas('warning');
        $this->actingAs($this->admin)->from('/admin/type')->delete("/admin/type/$typeId")->assertSessionHas('warning');
        $this->actingAs($this->admin)->from('/admin/brand')->delete("/admin/brand/$brandId")->assertSessionHas('warning');
        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'deleted_at' => null]);

        $freeCategory = DB::table('categories')->insertGetId(['category_name' => 'Free', 'status' => 1, 'created_at' => now()]);
        $this->actingAs($this->admin)->from('/admin/category')->delete("/admin/category/$freeCategory")->assertSessionHas('success');
        $this->actingAs($this->admin)->delete('/admin/type/999999')->assertNotFound();

        $headId = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Rent', 'status' => 1, 'created_at' => now()]);
        $this->actingAs($this->admin)->post('/admin/expense', ['voucher_no' => 'T-' . uniqid(), 'income_expense_head_id' => $headId, 'date' => date('Y-m-d'), 'paid' => 1000]);
        $this->actingAs($this->admin)->from('/admin/income-expense-head')->delete("/admin/income-expense-head/$headId")->assertSessionHas('warning');

        $templateId = DB::table('sms_templates')->insertGetId(['name' => 'Reminder', 'category' => 'appointment', 'body' => 'Hi', 'is_active' => 1, 'created_at' => now()]);
        DB::table('sms_automations')->insert(['name' => 'Remind', 'trigger_type' => 'appointment_reminder', 'message_body' => 'Hi', 'sms_template_id' => $templateId, 'is_active' => 1, 'created_at' => now()]);
        $this->actingAs($this->admin)->from('/admin/sms-templates')->delete("/admin/sms-templates/$templateId")->assertSessionHas('warning');
    }

    public function test_referral_delete_needs_delete_right_and_no_reward(): void
    {
        $referrer = $this->patient();
        $referred = $this->patient();
        $pending = DB::table('referrals')->insertGetId(['referrer_id' => $referrer, 'referred_customer_id' => $referred, 'status' => 'pending', 'created_at' => now()]);
        $rewarded = DB::table('referrals')->insertGetId(['referrer_id' => $referrer, 'referred_customer_id' => $this->patient(), 'status' => 'completed', 'rewarded_at' => now(), 'created_at' => now()]);

        $viewer = $this->userWithPermissions(['referrals.index']);
        $this->actingAs($viewer)->delete("/admin/membership/referrals/$pending")->assertForbidden();

        $this->actingAs($this->admin)->from('/admin/membership/referrals')->delete("/admin/membership/referrals/$rewarded")
            ->assertSessionHas('warning');
        $this->actingAs($this->admin)->from('/admin/membership/referrals')->delete("/admin/membership/referrals/$pending")
            ->assertSessionHas('success');
    }

    public function test_expense_amount_and_head_are_validated(): void
    {
        $expenseHead = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Expense', 'head_name' => 'Fuel', 'status' => 1, 'created_at' => now()]);
        $incomeHead = DB::table('income_expense_heads')->insertGetId(['head_type' => 'Income', 'head_name' => 'Misc income', 'status' => 1, 'created_at' => now()]);

        $post = fn ($head, $amount) => $this->actingAs($this->admin)->from('/admin/expense/create')->post('/admin/expense', ['voucher_no' => 'T-' . uniqid(), 'income_expense_head_id' => $head, 'date' => date('Y-m-d'), 'paid' => $amount]);
        $post($expenseHead, -50)->assertSessionHasErrors('paid');
        $post($expenseHead, 'abc')->assertSessionHasErrors('paid');
        $post($incomeHead, 50)->assertSessionHasErrors('income_expense_head_id');
        $post($expenseHead, 50)->assertSessionHasNoErrors();
    }
}
