<?php

namespace App\Http\Requests;

use App\Data\MetaData;
use App\Data\UserData;
use App\Exceptions\BadRequestException;
use App\Models\User;
use App\Utils\ResponseUtil;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BaseRequest extends FormRequest
{

    /**
     * Relations a client may eager-load through the 'relations' input. Empty by default: a request
     * opts in to each relation its Resource renders.
     *
     * @var array<int, string>
     */
    protected const array ALLOWED_RELATIONS = [];

    /**
     * Columns a client may sort by through the 'sortField' input. Never list a hidden column.
     *
     * @var array<int, string>
     */
    protected const array SORTABLE_FIELDS = [self::DEFAULT_SORT_FIELD];

    /**
     * The sort field when the client names none. Fixed: changing it reorders every list response.
     *
     * @var string
     */
    private const string DEFAULT_SORT_FIELD = 'created_at';

    /**
     * The sort direction when the client names none.
     *
     * @var string
     */
    private const string DEFAULT_SORT_DIRECTION = 'desc';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(ResponseUtil::error($validator->errors()->first()));
    }

    /**
     * Transform input value to brick/math BigDecimal.
     *
     * @param string $key
     * @param BigDecimal|null $default
     *
     * @return BigDecimal|null
     */
    public function bigDecimal(string $key, ?BigDecimal $default = null): ?BigDecimal
    {
        try {
            if (!$this->has($key)) {
                return $default;
            }

            $value = $this->string($key);

            if ($value->isEmpty()) {
                return $default;
            }

            return BigDecimal::of($this->string($key));
        } catch (DivisionByZeroException|NumberFormatException|RoundingNecessaryException $exception) {
            Log::error('Failed to parse input as BigDecimal: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Get array of ids from a string input separated by a given separator.
     *
     * @param string $key The input key to retrieve the string from.
     * @param string $separator The character that separates the ids in the string.
     * @param array|null $default The default value to return if the input is empty or not present.
     *
     * @return array|null An array of unique integer ids, or the default value if input is empty.
     */
    public function arrayIds(string $key, string $separator = ',', ?array $default = null): ?array
    {
        if (!$this->has($key)) {
            return $default;
        }

        return collect(explode($separator, $this->string($key)))
            ->map(fn(string $id) => trim($id))
            ->filter()
            ->unique()
            ->map(fn(string $id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * For pagination. Get the requested page number.
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->integer('page') ?: 1;
    }

    /**
     * For pagination. Get the requested page limit.
     *
     * @param int $maxPerPage
     *
     * @return int
     */
    public function getPerPage(int $maxPerPage = 1000): int
    {
        $perPage = $this->integer('perPage') ?: 10;

        // -1 is the documented "as many as allowed" sentinel.
        if ($perPage === -1) {
            return $maxPerPage;
        }

        // Anything else outside [1, max] falls back to the default. A negative page size must never
        // reach the query: the builder silently drops a negative LIMIT, so it would return the whole table.
        return $perPage < 1 || $perPage > $maxPerPage ? 10 : $perPage;
    }

    /**
     * For pagination. Get the requested page offset.
     *
     * @return int
     */
    public function getPageOffset(): int
    {
        $offset = $this->integer('offset');

        return $offset ?: ($this->getPage() - 1) * $this->getPerPage();
    }

    /**
     * Parse the 'relations' input into relation names for Eloquent's `with()`.
     *
     * Accepts a pipe-separated string ("causer|subject") or an array of names. Only exact names listed in
     * the request's ALLOWED_RELATIONS are accepted; anything else is rejected before it reaches Eloquent,
     * because Eloquent resolves an eager-load name by calling the model method of that name.
     *
     * @return array<int, string>
     * @throws BadRequestException
     */
    public function getRelations(): array
    {
        if (!$this->filled('relations')) {
            return [];
        }

        $data = $this->input('relations');
        $relationNames = is_string($data) ? explode('|', $data) : $data;

        if (!is_array($relationNames)) {
            throw new BadRequestException('The requested relation is not supported.');
        }

        foreach ($relationNames as $relationName) {
            if (!is_string($relationName) || !in_array($relationName, static::ALLOWED_RELATIONS, true)) {
                throw new BadRequestException('The requested relation is not supported.');
            }
        }

        return array_values(array_unique($relationNames));
    }

    /**
     * Get columns for database query.
     *
     * Client-selected columns are not supported: a column list is a raw SELECT expression, and a
     * partial record would make every Resource render missing fields as false values.
     *
     * @return array<int, string>
     * @throws BadRequestException
     */
    public function getColumns(): array
    {
        if ($this->filled('columns')) {
            throw new BadRequestException('Column selection is not supported.');
        }

        return ['*'];
    }

    /**
     * Get the field to sort by, limited to the request's SORTABLE_FIELDS.
     *
     * @return string
     * @throws BadRequestException
     */
    public function getSortField(): string
    {
        if (!$this->filled('sortField')) {
            return self::DEFAULT_SORT_FIELD;
        }

        $sortField = $this->input('sortField');

        if (!is_string($sortField) || !in_array($sortField, static::SORTABLE_FIELDS, true)) {
            throw new BadRequestException('The requested sort field is not supported.');
        }

        return $sortField;
    }

    /**
     * Get the sort direction: asc or desc.
     *
     * @return string
     * @throws BadRequestException
     */
    public function getSortDirection(): string
    {
        if (!$this->filled('sortDirection')) {
            return self::DEFAULT_SORT_DIRECTION;
        }

        $sortDirection = $this->input('sortDirection');
        $sortDirection = is_string($sortDirection) ? strtolower($sortDirection) : null;

        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            throw new BadRequestException('The sort direction must be asc or desc.');
        }

        return $sortDirection;
    }

    /**
     * Get request meta data.
     *
     * @return MetaData
     * @throws BadRequestException
     */
    public function getMetaData(): MetaData
    {
        return new MetaData(
            headers: $this->header(),
            filters: $this->array('filters'),
            ip: $this->ip(),
            search: $this->string('search'),
            relations: $this->getRelations(),
            columns: $this->getColumns(),
            groupBy: $this->string('groupBy'),
            sortField: $this->getSortField(),
            sortDirection: $this->getSortDirection(),
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            offset: $this->getPageOffset()
        );
    }

    /**
     * Get auth user data.
     *
     * @return UserData|null
     */
    public function getAuthUserData(): ?UserData
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (empty($authUser)) {
            return null;
        }

        return new UserData(
            firstName: $authUser->first_name,
            middleName: $authUser->middle_name,
            lastName: $authUser->last_name,
            username: $authUser->username,
            email: $authUser->email,
            emailVerifiedAt: $authUser->email_verified_at,
            phoneNumber: $authUser->phone_number,
            gender: $authUser->gender,
            birthdate: $authUser->birthdate,
            timezone: $authUser->timezone,
            profileImage: $authUser->profile_image,
            address: $authUser->address,
            roles: $authUser->getRoleNames()->toArray(),
            permissions: $authUser->getAllPermissions()->pluck('name')->toArray(),
            id: $authUser->id,
            createdAt: $authUser->created_at,
            updatedAt: $authUser->updated_at
        );
    }

}
