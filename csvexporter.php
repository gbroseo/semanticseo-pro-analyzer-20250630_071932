public static function exportCsv(array $data, string $filename = 'export.csv', string $delimiter = ',', string $enclosure = '"', string $escape = '\\'): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Headers already sent, cannot export CSV.');
        }

        // Prepare filename with RFC5987 encoding for non-ASCII characters
        $basename     = basename($filename);
        $fallbackName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $basename);

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$fallbackName}\"; filename*=UTF-8''" . rawurlencode($basename));

        // Output UTF-8 BOM for proper encoding in Excel and other programs
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new \RuntimeException('Failed to open output stream.');
        }

        if (empty($data)) {
            fclose($output);
            return;
        }

        $firstRow = $data[0];
        if (is_array($firstRow) && self::isAssoc($firstRow)) {
            $headers = array_keys($firstRow);
            fputcsv($output, $headers, $delimiter, $enclosure, $escape);
            foreach ($data as $row) {
                fputcsv($output, self::normalizeRow($row, $headers), $delimiter, $enclosure, $escape);
            }
        } else {
            foreach ($data as $row) {
                fputcsv($output, $row, $delimiter, $enclosure, $escape);
            }
        }

        fclose($output);
    }

    /**
     * Imports a CSV file into an array.
     *
     * @param string $filePath  Path to the CSV file.
     * @param bool   $hasHeader Whether the CSV has a header row.
     * @param string $delimiter Field delimiter (one character).
     * @param string $enclosure Field enclosure character.
     * @param string $escape    Escape character.
     *
     * @return array Parsed CSV data.
     *
     * @throws \InvalidArgumentException If the file is not readable.
     * @throws \RuntimeException         If the file cannot be opened or a row cannot be combined with headers.
     */
    public static function importCsv(string $filePath, bool $hasHeader = true, string $delimiter = ',', string $enclosure = '"', string $escape = '\\'): array
    {
        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("File {$filePath} not found or is not readable.");
        }

        $data   = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: {$filePath}");
        }

        $lineNumber = 1;
        if ($hasHeader) {
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            if ($headers === false) {
                fclose($handle);
                return [];
            }
            // Strip UTF-8 BOM from the first header if present
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $lineNumber = 2;
        }

        while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            if ($hasHeader) {
                $countHeaders = count($headers);
                $countRow     = count($row);

                if ($countRow !== $countHeaders) {
                    error_log(sprintf(
                        'CSV import warning: row %d has %d columns, expected %d in file %s',
                        $lineNumber,
                        $countRow,
                        $countHeaders,
                        $filePath
                    ));
                    if ($countRow < $countHeaders) {
                        $row = array_pad($row, $countHeaders, '');
                    } else {
                        $row = array_slice($row, 0, $countHeaders);
                    }
                }

                $combined = array_combine($headers, $row);
                if ($combined === false) {
                    fclose($handle);
                    throw new \RuntimeException(
                        "Failed to combine header and row at line {$lineNumber} in file {$filePath}."
                    );
                }

                $data[] = $combined;
            } else {
                $data[] = $row;
            }
            $lineNumber++;
        }

        fclose($handle);
        return $data;
    }

    /**
     * Determines if an array is associative.
     *
     * @param array $arr
     * @return bool
     */
    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Normalizes a row to ensure it matches the header order.
     *
     * @param array $row
     * @param array $headers
     * @return array
     */
    private static function normalizeRow(array $row, array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            $normalized[] = $row[$header] ?? '';
        }
        return $normalized;
    }
}