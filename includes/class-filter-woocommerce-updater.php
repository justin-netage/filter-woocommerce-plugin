<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates from a GitHub repository.
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Updater class
 *
 * Handles checking for updates from GitHub and integrating with WordPress update system.
 */
class Filter_WooCommerce_Updater {

    /**
     * Plugin file path
     *
     * @var string
     */
    private $file;

    /**
     * Plugin data
     *
     * @var array
     */
    private $plugin_data;

    /**
     * Plugin basename
     *
     * @var string
     */
    private $basename;

    /**
     * Plugin slug
     *
     * @var string
     */
    private $slug;

    /**
     * GitHub username
     *
     * @var string
     */
    private $github_username;

    /**
     * GitHub repository name
     *
     * @var string
     */
    private $github_repo;

    /**
     * GitHub API response
     *
     * @var object|null
     */
    private $github_response;

    /**
     * GitHub access token (optional, for private repos)
     *
     * @var string
     */
    private $access_token;

    /**
     * Constructor
     *
     * @param string $file Plugin file path.
     */
    public function __construct( $file ) {
        $this->file = $file;

        // Set GitHub repository details
        $this->github_username = 'justin-netage';
        $this->github_repo     = 'filter-woocommerce-plugin';

        // Optional: Set access token for private repositories
        // Can be set via constant or filter
        $this->access_token = defined( 'FILTER_WOOCOMMERCE_GITHUB_TOKEN' )
            ? FILTER_WOOCOMMERCE_GITHUB_TOKEN
            : '';
        $this->access_token = apply_filters( 'filter_woocommerce_github_token', $this->access_token );

        $this->init();
    }

