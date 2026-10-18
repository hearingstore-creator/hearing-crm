<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one SMS campaign in the background so the page never waits for the gateway.
 * Runs "as" the user who sent it, so branch visibility rules pick the same audience they saw.
 */
class SendSmsCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;          // never risk sending a campaign twice
    public int $timeout = 900;

    public function __construct(public int $campaignId, public ?int $userId)
    {
    }

    public function handle(SmsService $sms): void
    {
        $campaign = SmsCampaign::find($this->campaignId);
        if (!$campaign || $campaign->sent_at || $campaign->getRawOriginal('status') !== 'sending') {   // already handled
            return;
        }
        if ($this->userId && ($user = User::find($this->userId))) {
            auth()->setUser($user);
        }

        $sms->sendBulkCampaign($campaign);

        if ($campaign->create_follow_up) {
            $sms->createCampaignFollowUps($campaign->fresh());
        }
    }

    public function failed(Throwable $e): void
    {
        SmsCampaign::whereKey($this->campaignId)->update(['status' => 'failed']);
    }
}
