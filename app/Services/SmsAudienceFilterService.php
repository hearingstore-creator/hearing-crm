<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\SmsFilterGroup;
use App\Support\SmsPerson;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Handles advanced audience filtering for the SMS Compose builder.
 * Does NOT touch existing SmsService logic.
 */
class SmsAudienceFilterService
{
    /**
     * Filter leads based on criteria array from the audience builder.
     */
    public function filterLeads(array $filters): Collection
    {
        $query = Lead::query()->whereNull('deleted_at');

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $op = $filter['op'] ?? 'in';
            $values = $filter['values'] ?? [];
            $value = $filter['value'] ?? null;

            switch ($field) {
                case 'lead_status':
                    $query->whereIn('lead_status', $values);
                    break;
                case 'inquiry_for':
                    $query->where(function ($q) use ($values) {
                        foreach ($values as $v) {
                            $q->orWhere('inquiry_for', 'LIKE', '%' . $v . '%');
                        }
                    });
                    break;
                case 'lead_type':
                    $query->whereIn('lead_type', $values);
                    break;
                case 'source_of_entry':
                    $query->whereIn('source_of_entry', $values);
                    break;
                case 'gender':
                    $query->whereIn('gender', $values);
                    break;
                case 'district_id':
                    $query->whereIn('district_id', $values);
                    break;
                case 'lead_age_days':
                    $this->applyNumericOp($query, 'created_at', $op, $value, $values, true);
                    break;
            }
        }

