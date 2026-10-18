<?php

namespace App\Traits;

use App\Classes\Helper;

/**
 * Records that belong to a branch (vendor_id, NULL = head office).
 * New rows take the creator's branch; a user held to a branch (branch login or staff with a home
 * branch) only sees their branch's rows. Head office users who may view all branches see everything.
 */
trait BranchOwned
{
    public static function bootBranchOwned(): void
    {
        static::creating(function ($model) {
            if (!array_key_exists('vendor_id', $model->getAttributes()) && auth()->check()) {
                $model->vendor_id = auth()->user()->branchId();
            }
        });

        static::addGlobalScope('branch_owned', function ($builder) {
            if ($branchId = Helper::reportBranchId()) {
                $builder->where($builder->getModel()->getTable() . '.vendor_id', $branchId);
            }
        });
    }
}