    /**
     * Initialize the updater
     */
    private function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );

        // Add authorization header for GitHub API
        add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );
    }

    /**
     * Get plugin data
     *
     * @return array
     */
    private function get_plugin_data() {
        if ( empty( $this->plugin_data ) ) {
            $this->plugin_data = get_plugin_data( $this->file );
        }
        return $this->plugin_data;
    }

    /**
     * Get plugin basename
     *
     * @return string
     */
    private function get_basename() {
        if ( empty( $this->basename ) ) {
            $this->basename = plugin_basename( $this->file );
        }
        return $this->basename;
    }

    /**
     * Get plugin slug
     *
     * @return string
     */
    private function get_slug() {
        if ( empty( $this->slug ) ) {
            $this->slug = dirname( $this->get_basename() );
        }
        return $this->slug;
    }

    /**
     * Get GitHub release information
     *
     * @return object|false
     */
    private function get_github_release() {
        if ( ! empty( $this->github_response ) ) {
            return $this->github_response;
        }

        // Check transient first
        $transient_key = 'filter_woocommerce_github_response';
        $cached        = get_transient( $transient_key );

        if ( false !== $cached ) {
            // Check if it's a cached error
            if ( is_object( $cached ) && isset( $cached->error ) ) {
                return false;
            }
            $this->github_response = $cached;
            return $this->github_response;
        }

        // Fetch from GitHub API
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $args = array(
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            ),
            'timeout' => 10,
        );

        if ( ! empty( $this->access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            // Cache error for 30 minutes to avoid repeated failed requests
            set_transient( $transient_key, (object) array( 'error' => $response->get_error_message() ), 30 * MINUTE_IN_SECONDS );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        // Handle rate limiting
        if ( 403 === $response_code && isset( $data->message ) && strpos( $data->message, 'rate limit' ) !== false ) {
            // Cache rate limit error for 1 hour
            set_transient( $transient_key, (object) array( 'error' => 'rate_limited' ), HOUR_IN_SECONDS );
            return false;
        }

        if ( 200 !== $response_code ) {
            // Cache other errors for 30 minutes
            set_transient( $transient_key, (object) array( 'error' => 'http_' . $response_code ), 30 * MINUTE_IN_SECONDS );
            return false;
        }

        if ( empty( $data ) || isset( $data->message ) ) {
            return false;
        }

        $this->github_response = $data;

        // Cache successful response for 6 hours
        set_transient( $transient_key, $this->github_response, 6 * HOUR_IN_SECONDS );

        return $this->github_response;
    }

    /**
     * Get the download URL for the latest release
     *
     * @return string
     */
    private function get_download_url() {
        $release = $this->get_github_release();

        if ( ! $release ) {
            return '';
        }

        // Check for a zip asset first (preferred)
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( 'application/zip' === $asset->content_type ||
                     substr( $asset->name, -4 ) === '.zip' ) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fall back to the zipball URL
        return $release->zipball_url;
    }

    /**
     * Get version from release tag
     *
     * @return string
     */
    private function get_remote_version() {
        $release = $this->get_github_release();

        if ( ! $release ) {
            return '';
        }

        // Remove 'v' prefix if present (e.g., v1.0.0 -> 1.0.0)
        return ltrim( $release->tag_name, 'v' );
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Update transient.
     * @return object
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $plugin_data    = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];
        $remote_version  = $this->get_remote_version();

        if ( empty( $remote_version ) ) {
            return $transient;
        }

        // Compare versions
        if ( version_compare( $remote_version, $current_version, '>' ) ) {
            $download_url = $this->get_download_url();

            if ( ! empty( $download_url ) ) {
                $transient->response[ $this->get_basename() ] = (object) array(
                    'slug'        => $this->get_slug(),
                    'plugin'      => $this->get_basename(),
                    'new_version' => $remote_version,
                    'url'         => $plugin_data['PluginURI'],
                    'package'     => $download_url,
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => '',
                    'requires_php' => $plugin_data['RequiresPHP'] ?? '7.4',
                );
            }
        }

        return $transient;
    }

    /**
     * Provide plugin information for the update details popup
     *
     * @param false|object|array $result The result object or array.
     * @param string             $action The API action being performed.
     * @param object             $args   Plugin API arguments.
     * @return false|object
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( $this->get_slug() !== $args->slug ) {
            return $result;
        }

        $release     = $this->get_github_release();
        $plugin_data = $this->get_plugin_data();

        if ( ! $release ) {
            return $result;
        }

        $plugin_info = (object) array(
            'name'              => $plugin_data['Name'],
            'slug'              => $this->get_slug(),
            'version'           => $this->get_remote_version(),
            'author'            => $plugin_data['Author'],
            'author_profile'    => $plugin_data['AuthorURI'],
            'homepage'          => $plugin_data['PluginURI'],
            'requires'          => $plugin_data['RequiresWP'] ?? '5.8',
            'tested'            => '',
            'requires_php'      => $plugin_data['RequiresPHP'] ?? '7.4',
            'downloaded'        => 0,
            'last_updated'      => $release->published_at,
            'sections'          => array(
                'description'  => $plugin_data['Description'],
                'changelog'    => $this->parse_changelog( $release->body ),
            ),
            'download_link'     => $this->get_download_url(),
            'banners'           => array(),
        );

        return $plugin_info;
    }

    /**
     * Parse release notes into changelog HTML
     *
     * @param string $body Release body/notes.
     * @return string
     */
    private function parse_changelog( $body ) {
        if ( empty( $body ) ) {
            return '<p>No changelog available.</p>';
        }

        // Convert markdown to basic HTML
        $changelog = esc_html( $body );
        $changelog = nl2br( $changelog );

        // Convert markdown lists
        $changelog = preg_replace( '/^[-*]\s+(.+)$/m', '<li>$1</li>', $changelog );
        $changelog = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog );

        // Convert markdown headers
        $changelog = preg_replace( '/^###\s+(.+)$/m', '<h4>$1</h4>', $changelog );
        $changelog = preg_replace( '/^##\s+(.+)$/m', '<h3>$1</h3>', $changelog );

        return $changelog;
    }

    /**
     * Fix the source directory after download
     *
     * GitHub's zipball has a different folder name (e.g., username-repo-hash)
     * We need to rename it to match our plugin slug.
     *
     * @param string      $source        Downloaded source path.
     * @param string      $remote_source Remote source path.
     * @param WP_Upgrader $upgrader      Upgrader instance.
     * @param array       $hook_extra    Extra arguments.
     * @return string|WP_Error
     */
    public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
        global $wp_filesystem;

        // Check if this is our plugin being updated
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->get_basename() ) {
            return $source;
        }

        // Expected directory name
        $expected_dir = $this->get_slug();
        $new_source   = trailingslashit( $remote_source ) . $expected_dir . '/';

        // Check if source already has correct name
        if ( basename( $source ) === $expected_dir ) {
            return $source;
        }

        // Rename the source directory
        if ( $wp_filesystem->move( $source, $new_source ) ) {
            return $new_source;
        }

        return new WP_Error(
            'rename_failed',
            __( 'Unable to rename the update folder.', 'filter-woocommerce' )
        );
    }

    /**
     * Perform actions after plugin installation
     *
     * @param bool  $response   Installation response.
     * @param array $hook_extra Extra arguments.
     * @param array $result     Installation result.
     * @return array
     */
    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;

        // Check if this is our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->get_basename() ) {
            return $result;
        }

        // Clear the GitHub response cache
        delete_transient( 'filter_woocommerce_github_response' );

        // Re-activate the plugin if it was active
        $plugin = $this->get_basename();
        if ( is_plugin_active( $plugin ) ) {
            activate_plugin( $plugin );
        }

        return $result;
    }

    /**
     * Add authorization header for GitHub API requests
     *
     * @param array  $args HTTP request arguments.
     * @param string $url  Request URL.
     * @return array
     */
    public function add_auth_header( $args, $url ) {
        // Only add header for GitHub API requests related to this repo
        if ( strpos( $url, 'api.github.com' ) === false ) {
            return $args;
        }

        if ( strpos( $url, $this->github_repo ) === false ) {
            return $args;
        }

        if ( ! empty( $this->access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        return $args;
    }

    /**
     * Clear update cache
     *
     * Useful for forcing a fresh update check.
     */
    public static function clear_cache() {
        delete_transient( 'filter_woocommerce_github_response' );
        delete_site_transient( 'update_plugins' );
    }
}
