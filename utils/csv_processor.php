<?php
/**
 * CSV file processor
 */
class CSVProcessor {
    /**
     * Parse a CSV file and extract products data
     *
     * @param string $filePath Path to the CSV file
     * @param string $nameColumn Name of the column containing product names
     * @param string $categoryColumn Name of the column containing product categories
     * @param string $descriptionColumn Name of the column containing product descriptions
     * @return array Array of products with name, category, and description
     * @throws Exception If parsing fails
     */
    public function parseFile($filePath, $nameColumn, $categoryColumn, $descriptionColumn) {
        if (!file_exists($filePath)) {
            throw new Exception('Файл не найден: ' . $filePath);
        }

        // Get file content with correct encoding
        $content = $this->getFileContentWithCorrectEncoding($filePath);

        // Detect delimiter
        $delimiter = detectCsvDelimiter($content);

        // Parse CSV
        $rows = array_map(function($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, explode("\n", $content));

        if (empty($rows)) {
            throw new Exception('Файл пуст');
        }

        // Get header row
        $header = array_shift($rows);

        // Find required columns
        $nameIndex = array_search($nameColumn, $header);
        $categoryIndex = array_search($categoryColumn, $header);
        $descriptionIndex = array_search($descriptionColumn, $header);

        if ($nameIndex === false || $descriptionIndex === false) {
            throw new Exception('Не найдены обязательные колонки в файле');
        }

        if ($categoryIndex === false) {
            error_log('Колонка категории не найдена в файле. Категория будет пустой.');
        }

        // Extract products data
        $products = [];
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0])) continue;

            // Check if we have at least the name and description columns
            if (count($row) <= max($nameIndex, $descriptionIndex)) {
                continue;
            }

            $name = trim($row[$nameIndex]);
            $description = trim($row[$descriptionIndex]);

            // Handle the case when category column is missing
            $category = $categoryIndex !== false && isset($row[$categoryIndex]) ? 
                        trim($row[$categoryIndex]) : 
                        'Не указано';

            // Skip rows with empty required fields
            if (empty($name) || empty($description)) {
                continue;
            }

            $products[] = [
                'name' => $name,
                'category' => $category,
                'description' => $description
            ];
        }

        return $products;
    }

    /**
     * Get file content with correct encoding
     *
     * @param string $filePath Path to the CSV file
     * @return string File content in UTF-8 encoding
     */
    private function getFileContentWithCorrectEncoding($filePath) {
        $content = file_get_contents($filePath);

        // Remove BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Try to detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-5'], true);

        // Convert to UTF-8 if needed
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }
}