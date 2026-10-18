<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HelperTrait;

class Task extends Model
{
    use HasFactory, HelperTrait, \App\Traits\BranchOwned;

    protected $fillable = [
        'subject',
        'description',
        'vendor_id',
        'customer_id',
        'assigned_to',
        'status',
        'priority',
        'due_date',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'due_date' => 'date',
    ];

    /**
     * Get the customer that owns the task.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, TaskStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeByPriority($query, TaskPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to filter by assigned user.
     */
    public function scopeByAssignedUser($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to get overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED]);
    }

    /**
     * Check if task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast()
        && !in_array($this->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true);
    }
}
