<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;

/**
 * The Reports hub: every existing report in groups, shown only to people who may open it.
 *
 * Labels and permissions are read from config/acl.php (a report is found by its URL), so a renamed
 * menu or a changed permission follows automatically. The groups below only decide where each URL sits.
 */
class ReportHub
{
    public const MAX_STARS = 6;

    /** group => [icon, [url => short description]] */
    public const GROUPS = [
        'Sales' => ['fa-cash-register', [
            'admin/report/sales-performance' => 'Revenue, staff, product and doctor performance',
            'admin/sales/fulfillment'        => 'Delivery and report hand-over tracking',
            'admin/sales/pending-services'   => 'Services sold but not yet booked',
            'admin/report/services-status'   => 'Service bookings by status',
            'admin/report/advances'          => 'Patient advances taken and applied',
            'admin/deal-reports'             => 'Deal board results',
        ]],
        'Purchases & Stock' => ['fa-boxes', [
            'admin/report/stock'              => 'Stock on hand by product',
            'admin/report/purchase-stock'     => 'Purchases against stock movement',
            'admin/report/purchase-summary'   => 'Purchases by supplier and period',
            'admin/report/supplier-statement' => 'What each supplier is owed',
            'admin/report/warranty-expiry'    => 'Warranties ending soon',
            'admin/report/battery-usage-expiry' => 'Battery supply running out',
        ]],
        'Patients & Membership' => ['fa-users', [
            'admin/report/membership-loyalty' => 'Tiers, points and upgrades',
            'admin/customer-ledger/create'    => 'A patient\'s account statement',
            'admin/customer-lookup'           => 'Find a patient across records',
            'admin/membership/reward-ledger'  => 'Every points movement',
        ]],
        'Doctors & Referrals' => ['fa-user-md', [
            'admin/report/doctor-statement' => 'Referrals, commission and payments per doctor',
            'admin/report/doctor-referral'  => 'Doctor and referral summary',
        ]],
        'Leads & Marketing' => ['fa-bullseye', [
            'admin/report/lead-funnel' => 'Enquiry to sale funnel by source and staff',
            'admin/sms-analytics'      => 'Messages sent, delivered and failed',
        ]],
        'Accounts' => ['fa-balance-scale', [
            'admin/report/ledger'         => 'Income, expense and cash movements',
            'admin/report/vendor-monthly' => 'Month-by-month results per branch',
        ]],
        'Operations' => ['fa-tools', [
            'admin/repairs/history'                 => 'Closed repair jobs',
            'admin/repairs/supplier-reconciliation' => 'Lab bills against jobs',
            'admin/appointments/calendar'           => 'Bookings and appointment use',
        ]],
    ];

    /** url => [label, [permissions]] from config/acl.php, a URL listed under two menus keeps both permissions. */
    protected function catalogue(): array
    {
        $found = [];
        foreach (config('acl.menus', []) as $menu) {
            foreach ($menu['children'] ?? [] as $child) {
                $url = $child['route'] ?? null;
                if (!$url) {
                    continue;
                }
                $prefix = $child['permission'] ?? strtolower(str_replace(' ', '_', $child['name']));
                $found[$url]['label'] ??= $child['label'] ?? $child['name'];
                $found[$url]['perms'][] = $prefix . '.index';
            }
        }

        return $found;
    }

    /** Groups with the reports this user may open; empty groups are dropped. */
    public function forUser(User $user): array
    {
        $catalogue = $this->catalogue();
        $out = [];
        foreach (self::GROUPS as $group => [$icon, $reports]) {
            $items = [];
            foreach ($reports as $url => $about) {
                $entry = $catalogue[$url] ?? null;
                if (!$entry || !$user->hasAccess(implode('|', $entry['perms']))) {
                    continue;
                }
                $items[] = ['url' => $url, 'label' => $entry['label'], 'about' => $about];
            }
            if ($items) {
                $out[$group] = ['icon' => $icon, 'items' => $items];
            }
        }

        return $out;
    }

    /** URLs this user starred, limited to reports they can still open. */
    public function starred(User $user, array $groups): array
    {
        $saved = (array) UserPreference::read($user->id, 'starred_reports', []);
        $all = collect($groups)->flatMap(fn ($g) => $g['items'])->keyBy('url');

        return collect($saved)->filter(fn ($u) => $all->has($u))->map(fn ($u) => $all[$u])->take(self::MAX_STARS)->values()->all();
    }

    /** Star or unstar; returns the new list, or null when the report is not one this user may open or the limit is hit. */
    public function toggle(User $user, string $url): ?array
    {
        $groups = $this->forUser($user);
        $allowed = collect($groups)->flatMap(fn ($g) => array_column($g['items'], 'url'))->all();
        if (!in_array($url, $allowed, true)) {
            return null;
        }

        $saved = array_values(array_filter((array) UserPreference::read($user->id, 'starred_reports', []), fn ($u) => in_array($u, $allowed, true)));
        if (in_array($url, $saved, true)) {
            $saved = array_values(array_diff($saved, [$url]));
        } elseif (count($saved) >= self::MAX_STARS) {
            return null;
        } else {
            $saved[] = $url;
        }
        UserPreference::write($user->id, 'starred_reports', $saved);

        return $saved;
    }
}
