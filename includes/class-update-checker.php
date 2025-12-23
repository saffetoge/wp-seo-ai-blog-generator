<?php
/**
 * Simple GitHub Update Checker
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPBG_Update_Checker
{

    private $username = 'saffetoge';
    private $repository = 'wp-seo-ai-blog-generator';
    private $plugin_slug;
    private $plugin_file;
    private $version;
    private $github_token;

    public function __construct()
    {
        $this->plugin_file = WPBG_PLUGIN_BASENAME;
        $this->plugin_slug = dirname(WPBG_PLUGIN_BASENAME);

        // Get GitHub Token from settings
        $settings = get_option('wpbg_ai_settings');
        $this->github_token = isset($settings['github_access_token']) ? $settings['github_access_token'] : '';

        // Get current version from plugin header
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(WPBG_PLUGIN_DIR . 'wp-product-blog-generator.php');
        $this->version = $plugin_data['Version'];

        // Add filters to inject update data
        add_filter('site_transient_update_plugins', array($this, 'check_update'));
        add_filter('transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('http_request_args', array($this, 'add_github_auth_header'), 10, 2);
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_filter('upgrader_pre_download', array($this, 'pre_download_filter'), 10, 3);
        add_action('admin_notices', array($this, 'show_update_status_notice'));
    }

    /**
     * Check GitHub for updates
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_data();

        if ($remote) {
            $remote_version = ltrim($remote->tag_name, 'vV');

            error_log('WPBG Update Check - Local: ' . $this->version . ' | Remote: ' . $remote_version);

            if (version_compare($this->version, $remote_version, '<')) {
                $res = new stdClass();
                $res->slug = $this->plugin_slug;
                $res->plugin = $this->plugin_file;
                $res->new_version = $remote_version;
                $res->package = $this->get_release_download_url($remote);
                $res->url = 'https://github.com/' . $this->username . '/' . $this->repository;

                $transient->response[$this->plugin_file] = $res;
                error_log('WPBG Update Check: Update available and injected into transient.');
            }
        }

        return $transient;
    }

    /**
     * Get remote data from GitHub API
     */
    private function get_remote_data()
    {
        // Force refresh if user is on the updates page or forced via setting
        $force_check = isset($_GET['force-check']) || (isset($_GET['page']) && $_GET['page'] === 'wpbg-ai-settings' && isset($_GET['refresh_updates']));

        $remote = $force_check ? false : get_transient('wpbg_github_update_data');

        if (false === $remote) {
            $url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";

            $args = array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
                )
            );

            // Add Authorization header for private repos
            if ($this->github_token) {
                $args['headers']['Authorization'] = 'token ' . $this->github_token;
            }

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return false;
            }

            $remote = json_decode(wp_remote_retrieve_body($response));
            set_transient('wpbg_github_update_data', $remote, 12 * HOUR_IN_SECONDS);
        }

        return $remote;
    }

    /**
     * Provide plugin info for the thickbox
     */
    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $res;
        }

        if ($this->plugin_slug !== $args->slug) {
            return $res;
        }

        $remote = $this->get_remote_data();

        if (!$remote) {
            return $res;
        }

        $res = new stdClass();
        $res->name = 'WP SEO AI Blog Generator';
        $res->slug = $this->plugin_slug;
        $res->version = $remote->tag_name;
        $res->author = 'Saffet Öge';
        $res->homepage = 'https://github.com/' . $this->username . '/' . $this->repository;
        $res->download_link = $this->get_release_download_url($remote);
        $res->sections = array(
            'description' => 'WordPress eklentisi ile ürün adına göre SEO uyumlu blog yazıları oluşturun. AI ve SEO iyileştirmeleri içerir.',
            'changelog' => $remote->body
        );

        return $res;
    }

    /**
     * Get download URL - prefer GitHub zipball for reliability.
     */
    private function get_release_download_url($remote)
    {
        if (isset($remote->zipball_url)) {
            return $remote->zipball_url;
        }

        if (isset($remote->assets) && is_array($remote->assets)) {
            foreach ($remote->assets as $asset) {
                if (!empty($asset->browser_download_url) && preg_match('/\.zip$/i', $asset->name)) {
                    return $asset->browser_download_url;
                }
            }
        }

        return '';
    }

    /**
     * Add GitHub authorization header for private repos.
     */
    public function add_github_auth_header($args, $url)
    {
        if (empty($this->github_token) || empty($url)) {
            return $args;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Include codeload.github.com for zipball downloads
        $allowed_hosts = array('github.com', 'api.github.com', 'codeload.github.com');
        $is_github = false;
        foreach ($allowed_hosts as $allowed) {
            if (strpos($host, $allowed) !== false) {
                $is_github = true;
                break;
            }
        }

        if (!$is_github) {
            return $args;
        }

        if (empty($args['headers'])) {
            $args['headers'] = array();
        }

        $args['headers']['Authorization'] = 'token ' . $this->github_token;

        return $args;
    }

    /**
     * Pre-download filter to handle GitHub private repo downloads
     */
    public function pre_download_filter($reply, $package, $upgrader)
    {
        if (empty($package) || empty($this->github_token)) {
            return $reply;
        }

        // Check if this is a GitHub URL for our repo
        if (strpos($package, 'github.com') === false && strpos($package, 'api.github.com') === false) {
            return $reply;
        }

        if (strpos($package, $this->repository) === false) {
            return $reply;
        }

        // Download the file ourselves with proper authentication
        $tmpfname = wp_tempnam($package);
        if (!$tmpfname) {
            return new WP_Error('http_error', __('Geçici dosya oluşturulamadı.', 'wp-product-blog-generator'));
        }

        $response = wp_remote_get($package, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $tmpfname,
            'headers' => array(
                'Authorization' => 'token ' . $this->github_token,
                'Accept' => 'application/vnd.github.v3.raw',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            )
        ));

        if (is_wp_error($response)) {
            @unlink($tmpfname);
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            @unlink($tmpfname);
            $body = wp_remote_retrieve_body($response);
            error_log('WPBG Download Error - URL: ' . $package);
            error_log('WPBG Download Error - HTTP Code: ' . $response_code);
            error_log('WPBG Download Error - Response: ' . $body);
            return new WP_Error('http_error', sprintf(__('İndirme hatası: HTTP %d - %s', 'wp-product-blog-generator'), $response_code, substr($body, 0, 200)));
        }

        // Check if file was actually downloaded
        if (!file_exists($tmpfname) || filesize($tmpfname) < 1000) {
            @unlink($tmpfname);
            return new WP_Error('http_error', __('Dosya indirilemedi veya boş.', 'wp-product-blog-generator'));
        }

        error_log('WPBG Download Success - File: ' . $tmpfname . ' Size: ' . filesize($tmpfname));
        return $tmpfname;
    }

    /**
     * Ensure the extracted folder matches the plugin slug for WP updates.
     */
    public function rename_github_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $source;
        }

        $desired_source = trailingslashit($remote_source) . $this->plugin_slug;

        if ($source === $desired_source) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem || !$wp_filesystem->is_dir($source)) {
            return $source;
        }

        $wp_filesystem->move($source, $desired_source, true);
        return $desired_source;
    }

    /**
     * Show admin notice about update status
     */
    public function show_update_status_notice()
    {
        if (!isset($_GET['refresh_updates']) || !isset($_GET['page']) || $_GET['page'] !== 'wpbg-ai-settings') {
            return;
        }

        $remote = $this->get_remote_data();

        if (!$remote) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('GitHub bağlantısı kurulamadı. Lütfen API token ayarlarınızı kontrol edin.', 'wp-product-blog-generator') . '</p></div>';
            return;
        }

        $remote_version = ltrim($remote->tag_name, 'vV');
        $has_update = version_compare($this->version, $remote_version, '<');

        if ($has_update) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf(__('Yeni bir güncelleme bulundu! Mevcut Versiyon: %s, Yeni Versiyon: %s. <a href="%s">Eklentiler</a> sayfasından güncelleyebilirsiniz.', 'wp-product-blog-generator'), $this->version, $remote_version, admin_url('plugins.php')) . '</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Eklentiniz güncel. Mevcut Versiyon: %s, GitHub Versiyonu: %s.', 'wp-product-blog-generator'), $this->version, $remote_version) . '</p></div>';
        }
    }
}
