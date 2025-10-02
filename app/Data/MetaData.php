<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class MetaData extends Data
{

    public function __construct(
        public ?array $headers = null, // The request headers
        public ?array $filters = null, // The request filters that will be used for filtering in the database
        public ?string $ip = null, // The user's IP address
        public ?string $search = null, // The search query/filter/key that will be used for searching in the database
        public ?array $relations = null, // The database relations that will be used for a database query
        public ?array $columns = null, // The database columns that will be used for a database query
        public ?string $groupBy = null, // The database column name that will be used for grouping in a database query
        public ?string $sortField = null, // The field name that will be used for sorting
        public ?string $sortDirection = null, // The sort direction that will be used for sorting
        public ?int $page = null, // The current requested page that will be used for pagination
        public ?int $perPage = null, // The current requested per page or limit that will be used for pagination
        public ?int $offset = null, // The current requested offset that will be used for pagination
    )
    {
    }

}
