<?php
/**
 * Plugin Name: WP SEO AI Blog Generator
 * Plugin URI: https://github.com/saffetoge/wp-seo-ai-blog-generator
 * Description: WordPress eklentisi ile ürün adına göre SEO uyumlu blog yazıları oluşturun. Teknik özellikler ve açıklamaları otomatik olarak içerir.
 * Version: 1.0.1
 * Author: Saffet Öge
 * Author URI: https://github.com/saffetoge
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-product-blog-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * GitHub Plugin URI: https://github.com/saffetoge/wp-seo-ai-blog-generator
 * Primary Branch: main
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPBG_VERSION', '1.0.1');
define('WPBG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPBG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPBG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class WP_Product_Blog_Generator {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get the instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_update_checker();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_generate_product_blog', array($this, 'generate_product_blog_ajax'));
        add_action('wp_ajax_save_as_wordpress_post', array($this, 'save_as_wordpress_post_ajax'));
        add_action('wp_ajax_improve_content', array($this, 'improve_content_ajax'));
        add_action('wp_ajax_analyze_seo', array($this, 'analyze_seo_ajax'));
        add_action('wp_ajax_fetch_ai_models', array($this, 'fetch_ai_models_ajax'));
        add_action('wp_ajax_test_ai_connection', array($this, 'test_ai_connection_ajax'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        add_option('wpbg_settings', array(
            'default_template' => 'standard',
            'seo_enabled' => true,
            'schema_enabled' => true
        ));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary data
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'product_blog_generator';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_name varchar(255) NOT NULL,
            generated_content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-product-blog-generator', false, dirname(WPBG_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Product Blog Generator', 'wp-product-blog-generator'),
            __('Blog Generator', 'wp-product-blog-generator'),
            'manage_options',
            'wp-product-blog-generator',
            array($this, 'admin_page'),
            'dashicons-edit-page',
            30
        );
        
        // Add settings submenu
        add_submenu_page(
            'wp-product-blog-generator',
            __('AI Ayarları', 'wp-product-blog-generator'),
            __('AI Ayarları', 'wp-product-blog-generator'),
            'manage_options',
            'wpbg-ai-settings',
            array($this, 'ai_settings_page')
        );

        // Add Content Improver submenu
        add_submenu_page(
            'wp-product-blog-generator',
            __('İçerik İyileştirici', 'wp-product-blog-generator'),
            __('İçerik İyileştirici', 'wp-product-blog-generator'),
            'manage_options',
            'wpbg-content-improver',
            array($this, 'content_improver_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_wp-product-blog-generator' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wpbg-admin-style', WPBG_PLUGIN_URL . 'assets/admin-style.css', array(), WPBG_VERSION);
        wp_enqueue_script('wpbg-admin-script', WPBG_PLUGIN_URL . 'assets/admin-script.js', array('jquery'), WPBG_VERSION, true);
        
        wp_localize_script('wpbg-admin-script', 'wpbg_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpbg_nonce'),
            'generating_text' => __('Blog yazısı oluşturuluyor...', 'wp-product-blog-generator'),
            'error_text' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'wp-product-blog-generator')
        ));
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpbg-container">
                <div class="wpbg-form-section">
                    <h2><?php _e('Ürün Blog Yazısı Oluştur', 'wp-product-blog-generator'); ?></h2>
                    
                    <form id="wpbg-product-form">
                        <?php wp_nonce_field('wpbg_nonce', 'wpbg_nonce_field'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="product_name"><?php _e('Ürün Adı', 'wp-product-blog-generator'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="product_name" name="product_name" class="regular-text" required 
                                           placeholder="<?php _e('Örn: iPhone 15 Pro Max', 'wp-product-blog-generator'); ?>">
                                    <p class="description"><?php _e('Blog yazısı oluşturmak istediğiniz ürünün adını girin.', 'wp-product-blog-generator'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="generate_blog" id="generate_blog" class="button-primary" 
                                   value="<?php _e('Blog Yazısı Oluştur', 'wp-product-blog-generator'); ?>">
                            <span class="spinner"></span>
                        </p>
                    </form>
                </div>
                
                <div id="wpbg-result-section" class="wpbg-result-section" style="display: none;">
                    <h2><?php _e('Oluşturulan Blog Yazısı', 'wp-product-blog-generator'); ?></h2>
                    <div id="wpbg-generated-content"></div>
                    
                    <div class="wpbg-actions">
                        <button type="button" id="copy-content" class="button"><?php _e('İçeriği Kopyala', 'wp-product-blog-generator'); ?></button>
                        <button type="button" id="save-as-post" class="button-secondary"><?php _e('Yazı Olarak Kaydet', 'wp-product-blog-generator'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for generating product blog
     */
    public function generate_product_blog_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpbg_nonce')) {
            wp_die(__('Güvenlik kontrolü başarısız.', 'wp-product-blog-generator'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz bulunmuyor.', 'wp-product-blog-generator'));
        }
        
        $product_name = sanitize_text_field($_POST['product_name']);
        
        if (empty($product_name)) {
            wp_send_json_error(__('Ürün adı boş olamaz.', 'wp-product-blog-generator'));
        }
        
        // Generate blog content
        $generated_content = $this->generate_blog_content($product_name);
        
        // Save to database
        $this->save_generated_content($product_name, $generated_content);
        
        wp_send_json_success(array(
            'content' => $generated_content,
            'message' => __('Blog yazısı başarıyla oluşturuldu!', 'wp-product-blog-generator')
        ));
    }
    
    /**
     * Generate blog content for product
     */
    private function generate_blog_content($product_name) {
        // Include the required classes
        require_once WPBG_PLUGIN_DIR . 'includes/class-ai-integration.php';
        require_once WPBG_PLUGIN_DIR . 'includes/class-blog-generator.php';
        
        $generator = new WPBG_Blog_Generator();
        return $generator->generate_content($product_name);
    }
    
    /**
     * Save generated content to database
     */
    private function save_generated_content($product_name, $content) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'product_blog_generator';
        
        $wpdb->insert(
            $table_name,
            array(
                'product_name' => $product_name,
                'generated_content' => $content,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX handler for saving content as WordPress post
     */
    public function save_as_wordpress_post_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpbg_nonce')) {
            wp_send_json_error('Güvenlik kontrolü başarısız.');
        }
        
        // Check permissions
        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Yazı yayınlama yetkiniz bulunmuyor.');
        }
        
        $product_name = sanitize_text_field($_POST['product_name']);
        $content = wp_kses_post($_POST['content']);
        
        if (empty($product_name) || empty($content)) {
            wp_send_json_error('Gerekli alanlar eksik.');
        }
        
        // Create post
        $post_data = array(
            'post_title' => $product_name . ' İncelemesi: Teknik Özellikler ve Kullanım Rehberi',
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'meta_input' => array(
                '_wpbg_generated' => true,
                '_wpbg_product_name' => $product_name,
                '_wpbg_generated_date' => current_time('mysql')
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error('Yazı kaydedilemedi: ' . $post_id->get_error_message());
        }
        
        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'message' => 'Blog yazısı başarıyla kaydedildi!'
        ));
    }
    
    /**
     * AI Settings page
     */
    public function ai_settings_page() {
        // Handle form submission
        if (isset($_POST['save_ai_settings'])) {
            $this->save_ai_settings();
        }
        
        $current_settings = get_option('wpbg_ai_settings', array(
            'ai_provider' => 'gemini',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-1.5-flash',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o',
            'content_language' => 'tr',
            'content_style' => 'professional',
            'include_images' => true,
            'github_access_token' => ''
        ));
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpbg-ai-settings">
                <form method="post" action="">
                    <?php wp_nonce_field('wpbg_ai_settings', 'wpbg_ai_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_provider"><?php _e('AI Sağlayıcısı', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <select id="ai_provider" name="ai_provider" class="regular-text">
                                    <option value="gemini" <?php selected($current_settings['ai_provider'], 'gemini'); ?>>
                                        Google Gemini Pro
                                    </option>
                                    <option value="openai" <?php selected($current_settings['ai_provider'], 'openai'); ?>>
                                        OpenAI ChatGPT
                                    </option>
                                </select>
                                <p class="description"><?php _e('İçerik üretimi için kullanılacak AI servisini seçin.', 'wp-product-blog-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="gemini-settings" <?php echo $current_settings['ai_provider'] !== 'gemini' ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="gemini_api_key"><?php _e('Gemini API Key', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="gemini_api_key" name="gemini_api_key" 
                                       value="<?php echo esc_attr($current_settings['gemini_api_key']); ?>" 
                                       class="regular-text" placeholder="AIzaSy...">
                                <button type="button" class="button fetch-models" data-provider="gemini"><?php _e('Modelleri Getir', 'wp-product-blog-generator'); ?></button>
                                <p class="description">
                                    <?php _e('Google AI Studio\'dan alacağınız API anahtarını girin.', 'wp-product-blog-generator'); ?>
                                    <a href="https://aistudio.google.com/app/apikey" target="_blank"><?php _e('API anahtarı al', 'wp-product-blog-generator'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr class="gemini-settings" <?php echo $current_settings['ai_provider'] !== 'gemini' ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="gemini_model"><?php _e('Gemini Modeli', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <select id="gemini_model" name="gemini_model" class="regular-text ai-model-select">
                                    <option value="<?php echo esc_attr($current_settings['gemini_model']); ?>">
                                        <?php echo esc_html($current_settings['gemini_model']); ?> (Aktif)
                                    </option>
                                </select>
                                <p class="description"><?php _e('Kullanılacak Gemini modelini seçin.', 'wp-product-blog-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr class="openai-settings" <?php echo $current_settings['ai_provider'] !== 'openai' ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="openai_api_key"><?php _e('OpenAI API Key', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="openai_api_key" name="openai_api_key" 
                                       value="<?php echo esc_attr($current_settings['openai_api_key']); ?>" 
                                       class="regular-text" placeholder="sk-...">
                                <button type="button" class="button fetch-models" data-provider="openai"><?php _e('Modelleri Getir', 'wp-product-blog-generator'); ?></button>
                                <p class="description">
                                    <?php _e('OpenAI platformundan alacağınız API anahtarını girin.', 'wp-product-blog-generator'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('API anahtarı al', 'wp-product-blog-generator'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr class="openai-settings" <?php echo $current_settings['ai_provider'] !== 'openai' ? 'style="display:none;"' : ''; ?>>
                            <th scope="row">
                                <label for="openai_model"><?php _e('OpenAI Modeli', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <select id="openai_model" name="openai_model" class="regular-text ai-model-select">
                                    <option value="<?php echo esc_attr($current_settings['openai_model']); ?>">
                                        <?php echo esc_html($current_settings['openai_model']); ?> (Aktif)
                                    </option>
                                </select>
                                <p class="description"><?php _e('Kullanılacak ChatGPT modelini seçin.', 'wp-product-blog-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="content_language"><?php _e('İçerik Dili', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <select id="content_language" name="content_language" class="regular-text">
                                    <option value="tr" <?php selected($current_settings['content_language'], 'tr'); ?>>Türkçe</option>
                                    <option value="en" <?php selected($current_settings['content_language'], 'en'); ?>>English</option>
                                    <option value="de" <?php selected($current_settings['content_language'], 'de'); ?>>Deutsch</option>
                                    <option value="fr" <?php selected($current_settings['content_language'], 'fr'); ?>>Français</option>
                                </select>
                                <p class="description"><?php _e('Oluşturulacak içeriğin dili.', 'wp-product-blog-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="content_style"><?php _e('İçerik Stili', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <select id="content_style" name="content_style" class="regular-text">
                                    <option value="professional" <?php selected($current_settings['content_style'], 'professional'); ?>>Profesyonel</option>
                                    <option value="casual" <?php selected($current_settings['content_style'], 'casual'); ?>>Samimi</option>
                                    <option value="technical" <?php selected($current_settings['content_style'], 'technical'); ?>>Teknik</option>
                                    <option value="review" <?php selected($current_settings['content_style'], 'review'); ?>>İnceleme</option>
                                </select>
                                <p class="description"><?php _e('İçeriğin yazım stili ve tonu.', 'wp-product-blog-generator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="include_images"><?php _e('Görsel Önerileri', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="include_images" name="include_images" value="1" 
                                           <?php checked($current_settings['include_images'], true); ?>>
                                    <?php _e('İçeriğe görsel önerileri ekle', 'wp-product-blog-generator'); ?>
                                </label>
                                    <?php _e('AI, içeriğe uygun görsel önerilerini de ekleyecek.', 'wp-product-blog-generator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="github_access_token"><?php _e('GitHub Access Token (Private Repo)', 'wp-product-blog-generator'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="github_access_token" name="github_access_token" 
                                       value="<?php echo esc_attr($current_settings['github_access_token']); ?>" 
                                       class="regular-text" placeholder="github_pat_...">
                                <p class="description">
                                    <?php _e('Özel (Private) GitHub deponuzdan güncelleme alabilmek için gerekli token.', 'wp-product-blog-generator'); ?>
                                    <a href="https://github.com/settings/tokens" target="_blank"><?php _e('Token oluştur (repo yetkisiyle)', 'wp-product-blog-generator'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="save_ai_settings" id="save_ai_settings" 
                               class="button-primary" value="<?php _e('Ayarları Kaydet', 'wp-product-blog-generator'); ?>">
                        <button type="button" id="test_ai_connection" class="button">
                            <?php _e('Bağlantıyı Test Et', 'wp-product-blog-generator'); ?>
                        </button>
                        <a href="<?php echo esc_url(add_query_arg('refresh_updates', '1', admin_url('admin.php?page=wpbg-ai-settings'))); ?>" 
                           class="button button-secondary">
                            <?php _e('Güncellemeleri Kontrol Et', 'wp-product-blog-generator'); ?>
                        </a>
                    </p>
                </form>
                
                <div id="test-result" style="display:none; margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Show/hide API key fields based on provider selection
            $('#ai_provider').change(function() {
                const provider = $(this).val();
                $('.gemini-settings, .openai-settings').hide();
                $('.' + provider + '-settings').show();
            });
            
            // Fetch models
            $('.fetch-models').click(function() {
                const button = $(this);
                const provider = button.data('provider');
                const apiKey = $('#' + provider + '_api_key').val();
                const select = $('#' + provider + '_model');
                
                if (!apiKey) {
                    alert('Lütfen önce API anahtarını girin.');
                    return;
                }
                
                button.prop('disabled', true).text('Modeller listeleniyor...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'fetch_ai_models',
                        ai_provider: provider,
                        api_key: apiKey,
                        nonce: '<?php echo wp_create_nonce('wpbg_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            select.empty();
                            response.data.forEach(function(model) {
                                select.append($('<option>', {
                                    value: model.id,
                                    text: model.name
                                }));
                            });
                            alert(provider.charAt(0).toUpperCase() + provider.slice(1) + ' modelleri başarıyla güncellendi.');
                        } else {
                            alert('Hata: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        const details = xhr && xhr.responseText ? ('\n' + xhr.responseText) : '';
                        alert('API ile iletişim kurulurken bir hata oluştu: ' + error + details);
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Modelleri Getir');
                    }
                });
            });

            // Test AI connection
            $('#test_ai_connection').click(function() {
                const button = $(this);
                const result = $('#test-result');
                
                button.prop('disabled', true).text('Test ediliyor...');
                result.hide();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'test_ai_connection',
                        ai_provider: $('#ai_provider').val(),
                        gemini_api_key: $('#gemini_api_key').val(),
                        openai_api_key: $('#openai_api_key').val(),
                        nonce: '<?php echo wp_create_nonce('test_ai_connection'); ?>'
                    },
                    success: function(response) {
                        console.log('Test response:', response);
                        if (response && response.success) {
                            result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                        } else {
                            const errorMsg = response && response.data ? response.data : 'API test başarısız.';
                            result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        result.html('<div class="notice notice-error"><p>Test sırasında bir hata oluştu: ' + error + '</p></div>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Bağlantıyı Test Et');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Content Improver page
     */
    public function content_improver_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('İçerik İyileştirici & SEO Analizi', 'wp-product-blog-generator'); ?></h1>
            <p class="description"><?php _e('Mevcut yazılarınızı AI desteğiyle SEO uyumlu hale getirin ve analiz edin.', 'wp-product-blog-generator'); ?></p>

            <div class="wpbg-container">
                <div class="wpbg-editor-section">
                    <div class="wpbg-input-group" style="margin-bottom: 20px;">
                        <label for="wpbg-focus-keyword"><strong><?php _e('Odak Anahtar Kelime:', 'wp-product-blog-generator'); ?></strong></label>
                        <input type="text" id="wpbg-focus-keyword" class="regular-text" placeholder="<?php _e('Örn: en iyi oyun bilgisayarı', 'wp-product-blog-generator'); ?>">
                    </div>

                    <div class="wpbg-editor-wrapper" style="margin-bottom: 20px;">
                        <?php 
                        wp_editor('', 'wpbg_content_editor', array(
                            'textarea_name' => 'wpbg_content_editor',
                            'media_buttons' => true,
                            'tinymce' => true,
                            'quicktags' => true,
                            'editor_height' => 450
                        )); 
                        ?>
                    </div>

                    <div class="wpbg-actions">
                        <button type="button" id="wpbg-analyze-seo" class="button button-large"><?php _e('SEO Analizi Yap', 'wp-product-blog-generator'); ?></button>
                        <button type="button" id="wpbg-improve-content" class="button button-primary button-large"><?php _e('Yazıyı Baştan Aşağı Düzenle', 'wp-product-blog-generator'); ?></button>
                        <span class="spinner"></span>
                    </div>
                </div>

                <div class="wpbg-sidebar-section" style="margin-top: 20px;">
                    <div id="wpbg-seo-results" class="wpbg-card" style="display:none; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                        <h3><?php _e('SEO Analiz Sonuçları', 'wp-product-blog-generator'); ?></h3>
                        <div class="wpbg-score-bars">
                            <div class="wpbg-score-item" style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span><?php _e('SEO Skoru:', 'wp-product-blog-generator'); ?></span>
                                    <span id="seo-score-text">0/100</span>
                                </div>
                                <div class="wpbg-progress" style="background: #eee; height: 10px; border-radius: 5px; overflow: hidden;">
                                    <div id="seo-score-bar" class="bar" style="height: 100%; transition: width 0.3s;"></div>
                                </div>
                            </div>
                            <div class="wpbg-score-item" style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span><?php _e('Okunabilirlik:', 'wp-product-blog-generator'); ?></span>
                                    <span id="readability-score-text">0/100</span>
                                </div>
                                <div class="wpbg-progress" style="background: #eee; height: 10px; border-radius: 5px; overflow: hidden;">
                                    <div id="readability-score-bar" class="bar" style="height: 100%; transition: width 0.3s;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="wpbg-analysis-details">
                            <h4 style="color: #d63638;"><?php _e('Kritik Hatalar', 'wp-product-blog-generator'); ?></h4>
                            <ul id="wpbg-errors-list" class="wpbg-list errors" style="list-style-type: decimal; margin-left: 20px;"></ul>

                            <h4 style="color: #dba617;"><?php _e('İyileştirme Önerileri', 'wp-product-blog-generator'); ?></h4>
                            <ul id="wpbg-suggestions-list" class="wpbg-list suggestions" style="list-style-type: disc; margin-left: 20px;"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for content improvement
     */
    public function improve_content_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbg_nonce')) {
            wp_send_json_error('Güvenlik kontrolü başarısız.');
        }

        $content = stripslashes($_POST['content']);
        $keyword = sanitize_text_field($_POST['keyword']);

        if (empty($content)) {
            wp_send_json_error('İçerik boş olamaz.');
        }

        require_once WPBG_PLUGIN_DIR . 'includes/class-ai-integration.php';
        $ai = new WPBG_AI_Integration();
        $result = $ai->improve_content($content, $keyword);

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success($result['content']);
    }

    /**
     * AJAX handler for SEO analysis
     */
    public function analyze_seo_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpbg_nonce')) {
            wp_send_json_error('Güvenlik kontrolü başarısız.');
        }

        $content = stripslashes($_POST['content']);
        $keyword = sanitize_text_field($_POST['keyword']);

        if (empty($content)) {
            wp_send_json_error('Analiz edilecek içerik boş.');
        }

        require_once WPBG_PLUGIN_DIR . 'includes/class-ai-integration.php';
        $ai = new WPBG_AI_Integration();
        $result = $ai->analyze_seo($content, $keyword);

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success($result);
    }
    
    /**
     * Save AI settings
     */
    private function save_ai_settings() {
        if (!wp_verify_nonce($_POST['wpbg_ai_settings_nonce'], 'wpbg_ai_settings')) {
            wp_die(__('Güvenlik kontrolü başarısız.', 'wp-product-blog-generator'));
        }

        $existing_settings = get_option('wpbg_ai_settings', array());
        $ai_provider = isset($_POST['ai_provider']) ? sanitize_text_field(wp_unslash($_POST['ai_provider'])) : 'gemini';
        $gemini_api_key = isset($_POST['gemini_api_key']) ? sanitize_text_field(wp_unslash($_POST['gemini_api_key'])) : '';
        $gemini_model = isset($_POST['gemini_model']) ? sanitize_text_field(wp_unslash($_POST['gemini_model'])) : '';
        $openai_api_key = isset($_POST['openai_api_key']) ? sanitize_text_field(wp_unslash($_POST['openai_api_key'])) : '';
        $openai_model = isset($_POST['openai_model']) ? sanitize_text_field(wp_unslash($_POST['openai_model'])) : '';

        if ($gemini_api_key === '' && !empty($existing_settings['gemini_api_key'])) {
            $gemini_api_key = $existing_settings['gemini_api_key'];
        }
        if ($gemini_model === '' && !empty($existing_settings['gemini_model'])) {
            $gemini_model = $existing_settings['gemini_model'];
        }
        if ($openai_api_key === '' && !empty($existing_settings['openai_api_key'])) {
            $openai_api_key = $existing_settings['openai_api_key'];
        }
        if ($openai_model === '' && !empty($existing_settings['openai_model'])) {
            $openai_model = $existing_settings['openai_model'];
        }
        
        $settings = array(
            'ai_provider' => $ai_provider,
            'gemini_api_key' => $gemini_api_key,
            'gemini_model' => $gemini_model,
            'openai_api_key' => $openai_api_key,
            'openai_model' => $openai_model,
            'content_language' => sanitize_text_field(wp_unslash($_POST['content_language'])),
            'content_style' => sanitize_text_field(wp_unslash($_POST['content_style'])),
            'include_images' => isset($_POST['include_images']),
            'github_access_token' => isset($_POST['github_access_token']) ? sanitize_text_field(wp_unslash($_POST['github_access_token'])) : ''
        );
        
        update_option('wpbg_ai_settings', $settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>AI ayarları başarıyla kaydedildi!</p></div>';
        });
    }

    /**
     * Initialize the GitHub Update Checker
     */
    public function init_update_checker() {
        require_once WPBG_PLUGIN_DIR . 'includes/class-update-checker.php';
        new WPBG_Update_Checker();
    }

    /**
     * AJAX handler for fetching AI models
     */
    public function fetch_ai_models_ajax() {
        require_once WPBG_PLUGIN_DIR . 'includes/class-ai-integration.php';
        $ai = new WPBG_AI_Integration();
        $ai->fetch_ai_models_ajax();
    }

    /**
     * AJAX handler for testing AI connection
     */
    public function test_ai_connection_ajax() {
        require_once WPBG_PLUGIN_DIR . 'includes/class-ai-integration.php';
        $ai = new WPBG_AI_Integration();
        $ai->test_ai_connection();
    }
}

// Initialize the plugin
function wpbg_init() {
    return WP_Product_Blog_Generator::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'wpbg_init'); 
