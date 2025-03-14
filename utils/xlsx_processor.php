<?php
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

        if (!class_exists('DOMDocument')) {
            throw new Exception('Для обработки XLSX файлов требуется расширение DOMDocument');
        }

        try {
            // Extract shared strings and worksheet data
            $sharedStrings = $this->extractSharedStrings($filePath);
            $worksheetData = $this->extractWorksheetData($filePath, $sharedStrings);

            if (empty($worksheetData)) {
                throw new Exception('Не удалось извлечь данные из файла');
            }

            // Get header row
            $header = array_shift($worksheetData);

            // Find required columns
            $nameIndex = array_search($nameColumn, $header);
            $categoryIndex = array_search($categoryColumn, $header);
            $descriptionIndex = array_search($descriptionColumn, $header);

            if ($nameIndex === false || $descriptionIndex === false) {
                throw new Exception('Не найдены обязательные колонки в файле');
            }

            // Process rows
            $products = [];
            foreach ($worksheetData as $row) {
                if (empty($row[$nameIndex])) continue;

                $name = trim($row[$nameIndex]);
                $description = trim($row[$descriptionIndex]);
                $category = $categoryIndex !== false && isset($row[$categoryIndex]) ? 
                            trim($row[$categoryIndex]) : 
                            'Не указано';

                if (empty($name) || empty($description)) continue;

                $products[] = [
                    'name' => $name,
                    'category' => $category,
                    'description' => $description
                ];
            }

            return $products;

        } catch (Exception $e) {
            throw new Exception('Ошибка обработки XLSX файла: ' . $e->getMessage());
        }
    }

    /**
     * Extract shared strings from XLSX file
     *
     * @param string $filePath Path to XLSX file
     * @return array Array of shared strings
     */
    private function extractSharedStrings($filePath) {
        $zip = new ZipArchive();
        $strings = [];

        if ($zip->open($filePath) === true) {
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $dom = new DOMDocument();
                $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $nodes = $dom->getElementsByTagName('t');

                foreach ($nodes as $node) {
                    $strings[] = $node->nodeValue;
                }
            }
            $zip->close();
        }

        return $strings;
    }

    /**
     * Extract worksheet data from XLSX file
     *
     * @param string $filePath Path to XLSX file
     * @param array $sharedStrings Shared strings array
     * @return array Array of worksheet rows
     */
    private function extractWorksheetData($filePath, $sharedStrings) {
        $zip = new ZipArchive();
        $data = [];

        if ($zip->open($filePath) === true) {
            if (($xml = $zip->getFromName('xl/worksheets/sheet1.xml')) !== false) {
                $dom = new DOMDocument();
                $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $rows = $dom->getElementsByTagName('row');

                foreach ($rows as $row) {
                    $rowData = [];
                    $cells = $row->getElementsByTagName('c');

                    foreach ($cells as $cell) {
                        $value = '';
                        if ($cell->getElementsByTagName('v')->length > 0) {
                            $v = $cell->getElementsByTagName('v')->item(0)->nodeValue;
                            if ($cell->getAttribute('t') === 's') {
                                $value = $sharedStrings[$v];
                            } else {
                                $value = $v;
                            }
                        }
                        $rowData[] = $value;
                    }

                    $data[] = $rowData;
                }
            }
            $zip->close();
        }

        return $data;
    }
}