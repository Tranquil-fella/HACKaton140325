<?php
session_start();
require_once 'utils/functions.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анализатор описаний товаров</title>
    <link rel="stylesheet" href="https://cdn.replit.com/agent/bootstrap-agent-dark-theme.min.css">
    <link rel="stylesheet" href="static/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12 text-center mb-4">
                <h1>Анализатор описаний товаров</h1>
                <p class="lead">Загрузите CSV или XLSX файл с описаниями товаров для анализа с помощью GigaChat API</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card bg-dark">
                    <div class="card-header">
                        <h5>Загрузка файла</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <form action="process.php" method="post" enctype="multipart/form-data" id="upload-form" class="dropzone-form">
                            <div id="dropzone" class="dropzone">
                                <div class="dz-message">
                                    <i data-feather="upload-cloud" class="upload-icon"></i>
                                    <h3>Перетащите файл сюда или нажмите для выбора</h3>
                                    <p>Поддерживаемые форматы: CSV, XLSX</p>
                                </div>
                                <input type="file" name="file" id="file-input" accept=".csv,.xlsx" class="d-none">
                            </div>

                            <div id="file-info" class="mt-3 d-none">
                                <div class="card bg-dark-subtle">
                                    <div class="card-body">
                                        <h5 class="file-name">Имя файла</h5>
                                        <p class="file-size">Размер файла</p>
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label for="name-column">Название товара (колонка):</label>
                                <input type="text" class="form-control" id="name-column" name="name_column" placeholder="Например: Название товара" required>
                            </div>

                            <div class="form-group mt-3">
                                <label for="category-column">Категория товара (колонка):</label>
                                <input type="text" class="form-control" id="category-column" name="category_column" placeholder="Например: Категория" required>
                            </div>

                            <div class="form-group mt-3">
                                <label for="description-column">Описание товара (колонка):</label>
                                <input type="text" class="form-control" id="description-column" name="description_column" placeholder="Например: Описание" required>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3 w-100" id="submit-btn" disabled>Начать анализ</button>
                        </form>
                    </div>
                </div>

                <div class="card bg-dark mt-4">
                    <div class="card-header">
                        <h5>Информация о сервисе</h5>
                    </div>
                    <div class="card-body">
                        <p>Этот сервис анализирует описания товаров по трем критериям:</p>
                        <ul>
                            <li><strong>SEO-оптимизация</strong> - оценка соответствия описания требованиям поисковых систем</li>
                            <li><strong>Полнота информации</strong> - насколько полно описаны характеристики товара</li>
                            <li><strong>Читабельность</strong> - насколько текст легок для восприятия покупателем</li>
                        </ul>
                        <p>Для каждого товара будет сгенерировано изображение и предложены рекомендации по улучшению описания.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="static/js/dropzone.min.js"></script>
    <script src="static/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
    </script>
</body>
</html>
