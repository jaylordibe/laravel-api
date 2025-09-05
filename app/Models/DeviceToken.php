<?php

namespace App\Models;

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model properties
 * @property int $user_id
 * @property string $token
 * @property string $app_platform
 * @property string $device_type
 * @property string $device_os
 * @property string $device_os_version
 *
 * Model relationships
 * @property-read User|null $user
 */
class DeviceToken extends BaseModel
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = DatabaseTableConstant::DEVICE_TOKENS;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * The user that owns this address.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
