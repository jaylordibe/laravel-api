<?php

namespace App\Constants;

class AppConstant
{

    const int DEFAULT_PAGE = 1;
    const int DEFAULT_PER_PAGE = 10;
    const int MAX_PER_PAGE = 1000;
    const string DEFAULT_SORT_FIELD = 'created_at';
    const string DEFAULT_SORT_DIRECTION = 'asc';
    const int DEFAULT_NUMBER_OF_RECORDS_PER_SHEET = 10000;
    const string DEFAULT_DATE_FORMAT = 'Y-m-d';
    const string DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const int DEFAULT_SCALE = 3;

}
