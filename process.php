<?php
session_start();
require_once 'utils/functions.php';
require_once 'utils/api_client.php';
require_once 'utils/csv_processor.php';
require_once 'utils/xlsx_processor.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Неверный метод запроса';
    header('Location: index.php');
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = getFileUploadError($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
    $_SESSION['error'] = 'Ошибка загрузки файла: ' . $error;
    header('Location: index.php');
    exit;
}

// Use standard column names
$nameColumn = 'Название товара';
$categoryColumn = 'Категория';
$descriptionColumn = 'Описание';

// Get file info
$file = $_FILES['file'];
$filename = $file['name'];
$tempPath = $file['tmp_name'];
$fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Check file extension
if (!in_array($fileExt, ['csv', 'xlsx'])) {
    $_SESSION['error'] = 'Поддерживаются только файлы форматов CSV и XLSX';
    header('Location: index.php');
    exit;
}

// Process the file
try {
    // Create uploads directory if it doesn't exist
    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Create temp directory for images
    $imagesDir = __DIR__ . '/uploads/images';
    if (!is_dir($imagesDir)) {
        mkdir($imagesDir, 0755, true);
    }

    // Clean up old files if they exist
    if (isset($_SESSION['savedFilePath']) && file_exists($_SESSION['savedFilePath'])) {
        unlink($_SESSION['savedFilePath']);
    }

    // Save the uploaded file
    $savedFilePath = $uploadsDir . '/' . uniqid() . '_' . $filename;
    if (!move_uploaded_file($tempPath, $savedFilePath)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Clear previous session data
    unset($_SESSION['products']);
    unset($_SESSION['results']);

    // Parse the file content
    $products = [];
    if ($fileExt === 'csv') {
        $processor = new CSVProcessor();
        $products = $processor->parseFile($savedFilePath, $nameColumn, $categoryColumn, $descriptionColumn);
    } else { // xlsx
        $processor = new XLSXProcessor();
        $products = $processor->parseFile($savedFilePath, $nameColumn, $categoryColumn, $descriptionColumn);
    }

    if (empty($products)) {
        throw new Exception('Не удалось найти данные в файле или указанные колонки отсутствуют');
    }

    // Save products to session for processing
    $_SESSION['products'] = $products;
    $_SESSION['savedFilePath'] = $savedFilePath;

    // Redirect to results page for processing
    header('Location: results.php');
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = 'Ошибка обработки файла: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}