<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Is this record still used?" checks before a delete. Each check is [table, column, label];
 * soft-deleted rows do not count. Returns the labels that still use the record.
 */
class UsageGuard
{
    public const PRODUCT = [
        ['stocks', 'product_id', 'stock movements'],
        ['purchase_details', 'product_id', 'purchases'],
        ['sale_details', 'product_id', 'sales'],
        ['batches', 'product_id', 'batches'],
        ['purchase_return_details', 'product_id', 'purchase returns'],
        ['sale_return_details', 'product_id', 'sales returns'],
        ['repair_jobs', 'product_id', 'repair jobs'],
        ['repair_items', 'product_id', 'repair items'],
        ['serial_chains', 'product_id', 'warranty serial records'],
        ['proposal_items', 'product_id', 'proposals'],
        ['doctor_product_commissions', 'product_id', 'doctor commission rules'],
    ];
    public const CATEGORY = [['products', 'category_id', 'products'], ['doctor_category_commissions', 'category_id', 'doctor commission rules']];
    public const TYPE = [['products', 'type_id', 'products']];
    public const BRAND = [['products', 'brand_id', 'products']];
    public const INCOME_EXPENSE_HEAD = [['ledgers', 'income_expense_head_id', 'ledger entries']];
    public const SMS_TEMPLATE = [['sms_automations', 'sms_template_id', 'SMS automations']];

    /** @return string[] e.g. ["sales (12)", "purchases (3)"] */
    public static function usedIn(array $checks, int $id): array
    {
        $used = [];
        foreach ($checks as [$table, $column, $label]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table)->where($column, $id);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($count = $query->count()) {
                $used[] = "{$label} ({$count})";
            }
        }

        return $used;
    }

    /** Warning text for a blocked delete, or null when the record is unused. */
    public static function blockMessage(string $what, array $checks, int $id, bool $canDeactivate = true): ?string
    {
        $used = self::usedIn($checks, $id);
        if (!$used) {
            return null;
        }

        return "Cannot delete {$what}: it is still used in " . implode(', ', $used) . '.'
            . ($canDeactivate ? ' Set it to Inactive instead.' : '');
    }
}
