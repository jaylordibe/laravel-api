<?php

namespace App\Models;

use App\Constants\DatabaseTableConstant;
use App\Enums\AppPlatform;
use App\Enums\DeviceOs;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model properties
 * @property int $user_id
 * @property string $token
 * @property AppPlatform $app_platform
 * @property DeviceType $device_type
 * @property DeviceOs $device_os
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
        return [
            'app_platform' => AppPlatform::class,
            'device_type' => DeviceType::class,
            'device_os' => DeviceOs::class
        ];
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
