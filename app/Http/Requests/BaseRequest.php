<?php

namespace App\Http\Requests;

use App\Constants\AppConstant;
use App\Dtos\MetaDto;
use App\Models\User;
use App\Utils\ResponseUtil;
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
        return is_null($data) ? $default : (array) $data;
    }

    /**
     * Transform input value to array from a comma separated string.
     *
     * @param string $key
     * @param array|null $default
     *
     * @return array|null
     */
    public function getInputAsArrayFromCommaSeparatedString(string $key, ?array $default = null): ?array
    {
        $value = $this->input($key);

        if (empty($value)) {
            return $default;
        }

        return explode(',', (string) $value);
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
        $dateTime = null;

        try {
            $dateString = $this->getInputAsString($key);

            if (!empty($dateString)) {
                $dateTime = Carbon::parse($dateString);
            }
        } catch (InvalidFormatException $e) {
            Log::error("Failed to parse input as carbon datetime: {$e->getMessage()}");
        }

        return $dateTime;
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
     * @return int
     */
    public function getPageLimit(): int
    {
        $limit = $this->getInputAsInt('limit') ?: AppConstant::DEFAULT_PAGE_LIMIT;
        return $limit > AppConstant::MAX_PAGE_LIMIT ? AppConstant::DEFAULT_PAGE_LIMIT : $limit;
    }

    /**
     * For pagination. Get the requested page offset.
     *
     * @return int
     */
    public function getPageOffset(): int
    {
        $offset = $this->getInputAsInt('offset');
        return $offset ?: ($this->getPage() - 1) * $this->getPageLimit();
    }

    /**
     * Get request meta.
     *
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
     *
     * @return User
     */
    public function getAuthUser(): User
    {
        return Auth::user();
    }

}
