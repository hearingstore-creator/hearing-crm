<?php

namespace App\Services;

use App\Models\LoyaltySetting;
use App\Models\MembershipMilestone;
use App\Models\MembershipTier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Moves the membership amounts (tiers, milestones, loyalty settings) from one site to another as a file,
 * so values set on a test copy can be applied to the live site without retyping, and without touching customers:
 *
 *  - Tiers are matched by order (lowest purchase range first); the number of tiers must be the same on both sites.
 *    Customers keep their tier because the tier rows themselves are updated, never replaced.
 *  - Milestones are matched by type and threshold. Ones in the file but not on the site are added; ones on the
 *    site but not in the file are left alone.
 *  - Nothing is applied until the person has seen what would change.
 */
class MembershipSettingsTransfer
{
    public const FORMAT = 'zenhear-membership-settings';
    public const VERSION = 1;

    public function export(): array
    {
        $loyalty = LoyaltySetting::current();

        return [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'exported_at' => now()->toDateTimeString(),
            'loyalty'     => [
                'point_value'        => (float) $loyalty->point_value,
                'expiry_months'      => (int) $loyalty->expiry_months,
                'min_redeem_points'  => (int) $loyalty->min_redeem_points,
                'max_redeem_percent' => (float) $loyalty->max_redeem_percent,
            ],
            'tiers' => MembershipTier::orderBy('min_purchase')->get()->map(fn ($t) => [
                'name'                   => $t->name,
                'min_purchase'           => (float) $t->min_purchase,
                'max_purchase'           => $t->max_purchase === null ? null : (float) $t->max_purchase,
                'earn_percentage'        => (float) $t->earn_percentage,
                'referral_reward_points' => (int) $t->referral_reward_points,
            ])->all(),
            'milestones' => MembershipMilestone::orderBy('type')->orderBy('threshold')->get()->map(fn ($m) => [
                'name'         => $m->name,
                'type'         => $m->type,
                'threshold'    => (float) $m->threshold,
                'bonus_points' => (int) $m->bonus_points,
                'is_active'    => (bool) $m->is_active,
            ])->all(),
        ];
    }

    /**
     * Check a file's content and describe what applying it would change.
     *
     * @return array{data: array, changes: array<int, array{area: string, what: string, from: string, to: string}>, left_alone: string[]}
     * @throws InvalidArgumentException with a message fit to show the person
     */
    public function plan(mixed $data): array
    {
        $data = $this->validated($data);
        $changes = [];
        $leftAlone = [];

        // Loyalty
        $loyalty = LoyaltySetting::current();
        foreach (['point_value' => 'Point value', 'expiry_months' => 'Points expire after (months)', 'min_redeem_points' => 'Minimum points to redeem', 'max_redeem_percent' => 'Max % of an invoice payable with points'] as $key => $label) {
            if (round((float) $loyalty->$key, 2) !== round((float) $data['loyalty'][$key], 2)) {
                $changes[] = ['area' => 'Loyalty settings', 'what' => $label, 'from' => $this->num($loyalty->$key), 'to' => $this->num($data['loyalty'][$key])];
            }
        }

        // Tiers (by order)
        $site = MembershipTier::orderBy('min_purchase')->get();
        foreach ($data['tiers'] as $i => $t) {
            $row = $site[$i];
            $label = $row->name . ($row->name !== $t['name'] ? ' → ' . $t['name'] : '');
            foreach (['name' => 'Name', 'min_purchase' => 'From purchase', 'max_purchase' => 'Up to purchase', 'earn_percentage' => 'Earn %', 'referral_reward_points' => 'Referral reward points'] as $key => $what) {
                $from = $row->$key;
                if ($key === 'name' ? $from !== $t[$key] : $this->num($from) !== $this->num($t[$key])) {
                    $changes[] = ['area' => 'Tier ' . $label, 'what' => $what, 'from' => $this->num($from), 'to' => $this->num($t[$key])];
                }
            }
        }

        // Milestones (by type and threshold)
        $existing = MembershipMilestone::all();
        $matched = [];
        foreach ($data['milestones'] as $m) {
            $row = $existing->first(fn ($e) => $e->type === $m['type'] && round((float) $e->threshold, 2) === round($m['threshold'], 2));
            if (! $row) {
                $changes[] = ['area' => 'Milestone', 'what' => 'Add ' . $m['name'], 'from' => '—', 'to' => $this->describeMilestone($m)];
                continue;
            }
            $matched[] = $row->id;
            foreach (['name' => 'Name', 'bonus_points' => 'Bonus points', 'is_active' => 'Active'] as $key => $what) {
                $from = $key === 'is_active' ? ($row->is_active ? 'Yes' : 'No') : $this->num($row->$key);
                $to = $key === 'is_active' ? ($m['is_active'] ? 'Yes' : 'No') : $this->num($m[$key]);
                if ($from !== $to) {
                    $changes[] = ['area' => 'Milestone ' . $row->name, 'what' => $what, 'from' => $from, 'to' => $to];
                }
            }
        }
        foreach ($existing->whereNotIn('id', $matched) as $row) {
            $leftAlone[] = $row->name . ' (' . $this->describeMilestone(['type' => $row->type, 'threshold' => (float) $row->threshold, 'bonus_points' => $row->bonus_points]) . ')';
        }

        return ['data' => $data, 'changes' => $changes, 'left_alone' => $leftAlone];
    }

