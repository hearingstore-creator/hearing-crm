<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A public holiday: the call centre is closed, so lead call deadlines move to the next working day. */
class OfficeHoliday extends Model
{
    protected $fillable = ['date', 'name', 'created_by'];

    protected $casts = ['date' => 'date'];
}
