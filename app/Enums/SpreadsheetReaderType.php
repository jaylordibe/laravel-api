<?php

namespace App\Enums;

use App\Traits\EnumTrait;
use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

enum SpreadsheetReaderType: string
{

    use EnumTrait;

    case XLS = 'xls';
    case XLSX = 'xlsx';

    /**
     * Get the reader based on the type.
     *
     * @param SpreadsheetReaderType $type
     *
     * @return Xls|Xlsx
     * @throws Exception
     */
    public static function getReader(self $type): Xls|Xlsx
    {
        return match ($type) {
            self::XLS => new Xls(),
            self::XLSX => new Xlsx(),
            default => throw new Exception('Invalid reader type.')
        };
    }

}