    /** Apply a file's content; returns how many settings changed. */
    public function apply(mixed $data): int
    {
        $plan = $this->plan($data);
        $data = $plan['data'];

        DB::transaction(function () use ($data) {
            LoyaltySetting::current()->update($data['loyalty']);

            $site = MembershipTier::orderBy('min_purchase')->get();
            // Tier names are unique and can swap between rows, so park them on temporary names first
            foreach ($site as $i => $row) {
                $row->update(['name' => '~tmp' . $row->id]);
            }
            foreach ($data['tiers'] as $i => $t) {
                $site[$i]->update($t);
            }

            foreach ($data['milestones'] as $m) {
                $row = MembershipMilestone::where('type', $m['type'])->where('threshold', $m['threshold'])->first();
                $row ? $row->update(['name' => $m['name'], 'bonus_points' => $m['bonus_points'], 'is_active' => $m['is_active']])
                     : MembershipMilestone::create($m);
            }
        });

        return count($plan['changes']);
    }

    // ------------------------------------------------------------------ validation

    private function validated(mixed $data): array
    {
        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('This is not a membership settings file exported from this CRM.');
        }
        if ((int) ($data['version'] ?? 0) !== self::VERSION) {
            throw new InvalidArgumentException('This file was made by a different version of the CRM and cannot be read.');
        }

        $l = $data['loyalty'] ?? [];
        $loyalty = [
            'point_value'        => $this->number($l['point_value'] ?? null, 0.01, 1000, 'Point value'),
            'expiry_months'      => (int) $this->number($l['expiry_months'] ?? null, 1, 60, 'Expiry months'),
            'min_redeem_points'  => (int) $this->number($l['min_redeem_points'] ?? null, 0, 100000000, 'Minimum redeem points'),
            'max_redeem_percent' => $this->number($l['max_redeem_percent'] ?? null, 1, 100, 'Max redeem percent'),
        ];

