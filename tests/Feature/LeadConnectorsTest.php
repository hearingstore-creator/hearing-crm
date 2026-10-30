<?php

namespace Tests\Feature;

use App\Models\LeadCaptureForm;
use App\Models\LeadSubmission;
use App\Models\LeadWebhookEvent;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CrmFixtures;
use Tests\TestCase;

/** Marketing phase 2: Meta Lead Ads, WhatsApp, Google lead forms and a general webhook feed the lead inbox. */
class LeadConnectorsTest extends TestCase
{
    use DatabaseTransactions, CrmFixtures;

    protected LeadCaptureForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCrm();
        $this->form = LeadCaptureForm::create(['name' => 'Ads inbox', 'key' => LeadCaptureForm::newKey(), 'secret' => 'form-secret-123', 'is_active' => true, 'sla_minutes' => 15]);
        config([
            'connectors.meta.app_secret' => 'app-secret', 'connectors.meta.verify_token' => 'verify-me',
            'connectors.meta.page_token' => 'page-token', 'connectors.meta.form_key' => $this->form->key,
        ]);
    }

    private function metaPost(array $body, ?string $secret = 'app-secret')
    {
        $raw = json_encode($body);
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($secret) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $raw, $secret);
        }

        return $this->call('POST', '/api/v1/hooks/meta', [], [], [], $headers, $raw);
    }

    private function leadgen(string $id): array
    {
        return ['object' => 'page', 'entry' => [['id' => 'PAGE', 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => $id, 'page_id' => 'PAGE', 'form_id' => 'F1']]]]]];
    }

    private function graphLead(string $platform = 'fb'): array
    {
        return ['field_data' => [
            ['name' => 'full_name', 'values' => ['Abdul Karim']],
            ['name' => 'phone_number', 'values' => ['+8801711000001']],
            ['name' => 'which_ear_is_affected', 'values' => ['Both']],
        ], 'campaign_id' => '23850001', 'campaign_name' => 'Winter Hearing Offer', 'ad_name' => 'Video A', 'adset_name' => 'Dhaka 50+', 'platform' => $platform];
    }

    // ── Meta Lead Ads ──

    public function test_meta_verification_answers_only_the_right_token(): void
    {
        $this->get('/api/v1/hooks/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
        $this->get('/api/v1/hooks/meta?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertSuccessful()->assertSeeText('42');

        config(['connectors.meta.verify_token' => null]);
        $this->get('/api/v1/hooks/meta?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertNotFound();
    }

    public function test_unsigned_or_wrongly_signed_meta_calls_are_refused(): void
    {
        $this->metaPost($this->leadgen('L1'), null)->assertForbidden();
        $this->metaPost($this->leadgen('L1'), 'not-the-secret')->assertForbidden();
        $this->assertSame(0, LeadWebhookEvent::count());
    }

    public function test_a_facebook_lead_ad_becomes_a_lead_with_its_campaign(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->graphLead())]);

        $this->metaPost($this->leadgen('LG-100'))->assertSuccessful();

        $s = LeadSubmission::where('external_id', 'LG-100')->first();
        $this->assertNotNull($s);
        $this->assertSame('facebook', $s->source);
        $this->assertSame('Abdul Karim', $s->name);
        $this->assertStringContainsString('Which ear is affected: Both', $s->message);

        $campaign = MarketingCampaign::where('external_id', '23850001')->first();
        $this->assertNotNull($campaign, 'the ad campaign is added to Marketing → Campaigns');
        $this->assertSame('Winter Hearing Offer', $campaign->name);
        $this->assertSame('facebook', $campaign->channel);

        $lead = DB::table('leads')->where('id', $s->fresh()->lead_id)->first();
        $this->assertSame('facebook', $lead->source_of_entry);
        $this->assertSame($campaign->id, (int) $lead->campaign_id);
        $deal = DB::table('deals')->where('lead_id', $lead->id)->first();
        $this->assertSame('facebook', $deal->source);
        $this->assertSame($campaign->id, (int) $deal->campaign_id);
        $this->assertTrue(DB::table('lead_follow_ups')->where('deal_id', $deal->id)->where('follow_up_notes', 'like', 'Facebook lead form lead – call within 15 min%')->exists());

        // Meta sends the same lead again: nothing new
        $this->metaPost($this->leadgen('LG-100'))->assertSuccessful();
        $this->assertSame(1, LeadSubmission::where('external_id', 'LG-100')->count());
        $this->assertSame(1, LeadWebhookEvent::where('external_id', 'LG-100')->count());
    }

    public function test_an_instagram_lead_counts_for_instagram(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->graphLead('ig'))]);
        $this->metaPost($this->leadgen('LG-IG'))->assertSuccessful();

        $this->assertSame('instagram', LeadSubmission::where('external_id', 'LG-IG')->value('source'));
    }

    public function test_a_lead_meta_will_not_hand_over_is_logged_and_can_be_retried(): void
    {
        Http::fakeSequence('graph.facebook.com/*')
            ->push(['error' => ['message' => 'Invalid OAuth access token']], 400)
            ->push($this->graphLead());

        $this->metaPost($this->leadgen('LG-ERR'))->assertSuccessful();
        $event = LeadWebhookEvent::where('external_id', 'LG-ERR')->first();
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('Invalid OAuth access token', $event->note);

        $this->actingAs($this->admin)->post("/admin/web-leads/events/{$event->id}/retry")->assertSessionHas('success');
        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame(1, LeadSubmission::where('external_id', 'LG-ERR')->count());
    }

    // ── WhatsApp ──

    private function whatsapp(string $id, string $text, array $referral = []): array
    {
        $message = ['from' => '8801811000002', 'id' => $id, 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => $text]];
        if ($referral) {
            $message['referral'] = $referral;
        }

        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp', 'contacts' => [['profile' => ['name' => 'Salma Akter'], 'wa_id' => '8801811000002']], 'messages' => [$message],
        ]]]]]];
    }

    public function test_a_whatsapp_chat_from_an_ad_opens_one_enquiry(): void
    {
        $this->metaPost($this->whatsapp('wamid.1', 'Price of hearing aid?', ['source_id' => '120200001', 'source_type' => 'ad', 'headline' => 'Free hearing test', 'ctwa_clid' => 'CLID9']))->assertSuccessful();

        $s = LeadSubmission::where('source', 'whatsapp')->first();
        $this->assertSame('Salma Akter', $s->name);
        $this->assertSame('Price of hearing aid?', $s->message);
        $this->assertSame('CLID9', $s->fbclid);
        $this->assertSame('whatsapp', MarketingCampaign::where('external_id', '120200001')->value('channel'));
        $this->assertSame('whatsapp', DB::table('leads')->where('id', $s->fresh()->lead_id)->value('source_of_entry'));

        $this->metaPost($this->whatsapp('wamid.2', 'Hello?'))->assertSuccessful();
        $this->assertSame(1, LeadSubmission::where('source', 'whatsapp')->count(), 'the same conversation does not open another enquiry');
        $this->assertSame('ignored', LeadWebhookEvent::where('external_id', 'wamid.2')->value('status'));
    }

    // ── Google ──

    private function googlePayload(array $extra = []): array
    {
        return $extra + [
            'lead_id' => 'G-LEAD-1', 'api_version' => '1.0', 'form_id' => 99, 'campaign_id' => 777001, 'google_key' => 'form-secret-123', 'is_test' => false, 'gcl_id' => 'GCLID-55',
            'user_column_data' => [
                ['column_name' => 'Full Name', 'string_value' => 'Nasima Khatun', 'column_id' => 'FULL_NAME'],
                ['column_name' => 'User Phone', 'string_value' => '01911000003', 'column_id' => 'PHONE_NUMBER'],
                ['column_name' => 'Preferred branch', 'string_value' => 'Mirpur', 'column_id' => 'OTHER_1'],
            ],
        ];
    }

    public function test_a_google_lead_form_lead_arrives_with_its_click_id(): void
    {
        $url = '/api/v1/hooks/google/' . $this->form->key;
        $this->postJson($url, $this->googlePayload(['google_key' => 'wrong']))->assertForbidden();

        $this->postJson($url, $this->googlePayload(['is_test' => true, 'lead_id' => 'G-TEST']))->assertSuccessful();
        $this->assertSame('ignored', LeadWebhookEvent::where('external_id', 'G-TEST')->value('status'));

        $this->postJson($url, $this->googlePayload())->assertSuccessful();
        $s = LeadSubmission::where('external_id', 'G-LEAD-1')->first();
        $this->assertSame('google', $s->source);
        $this->assertSame('GCLID-55', $s->gclid);
        $this->assertStringContainsString('Preferred branch: Mirpur', $s->message);
        $this->assertSame('google', MarketingCampaign::where('external_id', '777001')->value('channel'));

        $this->postJson($url, $this->googlePayload())->assertSuccessful();
        $this->assertSame(1, LeadSubmission::where('external_id', 'G-LEAD-1')->count());
    }

    // ── general webhook ──

    public function test_the_general_webhook_takes_common_field_names_and_missed_calls(): void
    {
        $url = '/api/v1/hooks/lead/' . $this->form->key;
        $this->postJson($url, ['name' => 'X', 'phone' => '01711000009'])->assertForbidden();

        $this->postJson($url . '?token=form-secret-123', ['first_name' => 'Rafiq', 'last_name' => 'Islam', 'mobile' => '01711000010',
            'utm_source' => 'facebook', 'utm_campaign' => 'some_code', 'message' => 'Call me'])->assertStatus(202);
        $this->assertSame('Rafiq Islam', LeadSubmission::where('normalized_phone', '8801711000010')->value('name'));

        $this->withHeader('X-Webhook-Token', 'form-secret-123')->postJson($url, ['caller' => '01711000011', 'source' => 'missed_call'])->assertStatus(202);
        $missed = LeadSubmission::where('normalized_phone', '8801711000011')->first();
        $this->assertSame('phone_call', $missed->source);
        $this->assertStringStartsWith('Missed call', $missed->name);
        $this->assertSame('phone_call', DB::table('leads')->where('id', $missed->fresh()->lead_id)->value('source_of_entry'));

        $this->postJson($url . '?token=form-secret-123', ['name' => 'Abroad', 'phone' => '+447700900123'])->assertStatus(422);
        $this->assertSame('failed', LeadWebhookEvent::latest('id')->value('status'));
    }

    // ── screens ──

    public function test_the_inbox_shows_sources_and_the_connections_tab(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->graphLead())]);
        $this->metaPost($this->leadgen('LG-UI'));

        $this->actingAs($this->admin)->get('/admin/web-leads')->assertSuccessful()->assertSee('Lead Inbox')->assertSee('Facebook');
        $this->actingAs($this->admin)->get('/admin/web-leads?source=facebook')->assertSee('Abdul Karim');
        $this->actingAs($this->admin)->get('/admin/web-leads?source=website')->assertDontSee('Abdul Karim');
        $this->actingAs($this->admin)->get('/admin/web-leads?tab=connections')->assertSuccessful()
            ->assertSee(url('api/v1/hooks/meta'))->assertSee('api/v1/hooks/google/' . $this->form->key)->assertSee('Lead created');
    }
}
