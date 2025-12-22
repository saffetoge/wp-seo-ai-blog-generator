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
        $this->plugin_file = 'wp-product-blog-generator/wp-product-blog-generator.php';
        $this->plugin_slug = 'wp-product-blog-generator';

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

        if ($remote && version_compare($this->version, $remote->tag_name, '<')) {
            $res = new stdClass();
            $res->slug = $this->plugin_slug;
            $res->plugin = $this->plugin_file;
            $res->new_version = $remote->tag_name;
            $res->package = $remote->zipball_url;
            $res->url = 'https://github.com/' . $this->username . '/' . $this->repository;

            // For private repos, add the token to the package URL
            if ($this->github_token) {
                $res->package = add_query_arg('access_token', $this->github_token, $res->package);
            }

            $transient->response[$this->plugin_file] = $res;
        }

        return $transient;
    }

    /**
     * Get remote data from GitHub API
     */
    private function get_remote_data()
    {
        $remote = get_transient('wpbg_github_update_data');

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
        $res->download_link = $remote->zipball_url;
        $res->sections = array(
            'description' => 'WordPress eklentisi ile ürün adına göre SEO uyumlu blog yazıları oluşturun. AI ve SEO iyileştirmeleri içerir.',
            'changelog' => $remote->body
        );

        return $res;
    }
}
