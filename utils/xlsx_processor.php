<?php
/**
 * XLSX file processor
 */
class XLSXProcessor {
    /**
     * Parse an XLSX file and extract products data
     *
     * @param string $filePath Path to the XLSX file
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
        
        // Check if required libraries are available
        if (!class_exists('ZipArchive')) {
            throw new Exception('Для обработки XLSX файлов требуется расширение ZipArchive');
        }
        
        if (!class_exists('XMLReader')) {
            throw new Exception('Для обработки XLSX файлов требуется расширение XMLReader');
        }
        
        try {
            // Extract shared strings and worksheet data
            $sharedStrings = $this->extractSharedStrings($filePath);
            $worksheetData = $this->extractWorksheetData($filePath, $sharedStrings);
            
            if (empty($worksheetData)) {
                throw new Exception('Не удалось извлечь данные из XLSX файла');
            }
            
            // Get header row
            $header = $worksheetData[0];
            
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
            
            // Extract products data, starting from row 1 (skip header)
            $products = [];
            for ($i = 1; $i < count($worksheetData); $i++) {
                $row = $worksheetData[$i];
                
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
            
        } catch (Exception $e) {
            throw new Exception('Ошибка при обработке XLSX файла: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract shared strings from XLSX file
     *
     * @param string $filePath Path to the XLSX file
     * @return array Array of shared strings
     * @throws Exception If extraction fails
     */
    private function extractSharedStrings($filePath) {
        $sharedStrings = [];
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Не удалось открыть XLSX файл');
        }
        
        // Check if shared strings file exists
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            $zip->close();
            return $sharedStrings; // Empty array - file might not have shared strings
        }
        
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        
        if (!$xmlString) {
            return $sharedStrings;
        }
        
        $xml = new XMLReader();
        $xml->XML($xmlString);
        
        $currentValue = '';
        
        while ($xml->read()) {
            if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 't') {
                $xml->read();
                if ($xml->nodeType == XMLReader::TEXT) {
                    $currentValue .= $xml->value;
                }
            } else if ($xml->nodeType == XMLReader::END_ELEMENT && $xml->name === 'si') {
                $sharedStrings[] = $currentValue;
                $currentValue = '';
            }
        }
        
        $xml->close();
        
        return $sharedStrings;
    }
    
    /**
     * Extract worksheet data from XLSX file
     *
     * @param string $filePath Path to the XLSX file
     * @param array $sharedStrings Array of shared strings
     * @return array Two-dimensional array of worksheet data
     * @throws Exception If extraction fails
     */
    private function extractWorksheetData($filePath, $sharedStrings) {
        $worksheetData = [];
        
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Не удалось открыть XLSX файл');
        }
        
        // Find the first worksheet
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $worksheetPath = 'xl/worksheets/sheet1.xml'; // Default to first sheet
        
        if ($workbookXml) {
            // Parse workbook to find the first sheet
            $xml = new XMLReader();
            $xml->XML($workbookXml);
            
            while ($xml->read()) {
                if ($xml->nodeType == XMLReader::ELEMENT && $xml->name === 'sheet') {
                    $sheetId = $xml->getAttribute('sheetId');
                    if ($sheetId) {
                        $worksheetPath = 'xl/worksheets/sheet' . $sheetId . '.xml';
                        break;
                    }
                }
            }
            
            $xml->close();
        }
        
        // Get worksheet XML
        $worksheetXml = $zip->getFromName($worksheetPath);
        $zip->close();
        
        if (!$worksheetXml) {
            throw new Exception('Не удалось найти лист в XLSX файле');
        }
        
        // Parse worksheet
        $xml = new XMLReader();
        $xml->XML($worksheetXml);
        
        $currentRow = -1;
        $currentCol = -1;
        $currentValue = '';
        
        while ($xml->read()) {
            if ($xml->nodeType == XMLReader::ELEMENT) {
                if ($xml->name === 'row') {
                    $currentRow++;
                    $worksheetData[$currentRow] = [];
                    $currentCol = -1;
                } else if ($xml->name === 'c') {
                    $currentCol++;
                    
                    // Get column reference
                    $ref = $xml->getAttribute('r');
                    if ($ref) {
                        // Extract column index from reference (e.g., A1 -> 0, B1 -> 1)
                        preg_match('/([A-Z]+)\d+/', $ref, $matches);
                        if (isset($matches[1])) {
                            $colRef = $matches[1];
                            $colIndex = 0;
                            for ($i = 0; $i < strlen($colRef); $i++) {
                                $colIndex = $colIndex * 26 + (ord($colRef[$i]) - ord('A') + 1);
                            }
                            $currentCol = $colIndex - 1;
                        }
                    }
                    
                    // Get cell type and value
                    $type = $xml->getAttribute('t');
                    $currentValue = '';
                } else if ($xml->name === 'v') {
                    $xml->read();
                    if ($xml->nodeType == XMLReader::TEXT) {
                        $value = $xml->value;
                        
                        // If cell type is shared string, get value from shared strings
                        if ($type === 's' && isset($sharedStrings[(int)$value])) {
                            $currentValue = $sharedStrings[(int)$value];
                        } else {
                            $currentValue = $value;
                        }
                        
                        // Add value to worksheet data
                        $worksheetData[$currentRow][$currentCol] = $currentValue;
                    }
                }
            }
        }
        
        $xml->close();
        
        return $worksheetData;
    }
}
