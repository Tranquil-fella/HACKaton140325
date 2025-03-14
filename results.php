<?php
session_start();
require_once 'utils/functions.php';
require_once 'utils/api_client.php';

// Check if we have products to analyze
if (!isset($_SESSION['products']) || empty($_SESSION['products'])) {
    $_SESSION['error'] = 'Нет данных для анализа. Пожалуйста, загрузите файл снова.';
    header('Location: index.php');
    exit;
}

// Initialize API client
$apiClient = new GigaChatApiClient();

// Create results directory if it doesn't exist
$resultsDir = __DIR__ . '/uploads/results';
if (!is_dir($resultsDir)) {
    mkdir($resultsDir, 0755, true);
}

// Create images directory if it doesn't exist
$imagesDir = __DIR__ . '/uploads/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

// Process the products if not already processed
if (!isset($_SESSION['analyzed_products'])) {
    $products = $_SESSION['products'];
    $analyzedProducts = [];
    
    // Analyze each product
    foreach ($products as $index => $product) {
        try {
            // Analyze description with GigaChat API
            $analysis = $apiClient->analyzeDescription($product['name'], $product['category'], $product['description']);
            
            // Generate image for the product
            $imagePath = $apiClient->generateProductImage($product['name'], $product['category'], $imagesDir);
            
            // Add analysis results and image path to product data
            $product['seo_score'] = $analysis['seo_score'];
            $product['completeness_score'] = $analysis['completeness_score'];
            $product['readability_score'] = $analysis['readability_score'];
            $product['recommendations'] = $analysis['recommendations'];
            $product['image_path'] = $imagePath;
            
            $analyzedProducts[] = $product;
            
        } catch (Exception $e) {
            // Log the error but continue with other products
            error_log('Error analyzing product: ' . $e->getMessage());
            
            // Add product with error message
            $product['error'] = 'Ошибка анализа: ' . $e->getMessage();
            $analyzedProducts[] = $product;
        }
    }
    
    // Save analyzed products to session
    $_SESSION['analyzed_products'] = $analyzedProducts;
    
    // Save results to CSV
    $resultsCsvPath = $resultsDir . '/results_' . uniqid() . '.csv';
    saveResultsToCsv($analyzedProducts, $resultsCsvPath);
    $_SESSION['results_csv_path'] = $resultsCsvPath;
    
    // Create a ZIP archive with images
    $zipPath = $resultsDir . '/images_' . uniqid() . '.zip';
    createImagesZip($analyzedProducts, $zipPath);
    $_SESSION['images_zip_path'] = $zipPath;
}

