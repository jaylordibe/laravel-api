<?php

namespace App\Services;

use App\Constants\SpreadsheetReaderTypeConstant;
use Exception;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetService
{

    /**
     * Read the raw file and clean the data.
     *
     * @param string $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return array
     * @throws Exception
     */
    public function readRawFileAndCleanData(string $readerType, string $filePath, int $sheetIndex = 0): array
    {
        $rows = $this->readRawFileAsArray($readerType, $filePath, $sheetIndex);
        // Remove empty rows - checking the first 3 columns for empty values
        $rows = array_filter($rows, fn(array $row) => !empty($row[1]) && !empty($row[2]));
        // Re-index the array after removing empty rows
        $rows = array_values($rows);

        return $rows;
    }

    /**
     * Read the raw file as array.
     *
     * @param string $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return array
     * @throws Exception
     */
    public function readRawFileAsArray(string $readerType, string $filePath, int $sheetIndex = 0): array
    {
        $this->readRawFileAsWorkSheet($readerType, $filePath, $sheetIndex)->toArray();

        if (empty($rows)) {
            Log::warning("No data found in the file: {$filePath}");

            throw new Exception('No data found in the file.');
        }

        Log::info('SpreadsheetService@readRawFile', [
            'readerType' => $readerType,
            'filePath' => $filePath,
            'rowCount' => count($rows)
        ]);

        return $rows;
    }

    /**
     * Read the raw file as worksheet.
     *
     * @param string $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return Worksheet
     * @throws Exception
     */
    public function readRawFileAsWorkSheet(string $readerType, string $filePath, int $sheetIndex = 0): Worksheet
    {
        if (!file_exists($filePath)) {
            Log::error("File not found: {$filePath}");
            throw new Exception('File not found');
        }

        $reader = SpreadsheetReaderTypeConstant::getReader($readerType);
        $reader->setReadEmptyCells(false);
        $reader->setIgnoreRowsWithNoCells(true);
        $reader->setReadDataOnly(true);
        $inputSpreadsheet = $reader->load($filePath);

        return $inputSpreadsheet->getSheet($sheetIndex);
    }

}
