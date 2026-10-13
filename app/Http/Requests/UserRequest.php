<?php

namespace App\Http\Requests;

use App\Data\UserData;
use App\Data\UserFilterData;
use App\Enums\Gender;
use Illuminate\Validation\Rule;

class UserRequest extends BaseRequest
{

    /**
     * {@inheritDoc}
     */
    protected const array SORTABLE_FIELDS = ['id', 'created_at', 'updated_at', 'first_name', 'last_name', 'username', 'email'];

    /**
     * Get the validation rules that apply to the request.
     *
     * One rule per column UserRepository::save() writes, and nothing else: identity, verification,
     * ownership and access-control fields are not read from this request at all. Input is trimmed and
     * blank strings become null before these rules run (Laravel's global input middleware), so
     * `required` rejects whitespace-only values. String limits match the `varchar(255)` columns;
     * address is `text`, capped so one profile cannot hold an unbounded value.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'middleName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phoneNumber' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::enum(Gender::class)],
            'birthdate' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'address' => ['nullable', 'string', 'max:1000']
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Convert request to data.
     *
     * Username, email, verification and profile image have their own endpoints, so they are never
     * taken from this request; the repository does not write them on update either.
     *
     * @return UserData
     */
    public function toData(): UserData
    {
        return new UserData(
            firstName: $this->string('firstName'),
            middleName: $this->filled('middleName') ? $this->string('middleName') : null,
            lastName: $this->string('lastName'),
            username: '',
            email: '',
            emailVerifiedAt: null,
            phoneNumber: $this->string('phoneNumber'),
            gender: $this->enum('gender', Gender::class),
            birthdate: $this->date('birthdate'),
            timezone: $this->filled('timezone') ? $this->string('timezone') : null,
            profileImage: null,
            address: $this->filled('address') ? $this->string('address') : null,
            id: $this->route('userId'),
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

    /**
     * Convert request to filter data.
     *
     * @return UserFilterData
     */
    public function toFilterData(): UserFilterData
    {
        return new UserFilterData(
            id: $this->has('id') ? $this->integer('id') : null,
            meta: $this->getMetaData(),
            authUser: $this->getAuthUserData()
        );
    }

}
