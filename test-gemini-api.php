<?php
/**
 * Gemini API Test Script
 * 
 * Kullanıcının Gemini API anahtarını test etmek için basit script
 * 
 * Kullanım: php test-gemini-api.php GEMINI_API_KEY
 */

if ($argc < 2) {
    echo "Kullanım: php test-gemini-api.php YOUR_GEMINI_API_KEY\n";
    exit(1);
}

$api_key = $argv[1];

function test_gemini_api($api_key) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    
    $data = array(
        'contents' => array(
            array(
                'parts' => array(
                    array('text' => 'iPhone 15 Pro Max hakkında kısa bir inceleme yazısı yaz.')
                )
            )
        ),
        'generationConfig' => array(
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 1000,
        ),
        'safetySettings' => array(
            array(
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ),
            array(
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            )
        )
    );
    
    $json_data = json_encode($data);
    
    // cURL kullanarak istek gönder
    $ch = curl_init();
    
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Gemini-API-Test/1.0'
        ),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE => true
    ));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    echo "=== Gemini API Test Sonuçları ===\n";
    echo "HTTP Status Code: $http_code\n";
    
    if ($curl_error) {
        echo "cURL Hatası: $curl_error\n";
        return false;
    }
    
    if ($http_code !== 200) {
        echo "HTTP Hatası! Response:\n";
        echo $response . "\n";
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Parse Hatası: " . json_last_error_msg() . "\n";
        echo "Raw Response: $response\n";
        return false;
    }
    
    if (isset($result['error'])) {
        echo "Gemini API Hatası:\n";
        echo "Kod: " . ($result['error']['code'] ?? 'Bilinmiyor') . "\n";
        echo "Mesaj: " . ($result['error']['message'] ?? 'Bilinmiyor') . "\n";
        return false;
    }
    
    if (isset($result['candidates']) && 
        is_array($result['candidates']) && 
        !empty($result['candidates']) &&
        isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        
        $content = $result['candidates'][0]['content']['parts'][0]['text'];
        
        echo "✅ API Başarılı! Gemini yanıtı:\n";
        echo "=" . str_repeat("=", 50) . "\n";
        echo $content . "\n";
        echo "=" . str_repeat("=", 50) . "\n";
        
        return true;
    }
    
    echo "❌ Beklenmeyen API yanıt yapısı:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    return false;
}

// Test çalıştır
echo "Gemini API test ediliyor...\n";
echo "API Key: " . substr($api_key, 0, 10) . "...\n\n";

$success = test_gemini_api($api_key);

if ($success) {
    echo "\n🎉 Test başarılı! API çalışıyor.\n";
    echo "WordPress eklentinizde bu API anahtarını kullanabilirsiniz.\n";
} else {
    echo "\n❌ Test başarısız!\n";
    echo "Lütfen API anahtarınızı kontrol edin:\n";
    echo "1. Google AI Studio'dan doğru API key aldığınızdan emin olun\n";
    echo "2. API key'de boşluk veya yanlış karakter olmadığını kontrol edin\n";
    echo "3. Gemini API'nın bölgenizde aktif olduğundan emin olun\n";
    echo "4. API kullanım limitlerini kontrol edin\n";
}

echo "\n";
?> 