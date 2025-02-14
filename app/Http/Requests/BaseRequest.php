<?php

namespace App\Http\Requests;

use App\Constants\AppConstant;
use App\Data\MetaData;
use App\Data\UserData;
use App\Models\User;
use App\Utils\ResponseUtil;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BaseRequest extends FormRequest
{

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
     * Transform input value to string.
     *
     * @param string $key
     * @param string|null $default
     *
     * @return string|null
     */
    public function getInputAsString(string $key, ?string $default = null): ?string
    {
        $data = $this->input($key);

        return is_null($data) ? $default : (string) $data;
    }

    /**
     * Transform input value to int.
     *
     * @param string $key
     * @param int|null $default
     *
     * @return int|null
     */
    public function getInputAsInt(string $key, ?int $default = null): ?int
    {
        $data = $this->input($key);

        return is_null($data) ? $default : (int) $data;
    }

    /**
     * Transform input value to float.
     *
     * @param string $key
     * @param float|null $default
     *
     * @return float|null
     */
    public function getInputAsFloat(string $key, ?float $default = null): ?float
    {
        $data = $this->input($key);

        return is_null($data) ? $default : (float) $data;
    }

    /**
     * Transform input value to boolean.
     *
     * @param string $key
     * @param bool|null $default
     *
     * @return bool|null
     */
    public function getInputAsBoolean(string $key, ?bool $default = null): ?bool
    {
        $data = $this->input($key);

        return is_null($data) ? $default : filter_var($data, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Transform input value to array.
     *
     * @param string $key
     * @param array|null $default
     *
     * @return array|null
     */
    public function getInputAsArray(string $key, ?array $default = null): ?array
    {
        $data = $this->input($key);
        $value = $default;

        if (!empty($data)) {
            if (is_string($data)) {
                $value = explode('|', $data);
            } elseif (is_array($data)) {
                $value = $data;
            }
        }

        return $value;
    }

    /**
     * Transform input value to url.
     *
     * @param string $key
     *
     * @return string
     */
    public function getInputAsUrl(string $key): string
    {
        return urldecode($this->getInputAsString($key));
    }

    /**
     * Transform input value to carbon datetime.
     *
     * @param string $key
     *
     * @return Carbon|null
     */
    public function getInputAsCarbon(string $key): ?Carbon
    {
        try {
            $data = $this->getInputAsString($key);

            if (is_null($data)) {
                return null;
            }

            return Carbon::parse($data);
        } catch (InvalidFormatException $e) {
            Log::error("Failed to parse input as carbon datetime: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Transform input value to brick/math BigInteger.
     *
     * @param string $key
     *
     * @return BigInteger|null
     */
    public function getInputAsBigInteger(string $key): ?BigInteger
    {
        try {
            $data = $this->getInputAsString($key);

            if (is_null($data)) {
                return null;
            }

            return BigInteger::of($data);
        } catch (MathException $e) {
            Log::error('Failed to parse input as BigInteger: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Transform input value to brick/math BigDecimal.
     *
     * @param string $key
     *
     * @return BigDecimal|null
     */
    public function getInputAsBigDecimal(string $key): ?BigDecimal
    {
        try {
            $data = $this->getInputAsString($key);

            if (is_null($data)) {
                return null;
            }

            return BigDecimal::of($data);
        } catch (MathException $e) {
            Log::error('Failed to parse input as BigDecimal: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * For pagination. Get the requested page number.
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->getInputAsInt('page') ?: AppConstant::DEFAULT_PAGE;
    }

    /**
     * For pagination. Get the requested page limit.
     *
     * @param int $maxPerPage
     *
     * @return int
     */
    public function getPerPage(int $maxPerPage = AppConstant::MAX_PER_PAGE): int
    {
        $perPage = $this->getInputAsInt('perPage') ?: AppConstant::DEFAULT_PER_PAGE;

        return $perPage > $maxPerPage ? AppConstant::DEFAULT_PER_PAGE : $perPage;
    }

    /**
     * For pagination. Get the requested page offset.
     *
     * @return int
     */
    public function getPageOffset(): int
    {
        $offset = $this->getInputAsInt('offset');

        return $offset ?: ($this->getPage() - 1) * $this->getPerPage();
    }

    /**
     * Get relations for database query.
     * @return array
     */
    public function getRelations(): array
    {
        $data = $this->input('relations');
        $relations = [];

        if (!empty($data)) {
            if (is_string($data)) {
                $relations = explode('|', $data);
            } elseif (is_array($data)) {
                $relations = $data;
            }
        }

        return $relations;
    }

    /**
     * Get columns for database query.
     * @return array
     */
    public function getColumns(): array
    {
        $data = $this->input('columns');
        $columns = [];

        if (!empty($data)) {
            if (is_string($data)) {
                $columns = explode(',', $data);
            } elseif (is_array($data)) {
                $columns = $data;
            }
        }

        return $columns;
    }

    /**
     * Get request meta data.
     * @return MetaData
     */
    public function getMetaData(): MetaData
    {
        return new MetaData(
            ip: $this->ip(),
            search: $this->getInputAsString('search'),
            relations: $this->getRelations(),
            columns: $this->getColumns() ?? ['*'],
            groupBy: $this->getInputAsString('groupBy'),
            sortField: $this->getInputAsString('sortField'),
            sortDirection: $this->getInputAsString('sortDirection'),
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            offset: $this->getPageOffset(),
            exact: $this->getInputAsBoolean('exact'),
            all: $this->getPerPage() < AppConstant::MAX_PER_PAGE ? $this->getInputAsBoolean('all') : null
        );
    }

    /**
     * Get auth user data.
     * @return UserData
     */
    public function getAuthUserData(): UserData
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        return new UserData(
            firstName: $authUser->first_name,
            lastName: $authUser->last_name,
            username: $authUser->username,
            email: $authUser->email,
            middleName: $authUser->middle_name,
            timezone: $authUser->timezone,
            phoneNumber: $authUser->phone_number,
            birthday: $authUser->birthday,
            profilePhotoUrl: $authUser->profile_photo_url,
            roles: $authUser->getRoleNames()->toArray(),
            permissions: $authUser->getAllPermissions()->pluck('name')->toArray(),
            id: $authUser->id,
            createdAt: $authUser->created_at
        );
    }

}
