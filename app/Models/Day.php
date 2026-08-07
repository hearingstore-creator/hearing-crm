<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    protected $fillable = ['day'];

    public function label(): string
    {
        return $this->day;
    }

    public function shortLabel(): string
    {
        return substr($this->day, 0, 3);
    }

    public function isWeekend(): bool
    {
        return in_array($this->day, ['Saturday', 'Sunday']);
    }

    /**
     * Get Day model from a Carbon date.
     */
    public static function fromCarbon(\Carbon\Carbon $date): self
    {
        return self::where('day', $date->englishDayOfWeek)->firstOrFail();
    }
}