        return $query->get();
    }

    /**
     * Filter customers based on criteria array from the audience builder.
     */
    public function filterCustomers(array $filters): Collection
    {
        $query = Customer::query()
            ->with(['lead', 'membershipTier', 'saleMasters.saleDetails.product'])
            ->whereNull('deleted_at');

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $op = $filter['op'] ?? 'in';
            $values = $filter['values'] ?? [];
            $value = $filter['value'] ?? null;

            switch ($field) {
                case 'membership_tier':
                    $query->whereHas('membershipTier', function ($q) use ($values) {
                        $q->whereIn('name', $values);
                    });
                    break;
                case 'gender':
                    $query->whereHas('lead', function ($q) use ($values) {
                        $q->whereIn('gender', $values);
                    });
                    break;
                case 'district':
                    $query->whereHas('lead', function ($q) use ($values) {
                        $q->whereIn('district_id', $values);
                    });
                    break;
                case 'inquiry':
                    $query->whereHas('lead', function ($q) use ($values) {
                        foreach ($values as $v) {
                            $q->orWhere('inquiry_for', 'LIKE', '%' . $v . '%');
                        }
                    });
                    break;
                case 'product_brand':
                    $query->whereHas('saleMasters.saleDetails.product.brand', function ($q) use ($values) {
                        $q->whereIn('brand_name', $values);
                    });
                    break;
                case 'product_category':
                    $query->whereHas('saleMasters.saleDetails.product.category', function ($q) use ($values) {
                        $q->whereIn('category_name', $values);
                    });
                    break;
                case 'warranty_days':
                    // Handled post-query (requires calculation)
                    break;
                case 'age':
                    $query->whereHas('lead', function ($q) use ($op, $value, $values) {
                        $this->applyNumericOp($q, 'age', $op, $value, $values);
                    });
                    break;
                case 'purchase_count':
                    $count = (int) ($value ?? 0);
                    if ($op === 'gte') {
                        $query->has('saleMasters', '>=', $count);
                    } elseif ($op === 'lte') {
                        $query->has('saleMasters', '<=', $count);
                    } elseif ($op === 'eq') {
                        $query->has('saleMasters', '=', $count);
                    }
                    break;
            }
        }

        $results = $query->get();

        // Post-query filter for warranty
        foreach ($filters as $filter) {
            if (($filter['field'] ?? '') === 'warranty_days') {
                $maxDays = (int) ($filter['value'] ?? 30);
                $results = $results->filter(function ($customer) use ($maxDays) {
                    return $this->hasWarrantyWithinDays($customer, $maxDays);
                });
            }
        }

        return $results->values();
    }

    /**
     * Doctors, referrers (quack / agent), employees and branch managers.
     * Filters: group (doctor|quack|agent|employee|branch), role (employee role ids), person ("doctor:ID" / "user:ID" picks).
     * Groups and picked people are added together; with neither, nobody is selected. Inactive people are left out.
     */
    public function filterTeam(array $filters): Collection
    {
        $groups = [];
        $roles = [];
        $picked = [];
        foreach ($filters as $filter) {
            $values = (array) ($filter['values'] ?? []);
            match ($filter['field'] ?? '') {
                'group' => $groups = array_merge($groups, $values),
                'role' => $roles = array_merge($roles, $values),
                'person' => $picked = array_merge($picked, $values),
                default => null,
            };
        }

        $people = collect();

        $doctorTypes = array_values(array_intersect($groups, ['doctor', 'quack', 'agent']));
        $doctorIds = collect($picked)->filter(fn ($p) => str_starts_with($p, 'doctor:'))->map(fn ($p) => (int) substr($p, 7))->all();
        if ($doctorTypes || $doctorIds) {
            \App\Models\Doctor::query()->where('status', 1)
                ->where(function ($q) use ($doctorTypes, $doctorIds) {
                    if ($doctorTypes) {
                        $q->whereIn('referrer_type', $doctorTypes);
                    }
                    if ($doctorIds) {
                        $q->orWhereIn('id', $doctorIds);
                    }
                })
                ->orderBy('name')->get()
                ->each(function ($d) use (&$people) {
                    $type = ($d->referrer_type ?? 'doctor') === 'doctor' ? 'doctor' : 'referrer';
                    $people->push(new SmsPerson($d->id, (string) $d->name, $d->contact_number ?: null, $type, $d->referrer_type_label ?? ucfirst((string) $d->referrer_type)));
                });
        }

        $userIds = collect($picked)->filter(fn ($p) => str_starts_with($p, 'user:'))->map(fn ($p) => (int) substr($p, 5))->all();
        $wantEmployees = in_array('employee', $groups, true);
        $wantBranches = in_array('branch', $groups, true);
        if ($wantEmployees || $wantBranches || $userIds) {
            \App\Models\User::query()->where('status', 1)
                ->where(function ($q) use ($wantEmployees, $wantBranches, $roles, $userIds) {
                    if ($wantEmployees) {
                        $q->orWhere(function ($e) use ($roles) {
                            $e->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'vendor'));
                            if ($roles) {
                                $e->whereHas('roles', fn ($r) => $r->whereIn('roles.id', $roles));
                            }
                        });
                    }
                    if ($wantBranches) {
                        $q->orWhere('type', 'vendor');
                    }
                    if ($userIds) {
                        $q->orWhereIn('id', $userIds);
                    }
                })
                ->orderBy('name')->get()
                ->each(function ($u) use (&$people) {
                    $people->push(new SmsPerson($u->id, (string) $u->name, $u->mobile_no ?: null, $u->type === 'vendor' ? 'branch' : 'employee', $u->type === 'vendor' ? 'Branch' : 'Employee'));
                });
        }

        return $people->values();
    }

    /**
     * Get summary stats for a filtered collection.
     */
    public function getSummaryStats(Collection $records, string $type): array
    {

        $total = $records->count();
        $validPhones = 0;
        $missingPhones = 0;

        foreach ($records as $record) {
            $phone = in_array($type, ['leads', 'team'], true)
                ? $record->phone_number
                : ($record->lead->phone_number ?? null);

            if (!empty($phone)) {
                $validPhones++;
            } else {
                $missingPhones++;
            }
        }

        return [
            'total' => $total,
            'valid_phones' => $validPhones,
            'missing_phones' => $missingPhones,
            'ready_to_send' => $validPhones,
        ];
    }

    /**
     * Get quick filter counts for leads.
     */
    public function getLeadQuickFilterCounts(): array
    {
        return [
            ['id' => 'new', 'label' => 'New Leads', 'count' => Lead::where('lead_status', \App\Enums\LeadStatus::NEW->value)->count()],
            ['id' => 'appointment', 'label' => 'Appointment Scheduled', 'count' => Lead::whereNotIn('lead_status', ['won', 'lost'])
                ->whereIn('id', \App\Models\Appointment::whereNotNull('lead_id')->where('appointment_date', '>=', now()->startOfDay())->select('lead_id'))
                ->count()],
            ['id' => 'trial', 'label' => 'Trial Without Order', 'count' => Lead::where('lead_status', \App\Enums\LeadStatus::TRIAL->value)->count()],
            ['id' => 'lost', 'label' => 'Lost Leads', 'count' => Lead::where('lead_status', \App\Enums\LeadStatus::LOST->value)->count()],
        ];
    }

    /**
     * Get quick filter counts for customers.
     */
    public function getCustomerQuickFilterCounts(): array
    {
        $threshold = Carbon::now()->addDays(30)->toDateString();

        return [
            ['id' => 'warranty_30', 'label' => 'Warranty ≤ 30 Days', 'count' => 0], // calculated post-query
            ['id' => 'has_appointment', 'label' => 'Has Appointment This Week', 'count' => Customer::whereHas('appointments', function ($q) {
                $q->whereBetween('appointment_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            })->count()],
            ['id' => 'has_membership', 'label' => 'Has Membership', 'count' => Customer::whereNotNull('membership_tier_id')->count()],
        ];
    }

    /**
     * Apply a numeric comparison operator to a query.
     */
    protected function applyNumericOp($query, string $column, string $op, $value, array $values = [], bool $isDaysAgo = false): void
    {
        if ($isDaysAgo) {
            // Convert days to date comparison on created_at
            $value = (int) ($value ?? 0);
            $date = Carbon::now()->subDays($value);
            switch ($op) {
                case 'lte': $query->where($column, '>=', $date); break; // created <= X days ago means created_at >= date
                case 'gte': $query->where($column, '<=', $date); break;
                case 'between':
                    $min = (int) ($values[0] ?? 0);
                    $max = (int) ($values[1] ?? 0);
                    $query->whereBetween($column, [Carbon::now()->subDays($max), Carbon::now()->subDays($min)]);
                    break;
            }
        } else {
            switch ($op) {
                case 'eq': $query->where($column, $value); break;
                case 'gte': $query->where($column, '>=', $value); break;
                case 'lte': $query->where($column, '<=', $value); break;
                case 'between':
                    $query->whereBetween($column, [(int) ($values[0] ?? 0), (int) ($values[1] ?? 0)]);
                    break;
            }
        }
    }

    /**
     * Check if customer has products with warranty within N days.
     */
    protected function hasWarrantyWithinDays(Customer $customer, int $days): bool
    {
        $now = Carbon::now();
        $threshold = $now->copy()->addDays($days);

        foreach ($customer->saleMasters as $sale) {
            foreach ($sale->saleDetails as $detail) {
                $warrantyMonths = $detail->product->warranty_sales_months ?? null;
                if ($warrantyMonths && $sale->date) {
                    $expiry = $sale->date->copy()->addMonths($warrantyMonths);
                    if ($expiry >= $now && $expiry <= $threshold) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
