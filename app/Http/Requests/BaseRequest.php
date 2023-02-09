<?php

namespace App\Http\Requests;

use App\Constants\AppConstant;
use App\Constants\UserRoleConstant;
use App\Dtos\AuthUserDto;
use App\Dtos\MetaDto;
use App\Dtos\UserDto;
use App\Utils\ResponseUtil;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BaseRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     * @param Validator $validator
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(ResponseUtil::error($validator->errors()->first()));
    }

    /**
     * Transform input value to string.
     * @param string $key
     * @return string|null
     */
    public function getInputAsString(string $key): ?string
    {
        $data = $this->input($key);
        return is_null($data) ? null : (string) $data;
    }

    /**
     * Transform input value to int.
     * @param string $key
     * @return int|null
     */
    public function getInputAsInt(string $key): ?int
    {
        $data = $this->input($key);
        return is_null($data) ? null : (int) $data;
    }

    /**
     * Transform input value to float.
     * @param string $key
     * @return float|null
     */
    public function getInputAsFloat(string $key): ?float
    {
        $data = $this->input($key);
        return is_null($data) ? null : (float) $data;
    }

    /**
     * Transform input value to boolean.
     * @param string $key
     * @return bool|null
     */
    public function getInputAsBoolean(string $key): ?bool
    {
        $data = $this->input($key);
        return is_null($data) ? null : filter_var($data, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Transform input value to array.
     * @param string $key
     * @return array|null
     */
    public function getInputAsArray(string $key): ?array
    {
        $data = $this->input($key);
        return is_null($data) ? null : (array) $data;
    }

    /**
     * Transform input value to json object.
     * @param string $key
     * @return object|null
     */
    public function getInputAsJsonObject(string $key): ?object
    {
        $data = $this->input($key);
        return is_null($data) ? null : json_decode($data);
    }

    /**
     * Transform unix timestamp input to carbon datetime. Timestamp in seconds.
     * @param string $key
     * @return Carbon|null
     */
    public function getTimestampInputAsCarbon(string $key): ?Carbon
    {
        $inputTimestamp = $this->getInputAsInt($key);
        return is_null($inputTimestamp) ? null : Carbon::createFromTimestamp($inputTimestamp);
    }

    /**
     * Transform input value to carbon datetime.
     * @param string $key
     * @return Carbon|null
     */
    public function getInputAsCarbon(string $key): ?Carbon
    {
        $dateTime = null;

        try {
            $dateString = $this->getInputAsString($key);

            if (!empty($dateString)) {
                $dateTime = Carbon::parse($dateString);
            }
        } catch (InvalidFormatException $e) {
            Log::error('Failed to parse input as carbon datetime.');
        }

        return $dateTime;
    }

    /**
     * Transform input value to url.
     * @param string $key
     * @return string
     */
    public function getInputAsUrl(string $key): string
    {
        return urldecode($this->getInputAsString($key));
    }

    /**
     * For pagination. Get the requested page number.
     * @return int
     */
    public function getPage(): int
    {
        return $this->getInputAsInt('page') ?: AppConstant::DEFAULT_PAGE;
    }

    /**
     * For pagination. Get the requested page limit.
     * @return int
     */
    public function getPageLimit(): int
    {
        $limit = $this->getInputAsInt('limit') ?: AppConstant::DEFAULT_PAGE_LIMIT;
        return $limit > AppConstant::MAX_PAGE_LIMIT ? AppConstant::DEFAULT_PAGE_LIMIT : $limit;
    }

    /**
     * For pagination. Get the requested page offset.
     * @return int
     */
    public function getPageOffset(): int
    {
        $offset = $this->getInputAsInt('offset');
        return $offset ?: ($this->getPage() - 1) * $this->getPageLimit();
    }

    /**
     * Get request meta.
     * @return MetaDto
     */
    public function getMeta(): MetaDto
    {
        $metaDto = new MetaDto();
        $metaDto->setSearchQuery($this->getInputAsString('search'));
        $metaDto->setRelations($this->getInputAsArray('relations') ?: []);
        $metaDto->setSortField($this->getInputAsString('sortField') ?: AppConstant::DEFAULT_DB_QUERY_SORT_FIELD);
        $metaDto->setSortDirection($this->getInputAsString('sortDirection') ?: AppConstant::DEFAULT_DB_QUERY_SORT_DIRECTION);
        $metaDto->setPage($this->getPage());
        $metaDto->setLimit($this->getPageLimit());
        $metaDto->setOffset($this->getPageOffset());
        $metaDto->setRequestIp($this->ip());

        return $metaDto;
    }

    /**
     * Get authenticated user.
     * @return AuthUserDto
     */
    public function getAuthUser(): AuthUserDto
    {
        /** @var UserDto $userDto */
        $userDto = $this->user()->toDto();

        $authUserDto = new AuthUserDto();
        $authUserDto->setId($userDto->getId());
        $authUserDto->setFirstName($userDto->getFirstName());
        $authUserDto->setLastName($userDto->getLastName());
        $authUserDto->setEmail($userDto->getEmail());
        $authUserDto->setRole($userDto->getRole());

        return $authUserDto;
    }

    /**
     * Check if the authenticated user role is system admin.
     * @return bool
     */
    public function isSystemAdmin(): bool
    {
        return $this->getAuthUser()->getRole() === UserRoleConstant::SYSTEM_ADMIN;
    }

    /**
     * Check if the authenticated user role is admin.
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->getAuthUser()->getRole() === UserRoleConstant::ADMIN;
    }

    /**
     * Check if the authenticated user role is member.
     * @return bool
     */
    public function isMember(): bool
    {
        return $this->getAuthUser()->getRole() === UserRoleConstant::MEMBER;
    }
}
