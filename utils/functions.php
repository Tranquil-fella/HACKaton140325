<?php
/**
 * Common utility functions for the application
 */

/**
 * Get a human-readable error message for file upload errors
 *
 * @param int $errorCode The error code from $_FILES['file']['error']
 * @return string Human-readable error message
 */
function getFileUploadError($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Размер файла превышает допустимый размер, указанный в php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Размер файла превышает допустимый размер, указанный в форме';
        case UPLOAD_ERR_PARTIAL:
            return 'Файл был загружен только частично';
        case UPLOAD_ERR_NO_FILE:
            return 'Файл не был загружен';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Отсутствует временная папка';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Не удалось записать файл на диск';
        case UPLOAD_ERR_EXTENSION:
            return 'Загрузка файла остановлена расширением PHP';
        default:
            return 'Неизвестная ошибка при загрузке файла';
    }
}

/**
 * Save analysis results to a CSV file
 *
 * @param array $products Array of analyzed products
 * @param string $filePath Path where the CSV file should be saved
 * @return bool True on success, false on failure
 */
function saveResultsToCsv($products, $filePath) {
    try {
        $fp = fopen($filePath, 'w');
        if (!$fp) {
            throw new Exception('Не удалось создать файл результатов');
        }
        
        // Add UTF-8 BOM for proper encoding in Excel
        fputs($fp, "\xEF\xBB\xBF");
        
        // Write header row
        fputcsv($fp, [
            'Название товара',
            'Категория',
            'SEO-оптимизация (1-10)',
            'Полнота информации (1-10)',
            'Читабельность (1-10)',
            'Рекомендации',
            'Описание'
        ]);
        
        // Write data rows
        foreach ($products as $product) {
            if (isset($product['error'])) {
                fputcsv($fp, [
                    $product['name'],
                    $product['category'],
                    'Ошибка',
                    'Ошибка',
                    'Ошибка',
                    $product['error'],
                    $product['description']
                ]);
            } else {
                fputcsv($fp, [
                    $product['name'],
                    $product['category'],
                    $product['seo_score'],
                    $product['completeness_score'],
                    $product['readability_score'],
                    implode("\n", $product['recommendations']),
                    $product['description']
                ]);
            }
        }
        
        fclose($fp);
        return true;
    } catch (Exception $e) {
        error_log('Error saving CSV: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create a ZIP archive with product images
 *
 * @param array $products Array of analyzed products
 * @param string $zipPath Path where the ZIP file should be saved
 * @return bool True on success, false on failure
 */
function createImagesZip($products, $zipPath) {
    try {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Не удалось создать архив');
        }
        
        // Add images to the ZIP archive
        foreach ($products as $product) {
            if (isset($product['image_path']) && file_exists($product['image_path'])) {
                // Sanitize filename for safe storage
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $product['name']);
                $safeName = substr($safeName, 0, 50); // Limit filename length
                
                // Add the image to the ZIP with a sanitized name
                $zip->addFile($product['image_path'], $safeName . '.png');
            }
        }
        
        $zip->close();
        return true;
    } catch (Exception $e) {
        error_log('Error creating ZIP: ' . $e->getMessage());
        return false;
    }
}

/**
 * Detect CSV file encoding and return content in UTF-8
 *
 * @param string $filePath Path to the CSV file
 * @return string File content in UTF-8 encoding
 */
function getFileContentWithCorrectEncoding($filePath) {
    $content = file_get_contents($filePath);
    
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
function detectCsvDelimiter($content) {
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
