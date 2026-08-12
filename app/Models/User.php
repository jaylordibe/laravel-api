<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $phone_number
 * @property Gender|null $gender
 * @property Carbon|null $birthdate
 * @property string|null $timezone
 * @property string|null $profile_image
 * @property string|null $address
 *
 * Appended properties
 * @property string $full_name
 *
 * Model relationships
 * @property-read Collection<Role>|null $roles
 * @property-read Collection<Permission>|null $permissions
 *
 * @mixin Builder
 */
class User extends Authenticatable implements MustVerifyEmail
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
            'gender' => Gender::class,
            'birthdate' => 'datetime'
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
                    ->update([
                        'username' => $model->username . '_deleted_' . now()->format('YmdHis'),
                        'email' => $model->email . '_deleted_' . now()->format('YmdHis'),
                        'deleted_by' => Auth::id()
                    ]);
            }
        });
    }

    /**
     * Get the user's full name.
     */
    /**
     * Normalise the email to lower case on write.
     *
     * PostgreSQL compares `varchar` case-SENSITIVELY, and both the `users_email_unique`
     * index and every `where('email', ?)` lookup rely on that comparison. Under
     * MySQL's `utf8mb4_unicode_ci` collation the case difference was absorbed for
     * free; on PostgreSQL it is not, so without this the same address registered
     * as `Jay@Example.com` and `jay@example.com` produces TWO accounts that the
     * unique index happily accepts, and a sign-in typed in the wrong case fails
     * with "Invalid username or password".
     *
     * Normalising on write means the stored value is canonical, so the unique
     * index enforces real uniqueness. Callers must lower-case the value they look
     * up with — see UserRepository and AuthController.
     *
     * @return Attribute
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : Str::lower(trim($value))
        );
    }

    /**
     * Normalise the username to lower case on write, for the same reason as email.
     *
     * @return Attribute
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : Str::lower(trim($value))
        );
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $fullName = $this->first_name;

                if (!empty($this->middle_name)) {
                    $middleNameInitial = strtoupper(Str::substr($this->middle_name, 0, 1));
                    $fullName .= " {$middleNameInitial}. ";
                }

                if (!empty($this->last_name)) {
                    $fullName .= " {$this->last_name}";
                }

                return $fullName;
            }
        );
    }

}
