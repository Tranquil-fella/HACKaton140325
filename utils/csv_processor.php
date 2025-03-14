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
        $delimiter = $this->detectCsvDelimiter($content);
        
        // Parse CSV
        $rows = $this->parseCsvContent($content, $delimiter);
        
        if (count($rows) < 2) { // At least header row and one data row
            throw new Exception('Файл не содержит данных или имеет неверный формат');
        }
        
        // Extract header row
        $header = array_shift($rows);
        
        // Find column indexes
        $nameIndex = array_search($nameColumn, $header);
        $categoryIndex = array_search($categoryColumn, $header);
        $descriptionIndex = array_search($descriptionColumn, $header);
        
        if ($nameIndex === false) {
            throw new Exception('Колонка "' . $nameColumn . '" не найдена в файле');
        }
        
        if ($categoryIndex === false) {
            throw new Exception('Колонка "' . $categoryColumn . '" не найдена в файле');
        }
        
        if ($descriptionIndex === false) {
            throw new Exception('Колонка "' . $descriptionColumn . '" не найдена в файле');
        }
        
        // Extract products data
        $products = [];
        foreach ($rows as $row) {
            if (count($row) <= max($nameIndex, $categoryIndex, $descriptionIndex)) {
                // Skip rows with insufficient columns
                continue;
            }
            
            $name = trim($row[$nameIndex]);
            $category = trim($row[$categoryIndex]);
            $description = trim($row[$descriptionIndex]);
            
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
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // Try to detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R', 'ISO-8859-5'], true);
        
        // Convert to UTF-8 if needed
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        return $content;
    }
    
    /**
     * Detect CSV delimiter
     *
     * @param string $content CSV file content
     * @return string Detected delimiter
     */
    private function detectCsvDelimiter($content) {
        $delimiters = [',', ';', "\t", '|'];
        $results = [];
        
        // Get first line of the file
        $firstLine = strtok($content, "\n");
        
        // Count occurrences of each delimiter
        foreach ($delimiters as $delimiter) {
            $results[$delimiter] = substr_count($firstLine, $delimiter);
        }
        
        // Return the delimiter with the most occurrences
        $maxDelimiter = ','; // Default delimiter
        $maxCount = 0;
        
        foreach ($results as $delimiter => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $maxDelimiter = $delimiter;
            }
        }
        
        return $maxDelimiter;
    }
    
    /**
     * Parse CSV content
     *
     * @param string $content CSV content
     * @param string $delimiter CSV delimiter
     * @return array Parsed CSV rows
     */
    private function parseCsvContent($content, $delimiter) {
        $rows = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Handle quoted fields with embedded delimiters
            $pattern = '/(' . preg_quote($delimiter, '/') . '|^)(")(.*?)(")/s';
            $line = preg_replace_callback($pattern, function($matches) use ($delimiter) {
                // Replace delimiters inside quotes with a placeholder
                $field = str_replace($delimiter, '##DELIMITER##', $matches[3]);
                // Replace newlines with placeholder
                $field = str_replace(["\r\n", "\n", "\r"], '##NEWLINE##', $field);
                return $matches[1] . $field;
            }, $line);
            
            // Split by delimiter
            $fields = explode($delimiter, $line);
            
            // Restore placeholders
            foreach ($fields as &$field) {
                $field = str_replace('##DELIMITER##', $delimiter, $field);
                $field = str_replace('##NEWLINE##', "\n", $field);
                // Remove quotes if present
                if (strlen($field) >= 2 && $field[0] === '"' && $field[strlen($field) - 1] === '"') {
                    $field = substr($field, 1, -1);
                }
                // Handle double quotes (replace "" with ")
                $field = str_replace('""', '"', $field);
            }
            
            $rows[] = $fields;
        }
        
        return $rows;
    }
}
