<?php

namespace Tests\Feature;

use App\Models\LeadCaptureForm;
use App\Models\LeadSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

class PublicLeadFormTest extends TestCase
{
    use RefreshDatabase, CrmFixtures;

    private LeadCaptureForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->form = LeadCaptureForm::create([
            'name' => 'Website consultation form', 'key' => 'test-key', 'slug' => 'consultation', 'secret' => 'secret',
            'default_inquiry' => ['hearing_aid'], 'sla_minutes' => 15, 'is_active' => true,
        ]);
    }

    private function token(int $ago = 30): string
    {
        $time = time() - $ago;

        return $time . '.' . hash_hmac('sha256', $this->form->key . '|' . $time, config('app.key'));
    }

    public function test_form_shows_the_short_interest_list_and_the_hearing_animation(): void
    {
        $page = $this->get('/f/consultation')->assertOk();
        $page->assertSee('id="lf-scene"', false)->assertSee('name="t"', false)->assertSee('name="website"', false);
        foreach (['hearing_test', 'hearing_aid', 'repair', 'other'] as $iq) {
            $page->assertSee('value="' . $iq . '"', false);
        }
        foreach (['battery', 'cochlear_implant', 'ear_mold', 'equipment', 'accessories'] as $iq) {
            $page->assertDontSee('value="' . $iq . '"', false);
        }
        $page->assertDontSee('name="email"', false);
        // the form's default interest stays pre-selected
        $this->assertMatchesRegularExpression('/value="hearing_aid"[^>]*checked/', $page->getContent());
    }

    public function test_embedded_form_has_a_close_button_and_the_popup_script_loads(): void
    {
        $this->get('/f/consultation?embed=1')->assertOk()->assertSee('id="lf-close"', false)->assertDontSee('id="lf-back"', false);
        $this->get('/f/consultation/embed.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->assertSee('CRMLeadForm', false)->assertSee('Free hearing check', false);
    }

    public function test_a_submission_without_email_is_accepted(): void
    {
        $this->postJson('/f/consultation', [
            'name' => 'Rahim Uddin', 'phone' => '01712345678', 'inquiry_for' => ['hearing_test'],
            'message' => 'My father struggles on phone calls', 't' => $this->token(),
        ])->assertOk()->assertJson(['ok' => true]);

        $s = LeadSubmission::firstOrFail();
        $this->assertSame('Rahim Uddin', $s->name);
        $this->assertNull($s->email);
        $this->assertSame(['hearing_test'], $s->inquiry_for);
    }

    public function test_a_bad_phone_number_is_rejected(): void
    {
        $this->postJson('/f/consultation', ['name' => 'Rahim Uddin', 'phone' => '12345', 't' => $this->token()])
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(0, LeadSubmission::count());
    }
}
