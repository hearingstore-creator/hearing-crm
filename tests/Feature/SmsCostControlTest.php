<?php

namespace Tests\Feature;

use App\Classes\SMS;
use App\Enums\SmsAutomationTrigger;
use App\Http\Controllers\Admin\RepairController;
use App\Models\Customer;
use App\Models\RepairJob;
use App\Models\Setting;
use App\Services\SmsAutomationService;
use App\Services\SmsUsage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** SMS cost control: the mode switch, per-automation usage and cost, and the repair / pickup messages. */
class SmsCostControlTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected int $customerId;
    protected string $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();

        $this->app->bind(SMS::class, fn () => new class extends SMS {
            public function callApi($url, $params)
            {
                return json_encode(['status' => 'SUCCESS']);
            }
        });

        // the app always reads settings row 1
        DB::table('settings')->where('id', '!=', 1)->delete();
        if (! DB::table('settings')->where('id', 1)->exists()) {
            DB::table('settings')->insert(['id' => 1, 'company_name' => 'Test Co', 'created_at' => now()]);
        }
        Setting::find(1)->update(['sms_automation_mode' => 'all', 'sms_cost_per_sms' => 0.5]);
        DB::table('sms_automations')->update(['is_active' => true]);
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        $this->customerId = $this->patient();
        $this->phone = (string) DB::table('leads')->where('id', Customer::find($this->customerId)->lead_id)->value('phone_number');
    }

    protected function fire(SmsAutomationTrigger $trigger, string $key): bool
    {
        return app(SmsAutomationService::class)->fire($trigger, $this->phone, 'customer', $this->customerId, ['name' => 'Rahim', 'job_no' => 'J1', 'serial_text' => '', 'branch' => 'HQ', 'branch_phone' => '', 'promised_date' => 'soon', 'due' => '0.00', 'invoice' => 'INV1', 'item' => 'hearing device', 'company_phone' => ''], $key);
    }

    public function test_modes_decide_which_automations_send(): void
    {
        $this->assertTrue($this->fire(SmsAutomationTrigger::REPAIR_READY, 'k1'));
        $this->assertTrue($this->fire(SmsAutomationTrigger::CUSTOMER_CREATED, 'k2'));

        Setting::find(1)->update(['sms_automation_mode' => 'essential']);
        $this->assertTrue($this->fire(SmsAutomationTrigger::REPAIR_READY, 'k3'), 'essential still goes');
        $this->assertFalse($this->fire(SmsAutomationTrigger::CUSTOMER_CREATED, 'k4'), 'welcome message stops');
        $this->assertFalse($this->fire(SmsAutomationTrigger::WARRANTY_EXPIRING, 'k5'));

        Setting::find(1)->update(['sms_automation_mode' => 'off']);
        $this->assertFalse($this->fire(SmsAutomationTrigger::REPAIR_READY, 'k6'), 'paused means paused');
        $this->assertNull(app(SmsAutomationService::class)->active(SmsAutomationTrigger::TICKET_CREATED));

        $this->assertSame(3, DB::table('sms_logs')->count());
    }

    public function test_the_log_records_the_trigger_and_usage_adds_it_up_with_cost(): void
    {
        $this->fire(SmsAutomationTrigger::REPAIR_READY, 'u1');
        $this->fire(SmsAutomationTrigger::REPAIR_RECEIVED, 'u2');
        $this->fire(SmsAutomationTrigger::REPAIR_READY, 'u3');

        $this->assertSame(2, DB::table('sms_logs')->where('automation_trigger', 'repair_ready')->count());

        $usage = app(SmsUsage::class)->summary();
        $this->assertSame(3, $usage['month_parts']);
        $this->assertEquals(1.5, $usage['month_cost']);
        $ready = collect($usage['automations'])->firstWhere('name', 'Repair Ready for Pickup');
        $this->assertSame(2, $ready['messages']);
        $this->assertTrue($ready['essential']);
        $this->assertEquals(1.0, $ready['cost']);
    }

    public function test_a_long_message_counts_as_several_sms_parts(): void
    {
        DB::table('sms_automations')->where('trigger_type', 'repair_ready')->update(['message_body' => str_repeat('a', 400)]);
        $this->fire(SmsAutomationTrigger::REPAIR_READY, 'long');

        $this->assertSame(3, (int) DB::table('sms_logs')->value('sms_parts'), '400 characters = 3 parts of 153');
        $this->assertSame(3, app(SmsUsage::class)->summary()['month_parts']);
    }

    public function test_only_people_with_the_edit_permission_can_change_the_mode(): void
    {
        $manager = $this->userWithPermissions(['sms_automation.index', 'sms_automation.edit']);
        $viewer = $this->userWithPermissions(['sms_automation.index']);

        $this->actingAs($viewer)->post('/admin/sms-automations/mode', ['mode' => 'off'])->assertForbidden();
        $this->assertSame('all', Setting::find(1)->sms_automation_mode);

        $this->actingAs($manager)->post('/admin/sms-automations/mode', ['mode' => 'essential', 'cost_per_sms' => '0.42'])->assertSessionHas('success');
        $this->assertSame('essential', Setting::find(1)->sms_automation_mode);
        $this->assertEquals(0.42, (float) Setting::find(1)->sms_cost_per_sms);

        $this->actingAs($manager)->post('/admin/sms-automations/mode', ['mode' => 'everything'])->assertSessionHasErrors('mode');
    }

    public function test_the_automation_tab_shows_the_controls(): void
    {
        $this->actingAs($this->admin)->get('/admin/sms')->assertSuccessful()
            ->assertSee('Automatic SMS mode')->assertSee('Essential only')->assertSee('sms-auto-toggle', false)->assertSee('Repair Ready for Pickup');
    }

    public function test_the_repair_messages_go_through_the_automation_and_the_log(): void
    {
        $job = new RepairJob(['job_no' => 'RJ-TEST-1', 'original_serial_no' => 'SN-77', 'total_customer_cost' => 1500, 'total_customer_paid' => 500]);
        $job->id = 987654;
        $job->setRelation('customer', Customer::with('lead')->find($this->customerId));
        $job->setRelation('vendor', null);

        $call = function (string $method) use ($job) {
            $m = new \ReflectionMethod(RepairController::class, $method);
            $m->setAccessible(true);
            $m->invoke((new \ReflectionClass(RepairController::class))->newInstanceWithoutConstructor(), $job);
        };

        $call('sendIntakeSms');
        $call('sendReadySms');
        $call('sendReadySms');   // a second call for the same job sends nothing

        $logs = DB::table('sms_logs')->orderBy('id')->get();
        $this->assertCount(2, $logs, 'received once, ready once');
        $this->assertSame(['repair_received', 'repair_ready'], $logs->pluck('automation_trigger')->all());
        $this->assertStringContainsString('#RJ-TEST-1', $logs[1]->message);
        $this->assertStringContainsString('Due: Tk 1,000.00', $logs[1]->message);
        $this->assertSame('customer', $logs[0]->recipient_type);

        // switched off in the SMS Center: nothing more is sent for a new job
        DB::table('sms_automations')->where('trigger_type', 'repair_received')->update(['is_active' => false]);
        $job->id = 987655;
        $call('sendIntakeSms');
        $this->assertSame(2, DB::table('sms_logs')->count());
    }

    public function test_the_delivery_hub_sends_one_pickup_message_and_stays_quiet_on_other_steps(): void
    {
        $product = $this->product('Aid S');
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-S1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->customerId, [[$product, null, ['SN-S1'], 1]], 0)->assertSessionHasNoErrors();
        $saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();
        $url = "/admin/sales/{$saleId}/update-delivery-status";

        $this->actingAs($this->admin)->postJson($url, ['delivery_status' => 'sent_to_lab'])->assertSuccessful();
        $this->assertSame(0, DB::table('sms_logs')->count(), 'intermediate steps send nothing');

        $this->actingAs($this->admin)->postJson($url, ['delivery_status' => 'ready_for_delivery'])->assertSuccessful()->assertJsonPath('sms_sent', true);
        $log = DB::table('sms_logs')->first();
        $this->assertSame('order_ready', $log->automation_trigger);
        $this->assertStringContainsString('hearing device', $log->message);

        $this->actingAs($this->admin)->postJson($url, ['delivery_status' => 'sent_to_lab'])->assertSuccessful();
        $this->actingAs($this->admin)->postJson($url, ['delivery_status' => 'ready_for_delivery'])->assertSuccessful();
        $this->assertSame(1, DB::table('sms_logs')->count(), 'the same invoice is told only once');
    }

    public function test_a_manual_hub_sms_is_logged_as_manual_and_not_counted_against_the_automation_cap(): void
    {
        $product = $this->product('Aid M');
        $this->postPurchase($this->admin, $this->supplier(), [[$product, '', 'SN-M1', 1]])->assertSessionHasNoErrors();
        $this->postSale($this->admin, $this->customerId, [[$product, null, ['SN-M1'], 1]], 0)->assertSessionHasNoErrors();
        $saleId = (int) DB::table('sale_masters')->orderByDesc('id')->value('id');
        DB::table('sms_logs')->delete();
        DB::table('automation_logs')->delete();

        $this->actingAs($this->admin)->postJson("/admin/sales/{$saleId}/update-delivery-status", [
            'delivery_status' => 'ready_for_delivery', 'send_sms' => 1, 'custom_message' => 'Please come on Friday.',
        ])->assertSuccessful();

        $logs = DB::table('sms_logs')->get();
        $this->assertCount(1, $logs, 'the manual message replaces the automatic one');
        $this->assertSame('manual', $logs[0]->type);
        $this->assertNull($logs[0]->automation_trigger);
    }
}
