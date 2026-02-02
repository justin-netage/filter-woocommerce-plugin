<?php
/**
 * Admin class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Admin class
 *
 * Handles admin functionality for the plugin
 */
class Filter_WooCommerce_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . FILTER_WOOCOMMERCE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Filters', 'filter-woocommerce' ),
            __( 'Product Filters', 'filter-woocommerce' ),
            'manage_woocommerce',
            'filter-woocommerce',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'filter_woocommerce_settings',
            'filter_woocommerce_settings',
            array( $this, 'sanitize_settings' )
        );

        // General settings section
        add_settings_section(
            'filter_woocommerce_general',
            __( 'General Settings', 'filter-woocommerce' ),
            array( $this, 'render_general_section' ),
            'filter-woocommerce'
        );

        // AJAX filtering
        add_settings_field(
            'enable_ajax',
            __( 'Enable AJAX Filtering', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_general',
            array(
                'id'          => 'enable_ajax',
                'description' => __( 'Filter products without page reload.', 'filter-woocommerce' ),
            )
        );

        // Show product count
        add_settings_field(
            'show_count',
            __( 'Show Product Count', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_general',
            array(
                'id'          => 'show_count',
                'description' => __( 'Display the number of products for each filter option.', 'filter-woocommerce' ),
            )
        );

        // Hide empty filters
        add_settings_field(
            'hide_empty',
            __( 'Hide Empty Filters', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_general',
            array(
                'id'          => 'hide_empty',
                'description' => __( 'Hide filter options with no products.', 'filter-woocommerce' ),
            )
        );

        // Filter section
        add_settings_section(
            'filter_woocommerce_filters',
            __( 'Filter Options', 'filter-woocommerce' ),
            array( $this, 'render_filters_section' ),
            'filter-woocommerce'
        );

        // Price filter
        add_settings_field(
            'enable_price_filter',
            __( 'Price Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_price_filter',
                'description' => __( 'Enable filtering by price range.', 'filter-woocommerce' ),
            )
        );

        // Category filter
        add_settings_field(
            'enable_category_filter',
            __( 'Category Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_category_filter',
                'description' => __( 'Enable filtering by product category.', 'filter-woocommerce' ),
            )
        );

        // Attribute filter
        add_settings_field(
            'enable_attribute_filter',
            __( 'Attribute Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_attribute_filter',
                'description' => __( 'Enable filtering by product attributes.', 'filter-woocommerce' ),
            )
        );

        // Rating filter
        add_settings_field(
            'enable_rating_filter',
            __( 'Rating Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_rating_filter',
                'description' => __( 'Enable filtering by product rating.', 'filter-woocommerce' ),
            )
        );

        // Stock filter
        add_settings_field(
            'enable_stock_filter',
            __( 'Stock Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_stock_filter',
                'description' => __( 'Enable filtering by stock status.', 'filter-woocommerce' ),
            )
        );

        // Sale filter
        add_settings_field(
            'enable_sale_filter',
            __( 'Sale Filter', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_filters',
            array(
                'id'          => 'enable_sale_filter',
                'description' => __( 'Enable filtering products on sale.', 'filter-woocommerce' ),
            )
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input settings.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $checkboxes = array(
            'enable_ajax',
            'show_count',
            'hide_empty',
            'enable_price_filter',
            'enable_category_filter',
            'enable_attribute_filter',
            'enable_rating_filter',
            'enable_stock_filter',
            'enable_sale_filter',
        );

        foreach ( $checkboxes as $checkbox ) {
            $sanitized[ $checkbox ] = ! empty( $input[ $checkbox ] );
        }

        return $sanitized;
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configure the general behavior of the product filters.', 'filter-woocommerce' ) . '</p>';
    }

    /**
     * Render filters section description
     */
    public function render_filters_section() {
        echo '<p>' . esc_html__( 'Choose which filter types to enable.', 'filter-woocommerce' ) . '</p>';
    }

    /**
     * Render checkbox field
     *
     * @param array $args Field arguments.
     */
    public function render_checkbox_field( $args ) {
        $settings = get_option( 'filter_woocommerce_settings', array() );
        $value    = isset( $settings[ $args['id'] ] ) ? $settings[ $args['id'] ] : false;
        ?>
        <label>
            <input type="checkbox"
                   name="filter_woocommerce_settings[<?php echo esc_attr( $args['id'] ); ?>]"
                   value="1"
                   <?php checked( $value ); ?>>
            <?php echo esc_html( $args['description'] ); ?>
        </label>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'filter_woocommerce_settings' );
                do_settings_sections( 'filter-woocommerce' );
                submit_button( __( 'Save Settings', 'filter-woocommerce' ) );
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Shortcode Usage', 'filter-woocommerce' ); ?></h2>
            <p><?php esc_html_e( 'Use the following shortcode to display the product filter anywhere on your site:', 'filter-woocommerce' ); ?></p>
            <code>[wc_product_filter]</code>

            <h3><?php esc_html_e( 'Shortcode Attributes', 'filter-woocommerce' ); ?></h3>
            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Attribute', 'filter-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Default', 'filter-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'filter-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>show_price</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show price filter', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_categories</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show category filter', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_attributes</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show attribute filters', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_rating</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show rating filter', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_stock</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show in-stock filter', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_sale</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show on-sale filter', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>ajax</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Enable AJAX filtering', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>layout</code></td>
                        <td>vertical</td>
                        <td><?php esc_html_e( 'Filter layout (vertical/horizontal)', 'filter-woocommerce' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Active Filters Shortcode', 'filter-woocommerce' ); ?></h3>
            <p><?php esc_html_e( 'Display currently active filters:', 'filter-woocommerce' ); ?></p>
            <code>[wc_active_filters]</code>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_filter-woocommerce' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'filter-woocommerce-admin',
            FILTER_WOOCOMMERCE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FILTER_WOOCOMMERCE_VERSION
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=filter-woocommerce' ),
            __( 'Settings', 'filter-woocommerce' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }
}
