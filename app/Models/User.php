<?php

namespace App\Models;

use App\Dtos\UserDto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

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
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'email_verified_at',
        'birthday'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [];

    /**
     * Get the model id.
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->id;
    }

    /**
     * Checks if the model is empty.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->getId());
    }

    /**
     * Checks if the model is present.
     * @return bool
     */
    public function isPresent(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Transform model to dto.
     *
     * @return UserDto
     */
    public function toDto(): UserDto
    {
        $userDto = new UserDto();
        $userDto->setId($this->id);
        $userDto->setCreatedAt($this->created_at);
        $userDto->setUpdatedAt($this->updated_at);
        $userDto->setDeletedAt($this->deleted_at);
        $userDto->setCreatedBy($this->created_by);
        $userDto->setUpdatedBy($this->updated_by);
        $userDto->setDeletedBy($this->deleted_by);
        $userDto->setFirstName($this->first_name);
        $userDto->setMiddleName($this->middle_name);
        $userDto->setLastName($this->last_name);
        $userDto->setEmail($this->email);
        $userDto->setUsername($this->username);
        $userDto->setRole($this->role);
        $userDto->setPhoneNumber($this->phone_number);
        $userDto->setAddress($this->address);
        $userDto->setBirthday($this->birthday);
        $userDto->setProfileImage($this->profile_image);
        $userDto->setTimezone($this->timezone);

        return $userDto;
    }

    /**
     * Get the user who created this user.
     *
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated this user.
     *
     * @return BelongsTo
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this user.
     *
     * @return BelongsTo
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
