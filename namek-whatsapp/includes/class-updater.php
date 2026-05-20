<?php
defined('ABSPATH') || exit;

/**
 * GitHub-based auto-updater.
 * Checks the latest GitHub release and surfaces it in the WP updates screen.
 *
 * Release workflow:
 *   1. Bump NAMEK_WA_VERSION in namek-whatsapp.php
 *   2. Run: bash build.sh
 *   3. git tag v1.x.x && git push origin v1.x.x
 *   4. Create GitHub Release, attach namek-whatsapp.zip as asset
 *   → All client sites will show "Update available" on their next check
 */
class Namek_WA_Updater {

    private $plugin_file;
    private $plugin_slug;
    private $repo;
    private $version;

    public function __construct($plugin_file, $github_repo) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->repo        = $github_repo; // e.g. 'namek-india/namek-whatsapp-wp'
        $this->version     = NAMEK_WA_VERSION;
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api',                           [$this, 'plugin_info'], 10, 3);
        add_action('upgrader_process_complete',             [$this, 'clear_cache'], 10, 2);
    }

    /** Inject update info into WP's update transient if a newer release exists. */
    public function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $release = $this->get_latest_release();
        if (!$release) return $transient;

        $latest = ltrim($release->tag_name, 'v');

        if (version_compare($this->version, $latest, '<')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug'        => dirname($this->plugin_slug),
                'plugin'      => $this->plugin_slug,
                'new_version' => $latest,
                'url'         => "https://github.com/{$this->repo}",
                'package'     => $this->zip_url($release),
                'icons'       => [],
                'banners'     => [],
                'requires'    => '5.8',
                'requires_php'=> '7.4',
            ];
        }

        return $transient;
    }

    /** Provide plugin details for the "View version x.x details" popup. */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (($args->slug ?? '') !== dirname($this->plugin_slug)) return $result;

        $release = $this->get_latest_release();
        if (!$release) return $result;

        return (object) [
            'name'          => 'Namek WhatsApp',
            'slug'          => dirname($this->plugin_slug),
            'version'       => ltrim($release->tag_name, 'v'),
            'author'        => '<a href="https://namek.co.in">Namek</a>',
            'requires'      => '5.8',
            'requires_php'  => '7.4',
            'download_link' => $this->zip_url($release),
            'sections'      => [
                'changelog' => nl2br(esc_html($release->body ?? 'See GitHub for changelog.')),
            ],
        ];
    }

    /** Clear cached release info after a plugin update completes. */
    public function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient('namek_wa_github_release');
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function get_latest_release() {
        $cached = get_transient('namek_wa_github_release');
        if ($cached !== false) return $cached;

        $response = wp_remote_get(
            "https://api.github.com/repos/{$this->repo}/releases/latest",
            [
                'headers' => ['Accept' => 'application/vnd.github.v3+json'],
                'timeout' => 10,
                'sslverify' => false,
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        // Cache for 12 hours so we don't hammer the GitHub API
        set_transient('namek_wa_github_release', $release, 12 * HOUR_IN_SECONDS);
        return $release;
    }

    private function zip_url($release) {
        // Prefer the namek-whatsapp.zip asset attached to the release
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if ($asset->name === 'namek-whatsapp.zip') {
                    return $asset->browser_download_url;
                }
            }
        }
        // Fallback: GitHub's auto-generated source zip
        return "https://github.com/{$this->repo}/archive/refs/tags/{$release->tag_name}.zip";
    }
}
