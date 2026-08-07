<?php

namespace App\Services;

use ZipArchive;

class ExcelImportService
{
    /**
     * Process and extract data from CSV or XLSX file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    public function getData($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->parseCSV($file),
            'xlsx'       => $this->parseXLSX($file),
            default      => throw new \Exception("Unsupported file type: {$extension}"),
        };
    }

    /**
     * Parse CSV file to associative array
     */
    private function parseCSV($file): array
    {
        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $header = null;
            
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    // First row is header
                    $header = array_map(fn($v) => trim($v), $data);
                } else {
                    // Data rows - normalize values
                    $data = array_map(fn($v) => $this->normalizeValue($v), $data);
                    
                    // Pad row to match header count (fill missing columns with null)
                    $data = array_pad($data, count($header), null);
                    
                    // Combine header with data
                    $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Parse XLSX file to associative array
     */
    private function parseXLSX($file): array
    {
        $zip = new ZipArchive;
        $rows = [];

        if ($zip->open($file->getRealPath()) === true) {
            // Read shared strings
            $sharedStrings = [];
            $xml = $zip->getFromName('xl/sharedStrings.xml');
            if ($xml) {
                $xmlObj = simplexml_load_string($xml);
                foreach ($xmlObj->si as $item) {
                    $sharedStrings[] = (string)$item->t;
                }
            }

            // Read first worksheet
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml) {
                $xmlObj = simplexml_load_string($sheetXml);
                
                foreach ($xmlObj->sheetData->row as $rowXml) {
                    $cells = [];
                    $currentColIndex = 0;

                    foreach ($rowXml->c as $c) {
                        // Get column reference (e.g., "B2")
                        $cellRef = (string)$c['r'];
                        $colLetter = preg_replace('/[0-9]/', '', $cellRef);
                        $colIndex = $this->columnLetterToIndex($colLetter);

                        // Fill empty columns before this cell with null
                        while ($currentColIndex < $colIndex) {
                            $cells[] = null;
                            $currentColIndex++;
                        }

                        // Get cell value
                        $val = (string)$c->v;
                        if (isset($c['t']) && $c['t'] == 's') {
                            $val = $sharedStrings[(int)$val] ?? $val;
                        }
                        
                        $cells[] = $val;
                        $currentColIndex++;
                    }

                    $rows[] = $cells;
                }
            }
            $zip->close();
        }

        if (empty($rows)) {
            return [];
        }

        // First row is header
        $headerRow = array_shift($rows);
        $header = array_map(
            fn($v) => str_replace(' ', '_', strtolower(trim($v))), 
            $headerRow
        );
        
        $formatted = [];
        foreach ($rows as $row) {
            // Normalize values (trim and convert empty to null)
            $row = array_map(fn($v) => $this->normalizeValue($v), $row);
            
            // Pad row to match header count (fill missing columns with null)
            $row = array_pad($row, count($header), null);
            
            // Combine header with data
            $formatted[] = array_combine($header, $row);
        }

        return $formatted;
    }

    /**
     * Convert Excel column letter to 0-based index
     * e.g., A -> 0, B -> 1, Z -> 25, AA -> 26
     */
    private function columnLetterToIndex($letter): int
    {
        $letter = strtoupper($letter);
        $length = strlen($letter);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $index *= 26;
            $index += ord($letter[$i]) - 65 + 1;
        }

        return $index - 1; // Return 0-based index
    }

    /**
     * Normalize cell values:
     * - Trim whitespace
     * - Convert empty strings to null
     */
    private function normalizeValue($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        return $value === '' ? null : $value;
    }
}