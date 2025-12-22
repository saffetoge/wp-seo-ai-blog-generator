<?php
/**
 * AI Integration Class
 * 
 * Handles integration with Google Gemini and OpenAI APIs
 * 
 * @package WP_Product_Blog_Generator
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPBG_AI_Integration
{

    /**
     * AI Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Safe wrapper for WordPress functions
        $default_settings = array(
            'ai_provider' => 'gemini',
            'gemini_api_key' => '',
            'openai_api_key' => '',
            'content_language' => 'tr',
            'content_style' => 'professional',
            'include_images' => true
        );

        if (function_exists('get_option')) {
            $this->settings = get_option('wpbg_ai_settings', $default_settings);
        } else {
            $this->settings = $default_settings;
        }
    }

    /**
     * Generate content using selected AI provider
     */
    public function generate_product_content($product_name)
    {
        if (empty($product_name)) {
            return false;
        }

        $prompt = $this->build_content_prompt($product_name);

        switch ($this->settings['ai_provider']) {
            case 'gemini':
                return $this->generate_with_gemini($prompt);
            case 'openai':
                return $this->generate_with_openai($prompt);
            default:
                return array('error' => 'Geçersiz AI sağlayıcısı.');
        }
    }

    /**
     * Build comprehensive content prompt
     */
    private function build_content_prompt($product_name)
    {
        $language_map = array(
            'tr' => 'Türkçe',
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français'
        );

        $style_map = array(
            'professional' => 'profesyonel ve güvenilir',
            'casual' => 'samimi ve rahat',
            'technical' => 'teknik ve detaylı',
            'review' => 'inceleme tarzında ve objektif'
        );

        $language = $language_map[$this->settings['content_language']] ?? 'Türkçe';
        $style = $style_map[$this->settings['content_style']] ?? 'profesyonel';

        $prompt = "Sen bir teknoloji uzmanı ve içerik editörüsün. '{$product_name}' ürünü hakkında Google SEO uyumlu, kapsamlı bir blog yazısı oluştur.

GEREKSINIMLER:
- Dil: {$language}
- Stil: {$style}
- SEO uyumlu başlıklar (H1, H2, H3 yapısında)
- En az 1500 kelime
- İnsan tarafından yazılmış gibi doğal bir ton
- Google E-A-T kriterlerine uygun

YAZIYI ŞU YAPIDA OLUŞTUR:

1. GİRİŞ BÖLÜMÜ
- Ürün hakkında genel bilgi
- Neden bu inceleme önemli
- İçerikte neler bulunacağı

2. ÜRÜN HAKKINDA GENEL BİLGİLER
- Ürünün kategorisi ve konumu
- Ana özelliklerinin özeti
- Hedef kitle analizi

3. TEKNİK ÖZELLİKLER
- Detaylı spesifikasyonlar
- Performans verileri
- Karşılaştırma metrikleri

4. KULLANICI DENEYİMİ
- Kurulum/başlangıç süreci
- Günlük kullanım deneyimi
- Güçlü ve zayıf yönler

5. AVANTAJLAR VE DEZAVANTAJLAR
- Net artılar ve eksiler listesi
- Objektif değerlendirme

6. SATIN ALMA REHBERİ
- Kimler için uygun
- Alternatif ürünlerle karşılaştırma
- Fiyat-performans analizi

7. SONUÇ VE ÖNERİLER
- Genel değerlendirme
- Satın alma önerisi
- Son tavsiyelar";

        if ($this->settings['include_images']) {
            $prompt .= "\n\n8. GÖRSEL ÖNERİLERİ
- Her bölüm için uygun görsel açıklamaları
- Alt text önerileri
- Görsel konumlandırma tavsiyeleri";
        }

        $prompt .= "\n\nÖNEMLİ NOTLAR:
- Başlıkları emoji ile destekle (🔧, 📱, ⭐ vb.)
- Her paragraf 3-4 cümle olsun
- Bullet point'ler kullan
- İnsansı hata ve varyasyonlar ekle
- Anahtar kelimeleri doğal şekilde dağıt
- Call-to-action'lar ekle
- Meta description için özet çıkar

ÇIKTI FORMATI: HTML etiketleri ile birlikte tam makale içeriği ver.";

        return $prompt;
    }

    /**
     * Generate content with Google Gemini
     */
    private function generate_with_gemini($prompt)
    {
        $api_key = $this->settings['gemini_api_key'] ?? '';
        $model = $this->settings['gemini_model'] ?? 'gemini-1.5-flash';

        if (empty($api_key)) {
            return array('error' => 'Gemini API anahtarı tanımlanmamış.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;

        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
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

        // Use WordPress HTTP API with improved error handling
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress-Product-Blog-Generator/1.0'
            ),
            'body' => json_encode($data),
            'timeout' => 120,
            'blocking' => true,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return array('error' => 'API bağlantı hatası: ' . $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log raw response for debugging
        error_log('Gemini API Response Code: ' . $http_code);
        error_log('Gemini API Response Body: ' . substr($body, 0, 500));

        if ($http_code !== 200) {
            return array('error' => 'HTTP Hatası (' . $http_code . '): ' . $body);
        }

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'JSON parse hatası: ' . json_last_error_msg());
        }

        if (isset($result['error'])) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Bilinmeyen API hatası';
            return array('error' => 'Gemini API hatası: ' . $error_message);
        }

        // Check for the correct response structure
        if (
            isset($result['candidates']) &&
            is_array($result['candidates']) &&
            !empty($result['candidates']) &&
            isset($result['candidates'][0]['content']['parts'][0]['text'])
        ) {

            $content = $result['candidates'][0]['content']['parts'][0]['text'];

            // Clean up the content
            $content = trim($content);

            return array('content' => $content);
        }

        return array('error' => 'Beklenmeyen API yanıtı yapısı. Raw response: ' . json_encode($result));
    }

    /**
     * Generate content with OpenAI
     */
    private function generate_with_openai($prompt)
    {
        $api_key = $this->settings['openai_api_key'] ?? '';
        $model = $this->settings['openai_model'] ?? 'gpt-4o';

        if (empty($api_key)) {
            return array('error' => 'OpenAI API anahtarı tanımlanmamış.');
        }

        $url = 'https://api.openai.com/v1/chat/completions';

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Sen profesyonel bir teknoloji yazarı ve SEO uzmanısın. Google SEO kurallarına uygun, kaliteli ve okunabilir içerikler üretiyorsun.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($data),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return array('error' => 'API bağlantı hatası: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['error'])) {
            return array('error' => 'OpenAI API hatası: ' . $result['error']['message']);
        }

        if (isset($result['choices'][0]['message']['content'])) {
            return array('content' => $result['choices'][0]['message']['content']);
        }

        return array('error' => 'Beklenmeyen API yanıtı.');
    }

    /**
     * Test AI connection via AJAX
     */
    public function test_ai_connection()
    {
        // Debug log
        error_log('test_ai_connection called');
        error_log('POST data: ' . print_r($_POST, true));

        // Set proper headers
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        // Güvenlik kontrolleri - daha esnek approach
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Nonce bulunamadı.');
            return;
        }

        if (function_exists('wp_verify_nonce')) {
            $nonce_check = wp_verify_nonce($_POST['nonce'], 'test_ai_connection');
            if (!$nonce_check) {
                wp_send_json_error('Güvenlik kontrolü başarısız.');
                return;
            }
        }

        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz bulunmuyor.');
            return;
        }

        // POST verilerini kontrol et
        if (!isset($_POST['ai_provider'])) {
            wp_send_json_error('AI sağlayıcısı belirtilmemiş.');
            return;
        }

        $provider = sanitize_text_field($_POST['ai_provider']);
        $test_prompt = "Merhaba, API bağlantı testi. Lütfen kısa bir yanıt ver.";

        // API anahtarlarını kontrol et
        $api_key = '';
        if ($provider === 'gemini') {
            $api_key = isset($_POST['gemini_api_key']) ? sanitize_text_field($_POST['gemini_api_key']) : '';
            if (empty($api_key)) {
                wp_send_json_error('Gemini API anahtarı boş olamaz.');
                return;
            }
        } elseif ($provider === 'openai') {
            $api_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
            if (empty($api_key)) {
                wp_send_json_error('OpenAI API anahtarı boş olamaz.');
                return;
            }
        } else {
            wp_send_json_error('Geçersiz AI sağlayıcısı: ' . $provider);
            return;
        }

        // Temporarily update settings for testing
        $temp_settings = $this->settings;
        $temp_settings['ai_provider'] = $provider;
        $temp_settings['gemini_api_key'] = isset($_POST['gemini_api_key']) ? sanitize_text_field($_POST['gemini_api_key']) : '';
        $temp_settings['openai_api_key'] = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
        $this->settings = $temp_settings;

        try {
            switch ($provider) {
                case 'gemini':
                    $result = $this->generate_with_gemini($test_prompt);
                    break;
                case 'openai':
                    $result = $this->generate_with_openai($test_prompt);
                    break;
                default:
                    wp_send_json_error('Geçersiz AI sağlayıcısı: ' . $provider);
                    return;
            }

            if (isset($result['error'])) {
                wp_send_json_error($result['error']);
            } else {
                wp_send_json_success(array(
                    'message' => $provider . ' API bağlantısı başarılı! Sistem çalışmaya hazır.',
                    'response' => isset($result['content']) ? substr($result['content'], 0, 100) . '...' : 'Yanıt alındı.'
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error('Test sırasında hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler to fetch models
     */
    public function fetch_ai_models_ajax()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbg_nonce')) {
            wp_send_json_error('Güvenlik kontrolü başarısız.');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz bulunmuyor.');
        }

        $provider = sanitize_text_field($_POST['ai_provider']);
        $api_key = sanitize_text_field($_POST['api_key']);

        if (empty($api_key)) {
            wp_send_json_error('API anahtarı boş olamaz.');
        }

        if ($provider === 'gemini') {
            $result = $this->fetch_gemini_models($api_key);
        } elseif ($provider === 'openai') {
            $result = $this->fetch_openai_models($api_key);
        } else {
            wp_send_json_error('Geçersiz sağlayıcı.');
        }

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success($result['models']);
    }

    /**
     * Fetch Gemini models
     */
    public function fetch_gemini_models($api_key)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;

        $response = wp_remote_get($url, array('timeout' => 30));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('error' => $data['error']['message']);
        }

        $models = array();
        if (isset($data['models'])) {
            foreach ($data['models'] as $m) {
                // Sadece text generation destekleyen modelleri alalım
                if (in_array('generateContent', $m['supportedGenerationMethods'] ?? array())) {
                    $name = str_replace('models/', '', $m['name']);
                    $models[] = array(
                        'id' => $name,
                        'name' => $m['displayName']
                    );
                }
            }
        }

        return array('models' => $models);
    }

    /**
     * Fetch OpenAI models
     */
    public function fetch_openai_models($api_key)
    {
        $url = 'https://api.openai.com/v1/models';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            return array('error' => $data['error']['message']);
        }

        $models = array();
        if (isset($data['data'])) {
            foreach ($data['data'] as $m) {
                // Sadece gpt modellerini filtrele
                if (strpos($m['id'], 'gpt') !== false) {
                    $models[] = array(
                        'id' => $m['id'],
                        'name' => strtoupper($m['id'])
                    );
                }
            }
        }

        // Sort models by ID descending to get newer ones first
        usort($models, function ($a, $b) {
            return strcmp($b['id'], $a['id']);
        });

        return array('models' => $models);
    }

    /**
     * Improve content using AI
     */
    public function improve_content($content, $focus_keyword = '')
    {
        $prompt = "Aşağıdaki metni baştan aşağı düzenle, SEO uyumlu hale getir ve okunabilirliğini artır. 
Metnin tonu profesyonel ve ilgi çekici olsun. Başlıklar (H2, H3) ve bullet point'ler ekle. 
HTML etiketlerini koru ve içeriği zenginleştirerek geri dön.\n\n";

        if (!empty($focus_keyword)) {
            $prompt .= "Odak Anahtar Kelime: {$focus_keyword}\n";
            $prompt .= "Bu anahtar kelimeyi doğal bir şekilde metne dahil et.\n\n";
        }

        $prompt .= "DÜZENLENECEK METİN:\n" . $content;

        return $this->generate_product_content($prompt);
    }

    /**
     * Analyze SEO of content
     */
    public function analyze_seo($content, $focus_keyword = '')
    {
        $prompt = "Aşağıdaki metni Google SEO ve Yoast SEO kriterlerine göre analiz et. 
Analiz sonucunda şunları ver:
1. SEO Skoru (100 üzerinden)
2. Okunabilirlik Skoru (100 üzerinden)
3. Kritik Hatalar (Liste)
4. İyileştirme Önerileri (Liste)
5. Anahtar Kelime Yoğunluğu Analizi

Yanıtı JSON formatında ver. Format: 
{
  \"seo_score\": 85,
  \"readability_score\": 70,
  \"errors\": [\"...\"],
  \"suggestions\": [\"...\"],
  \"keywords\": {\"keyword\": \"...\", \"density\": \"...\"}
}

METİN:\n" . $content;

        if (!empty($focus_keyword)) {
            $prompt .= "\n\nOdak Anahtar Kelime: " . $focus_keyword;
        }

        $result = $this->generate_product_content($prompt);

        if (isset($result['error'])) {
            return $result;
        }

        // Extract JSON from response
        $json_str = $result['content'];
        if (preg_match('/\{.*\}/s', $json_str, $matches)) {
            $analysis = json_decode($matches[0], true);
            if ($analysis) {
                return $analysis;
            }
        }

        return array('error' => 'SEO analizi JSON formatında alınamadı.');
    }

    /**
     * Add schema markup for AI-generated content
     */
    public function add_schema_markup($content, $product_name)
    {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'itemReviewed' => array(
                '@type' => 'Product',
                'name' => $product_name,
                'description' => 'Detailed product review and analysis'
            ),
            'author' => array(
                '@type' => 'Person',
                'name' => 'AI Technology Expert'
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            ),
            'datePublished' => date('c'),
            'dateModified' => date('c'),
            'headline' => $product_name . ' İncelemesi',
            'description' => $product_name . ' hakkında detaylı inceleme ve teknik analiz.',
            'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => '4.5',
                'bestRating' => '5',
                'worstRating' => '1'
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink()
            )
        );

        $schema_markup = '<script type="application/ld+json">' . "\n";
        $schema_markup .= json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $schema_markup .= '</script>' . "\n";

        return $content . $schema_markup;
    }
}