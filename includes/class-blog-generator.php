<?php
/**
 * Blog Generator Class
 * 
 * Handles the generation of SEO-optimized blog content for products
 * 
 * @package WP_Product_Blog_Generator
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPBG_Blog_Generator {
    
    /**
     * Product name
     */
    private $product_name;
    
    /**
     * Generated content
     */
    private $content;
    
    /**
     * SEO keywords
     */
    private $keywords = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor - no hooks needed here
    }
    
    /**
     * WordPress function wrappers for better compatibility
     */
    private function safe_sanitize_text_field($text) {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field($text);
        }
        return strip_tags(trim($text));
    }
    
    private function safe_esc_html($text) {
        if (function_exists('esc_html')) {
            return esc_html($text);
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function safe_esc_attr($text) {
        if (function_exists('esc_attr')) {
            return esc_attr($text);
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function safe_get_option($option, $default = false) {
        if (function_exists('get_option')) {
            return get_option($option, $default);
        }
        return $default;
    }
    
    private function safe_get_permalink() {
        if (function_exists('get_permalink')) {
            return get_permalink();
        }
        return '#';
    }
    
    private function safe_get_bloginfo($show = '') {
        if (function_exists('get_bloginfo')) {
            return get_bloginfo($show);
        }
        return '';
    }
    
    private function safe_do_action($hook_name, ...$args) {
        if (function_exists('do_action')) {
            return do_action($hook_name, ...$args);
        }
    }
    
    private function safe_apply_filters($hook_name, $value, ...$args) {
        if (function_exists('apply_filters')) {
            return apply_filters($hook_name, $value, ...$args);
        }
        return $value;
    }
    
    private function safe_home_url() {
        if (function_exists('home_url')) {
            return home_url();
        }
        return '';
    }
    
    /**
     * Generate blog content for a product
     * 
     * @param string $product_name The product name
     * @return string Generated HTML content
     */
    public function generate_content($product_name) {
        $this->product_name = $this->safe_sanitize_text_field($product_name);
        $this->generate_keywords();
        
        // Apply filters before generation
        $this->safe_do_action('wpbg_before_content_generation', $this->product_name);
        
        // Try AI generation first
        $ai_integration = new WPBG_AI_Integration();
        $ai_result = $ai_integration->generate_product_content($product_name);
        
        if (isset($ai_result['content'])) {
            // AI generation successful
            $content = $this->format_ai_content($ai_result['content']);
            $content = $ai_integration->add_schema_markup($content, $product_name);
        } else {
            // Fallback to template-based generation
            $content = $this->build_seo_optimized_content();
            if (isset($ai_result['error'])) {
                $content = '<div class="ai-error-notice">⚠️ AI içerik oluşturulamadı: ' . $this->safe_esc_html($ai_result['error']) . '</div>' . "\n" . $content;
            }
        }
        
        // Apply filters after generation
        $content = $this->safe_apply_filters('wpbg_filter_generated_content', $content, $this->product_name);
        $this->safe_do_action('wpbg_after_content_generation', $this->product_name, $content);
        
        return $content;
    }
    
    /**
     * Format AI-generated content
     */
    private function format_ai_content($ai_content) {
        // Add featured image placeholder if not present
        if (strpos($ai_content, '<img') === false) {
            $featured_image = $this->generate_featured_image();
            $ai_content = $featured_image . $ai_content;
        }
        
        // Ensure proper HTML structure
        if (strpos($ai_content, '<article') === false) {
            $ai_content = '<article class="ai-generated-content">' . "\n" . $ai_content . "\n" . '</article>';
        }
        
        // Add AI generation notice (optional, can be disabled)
        $settings = $this->safe_get_option('wpbg_ai_settings', array());
        if (isset($settings['show_ai_notice']) && $settings['show_ai_notice']) {
            $ai_notice = '<div class="ai-generated-notice">🤖 Bu içerik AI teknolojisi ile oluşturulmuş ve editöryal kontrolden geçmiştir.</div>' . "\n";
            $ai_content = $ai_notice . $ai_content;
        }
        
        return $ai_content;
    }
    
    /**
     * Detect product category automatically
     */
    private function detect_product_category() {
        $product_lower = strtolower($this->product_name);
        
        // 3D Printer detection
        if (strpos($product_lower, 'bambu') !== false || 
            strpos($product_lower, 'printer') !== false ||
            strpos($product_lower, '3d') !== false ||
            strpos($product_lower, 'prusa') !== false ||
            strpos($product_lower, 'ender') !== false ||
            strpos($product_lower, 'combo') !== false) {
            return '3d_printer';
        }
        
        // Phone detection
        if (strpos($product_lower, 'phone') !== false || 
            strpos($product_lower, 'iphone') !== false ||
            strpos($product_lower, 'samsung') !== false ||
            strpos($product_lower, 'galaxy') !== false ||
            strpos($product_lower, 'pixel') !== false ||
            preg_match('/\d+\s*(pro\s*)?(max|plus)?/i', $product_lower)) {
            return 'smartphone';
        }
        
        // Laptop detection
        if (strpos($product_lower, 'laptop') !== false || 
            strpos($product_lower, 'macbook') !== false ||
            strpos($product_lower, 'thinkpad') !== false ||
            strpos($product_lower, 'notebook') !== false) {
            return 'laptop';
        }
        
        // TV detection
        if (strpos($product_lower, 'tv') !== false || 
            strpos($product_lower, 'televizyon') !== false ||
            strpos($product_lower, 'smart tv') !== false) {
            return 'tv';
        }
        
        // Gaming detection
        if (strpos($product_lower, 'playstation') !== false || 
            strpos($product_lower, 'xbox') !== false ||
            strpos($product_lower, 'nintendo') !== false ||
            strpos($product_lower, 'konsol') !== false) {
            return 'gaming';
        }
        
        return 'general';
    }
    
    /**
     * Generate SEO keywords based on product name
     */
    private function generate_keywords() {
        $base_keywords = array(
            $this->product_name,
            $this->product_name . ' inceleme',
            $this->product_name . ' özellikleri',
            $this->product_name . ' fiyat',
            $this->product_name . ' teknik özellikler',
            $this->product_name . ' avantajları',
            $this->product_name . ' kullanım',
            $this->product_name . ' rehber'
        );
        
        // Add generic tech keywords
        $tech_keywords = array(
            'teknoloji', 'yenilik', 'performans', 'kalite', 'özellik',
            'avantaj', 'kullanım', 'deneyim', 'inceleme', 'analiz'
        );
        
        $this->keywords = array_merge($base_keywords, $tech_keywords);
    }
    
    /**
     * Build SEO optimized content structure
     */
    private function build_seo_optimized_content() {
        $category = $this->detect_product_category();
        $html = '';
        
        // SEO Meta Tags
        $html .= $this->generate_seo_meta_tags();
        
        // Featured Image Placeholder
        $html .= $this->generate_featured_image();
        
        // Article with proper heading structure
        $html .= $this->generate_seo_article_header($category);
        $html .= $this->generate_human_like_introduction($category);
        $html .= $this->generate_dynamic_specifications($category);
        $html .= $this->generate_detailed_features($category);
        $html .= $this->generate_pros_and_cons($category);
        $html .= $this->generate_user_experience($category);
        $html .= $this->generate_buying_guide($category);
        $html .= $this->generate_natural_conclusion($category);
        $html .= $this->generate_rich_schema($category);
        
        return $html;
    }
    
    /**
     * Generate featured image placeholder
     */
    private function generate_featured_image() {
        $html = '<div class="featured-image">' . "\n";
        $html .= '<img src="https://via.placeholder.com/800x400/0073aa/ffffff?text=' . urlencode($this->product_name) . '" ';
        $html .= 'alt="' . $this->safe_esc_attr($this->product_name) . ' detaylı inceleme görseli" ';
        $html .= 'title="' . $this->safe_esc_attr($this->product_name) . ' - Teknik özellikler ve kullanım rehberi" ';
        $html .= 'width="800" height="400" loading="lazy">' . "\n";
        $html .= '<figcaption>' . $this->safe_esc_html($this->product_name) . ' - Kapsamlı İnceleme ve Analiz</figcaption>' . "\n";
        $html .= '</div>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate human-like SEO article header
     */
    private function generate_seo_article_header($category) {
        $natural_titles = $this->get_natural_titles($category);
        
        $html = '<article class="product-review" itemscope itemtype="https://schema.org/Review">' . "\n";
        $html .= '<header class="review-header">' . "\n";
        $html .= '<h1 class="review-title" itemprop="name">' . $natural_titles['main'] . '</h1>' . "\n";
        $html .= '<div class="review-meta">' . "\n";
        $html .= '<span class="publish-date" itemprop="datePublished" content="' . date('c') . '">';
        $html .= date('d F Y') . ' tarihinde yayınlandı</span>' . "\n";
        $html .= '<span class="author" itemprop="author" itemscope itemtype="https://schema.org/Person">';
        $html .= '<span itemprop="name">Teknoloji Uzmanı</span></span>' . "\n";
        $html .= '<div class="reading-time">📖 Okuma süresi: 8-10 dakika</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</header>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Get natural titles based on category
     */
    private function get_natural_titles($category) {
        $product = $this->product_name;
        
        switch ($category) {
            case '3d_printer':
                return [
                    'main' => $product . ' İncelemesi: 3D Baskı Dünyasında Yeni Standart',
                    'specs' => '🔧 Teknik Özellikler ve Performans Analizi',
                    'features' => '⭐ Öne Çıkan Özellikler ve Yenilikler',
                    'experience' => '👤 Kullanıcı Deneyimi ve Pratik Kullanım',
                    'guide' => '📋 Satın Alma Rehberi ve Öneriler'
                ];
            case 'smartphone':
                return [
                    'main' => $product . ' İncelemesi: Akıllı Telefon Segmentinde Fark Yaratan Model',
                    'specs' => '📱 Teknik Özellikler ve Donanım Analizi',
                    'features' => '✨ Dikkat Çeken Özellikler ve İnovasyonlar',
                    'experience' => '💯 Kullanım Deneyimi ve Günlük Performans',
                    'guide' => '🛒 Satın Alma Önerileri ve Karşılaştırma'
                ];
            case 'laptop':
                return [
                    'main' => $product . ' İncelemesi: Taşınabilir Bilgisayar Kategorisinde Güçlü Seçenek',
                    'specs' => '💻 Donanım Özellikleri ve Performans Değerlendirmesi',
                    'features' => '🚀 Öne Çıkan Özellikler ve Teknolojiler',
                    'experience' => '⚡ Kullanım Senaryoları ve Performans Testleri',
                    'guide' => '💡 Satın Alma Kılavuzu ve Alternatifleri'
                ];
            default:
                return [
                    'main' => $product . ' İncelemesi: Detaylı Analiz ve Kullanıcı Rehberi',
                    'specs' => '⚙️ Teknik Özellikler ve Spesifikasyonlar',
                    'features' => '🌟 Öne Çıkan Özellikler ve Avantajlar',
                    'experience' => '🎯 Kullanım Deneyimi ve Performans',
                    'guide' => '📊 Satın Alma Rehberi ve Öneriler'
                ];
        }
    }
    
    /**
     * Generate SEO meta tags
     */
    private function generate_seo_meta_tags() {
        $title = $this->get_natural_titles($this->detect_product_category())['main'];
        $description = $this->product_name . ' hakkında kapsamlı inceleme. Teknik özellikler, kullanıcı deneyimi, avantajlar ve satın alma rehberi. Uzman görüşleri ve detaylı analiz.';
        $keywords = implode(', ', array_slice($this->keywords, 0, 15));
        
        $html = '<!-- SEO Meta Etiketleri -->' . "\n";
        $html .= '<title>' . esc_html($title) . '</title>' . "\n";
        $html .= '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        $html .= '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">' . "\n";
        $html .= '<meta name="author" content="Teknoloji Uzmanı">' . "\n";
        $html .= '<link rel="canonical" href="' . get_permalink() . '">' . "\n";
        
        // OpenGraph Meta Tags
        $html .= '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        $html .= '<meta property="og:type" content="article">' . "\n";
        $html .= '<meta property="og:url" content="' . get_permalink() . '">' . "\n";
        $html .= '<meta property="og:site_name" content="' . get_bloginfo('name') . '">' . "\n";
        $html .= '<meta property="og:locale" content="tr_TR">' . "\n";
        
        // Twitter Card Meta Tags
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . esc_attr(substr($description, 0, 150)) . '">' . "\n";
        
        // Article specific meta tags
        $html .= '<meta property="article:published_time" content="' . date('c') . '">' . "\n";
        $html .= '<meta property="article:author" content="Teknoloji Uzmanı">' . "\n";
        $html .= '<meta property="article:section" content="Teknoloji İncelemeleri">' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate human-like introduction
     */
    private function generate_human_like_introduction($category) {
        $titles = $this->get_natural_titles($category);
        
        $intros = $this->get_category_intros($category);
        $selected_intro = $intros[array_rand($intros)];
        
        $html = '<section class="article-introduction">' . "\n";
        $html .= '<h2 class="intro-title">🚀 Giriş</h2>' . "\n";
        $html .= '<p class="lead-paragraph">' . sprintf($selected_intro, $this->product_name) . '</p>' . "\n";
        
        $html .= '<div class="article-highlights">' . "\n";
        $html .= '<h3>📋 Bu İncelemede Neler Bulacaksınız?</h3>' . "\n";
        $html .= '<ul class="content-overview">' . "\n";
        $html .= '<li>🔍 Detaylı teknik özellikler ve performans analizi</li>' . "\n";
        $html .= '<li>💡 Gerçek kullanıcı deneyimleri ve pratik ipuçları</li>' . "\n";
        $html .= '<li>⚖️ Avantajlar ve dezavantajlar objektif değerlendirmesi</li>' . "\n";
        $html .= '<li>🛒 Satın alma önerileri ve alternatifleri</li>' . "\n";
        $html .= '<li>📊 Rakip ürünlerle karşılaştırma</li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Get category specific introductions
     */
    private function get_category_intros($category) {
        switch ($category) {
            case '3d_printer':
                return [
                    "%s, 3D baskı teknolojisinde yeni standartlar belirleyen ve kullanıcılarına profesyonel sonuçlar sunan bir üründür. Bu kapsamlı incelemede, baskı kalitesinden kurulum kolaylığına kadar tüm detayları ele alacağız.",
                    "3D baskı dünyasında %s, hem başlangıç seviyesi hem de ileri düzey kullanıcılar için tasarlanmış özelliklerle dikkat çekiyor. Teknolojik yenilikleri ve kullanım kolaylığıyla öne çıkan bu ürünü mercek altına aldık.",
                    "%s ile tanıştığımızda, 3D baskı teknolojisinin ne kadar geliştiğini bir kez daha görüyoruz. Bu detaylı incelemede, üründün sunduğu imkanları ve gerçek performansını değerlendireceğiz."
                ];
            case 'smartphone':
                return [
                    "%s, akıllı telefon pazarında kendine özgü konumuyla dikkat çeken bir model. Kamera performansından batarya ömrüne kadar tüm özelliklerini gerçek kullanım senaryolarında test ettik.",
                    "Modern mobil teknolojinin en son örneklerinden biri olan %s, kullanıcı beklentilerini karşılamak için tasarlanmış. Bu kapsamlı incelemede, günlük kullanımdaki performansını değerlendiriyoruz.",
                    "%s ile akıllı telefon deneyiminin ne kadar geliştiğini görmek mümkün. İnovatif özellikleri ve kullanım deneyimiyle öne çıkan bu modeli detaylıca inceledik."
                ];
            default:
                return [
                    "%s, kategorisinde öne çıkan özellikleriyle kullanıcılarına kaliteli bir deneyim sunmayı hedefleyen bir ürün. Bu kapsamlı incelemede, performansından kullanım kolaylığına kadar tüm yönlerini ele alacağız.",
                    "Teknoloji dünyasında kendine sağlam bir yer edinen %s, kullanıcı beklentilerini karşılamak için geliştirilmiş. Bu detaylı analizde, gerçek performansını ve kullanım deneyimini değerlendiriyoruz.",
                    "%s ile tanıştığımızda, modern teknolojinin kullanıcı odaklı yaklaşımını net bir şekilde görebiliyoruz. Bu incelemede, ürünün tüm yönlerini objektif bir gözle ele alacağız."
                ];
        }
    }
    
    /**
     * Generate dynamic specifications section
     */
    private function generate_dynamic_specifications($category) {
        $html = '<section class="dynamic-specifications">' . "\n";
        $html .= '<h2>⚙️ Teknik Özellikler ve Spesifikasyonlar</h2>' . "\n";
        
        $specs = $this->generate_product_specs();
        
        $html .= '<div class="specs-grid">' . "\n";
        $html .= '<table class="specs-table" itemscope itemtype="https://schema.org/Product">' . "\n";
        $html .= '<caption itemprop="name">' . $this->safe_esc_html($this->product_name) . ' Teknik Özellikleri</caption>' . "\n";
        $html .= '<thead><tr><th>Özellik</th><th>Detay</th></tr></thead>' . "\n";
        $html .= '<tbody>' . "\n";
        
        foreach ($specs as $spec => $detail) {
            $html .= '<tr>' . "\n";
            $html .= '<td><strong>' . $this->safe_esc_html($spec) . '</strong></td>' . "\n";
            $html .= '<td itemprop="description">' . $this->safe_esc_html($detail) . '</td>' . "\n";
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</tbody></table>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate detailed features section
     */
    private function generate_detailed_features($category) {
        $titles = $this->get_natural_titles($category);
        $html = '<section class="detailed-features">' . "\n";
        $html .= '<h2>' . $titles['features'] . '</h2>' . "\n";
        
        $features = $this->generate_product_features();
        
        $html .= '<div class="features-grid">' . "\n";
        foreach ($features as $feature) {
            $html .= '<div class="feature-item">' . "\n";
            $html .= '<h3>' . $this->safe_esc_html($feature['title']) . '</h3>' . "\n";
            $html .= '<p>' . $this->safe_esc_html($feature['description']) . '</p>' . "\n";
            $html .= '</div>' . "\n";
        }
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate pros and cons section
     */
    private function generate_pros_and_cons($category) {
        $html = '<section class="pros-and-cons">' . "\n";
        $html .= '<h2>⚖️ Avantajlar ve Dezavantajlar</h2>' . "\n";
        
        $pros_cons = $this->get_category_pros_cons($category);
        
        $html .= '<div class="pros-cons-container">' . "\n";
        
        // Pros
        $html .= '<div class="pros-section">' . "\n";
        $html .= '<h3 class="pros-title">✅ Avantajlar</h3>' . "\n";
        $html .= '<ul class="pros-list">' . "\n";
        foreach ($pros_cons['pros'] as $pro) {
            $html .= '<li>' . $this->safe_esc_html($pro) . '</li>' . "\n";
        }
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        
        // Cons
        $html .= '<div class="cons-section">' . "\n";
        $html .= '<h3 class="cons-title">❌ Dezavantajlar</h3>' . "\n";
        $html .= '<ul class="cons-list">' . "\n";
        foreach ($pros_cons['cons'] as $con) {
            $html .= '<li>' . $this->safe_esc_html($con) . '</li>' . "\n";
        }
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate user experience section
     */
    private function generate_user_experience($category) {
        $titles = $this->get_natural_titles($category);
        $html = '<section class="user-experience">' . "\n";
        $html .= '<h2>' . $titles['experience'] . '</h2>' . "\n";
        
        $experiences = $this->get_category_experiences($category);
        
        foreach ($experiences as $experience) {
            $html .= '<div class="experience-block">' . "\n";
            $html .= '<h3>' . $this->safe_esc_html($experience['title']) . '</h3>' . "\n";
            $html .= '<p>' . $this->safe_esc_html($experience['content']) . '</p>' . "\n";
            $html .= '</div>' . "\n";
        }
        
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate buying guide section
     */
    private function generate_buying_guide($category) {
        $titles = $this->get_natural_titles($category);
        $html = '<section class="buying-guide">' . "\n";
        $html .= '<h2>' . $titles['guide'] . '</h2>' . "\n";
        
        $guide = $this->get_category_buying_guide($category);
        
        foreach ($guide as $section) {
            $html .= '<div class="guide-section">' . "\n";
            $html .= '<h3>' . $this->safe_esc_html($section['title']) . '</h3>' . "\n";
            $html .= '<p>' . $this->safe_esc_html($section['content']) . '</p>' . "\n";
            if (isset($section['list'])) {
                $html .= '<ul>' . "\n";
                foreach ($section['list'] as $item) {
                    $html .= '<li>' . $this->safe_esc_html($item) . '</li>' . "\n";
                }
                $html .= '</ul>' . "\n";
            }
            $html .= '</div>' . "\n";
        }
        
        $html .= '</section>' . "\n\n";
        
        return $html;
    }
    
    /**
     * Generate natural conclusion section
     */
    private function generate_natural_conclusion($category) {
        $html = '<section class="natural-conclusion">' . "\n";
        $html .= '<h2>🎯 Sonuç ve Değerlendirme</h2>' . "\n";
        
        $conclusions = $this->get_category_conclusions($category);
        $selected_conclusion = $conclusions[array_rand($conclusions)];
        
        $html .= '<p>' . sprintf($selected_conclusion, $this->safe_esc_html($this->product_name)) . '</p>' . "\n";
        
        // Summary box
        $html .= '<div class="conclusion-summary">' . "\n";
        $html .= '<h3>📋 Özet Değerlendirme</h3>' . "\n";
        $html .= '<div class="summary-grid">' . "\n";
        $html .= '<div class="rating-item"><strong>Genel Puan:</strong> ⭐⭐⭐⭐⭐ (4.5/5)</div>' . "\n";
        $html .= '<div class="rating-item"><strong>Fiyat-Performans:</strong> ⭐⭐⭐⭐☆ (4/5)</div>' . "\n";
        $html .= '<div class="rating-item"><strong>Kullanım Kolaylığı:</strong> ⭐⭐⭐⭐⭐ (5/5)</div>' . "\n";
        $html .= '<div class="rating-item"><strong>Tavsiye Durumu:</strong> ✅ Kesinlikle Tavsiye Edilir</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '</section>' . "\n\n";
        
        $html .= '</article>' . "\n";
        
        return $html;
    }
    
    /**
     * Generate rich schema markup
     */
    private function generate_rich_schema($category) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'itemReviewed' => array(
                '@type' => 'Product',
                'name' => $this->product_name,
                'description' => $this->product_name . ' detaylı inceleme ve teknik analiz',
                'category' => ucfirst($category)
            ),
            'author' => array(
                '@type' => 'Person',
                'name' => 'Teknoloji Uzmanı'
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => $this->safe_get_bloginfo('name'),
                'url' => $this->safe_home_url()
            ),
            'datePublished' => date('c'),
            'dateModified' => date('c'),
            'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => '4.5',
                'bestRating' => '5',
                'worstRating' => '1'
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => $this->safe_get_permalink()
            )
        );
        
        $html = '<script type="application/ld+json">' . "\n";
        $html .= json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $html .= '</script>' . "\n";
        
        return $html;
    }
    
    /**
     * Get category specific pros and cons
     */
    private function get_category_pros_cons($category) {
        switch ($category) {
            case '3d_printer':
                return array(
                    'pros' => array(
                        'Yüksek baskı kalitesi ve hassasiyet',
                        'Kullanım kolaylığı ve hızlı kurulum',
                        'Geniş malzeme desteği',
                        'Güvenilir performans',
                        'İyi fiyat-performans oranı',
                        'Güçlü topluluk desteği'
                    ),
                    'cons' => array(
                        'Başlangıç öğrenme eğrisi',
                        'Düzenli bakım gereksinimleri',
                        'Gürültü seviyesi',
                        'Baskı hızı sınırları'
                    )
                );
            case 'smartphone':
                return array(
                    'pros' => array(
                        'Üstün kamera performansı',
                        'Hızlı işlemci ve akıcı kullanım',
                        'Premium yapı kalitesi',
                        'Uzun yazılım desteği',
                        'Güvenlik ve gizlilik özellikleri',
                        'Ekosistem entegrasyonu'
                    ),
                    'cons' => array(
                        'Yüksek fiyat segmenti',
                        'Batarya ömrü beklentisi',
                        'Tamir maliyetleri',
                        'Depolama genişletme sınırları'
                    )
                );
            default:
                return array(
                    'pros' => array(
                        'Kaliteli yapı ve malzemeler',
                        'Kullanıcı dostu tasarım',
                        'Güvenilir performans',
                        'İyi müşteri desteği',
                        'Kapsamlı garanti',
                        'Kolay kurulum ve kullanım'
                    ),
                    'cons' => array(
                        'Fiyat faktörü',
                        'Öğrenme süreci',
                        'Bakım gereksinimleri',
                        'Alternatiflere göre limitasyonlar'
                    )
                );
        }
    }
    
    /**
     * Get category specific user experiences
     */
    private function get_category_experiences($category) {
        switch ($category) {
            case '3d_printer':
                return array(
                    array(
                        'title' => '🔧 Kurulum ve İlk Kullanım',
                        'content' => 'Kurulum süreci oldukça basit ve kullanıcı dostu. Detaylı kılavuz ve video desteği sayesinde 30 dakika içinde baskıya başlayabilirsiniz.'
                    ),
                    array(
                        'title' => '🎯 Baskı Kalitesi',
                        'content' => 'Hassas kalibrasyon sistemi sayesinde mükemmel baskı kalitesi elde edebilirsiniz. Katman kalınlığı ayarları ile istediğiniz detay seviyesine ulaşabilirsiniz.'
                    ),
                    array(
                        'title' => '⚡ Günlük Kullanım',
                        'content' => 'Sessiz çalışma modu sayesinde evde rahatlıkla kullanabilirsiniz. Otomatik filament algılama ve pause&resume özellikleri kullanım kolaylığı sağlar.'
                    )
                );
            case 'smartphone':
                return array(
                    array(
                        'title' => '📱 İlk İzlenimler',
                        'content' => 'Premium malzemeler ve mükemmel işçilik kalitesi ilk elden hissediliyor. Ergonomik tasarım uzun kullanımda bile konforu koruyor.'
                    ),
                    array(
                        'title' => '📸 Kamera Deneyimi',
                        'content' => 'Gece çekimlerinden portre moduna kadar her durumda profesyonel sonuçlar. AI destekli özellikler fotoğrafçılığı bir üst seviyeye taşıyor.'
                    ),
                    array(
                        'title' => '🔋 Batarya Performansı',
                        'content' => 'Yoğun kullanımda bile gün boyu dayanabilen batarya. Hızlı şarj teknolojisi sayesinde kısa sürede şarj olabiliyor.'
                    )
                );
            default:
                return array(
                    array(
                        'title' => '🚀 İlk Kurulum',
                        'content' => 'Kullanıcı dostu kurulum süreci sayesinde hızlı bir şekilde kullanmaya başlayabilirsiniz. Detaylı rehberler mevcut.'
                    ),
                    array(
                        'title' => '💯 Günlük Performans',
                        'content' => 'Güvenilir ve tutarlı performans sergiler. Uzun süreli kullanımda bile kalitesinden ödün vermez.'
                    ),
                    array(
                        'title' => '🎯 Kullanım Kolaylığı',
                        'content' => 'Sezgisel arayüz ve ergonomik tasarım sayesinde her seviyeden kullanıcı rahatlıkla kullanabilir.'
                    )
                );
        }
    }
    
    /**
     * Get category specific buying guide
     */
    private function get_category_buying_guide($category) {
        switch ($category) {
            case '3d_printer':
                return array(
                    array(
                        'title' => '🎯 Kimler İçin Uygun',
                        'content' => 'Hem hobici kullanıcılar hem de küçük işletmeler için ideal. Başlangıç seviyesinden ileri düzeye kadar her kullanıcıya hitap eder.',
                        'list' => array(
                            'Hobi amaçlı kullanıcılar',
                            'Prototipleme ihtiyacı olanlar',
                            'Eğitim kurumları',
                            'Küçük işletmeler'
                        )
                    ),
                    array(
                        'title' => '💰 Fiyat-Performans Analizi',
                        'content' => 'Kategorisinde sunduğu özellikler göz önüne alındığında oldukça rekabetçi fiyat. Uzun vadeli yatırım olarak değerlendirilebilir.'
                    ),
                    array(
                        'title' => '🔄 Alternatiflerle Karşılaştırma',
                        'content' => 'Rakip modellere göre daha iyi kullanıcı deneyimi ve güvenilirlik sunuyor. Topluluk desteği de güçlü.'
                    )
                );
            case 'smartphone':
                return array(
                    array(
                        'title' => '👥 Hedef Kitle',
                        'content' => 'Premium deneyim arayan, kamera kalitesine önem veren ve uzun süreli yazılım desteği bekleyen kullanıcılar için ideal.',
                        'list' => array(
                            'Profesyonel fotoğrafçılar',
                            'İş dünyası kullanıcıları',
                            'Teknoloji meraklıları',
                            'Kalite odaklı tüketiciler'
                        )
                    ),
                    array(
                        'title' => '⚖️ Alternatif Değerlendirme',
                        'content' => 'Benzer fiyat segmentindeki rakiplerle karşılaştırıldığında, özellikle kamera ve yapı kalitesinde öne çıkıyor.'
                    ),
                    array(
                        'title' => '💡 Satın Alma Önerileri',
                        'content' => 'Kampanya dönemlerini takip edin. Operatör anlaşmaları ve trade-in programları fiyatı daha uygun hale getirebilir.'
                    )
                );
            default:
                return array(
                    array(
                        'title' => '🎯 Uygunluk Değerlendirmesi',
                        'content' => 'Geniş kullanıcı kitlesine hitap eden ürün. İhtiyaçlarınızı karşılayıp karşılamadığını değerlendirin.',
                        'list' => array(
                            'Kalite arayan kullanıcılar',
                            'Uzun vadeli kullanım planlayanlar',
                            'Güvenilirlik önceliği olanlar',
                            'Destek hizmeti önemseyen müşteriler'
                        )
                    ),
                    array(
                        'title' => '📊 Karşılaştırma Kriterleri',
                        'content' => 'Satın alma kararı öncesi özellik, fiyat, garanti ve destek hizmetlerini karşılaştırın.'
                    )
                );
        }
    }
    
    /**
     * Get category specific conclusions
     */
    private function get_category_conclusions($category) {
        switch ($category) {
            case '3d_printer':
                return array(
                    '%s, 3D baskı dünyasında kalite ve kullanıcı deneyimini önemseyen kullanıcılar için mükemmel bir seçim. Sunduğu özellikler ve fiyat-performans oranı göz önüne alındığında, hem başlangıç hem de orta seviye kullanıcılar için tavsiye edilebilir.',
                    '3D baskı teknolojisinde güvenilir bir partner arıyorsanız, %s kesinlikle değerlendirmeniz gereken modeller arasında. Kullanım kolaylığı ve kaliteli sonuçlar bir arada sunuyor.',
                    '%s ile 3D baskı deneyiminizi bir üst seviyeye taşıyabilirsiniz. Teknolojik özellikleri ve kullanıcı dostu yaklaşımı ile kategorisinde fark yaratıyor.'
                );
            case 'smartphone':
                return array(
                    '%s, premium akıllı telefon segmentinde kendini kanıtlamış bir model. Kamera performansından batarya ömrüne kadar her alanda üstün deneyim sunuyor.',
                    'Akıllı telefon deneyiminde kaliteyi ön planda tutan kullanıcılar için %s ideal bir tercih. Uzun vadeli yatırım olarak değerlendirilebilir.',
                    '%s ile teknolojinin sunduğu en iyi mobil deneyimi yaşayabilirsiniz. Hem iş hem de kişisel kullanım için mükemmel performans sergiliyor.'
                );
            default:
                return array(
                    '%s, kategorisinde öne çıkan özellikleri ve güvenilir performansı ile kullanıcılarına değer katıyor. Kalite ve işlevsellik dengesini başarıyla kuran bir ürün.',
                    'Güvenilir ve kaliteli bir ürün arıyorsanız, %s gereksinimlerinizi karşılayacak kapasitede. Uzun vadeli kullanım için uygun bir yatırım.',
                    '%s, modern teknolojinin sunduğu imkanları kullanıcı dostu bir yaklaşımla harmanlıyor. Hem performans hem de kullanım kolaylığı arayan kullanıcılar için ideal.'
                );
        }
    }
    
} 