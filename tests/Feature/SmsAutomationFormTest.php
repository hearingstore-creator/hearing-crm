<?php

namespace Tests\Feature;

use App\Models\SmsAutomation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** The create / edit pages share one form: it renders, keeps the field names, and saves as before. */
class SmsAutomationFormTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
    }

    public function test_the_create_form_renders_with_its_fields_and_preview(): void
    {
        $this->actingAs($this->admin)->get('/admin/sms-automations/create')->assertSuccessful()
            ->assertSee('name="message_body"', false)
            ->assertSee('name="trigger_type"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('Live preview')
            ->assertSee('Save Automation');
    }

    public function test_the_edit_form_is_filled_in_and_can_be_saved(): void
    {
        $auto = SmsAutomation::first();
        $this->assertNotNull($auto, 'seeded automations are expected');

        $this->actingAs($this->admin)->get('/admin/sms-automations/' . $auto->id . '/edit')->assertSuccessful()
            ->assertSee('Update Automation')
            ->assertSee('<option value="' . $auto->trigger_type . '" selected>', false);

        $this->actingAs($this->admin)->put('/admin/sms-automations/' . $auto->id, [
            'name'         => 'Renamed rule',
            'trigger_type' => $auto->trigger_type,
            'description'  => 'x',
            'message_body' => 'Hello {{name}}',
            'is_active'    => 0,
        ])->assertSessionHasNoErrors();

        $auto->refresh();
        $this->assertSame('Renamed rule', $auto->name);
        $this->assertFalse((bool) $auto->is_active);
    }
}
