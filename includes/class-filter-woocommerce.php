<?php
/**
 * Main plugin class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce main class
 */
class Filter_WooCommerce {

    /**
     * Single instance of the class
     *
     * @var Filter_WooCommerce|null
     */
    private static $instance = null;

    /**
     * Filter handler instance
     *
     * @var Filter_WooCommerce_Handler|null
     */
    public $filter_handler = null;

    /**
     * Admin instance
     *
     * @var Filter_WooCommerce_Admin|null
     */
    public $admin = null;

    /**
     * Get single instance of the class
     *
     * @return Filter_WooCommerce
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce-handler.php';
        require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce-ajax.php';
        require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce-shortcode.php';
        require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-filter-woocommerce-widget.php';

        // Admin classes
        if ( is_admin() ) {
            require_once FILTER_WOOCOMMERCE_PLUGIN_DIR . 'includes/admin/class-filter-woocommerce-admin.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action( 'init', array( $this, 'init' ) );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Register widget
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize filter handler
        $this->filter_handler = new Filter_WooCommerce_Handler();

        // Initialize AJAX handler
        new Filter_WooCommerce_Ajax();

        // Initialize shortcode
        new Filter_WooCommerce_Shortcode();

        // Initialize admin
        if ( is_admin() ) {
            $this->admin = new Filter_WooCommerce_Admin();
        }
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_assets() {
        // Only enqueue for admin users
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Only enqueue on WooCommerce pages
        if ( ! is_woocommerce() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'filter-woocommerce',
            FILTER_WOOCOMMERCE_PLUGIN_URL . 'assets/css/filter-woocommerce.css',
            array(),
            FILTER_WOOCOMMERCE_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'filter-woocommerce',
            FILTER_WOOCOMMERCE_PLUGIN_URL . 'assets/js/filter-woocommerce.js',
            array( 'jquery' ),
            FILTER_WOOCOMMERCE_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'filter-woocommerce',
            'filterWooCommerce',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'filter_woocommerce_nonce' ),
                'loading'  => esc_html__( 'Loading...', 'filter-woocommerce' ),
                'noResults' => esc_html__( 'No products found.', 'filter-woocommerce' ),
            )
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget( 'Filter_WooCommerce_Widget' );
    }

    /**
     * Get plugin settings
     *
     * @param string $key     Optional setting key.
     * @param mixed  $default Default value if setting not found.
     * @return mixed
     */
    public function get_setting( $key = '', $default = null ) {
        $settings = get_option( 'filter_woocommerce_settings', array() );

        if ( empty( $key ) ) {
            return $settings;
        }

        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update plugin settings
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public function update_setting( $key, $value ) {
        $settings = get_option( 'filter_woocommerce_settings', array() );
        $settings[ $key ] = $value;
        return update_option( 'filter_woocommerce_settings', $settings );
    }
}
