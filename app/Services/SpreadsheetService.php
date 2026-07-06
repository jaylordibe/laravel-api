<?php

namespace App\Services;

use App\Enums\SpreadsheetReaderType;
use App\Exceptions\ProcessingException;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class SpreadsheetService
{

    /**
     * Read the raw file and clean the data.
     *
     * @param SpreadsheetReaderType $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return array
     * @throws ProcessingException
     */
    public function readRawFileAndCleanData(SpreadsheetReaderType $readerType, string $filePath, int $sheetIndex = 0): array
    {
        // Remove empty rows - checking the first 3 columns for empty values
        // Re-index the array after removing empty rows
        return $this->readRawFileAsArray($readerType, $filePath, $sheetIndex)
                |> (fn($x) => array_filter($x, fn(array $row) => !empty($row[1]) && !empty($row[2])))
                |> array_values(...);
    }

    /**
     * Read the raw file as an array.
     *
     * @param SpreadsheetReaderType $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return array
     * @throws ProcessingException
     */
    public function readRawFileAsArray(SpreadsheetReaderType $readerType, string $filePath, int $sheetIndex = 0): array
    {
        $rows = $this->readRawFileAsWorkSheet($readerType, $filePath, $sheetIndex)->toArray();

        if (empty($rows)) {
            Log::warning("No data found in the file: {$filePath}");

            throw new ProcessingException('No data found in the file.');
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
     * @param SpreadsheetReaderType $readerType
     * @param string $filePath - the relative path of the file under the storage directory (storage/app/public)
     * @param int $sheetIndex
     *
     * @return Worksheet
     * @throws ProcessingException
     */
    public function readRawFileAsWorkSheet(SpreadsheetReaderType $readerType, string $filePath, int $sheetIndex = 0): Worksheet
    {
        if (!file_exists($filePath)) {
            Log::error("File not found: {$filePath}");
            throw new ProcessingException('File not found');
        }

        try {
            $reader = SpreadsheetReaderType::getReader($readerType);
            $reader->setReadEmptyCells(false);
            $reader->setIgnoreRowsWithNoCells(true);
            $reader->setReadDataOnly(true);
            $inputSpreadsheet = $reader->load($filePath);

            return $inputSpreadsheet->getSheet($sheetIndex);
        } catch (Throwable $e) {
            // PhpSpreadsheet's load() throws its own exceptions on a corrupt/unreadable
            // file; convert any failure to the uniform envelope and keep the raw cause
            // server-side rather than leaking it to the client.
            Log::error("Failed to read spreadsheet file: {$filePath}", ['exception' => $e]);

            throw new ProcessingException('Unable to read the uploaded spreadsheet file.');
        }
    }

}
