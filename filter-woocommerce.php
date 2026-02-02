<?php
/**
 * Plugin Name: Filter for WooCommerce
 * Plugin URI: https://github.com/justin-netage/filter-woocommerce-plugin
 * Description: Vehicle attribute filtering plugin for WooCommerce - filter by Make, Model, Year, Kilometers, and more.
 * Version: 1.2.0
 * Author: Justin Netage
 * Author URI: https://github.com/justin-netage
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: filter-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'FILTER_WOOCOMMERCE_VERSION', '1.2.0' );
define( 'FILTER_WOOCOMMERCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FILTER_WOOCOMMERCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FILTER_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FILTER_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );

/**
 * Initialize the GitHub updater
 *
 * Runs early to ensure updates work even if WooCommerce is not active.
 */
function filter_woocommerce_init_updater() {
    require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce-updater.php';
    new Filter_WooCommerce_Updater( FILTER_WOOCOMMERCE_PLUGIN_FILE );
}
add_action( 'admin_init', 'filter_woocommerce_init_updater' );

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function filter_woocommerce_is_woocommerce_active() {
    return in_array(
        'woocommerce/woocommerce.php',
        apply_filters( 'active_plugins', get_option( 'active_plugins' ) ),
        true
    );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function filter_woocommerce_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'Filter for WooCommerce requires WooCommerce to be installed and active.', 'filter-woocommerce' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function filter_woocommerce_init() {
    // Check if WooCommerce is active
    if ( ! filter_woocommerce_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'filter_woocommerce_admin_notice' );
        return;
    }

    // Load text domain
    load_plugin_textdomain(
        'filter-woocommerce',
        false,
        dirname( FILTER_WOOCOMMERCE_PLUGIN_BASENAME ) . '/languages'
    );

    // Include required files
    require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce.php';

    // Initialize the main plugin class
    Filter_WooCommerce::get_instance();
}
add_action( 'plugins_loaded', 'filter_woocommerce_init' );

/**
 * Activation hook
 */
function filter_woocommerce_activate() {
    // Check PHP version
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'Filter for WooCommerce requires PHP 7.4 or higher.', 'filter-woocommerce' ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }

    // Create necessary database tables or options
    add_option( 'filter_woocommerce_version', FILTER_WOOCOMMERCE_VERSION );
    add_option( 'filter_woocommerce_settings', array() );

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'filter_woocommerce_activate' );

/**
 * Deactivation hook
 */
function filter_woocommerce_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'filter_woocommerce_deactivate' );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
