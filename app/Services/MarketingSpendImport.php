<?php

namespace App\Services;

use App\Models\MarketingCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reads the spend exports of Meta Ads Manager and Google Ads (CSV) into campaign × month totals.
 *
 * Meta:   "Campaign name", "Amount spent (BDT)", "Reporting starts" (one row per campaign, or per day)
 * Google: "Campaign", "Cost", "Month" or "Day" (title lines above the header are skipped)
 * Column names are found by meaning, so small differences between exports do not matter.
 */
class MarketingSpendImport
{
    /**
     * @return array{platform: string, currency: ?string, rows: array<int, array{name: string, month: string, amount: float, campaign_id: ?int, campaign_name: ?string}>, skipped: int}
     */
    public function parse(string $path, string $platform, float $rate = 1.0): array
    {
        $lines = $this->readCsv($path);
        [$headerIndex, $cols] = $this->findHeader($lines);
        if ($headerIndex === null) {
            throw new \DomainException('Could not find the campaign, amount and date columns. Export the report as CSV with "Campaign", the amount spent and a date or month column.');
        }

        $currency = null;
        if (preg_match('/\(([A-Z]{3})\)/', (string) ($lines[$headerIndex][$cols['amount']] ?? ''), $m)) {
            $currency = $m[1];
        }
        if ($currency === null && isset($cols['currency'])) {
            $currency = strtoupper(trim((string) ($lines[$headerIndex + 1][$cols['currency']] ?? ''))) ?: null;
        }

        $totals = [];
        $skipped = 0;
        foreach (array_slice($lines, $headerIndex + 1) as $row) {
            $name = trim((string) ($row[$cols['campaign']] ?? ''));
            $amountRaw = (string) ($row[$cols['amount']] ?? '');
            $dateRaw = trim((string) ($row[$cols['date']] ?? ''));
            if ($name === '' || preg_match('/^(total|--)/i', $name)) {
                $skipped++;
                continue;
            }
            $amount = (float) preg_replace('/[^0-9.\-]/', '', $amountRaw);
            $month = $this->month($dateRaw);
            if ($month === null || $amount <= 0) {
                $skipped++;
                continue;
            }
            $key = mb_strtolower($name) . '|' . $month;
            $totals[$key] ??= ['name' => $name, 'month' => $month, 'amount' => 0.0];
            $totals[$key]['amount'] += $amount * $rate;
        }

        $rows = array_map(function ($t) {
            $campaign = MarketingCampaign::findByCode($t['name'])
                ?? MarketingCampaign::findByCode(Str::slug($t['name'], '_'))
                ?? MarketingCampaign::whereRaw('LOWER(name) = ?', [mb_strtolower($t['name'])])->first();

            return $t + ['amount' => round($t['amount'], 2), 'campaign_id' => $campaign?->id, 'campaign_name' => $campaign?->name];
        }, array_values($totals));

        return ['platform' => $platform, 'currency' => $currency, 'rows' => $rows, 'skipped' => $skipped];
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);   // UTF-8 BOM
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');   // Google Ads sometimes exports UTF-16
        }
        $delimiter = substr_count(strtok($content, "\n") ?: '', "\t") > substr_count(strtok($content, "\n") ?: '', ',') ? "\t" : ',';

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0: ?int, 1: array<string, int>} */
    private function findHeader(array $lines): array
    {
        foreach (array_slice($lines, 0, 15, true) as $i => $row) {
            $cols = [];
            foreach ($row as $j => $cell) {
                $c = mb_strtolower(trim((string) $cell));
                if (!isset($cols['campaign']) && in_array($c, ['campaign name', 'campaign'], true)) {
                    $cols['campaign'] = $j;
                } elseif (!isset($cols['amount']) && (str_starts_with($c, 'amount spent') || in_array($c, ['cost', 'spend', 'amount'], true))) {
                    $cols['amount'] = $j;
                } elseif (!isset($cols['date']) && in_array($c, ['reporting starts', 'month', 'day', 'date', 'reporting start', 'week'], true)) {
                    $cols['date'] = $j;
                } elseif ($c === 'currency code' || $c === 'currency') {
                    $cols['currency'] = $j;
                }
            }
            if (isset($cols['campaign'], $cols['amount'], $cols['date'])) {
                return [$i, $cols];
            }
        }

        return [null, []];
    }

    /** "2026-10-03", "Oct 2026", "October 2026", "2026-10", "10/03/2026" → "2026-10". */
    private function month(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}";
        }
        try {
            return Carbon::parse($raw)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }
}
