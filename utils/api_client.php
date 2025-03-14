<?php
/**
 * GigaChat API Client for product description analysis
 */
class GigaChatApiClient {
    private $apiKey;
    private $accessToken;
    private $tokenExpiration;
    private $baseUrl = 'https://gigachat.devices.sberbank.ru/api/v1';
    private $authUrl = 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->apiKey = getenv('GIGACHAT_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('API ключ GigaChat не найден. Установите переменную окружения GIGACHAT_API_KEY.');
        }
        
        // Get access token
        $this->getAccessToken();
    }
    
    /**
     * Get access token for GigaChat API
     * 
     * @return string Access token
     * @throws Exception If token retrieval fails
     */
    private function getAccessToken() {
        // Check if we already have a valid token
        if ($this->accessToken && time() < $this->tokenExpiration) {
            return $this->accessToken;
        }
        
        // Generate UUID for RqUID
        $rquid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Request new token
        $ch = curl_init($this->authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'RqUID: ' . $rquid,
            'Authorization: Basic ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'scope=GIGACHAT_API_PERS');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Note: In production, this should be true
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Ошибка cURL при получении токена: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Ошибка получения токена доступа. Код HTTP: ' . $httpCode . '. Ответ: ' . $response);
        }
        
        $data = json_decode($response, true);
        if (!isset($data['access_token']) || !isset($data['expires_at'])) {
            throw new Exception('Неверный формат ответа при получении токена');
        }
        
        $this->accessToken = $data['access_token'];
        $this->tokenExpiration = $data['expires_at'];
        
        return $this->accessToken;
    }
    
    /**
     * Make a request to GigaChat API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Data to send
     * @param string $method HTTP method (GET, POST)
     * @return array Response data
     * @throws Exception If request fails
     */
    private function makeRequest($endpoint, $data = [], $method = 'POST') {
        // Create logs directory if it doesn't exist
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        // Generate log filename with timestamp
        $logFile = $logsDir . '/gigachat_' . date('Y-m-d') . '.log';
        
        // Log request
        $logEntry = date('Y-m-d H:i:s') . " REQUEST to $endpoint:\n";
        $logEntry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Ensure we have a valid token
        $token = $this->getAccessToken();
        
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Note: In production, this should be true
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Ошибка cURL при запросе к API: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        // Log response
        $logFile = __DIR__ . '/../logs/gigachat_' . date('Y-m-d') . '.log';
        $logEntry = date('Y-m-d H:i:s') . " RESPONSE (HTTP $httpCode):\n";
        $logEntry .= $response . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception('Ошибка API запроса. Код HTTP: ' . $httpCode . '. Ответ: ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Analyze product description
     * 
     * @param string $name Product name
     * @param string $category Product category
     * @param string $description Product description
     * @return array Analysis results
     * @throws Exception If analysis fails
     */
    public function analyzeDescription($name, $category, $description) {
        $prompt = <<<EOT
Проанализируй описание товара и оцени его по трем критериям по шкале от 1 до 10:
1. SEO-оптимизация
2. Полнота информации
3. Читабельность

Также предложи не менее 3 конкретных рекомендаций по улучшению описания.

Название товара: {$name}
Категория: {$category}
Описание:
{$description}

Формат ответа должен быть в JSON:
        {гргррвраfdfdbbsfbnsfnggkfkfkfksgfhfhwertwrwerwrwr51516515  "seo_score": число от 1 до 10,
  "completeness_score": число от 1 до 10,
  "readability_score": число от 1 до 10,
  "recommendations": ["рекомендация 1", "рекомендация 2", "рекомендация 3"]
}
EOT;

        $data = [
            'model' => 'GigaChat-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 1500
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Неверный формат ответа от API при анализе описания');
        }
        
        // Extract JSON from response
        $content = $response['choices'][0]['message']['content'];
        $matches = [];
        
        // Handle different JSON formats that might be returned
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonStr = $matches[0];
            $analysisData = json_decode($jsonStr, true);
            
            if (!$analysisData) {
                throw new Exception('Не удалось распарсить JSON из ответа API');
            }
            
            // Ensure all required fields are present
            if (!isset($analysisData['seo_score']) || !isset($analysisData['completeness_score']) || 
                !isset($analysisData['readability_score']) || !isset($analysisData['recommendations'])) {
                throw new Exception('Ответ от API не содержит все необходимые поля');
            }
            
            return $analysisData;
        } else {
            throw new Exception('Не удалось найти JSON в ответе API');
        }
    }
    
    /**
     * Generate product image using GigaChat API
     * 
     * @param string $name Product name
     * @param string $category Product category
     * @param string $imagesDir Directory to save images
     * @return string Path to the generated image
     * @throws Exception If image generation fails
     */
    public function generateProductImage($name, $category, $imagesDir) {
        $prompt = "Сгенерируй реалистичное профессиональное изображение товара для каталога. Товар: {$name}. Категория: {$category}. Профессиональное фото на белом фоне. Без текста и надписей.";
        
        // Use chat completions endpoint to generate images
        $data = [
            'model' => 'GigaChat',
            'stream' => false,
            'update_interval' => 0,
            'function_call' => 'auto',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ты - генератор изображений товаров для каталога. Создавай высококачественные изображения без текста и надписей.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        try {
            // Make API request for image
            $response = $this->makeRequest('/chat/completions', $data);
            
            if (!isset($response['choices'][0]['message']['content'])) {
                throw new Exception('Неверный формат ответа от API при генерации изображения');
            }
            
            // Extract the image ID from the response content
            $content = $response['choices'][0]['message']['content'];
            $matches = [];
            
            // Try to find image ID in the response
            if (preg_match('/<img.*?src="([^"]+)".*?fuse="true".*?>/i', $content, $matches)) {
                $imageId = $matches[1];
                $imageName = md5($name . $category . time()) . '.png';
                $imagePath = $imagesDir . '/' . $imageName;
                
                // Use the image ID to construct the download URL (you'll need to implement this part based on GigaChat's image retrieval API)
                $imageUrl = "https://gigachat.devices.sberbank.ru/api/v1/files/" . $imageId;
                
                // Download the image
                $imageContent = file_get_contents($imageUrl);
                if ($imageContent === false) {
                    // Generate a placeholder image
                    $this->generatePlaceholderImage($name, $category, $imagePath);
                    return $imagePath;
                }
                
                if (file_put_contents($imagePath, $imageContent) === false) {
                    throw new Exception('Не удалось сохранить изображение');
                }
                
                return $imagePath;
            } else {
                // Generate a placeholder image since no URL was found
                $imageName = md5($name . $category . time()) . '.png';
                $imagePath = $imagesDir . '/' . $imageName;
                $this->generatePlaceholderImage($name, $category, $imagePath);
                return $imagePath;
            }
            
        } catch (Exception $e) {
            // Log the error but don't halt execution
            error_log('Error generating image: ' . $e->getMessage());
            
            // Generate placeholder image
            $imageName = md5($name . $category . time()) . '.png';
            $imagePath = $imagesDir . '/' . $imageName;
            $this->generatePlaceholderImage($name, $category, $imagePath);
            return $imagePath;
        }
    }
    
    /**
     * Generate a simple placeholder image with product name and category
     * 
     * @param string $name Product name
     * @param string $category Product category
     * @param string $outputPath Path to save the generated image
     * @return void
     */
    private function generatePlaceholderImage($name, $category, $outputPath) {
        // Create image
        $width = 600;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);
        
        // Define colors
        $backgroundColor = imagecolorallocate($image, 240, 240, 240);
        $textColor = imagecolorallocate($image, 50, 50, 50);
        $borderColor = imagecolorallocate($image, 200, 200, 200);
        
        // Fill background
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $backgroundColor);
        
        // Draw border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
        
        // Write product info
        $nameText = "Товар: " . $name;
        $categoryText = "Категория: " . $category;
        
        // Center text
        $fontSize = 5;
        $nameWidth = imagefontwidth($fontSize) * strlen($nameText);
        $categoryWidth = imagefontwidth($fontSize) * strlen($categoryText);
        $nameX = intval(($width - $nameWidth) / 2);
        $categoryX = intval(($width - $categoryWidth) / 2);
        
        // Draw text
        imagestring($image, $fontSize, $nameX, $height/2 - 20, $nameText, $textColor);
        imagestring($image, $fontSize, $categoryX, $height/2 + 10, $categoryText, $textColor);
        
        // Save image
        imagepng($image, $outputPath);
        imagedestroy($image);
    }
}
