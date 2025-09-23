<?php

namespace App\Http\Requests;

use App\Data\MetaData;
use App\Data\UserData;
use App\Models\User;
use App\Utils\ResponseUtil;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            $data = $this->string($key);

            if (is_null($data)) {
                return $default;
            }

            return BigDecimal::of($data);
        } catch (DivisionByZeroException|NumberFormatException|RoundingNecessaryException $exception) {
            Log::error('Failed to parse input as BigDecimal: ' . $exception->getMessage());

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

        if ($perPage === -1) {
            return $maxPerPage;
        }

        return $perPage > $maxPerPage ? 10 : $perPage;
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
     * Parses the 'relations' input and constructs an array of Eloquent relations with selected columns.
     *
     * This method processes a relations payload, which can be a string (pipe-separated) or an array,
     * and transforms it into an array compatible with Laravel's `with()` method for eager loading.
     * Each relation can specify columns to select, and the method ensures that columns are prefixed
     * with their respective table names to avoid ambiguous column errors in the resulting SQL query.
     * If a relation specifies columns, the related model's primary key is automatically included.
     *
     * Example input (string): "relation1:id,name|relation2:id,description"
     * Example input (array): ["relation1:id,name", "relation2:id,description"]
     * Example output: [
     *     'relation1' => Closure(Relation) { $query->addSelect(['table1.id', 'table1.name']) },
     *     'relation2' => Closure(Relation) { $query->addSelect(['table2.id', 'table2.description']) }
     * ]
     *
     * @return array An array of relation names or relation closures for use with Eloquent's `with()` method.
     */
    public function getRelations(): array
    {
        $data = $this->input('relations');
        $relations = [];

        if (empty($data)) {
            return $relations;
        }

        $relationItems = [];

        // Parse the input relations
        if (is_string($data)) {
            $relationItems = str_contains($data, '|') ? explode('|', $data) : [$data];
        } elseif (is_array($data)) {
            $relationItems = $data;
        }

        foreach ($relationItems as $relationItem) {
            if (str_contains($relationItem, ':')) {
                // Split relation name and columns
                $relationKey = Str::before($relationItem, ':');
                $columnsString = Str::after($relationItem, ':');

                if (empty($columnsString)) {
                    $relations[] = $relationKey;
                    continue;
                }

                // Parse columns into an array
                $columns = str_contains($columnsString, ',') ? explode(',', $columnsString) : [$columnsString];

                // Define the relation with a closure
                $relations[$relationKey] = function (Relation $queryBuilder) use ($columns) {
                    $relatedModel = $queryBuilder->getRelated();
                    $tableName = $relatedModel->getTable();
                    $primaryKey = $relatedModel->getKeyName();

                    // Ensure the primary key is included
                    if (!in_array($primaryKey, $columns)) {
                        $columns[] = $primaryKey;
                    }

                    // Prefix each column with the table name to avoid ambiguity
                    $qualifiedColumns = array_map(function (string $column) use ($tableName) {
                        return "{$tableName}.{$column}";
                    }, $columns);

                    $queryBuilder->addSelect($qualifiedColumns);
                };
            } else {
                // If no columns specified, just add the relation
                $relations[] = $relationItem;
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
                $columns = explode('|', $data);
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
            search: $this->string('search'),
            relations: $this->getRelations(),
            columns: $this->getColumns() ?? ['*'],
            groupBy: $this->string('groupBy'),
            sortField: $this->string('sortField'),
            sortDirection: $this->string('sortDirection', 'asc'),
            page: $this->getPage(),
            perPage: $this->getPerPage(),
            offset: $this->getPageOffset()
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
            profileImage: $authUser->profile_image,
            roles: $authUser->getRoleNames()->toArray(),
            permissions: $authUser->getAllPermissions()->pluck('name')->toArray(),
            id: $authUser->id,
            createdAt: $authUser->created_at
        );
    }

}
