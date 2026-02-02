<?php
/**
 * Widget class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Widget class
 *
 * Widget for displaying vehicle filters in sidebars
 */
class Filter_WooCommerce_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'filter_woocommerce_widget',
            __( 'Vehicle Filter', 'filter-woocommerce' ),
            array(
                'description' => __( 'Display vehicle attribute filters for WooCommerce.', 'filter-woocommerce' ),
                'classname'   => 'widget_filter_woocommerce',
            )
        );
    }

    /**
     * Widget output
     *
     * @param array $args     Widget arguments.
     * @param array $instance Widget instance.
     */
    public function widget( $args, $instance ) {
        // Only show on WooCommerce pages
        if ( ! is_woocommerce() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
            return;
        }

        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Find Your Vehicle', 'filter-woocommerce' );
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        // Build shortcode attributes from widget settings
        $shortcode_atts = array(
            'ajax'        => ! empty( $instance['ajax'] ) ? 'yes' : 'no',
            'layout'      => 'vertical',
            'collapsible' => ! empty( $instance['collapsible'] ) ? 'yes' : 'no',
            'show_count'  => ! empty( $instance['show_count'] ) ? 'yes' : 'no',
        );

        // Render filter using shortcode
        echo do_shortcode( '[wc_vehicle_filter ' . $this->build_shortcode_string( $shortcode_atts ) . ']' );

        echo $args['after_widget'];
    }

    /**
     * Widget form in admin
     *
     * @param array $instance Widget instance.
     * @return void
     */
    public function form( $instance ) {
        $defaults = array(
            'title'       => __( 'Find Your Vehicle', 'filter-woocommerce' ),
            'ajax'        => true,
            'collapsible' => false,
            'show_count'  => true,
        );

        $instance = wp_parse_args( (array) $instance, $defaults );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'filter-woocommerce' ); ?>
            </label>
            <input type="text"
                   class="widefat"
                   id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   value="<?php echo esc_attr( $instance['title'] ); ?>">
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'ajax' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'ajax' ) ); ?>"
                   <?php checked( $instance['ajax'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'ajax' ) ); ?>">
                <?php esc_html_e( 'Enable AJAX Filtering', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'collapsible' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'collapsible' ) ); ?>"
                   <?php checked( $instance['collapsible'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'collapsible' ) ); ?>">
                <?php esc_html_e( 'Make sections collapsible', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_count' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_count' ) ); ?>"
                   <?php checked( $instance['show_count'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_count' ) ); ?>">
                <?php esc_html_e( 'Show product count', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p class="description">
            <?php esc_html_e( 'This widget displays filters for vehicle attributes: New/Used, Make, Model, Region, Transmission, Body Type, Kilometers, and Year.', 'filter-woocommerce' ); ?>
        </p>
        <?php
    }

    /**
     * Update widget settings
     *
     * @param array $new_instance New instance values.
     * @param array $old_instance Old instance values.
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        $instance['title']       = sanitize_text_field( $new_instance['title'] );
        $instance['ajax']        = ! empty( $new_instance['ajax'] );
        $instance['collapsible'] = ! empty( $new_instance['collapsible'] );
        $instance['show_count']  = ! empty( $new_instance['show_count'] );

        return $instance;
    }

    /**
     * Build shortcode attribute string
     *
     * @param array $atts Attributes.
     * @return string
     */
    private function build_shortcode_string( $atts ) {
        $string = '';
        foreach ( $atts as $key => $value ) {
            $string .= sprintf( '%s="%s" ', esc_attr( $key ), esc_attr( $value ) );
        }
        return trim( $string );
    }
}
