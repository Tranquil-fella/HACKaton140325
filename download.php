<?php
session_start();
require_once 'utils/functions.php';

// Check if the type parameter is set
if (!isset($_GET['type'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Тип файла для скачивания не указан';
    exit;
}

$type = $_GET['type'];

// Handle CSV download
if ($type === 'csv') {
    if (!isset($_SESSION['results_csv_path']) || !file_exists($_SESSION['results_csv_path'])) {
        header('HTTP/1.1 404 Not Found');
        echo 'Файл результатов не найден. Пожалуйста, проанализируйте данные снова.';
        exit;
    }
    
    $filePath = $_SESSION['results_csv_path'];
    $fileName = 'results_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the file
    readfile($filePath);
    exit;
}

// Handle images ZIP download
if ($type === 'images') {
    if (!isset($_SESSION['images_zip_path']) || !file_exists($_SESSION['images_zip_path'])) {
        header('HTTP/1.1 404 Not Found');
        echo 'Архив с изображениями не найден. Пожалуйста, проанализируйте данные снова.';
        exit;
    }
    
    $filePath = $_SESSION['images_zip_path'];
    $fileName = 'product_images_' . date('Y-m-d_H-i-s') . '.zip';
    
    // Set headers for file download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output the file
    readfile($filePath);
    exit;
}

// If we get here, the type is not valid
header('HTTP/1.1 400 Bad Request');
echo 'Неверный тип файла для скачивания';
exit;
