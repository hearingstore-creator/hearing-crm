<?php
namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

trait HelperTrait
{
    use SoftDeletes, HasActivityLog;

     /**
     * Automatically merge fillable attributes for user tracking
     */
    public function initializeHelperTrait()
    {
        // Ensure fillable includes these tracking fields
        $this->fillable = array_unique(array_merge($this->fillable, [
            'created_by',
            'updated_by',
            'deleted_by',
        ]));
    }
    

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function bootHelperTrait()
    {
        
        static::creating(function ($model) {
            if (Auth::user()) {
               $model->created_by = Auth::User()->id;
            }
           
        });

        static::updating(function ($model) {
            if (Auth::user()) {
                $model->updated_by = Auth::User()->id;
             }
        });
    }

    /**
     * Run the soft delete operation on the model.
     *
     * @return void
     */
    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->timestamps && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }
        if($user = Auth::user()){
            $this->deleted_by = Auth::User()->id;
            $columns['deleted_by'] = Auth::User()->id;
        }
        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->select('id', 'name');
    }
}
