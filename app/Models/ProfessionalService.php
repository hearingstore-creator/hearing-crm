<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProfessionalService extends Pivot
{
    protected $table = 'professional_services';
    
    protected $fillable = [
        'user_id',
        'appointment_service_id',
    ];
}
