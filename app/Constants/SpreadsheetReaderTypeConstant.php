<?php

namespace App\Constants;

use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class SpreadsheetReaderTypeConstant extends BaseConstant
{

    const string XLS = 'xls';
    const string XLSX = 'xlsx';

    /**
     * Get the reader based on the type.
     *
     * @param string $type
     *
     * @return Xls|Xlsx
     * @throws Exception
     */
    public static function getReader(string $type): Xls|Xlsx
    {
        return match ($type) {
            self::XLS => new Xls(),
            self::XLSX => new Xlsx(),
            default => throw new Exception('Invalid reader type.')
        };
    }

}
