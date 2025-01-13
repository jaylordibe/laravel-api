<?php

namespace App\Constants;

class AppConstant
{

    const int DEFAULT_PAGE = 1;
    const int DEFAULT_PER_PAGE = 10;
    const int MAX_PER_PAGE = 100;
    const int MAX_PER_PAGE_GET_ALL = 1000;
    const string DEFAULT_DB_QUERY_SORT_FIELD = 'created_at';
    const string DEFAULT_DB_QUERY_SORT_DIRECTION = 'asc';
    const int DEFAULT_NUMBER_OF_RECORDS_PER_SHEET = 10000;

}
