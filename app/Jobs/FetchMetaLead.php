<?php

namespace App\Jobs;

use App\Models\LeadWebhookEvent;
use App\Services\LeadConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Reads a Meta Lead Ads lead from the Graph API after Meta's webhook said it exists. */
class FetchMetaLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public int $eventId)
    {
    }

    public function handle(LeadConnector $connector): void
    {
        $event = LeadWebhookEvent::find($this->eventId);
        if (!$event || in_array($event->status, ['processed', 'duplicate', 'ignored'], true)) {
            return;
        }
        $connector->fetchMetaLead($event);
    }
}
