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
 * Handles admin functionality for the vehicle filter plugin
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
            __( 'Vehicle Filters', 'filter-woocommerce' ),
            __( 'Vehicle Filters', 'filter-woocommerce' ),
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
                'description' => __( 'Filter vehicles without page reload.', 'filter-woocommerce' ),
            )
        );

        // Show product count
        add_settings_field(
            'show_count',
            __( 'Show Vehicle Count', 'filter-woocommerce' ),
            array( $this, 'render_checkbox_field' ),
            'filter-woocommerce',
            'filter_woocommerce_general',
            array(
                'id'          => 'show_count',
                'description' => __( 'Display the number of vehicles for each filter option.', 'filter-woocommerce' ),
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
                'description' => __( 'Hide filter options with no vehicles.', 'filter-woocommerce' ),
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
        echo '<p>' . esc_html__( 'Configure the general behavior of the vehicle filters.', 'filter-woocommerce' ) . '</p>';
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

        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $vehicle_attributes = $filter_handler->get_vehicle_attributes();
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

            <h2><?php esc_html_e( 'Vehicle Attributes', 'filter-woocommerce' ); ?></h2>
            <p><?php esc_html_e( 'The following vehicle attributes are configured for filtering:', 'filter-woocommerce' ); ?></p>

            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Attribute', 'filter-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'filter-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'filter-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'filter-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $vehicle_attributes as $slug => $config ) : ?>
                        <?php
                        $taxonomy = 'pa_' . $slug;
                        $exists = taxonomy_exists( $taxonomy );
                        $term_count = $exists ? wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $config['label'] ); ?></strong></td>
                            <td><code>pa_<?php echo esc_html( $slug ); ?></code></td>
                            <td><?php echo esc_html( ucfirst( $config['type'] ) ); ?></td>
                            <td>
                                <?php if ( $exists && $term_count > 0 ) : ?>
                                    <span style="color: green;">&#10003; <?php printf( esc_html__( '%d terms', 'filter-woocommerce' ), $term_count ); ?></span>
                                <?php elseif ( $exists ) : ?>
                                    <span style="color: orange;"><?php esc_html_e( 'No terms', 'filter-woocommerce' ); ?></span>
                                <?php else : ?>
                                    <span style="color: red;"><?php esc_html_e( 'Not created', 'filter-woocommerce' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="description">
                <?php
                printf(
                    esc_html__( 'To add these attributes, go to %s and create product attributes with the slugs shown above.', 'filter-woocommerce' ),
                    '<a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ) . '">' . esc_html__( 'Products → Attributes', 'filter-woocommerce' ) . '</a>'
                );
                ?>
            </p>

            <hr>

            <h2><?php esc_html_e( 'Shortcode Usage', 'filter-woocommerce' ); ?></h2>
            <p><?php esc_html_e( 'Use the following shortcode to display the vehicle filter anywhere on your site:', 'filter-woocommerce' ); ?></p>
            <code>[wc_vehicle_filter]</code>

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
                        <td><code>ajax</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Enable AJAX filtering', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>layout</code></td>
                        <td>vertical</td>
                        <td><?php esc_html_e( 'Filter layout (vertical/horizontal)', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>collapsible</code></td>
                        <td>no</td>
                        <td><?php esc_html_e( 'Make filter sections collapsible', 'filter-woocommerce' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>show_count</code></td>
                        <td>yes</td>
                        <td><?php esc_html_e( 'Show vehicle count per option', 'filter-woocommerce' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e( 'Active Filters Shortcode', 'filter-woocommerce' ); ?></h3>
            <p><?php esc_html_e( 'Display currently active filters:', 'filter-woocommerce' ); ?></p>
            <code>[wc_active_filters]</code>

            <hr>

            <h2><?php esc_html_e( 'Required WooCommerce Attributes', 'filter-woocommerce' ); ?></h2>
            <p><?php esc_html_e( 'Create the following attributes in WooCommerce with the exact slugs specified:', 'filter-woocommerce' ); ?></p>
            <ol>
                <li><strong>new-or-used</strong> - <?php esc_html_e( 'New or Used condition', 'filter-woocommerce' ); ?></li>
                <li><strong>make</strong> - <?php esc_html_e( 'Vehicle manufacturer (Toyota, Ford, etc.)', 'filter-woocommerce' ); ?></li>
                <li><strong>model</strong> - <?php esc_html_e( 'Vehicle model (Corolla, Mustang, etc.)', 'filter-woocommerce' ); ?></li>
                <li><strong>region</strong> - <?php esc_html_e( 'Geographic region', 'filter-woocommerce' ); ?></li>
                <li><strong>transmission</strong> - <?php esc_html_e( 'Automatic, Manual, CVT, etc.', 'filter-woocommerce' ); ?></li>
                <li><strong>body-type</strong> - <?php esc_html_e( 'Sedan, SUV, Hatchback, etc.', 'filter-woocommerce' ); ?></li>
                <li><strong>kilometers</strong> - <?php esc_html_e( 'Mileage/Odometer reading (numeric)', 'filter-woocommerce' ); ?></li>
                <li><strong>vehicle-year</strong> - <?php esc_html_e( 'Year model (2020, 2021, etc.)', 'filter-woocommerce' ); ?></li>
            </ol>
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
