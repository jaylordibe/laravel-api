<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 *
 * Model properties.
 * @property-read User|null $createdByUser
 * @property-read User|null $updatedByUser
 * @property-read User|null $deletedByUser
 */
class BaseModel extends Model
{

    use SoftDeletes, HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (Auth::check()) {
                if (!$model->isDirty('created_by')) {
                    $model->created_by = Auth::id();
                }

                if (!$model->isDirty('updated_by')) {
                    $model->updated_by = Auth::id();
                }
            }
        });

        static::updating(function (self $model) {
            if (Auth::check() && !$model->isDirty('updated_by')) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleted(function (self $model) {
            if (Auth::check()) {
                $model->newQuery()
                    ->withTrashed()
                    ->where($model->getKeyName(), $model->getKey())
                    ->update(['deleted_by' => Auth::id()]);
            }
        });
    }

    /**
     * Get the user who created this model.
     *
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated this model.
     *
     * @return BelongsTo
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this model.
     *
     * @return BelongsTo
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

}
