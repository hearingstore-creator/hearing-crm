<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
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
        'priority_id',
        'resolved_at',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'resolved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
