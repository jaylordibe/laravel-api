<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class MetaData extends Data
{

    public function __construct(
        public string $searchQuery = '', // The search query/filter/key that will be used for searching in the database
        public array $relations = [], // The database relations that will be used for a database query
        public array $columns = [], // The database columns that will be used for a database query
        public string $groupBy = '', // The database column name that will be used for grouping in a database query
        public string $sortField = '', // The field name that will be used for sorting
        public string $sortDirection = '', // The sort direction that will be used for sorting
        public int $page = 0, // The current requested page that will be used for pagination
        public int $limit = 0, // The current requested limit that will be used for pagination
        public int $offset = 0, // The current requested offset that will be used for pagination
        public bool $exact = false // Boolean value that will be used for getting either exact or related rows in the database
    )
    {
    }

}
