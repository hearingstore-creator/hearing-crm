<?php

namespace App\Models;

use App\Traits\HelperTrait;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HelperTrait;

    protected $fillable = [
        'lead_id',
        'customer_id',
        'deal_id',
        'sale_master_id',
        'service_detail_id',
        'sale_invoice_no',
        'branch_id',
        'user_id',
        'resource_id',
        'service_id',
        'professional_time_slot_id',
        'appointment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_time_unknown',
        'status',
        'is_overbooked',
        'overbook_reason',
        'needs_reschedule',
        'source',
        'notes',
        'advance_amount',
        'customer_advance_id',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'advance_amount'   => 'float',
        'duration_minutes' => 'integer',
        'is_time_unknown'  => 'boolean',
        'is_overbooked'    => 'boolean',
        'needs_reschedule' => 'boolean',
        'status'           => AppointmentStatus::class,
    ];

    public function branch()
    {
        return $this->belongsTo(User::class, 'branch_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function histories()
    {
        return $this->hasMany(AppointmentHistory::class, 'appointment_id');
    }

    /**
     * Relations
     */

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service()
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public function professionalTimeSlot()
    {
        return $this->belongsTo(ProfessionalTimeSlot::class, 'professional_time_slot_id');
    }

    public function customerAdvance()
    {
        return $this->belongsTo(CustomerAdvance::class, 'customer_advance_id');
    }

    public function hasActiveAdvance(): bool
    {
        return $this->customerAdvance && $this->customerAdvance->status === CustomerAdvance::STATUS_ACTIVE && $this->customerAdvance->balance > 0;
    }

    /** Set when the appointment was booked from a deal (Deals Board drawer). */
    public function deal()
    {
        return $this->belongsTo(Deal::class)->withoutGlobalScopes();
    }

    /** Set when the appointment is booked for a service in a sale. */
    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    /** The specific sold service line item this appointment fulfills. */
    public function serviceDetail()
    {
        return $this->belongsTo(ServiceDetail::class, 'service_detail_id');
    }

    /** "Appointment Booked" SMS (text managed in SMS Center → Automation). Customers get it on their customer record. */
    public function notifyBooked(): void
    {
        $person = null;
        $recipientType = 'lead';
        $recipientId = (int) $this->lead_id;

        if ($this->customer_id) {
            $customer = Customer::withoutGlobalScopes()->with('lead')->find($this->customer_id);
            if ($customer && $customer->lead) {
                $person = $customer->lead;
                $recipientType = 'customer';
                $recipientId = (int) $customer->id;
            }
        } elseif ($this->lead_id) {
            $person = Lead::find($this->lead_id);
        }

        if (!$person?->phone_number) {
            return;
        }

        $timeStr = '';
        if ($this->start_time) {
            $timeStr = date('g:i A', strtotime($this->start_time));
            if ($this->end_time) {
                $timeStr .= ' - ' . date('g:i A', strtotime($this->end_time));
            }
        } elseif ($this->professional_time_slot_id) {
            $slot = ProfessionalTimeSlot::with('timeSlot')->find($this->professional_time_slot_id);
            $timeStr = $slot?->timeSlot ? $slot->timeSlot->start_time->format('g:i A') . ' - ' . $slot->timeSlot->end_time->format('g:i A') : '';
        }

        $branchName = $this->branch?->company_name ?? $this->branch?->name ?? 'Universal Hearing Center';
        $date = date('d M Y', strtotime($this->appointment_date));

        app(\App\Services\SmsAutomationService::class)->fire(\App\Enums\SmsAutomationTrigger::APPOINTMENT_BOOKED, $person->phone_number, $recipientType, $recipientId, [
            'name'             => $person->full_name,
            'customer_name'    => $person->full_name,
            'service'          => $this->service->name ?? 'N/A',
            'professional'     => $this->user->name ?? 'N/A',
            'date'             => $date,
            'appointment_date' => $date,
            'time'             => $timeStr,
            'branch'           => $branchName,
            'branch_name'      => $branchName,
        ], 'appt-booked:' . $this->id);
    }

    /** "Appointment Rescheduled" SMS */
    public function notifyRescheduled(): void
    {
        $person = null;
        $recipientType = 'lead';
        $recipientId = (int) $this->lead_id;

        if ($this->customer_id) {
            $customer = Customer::withoutGlobalScopes()->with('lead')->find($this->customer_id);
            if ($customer && $customer->lead) {
                $person = $customer->lead;
                $recipientType = 'customer';
                $recipientId = (int) $customer->id;
            }
        } elseif ($this->lead_id) {
            $person = Lead::find($this->lead_id);
        }

        if (!$person?->phone_number) {
            return;
        }

        $timeStr = '';
        if ($this->start_time) {
            $timeStr = date('g:i A', strtotime($this->start_time));
            if ($this->end_time) {
                $timeStr .= ' - ' . date('g:i A', strtotime($this->end_time));
            }
        } elseif ($this->professional_time_slot_id) {
            $slot = ProfessionalTimeSlot::with('timeSlot')->find($this->professional_time_slot_id);
            $timeStr = $slot?->timeSlot ? $slot->timeSlot->start_time->format('g:i A') . ' - ' . $slot->timeSlot->end_time->format('g:i A') : '';
        }

        $branchName = $this->branch?->company_name ?? $this->branch?->name ?? 'Universal Hearing Center';
        $date = date('d M Y', strtotime($this->appointment_date));

        app(\App\Services\SmsAutomationService::class)->fire(\App\Enums\SmsAutomationTrigger::APPOINTMENT_RESCHEDULED, $person->phone_number, $recipientType, $recipientId, [
            'name'             => $person->full_name,
            'customer_name'    => $person->full_name,
            'service'          => $this->service->name ?? 'N/A',
            'professional'     => $this->user->name ?? 'N/A',
            'date'             => $date,
            'appointment_date' => $date,
            'time'             => $timeStr,
            'branch'           => $branchName,
            'branch_name'      => $branchName,
        ], 'appt-resched:' . $this->id . ':' . time());
    }

    /**
     * Automatically mark any linked service line items as Done when this appointment completes.
     */
    public function syncCompletedService(): void
    {
        $deliveredBy = $this->user_id ?: auth()->id();

        // 1. If explicit service_detail_id is linked
        if ($this->service_detail_id) {
            $sd = ServiceDetail::find($this->service_detail_id);
            if ($sd && $sd->fulfillment_status !== ServiceDetail::STATUS_DONE) {
                $sd->fulfillment_status = ServiceDetail::STATUS_DONE;
                $sd->done_by = $deliveredBy;
                $sd->done_at = now();
                $sd->save();
            }
        }

        // 2. Also check any service details pointing to this appointment_id
        $linked = ServiceDetail::where('appointment_id', $this->id)
            ->where('fulfillment_status', '!=', ServiceDetail::STATUS_DONE)
            ->get();

        foreach ($linked as $sd) {
            $sd->fulfillment_status = ServiceDetail::STATUS_DONE;
            $sd->done_by = $deliveredBy;
            $sd->done_at = now();
            $sd->save();
        }
    }
}
