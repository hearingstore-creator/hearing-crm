<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app alert shown in the header bell. Users can mute a category in Notification settings. */
class CrmAlert extends Notification
{
    /** key => [label, description, icon] */
    public const CATEGORIES = [
        'deal_assigned' => ['Deals assigned to me', 'A deal is transferred or assigned to you.', 'fa-user-check'],
        'web_lead_sla'  => ['Website lead not called', 'A website lead is still waiting for the first call.', 'fa-globe'],
        'daily_digest'  => ['Good morning summary', 'Your activities for the day, every morning.', 'fa-sun'],
        'company_daily' => ['Yesterday at a glance', 'Company numbers for yesterday (owners).', 'fa-chart-line'],
        'ticket_assigned' => ['Tickets given to me', 'A support ticket is created for you or given to you.', 'fa-user-check'],
        'ticket_new'      => ['New tickets without an owner', 'A new support ticket has nobody to handle it yet (support managers).', 'fa-ticket-alt'],
        'ticket_overdue'  => ['Ticket overdue', 'A support ticket is past its deadline.', 'fa-stopwatch'],
        'ticket_stale'    => ['Ticket with no update', 'A support ticket has had no update for a few days.', 'fa-hourglass-half'],
        'ticket_reopened' => ['Ticket reopened', 'A solved or closed ticket was reopened.', 'fa-undo'],
        'task_due'        => ['Tasks due', 'A task is due tomorrow or overdue.', 'fa-tasks'],
        'support_digest'  => ['Support work today', 'Your tickets and tasks due, every morning.', 'fa-headset'],
        'sms_balance'     => ['SMS balance low', 'The SMS gateway credit is at or below the warning level (owners).', 'fa-battery-quarter'],
    ];

    public function __construct(
        public string $title,
        public string $text,
        public ?string $url = null,
        public string $icon = 'fa-bell',
        public string $tone = 'info',   // info | success | warning | danger
        public string $category = 'general',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'text' => $this->text, 'url' => $this->url, 'icon' => $this->icon, 'tone' => $this->tone, 'category' => $this->category];
    }
}
