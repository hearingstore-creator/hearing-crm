<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What happened when a sequence step ran. */
class SequenceRun extends Model
{
    protected $fillable = ['enrollment_id', 'step_id', 'channel', 'status', 'detail'];
}
