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
{
  "seo_score": число от 1 до 10,
  "completeness_score": число от 1 до 10,
  "readability_score": число от 1 до 10,
  "recommendations": ["рекомендация 1", "рекомендация 2", "рекомендация 3"]
}
EOT;

        $data = [
            'model' => 'GigaChat',
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
        $prompt = "Высококачественное изображение товара: {$name}. Категория: {$category}. Профессиональное фото на белом фоне.";
        
        $data = [
            'model' => 'GigaChat:latest',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'update_interval' => 0,
            'temperature' => 0.7
        ];
        
        try {
            $response = $this->makeRequest('/images/generations', $data);
            
            if (!isset($response['data'][0]['url'])) {
                throw new Exception('Неверный формат ответа от API при генерации изображения');
            }
            
            // Download the image
            $imageUrl = $response['data'][0]['url'];
            $imageName = md5($name . $category . time()) . '.png';
            $imagePath = $imagesDir . '/' . $imageName;
            
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                throw new Exception('Не удалось скачать изображение по URL: ' . $imageUrl);
            }
            
            if (file_put_contents($imagePath, $imageContent) === false) {
                throw new Exception('Не удалось сохранить изображение');
            }
            
            return $imagePath;
        } catch (Exception $e) {
            // Log the error but don't halt execution
            error_log('Error generating image: ' . $e->getMessage());
            return '';
        }
    }
}
