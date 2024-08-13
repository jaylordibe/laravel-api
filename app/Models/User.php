<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Model properties
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property-read string|null $full_name
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $timezone
 * @property string|null $phone_number
 * @property Carbon|null $birthday
 * @property string|null $profile_photo_url
 *
 * Model relationships
 * @property-read Collection|Role[]|null $roles
 * @property-read Collection|Permission[]|null $permissions
 * @property-read Collection|Address[]|null $addresses
 *
 * @mixin Builder
 */
class User extends Authenticatable
{

    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = DatabaseTableConstant::USERS;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name'
    ];

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
     * Get the user's full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value, array $attributes) => "{$attributes['first_name']} {$attributes['last_name']}"
        );
    }

    /**
     * The addresses of this user.
     *
     * @return HasMany
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

}
