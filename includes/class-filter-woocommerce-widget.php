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
 * Widget for displaying product filters in sidebars
 */
class Filter_WooCommerce_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'filter_woocommerce_widget',
            __( 'Product Filter', 'filter-woocommerce' ),
            array(
                'description' => __( 'Display product filters for WooCommerce.', 'filter-woocommerce' ),
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

        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Filter Products', 'filter-woocommerce' );
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        // Build shortcode attributes from widget settings
        $shortcode_atts = array(
            'show_price'      => ! empty( $instance['show_price'] ) ? 'yes' : 'no',
            'show_categories' => ! empty( $instance['show_categories'] ) ? 'yes' : 'no',
            'show_attributes' => ! empty( $instance['show_attributes'] ) ? 'yes' : 'no',
            'show_rating'     => ! empty( $instance['show_rating'] ) ? 'yes' : 'no',
            'show_stock'      => ! empty( $instance['show_stock'] ) ? 'yes' : 'no',
            'show_sale'       => ! empty( $instance['show_sale'] ) ? 'yes' : 'no',
            'ajax'            => ! empty( $instance['ajax'] ) ? 'yes' : 'no',
            'layout'          => 'vertical',
        );

        // Render filter using shortcode
        echo do_shortcode( '[wc_product_filter ' . $this->build_shortcode_string( $shortcode_atts ) . ']' );

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
            'title'           => __( 'Filter Products', 'filter-woocommerce' ),
            'show_price'      => true,
            'show_categories' => true,
            'show_attributes' => true,
            'show_rating'     => true,
            'show_stock'      => true,
            'show_sale'       => true,
            'ajax'            => true,
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
                   id="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_price' ) ); ?>"
                   <?php checked( $instance['show_price'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>">
                <?php esc_html_e( 'Show Price Filter', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_categories' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_categories' ) ); ?>"
                   <?php checked( $instance['show_categories'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_categories' ) ); ?>">
                <?php esc_html_e( 'Show Categories Filter', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_attributes' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_attributes' ) ); ?>"
                   <?php checked( $instance['show_attributes'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_attributes' ) ); ?>">
                <?php esc_html_e( 'Show Attributes Filter', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_rating' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_rating' ) ); ?>"
                   <?php checked( $instance['show_rating'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_rating' ) ); ?>">
                <?php esc_html_e( 'Show Rating Filter', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_stock' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_stock' ) ); ?>"
                   <?php checked( $instance['show_stock'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_stock' ) ); ?>">
                <?php esc_html_e( 'Show Stock Filter', 'filter-woocommerce' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox"
                   class="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_sale' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_sale' ) ); ?>"
                   <?php checked( $instance['show_sale'] ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_sale' ) ); ?>">
                <?php esc_html_e( 'Show On Sale Filter', 'filter-woocommerce' ); ?>
            </label>
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

        $instance['title']           = sanitize_text_field( $new_instance['title'] );
        $instance['show_price']      = ! empty( $new_instance['show_price'] );
        $instance['show_categories'] = ! empty( $new_instance['show_categories'] );
        $instance['show_attributes'] = ! empty( $new_instance['show_attributes'] );
        $instance['show_rating']     = ! empty( $new_instance['show_rating'] );
        $instance['show_stock']      = ! empty( $new_instance['show_stock'] );
        $instance['show_sale']       = ! empty( $new_instance['show_sale'] );
        $instance['ajax']            = ! empty( $new_instance['ajax'] );

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