$analyzedProducts = $_SESSION['analyzed_products'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Результаты анализа описаний товаров</title>
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    <link rel="stylesheet" href="static/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12 text-center mb-4">
                <h1>Результаты анализа описаний товаров</h1>
                <p class="lead">Анализ выполнен с помощью GigaChat API</p>
            </div>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-md-10">
                <div class="card bg-dark">
                    <div class="card-header">
                        <h5>Сводная информация</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="stats-box">
                                    <h3><?php echo count($analyzedProducts); ?></h3>
                                    <p>Всего товаров проанализировано</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="stats-box">
                                    <h3>
                                        <?php 
                                        $avgSeoScore = array_sum(array_column(array_filter($analyzedProducts, function($p) { 
                                            return isset($p['seo_score']); 
                                        }), 'seo_score')) / count($analyzedProducts);
                                        echo number_format($avgSeoScore, 1);
                                        ?>
                                    </h3>
                                    <p>Средний SEO-рейтинг</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="stats-box">
                                    <h3>
                                        <?php 
                                        $avgReadabilityScore = array_sum(array_column(array_filter($analyzedProducts, function($p) { 
                                            return isset($p['readability_score']); 
                                        }), 'readability_score')) / count($analyzedProducts);
                                        echo number_format($avgReadabilityScore, 1);
                                        ?>
                                    </h3>
                                    <p>Средняя читабельность</p>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <canvas id="scoresChart"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="download-box text-center">
                                    <h4>Скачать результаты</h4>
                                    <a href="download.php?type=csv" class="btn btn-primary m-2">
                                        <i data-feather="file-text"></i> Скачать CSV с результатами
                                    </a>
                                    <a href="download.php?type=images" class="btn btn-primary m-2">
                                        <i data-feather="image"></i> Скачать архив с изображениями
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <?php foreach ($analyzedProducts as $index => $product): ?>
                <div class="card bg-dark mb-4 product-card">
                    <div class="card-header">
                        <h5><?php echo htmlspecialchars($product['name']); ?></h5>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category']); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <?php if (isset($product['image_path']) && file_exists($product['image_path'])): ?>
                                <div class="product-image-container">
                                    <img src="<?php echo 'uploads/images/' . basename($product['image_path']); ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </div>
                                <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i data-feather="image" class="placeholder-icon"></i>
                                    <p>Изображение не удалось сгенерировать</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <?php if (isset($product['error'])): ?>
                                <div class="alert alert-danger">
                                    <?php echo $product['error']; ?>
                                </div>
                                <?php else: ?>
                                <h6>Описание:</h6>
                                <p class="description-text"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                
                                <div class="scores-container">
                                    <div class="score-item">
                                        <div class="score-label">SEO-оптимизация</div>
                                        <div class="progress">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $product['seo_score'] * 10; ?>%" aria-valuenow="<?php echo $product['seo_score']; ?>" aria-valuemin="0" aria-valuemax="10">
                                                <?php echo $product['seo_score']; ?>/10
                                            </div>
                                        </div>
                                    </div>
                                    <div class="score-item">
                                        <div class="score-label">Полнота информации</div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $product['completeness_score'] * 10; ?>%" aria-valuenow="<?php echo $product['completeness_score']; ?>" aria-valuemin="0" aria-valuemax="10">
                                                <?php echo $product['completeness_score']; ?>/10
                                            </div>
                                        </div>
                                    </div>
                                    <div class="score-item">
                                        <div class="score-label">Читабельность</div>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $product['readability_score'] * 10; ?>%" aria-valuenow="<?php echo $product['readability_score']; ?>" aria-valuemin="0" aria-valuemax="10">
                                                <?php echo $product['readability_score']; ?>/10
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="recommendations mt-3">
                                    <h6>Рекомендации по улучшению:</h6>
                                    <ul>
                                        <?php foreach ($product['recommendations'] as $recommendation): ?>
                                        <li><?php echo htmlspecialchars($recommendation); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-md-10 text-center">
                <a href="index.php" class="btn btn-secondary">
                    <i data-feather="upload"></i> Загрузить новый файл
                </a>
            </div>
        </div>
    </div>

    <script src="static/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
            
            // Chart setup
            const ctx = document.getElementById('scoresChart').getContext('2d');
            const productsData = <?php echo json_encode(array_filter($analyzedProducts, function($p) { 
                return !isset($p['error']); 
            })); ?>;
            
            // Prepare data for chart
            const labels = [];
            const seoScores = [];
            const completenessScores = [];
            const readabilityScores = [];
            
            productsData.forEach(product => {
                labels.push(product.name.length > 20 ? product.name.substring(0, 20) + '...' : product.name);
                seoScores.push(product.seo_score);
                completenessScores.push(product.completeness_score);
                readabilityScores.push(product.readability_score);
            });
            
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'SEO-оптимизация',
                            data: seoScores,
                            backgroundColor: 'rgba(23, 162, 184, 0.5)',
                            borderColor: 'rgba(23, 162, 184, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Полнота информации',
                            data: completenessScores,
                            backgroundColor: 'rgba(40, 167, 69, 0.5)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Читабельность',
                            data: readabilityScores,
                            backgroundColor: 'rgba(255, 193, 7, 0.5)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 10
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Оценки описаний товаров'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