        // Tiers: same rules as the Membership Tiers screen
        $tiers = array_values($data['tiers'] ?? []);
        usort($tiers, fn ($a, $b) => ((float) ($a['min_purchase'] ?? 0)) <=> ((float) ($b['min_purchase'] ?? 0)));
        $siteCount = MembershipTier::count();
        if (count($tiers) !== $siteCount) {
            throw new InvalidArgumentException("The file has " . count($tiers) . " tiers but this site has {$siteCount}. Tiers cannot be added or removed by import.");
        }
        $clean = [];
        foreach ($tiers as $t) {
            $name = trim((string) ($t['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                throw new InvalidArgumentException('Every tier needs a name.');
            }
            $clean[] = [
                'name'                   => $name,
                'min_purchase'           => $this->number($t['min_purchase'] ?? null, 0, 999999999, "Tier {$name} from purchase"),
                'max_purchase'           => ($t['max_purchase'] ?? null) === null ? null : $this->number($t['max_purchase'], 0, 999999999, "Tier {$name} up to purchase"),
                'earn_percentage'        => $this->number($t['earn_percentage'] ?? null, 0, 100, "Tier {$name} earn %"),
                'referral_reward_points' => (int) $this->number($t['referral_reward_points'] ?? null, 0, 100000000, "Tier {$name} referral points"),
            ];
        }
        if (count(array_unique(array_map('mb_strtolower', array_column($clean, 'name')))) !== count($clean)) {
            throw new InvalidArgumentException('Tier names must be different from each other.');
        }
        $this->checkRanges($clean);

        // Milestones
        $milestones = [];
        $seen = [];
        foreach ($data['milestones'] ?? [] as $m) {
            $type = $m['type'] ?? null;
            if (! in_array($type, ['lifetime_spend', 'purchase_count'], true)) {
                throw new InvalidArgumentException('A milestone has an unknown type.');
            }
            $name = trim((string) ($m['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                throw new InvalidArgumentException('Every milestone needs a name.');
            }
            $threshold = $this->number($m['threshold'] ?? null, 0.01, 999999999, "Milestone {$name} threshold");
            $key = $type . '|' . round($threshold, 2);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Two milestones in the file have the same type and threshold ({$name}).");
            }
            $seen[$key] = true;
            $milestones[] = [
                'name' => $name, 'type' => $type, 'threshold' => $threshold,
                'bonus_points' => (int) $this->number($m['bonus_points'] ?? null, 1, 1000000, "Milestone {$name} bonus points"),
                'is_active' => (bool) ($m['is_active'] ?? true),
            ];
        }

        return ['loyalty' => $loyalty, 'tiers' => $clean, 'milestones' => $milestones];
    }

    /** First tier starts at 0, the last has no upper limit, and ranges meet with no gap or overlap (0.01 apart). */
    private function checkRanges(array $tiers): void
    {
        $count = count($tiers);
        if ($count === 0) {
            return;
        }
        if (round($tiers[0]['min_purchase'], 2) !== 0.0) {
            throw new InvalidArgumentException('The first tier must start at 0.');
        }
        if ($tiers[$count - 1]['max_purchase'] !== null) {
            throw new InvalidArgumentException('The highest tier must have no upper limit.');
        }
        for ($i = 0; $i < $count - 1; $i++) {
            $cur = $tiers[$i];
            if ($cur['max_purchase'] === null || $cur['max_purchase'] <= $cur['min_purchase']) {
                throw new InvalidArgumentException("Tier {$cur['name']} needs an upper limit above its start.");
            }
            if (round($tiers[$i + 1]['min_purchase'], 2) !== round($cur['max_purchase'] + 0.01, 2)) {
                throw new InvalidArgumentException("There is a gap or overlap between tier {$cur['name']} and {$tiers[$i + 1]['name']}.");
            }
        }
    }

    private function number(mixed $value, float $min, float $max, string $label): float
    {
        if (! is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            throw new InvalidArgumentException("{$label} is missing or out of range.");
        }

        return (float) $value;
    }

    private function num(mixed $v): string
    {
        return $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    private function describeMilestone(array $m): string
    {
        return ($m['type'] === 'lifetime_spend' ? 'spend ' : 'purchases ') . $this->num($m['threshold']) . ' → ' . $this->num($m['bonus_points']) . ' points';
    }
}
