<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property string $first_name
 * @property ?string $middle_name
 * @property string $last_name
 * @property string $email
 * @property ?Carbon $email_verified_at
 * @property string $password
 * @property ?string $remember_token
 * @property string $role
 * @property ?string $timezone
 */
class User extends Authenticatable
{

    use HasApiTokens, HasFactory, Notifiable;

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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

}
