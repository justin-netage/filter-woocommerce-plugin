<?php
/**
 * Shortcode handler class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Shortcode class
 *
 * Handles shortcodes for the vehicle filter plugin
 */
class Filter_WooCommerce_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_shortcodes();
    }

    /**
     * Register shortcodes
     */
    private function register_shortcodes() {
        add_shortcode( 'wc_product_filter', array( $this, 'render_filter' ) );
        add_shortcode( 'wc_vehicle_filter', array( $this, 'render_filter' ) ); // Alias
        add_shortcode( 'wc_active_filters', array( $this, 'render_active_filters' ) );
    }

    /**
     * Render product filter shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_filter( $atts ) {
        $atts = shortcode_atts(
            array(
                'ajax'       => 'yes',
                'layout'     => 'vertical',
                'collapsible' => 'no',
                'show_count' => 'yes',
            ),
            $atts,
            'wc_product_filter'
        );

        // Get filter handler instance
        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $available_filters = $filter_handler->get_available_filters();
        $active_filters = $filter_handler->get_active_filters();
        $vehicle_attributes = $filter_handler->get_vehicle_attributes();

        ob_start();
        ?>
        <div class="filter-woocommerce-container"
             data-ajax="<?php echo esc_attr( $atts['ajax'] ); ?>"
             data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
            <form class="filter-woocommerce-form" method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">

                <?php foreach ( $vehicle_attributes as $attr_slug => $attr_config ) : ?>
                    <?php
                    // Skip if no terms available for this attribute
                    if ( ! isset( $available_filters[ $attr_slug ] ) ) {
                        continue;
                    }

                    $filter_data = $available_filters[ $attr_slug ];
                    $is_range = $attr_config['type'] === 'range';
                    $has_dependency = isset( $attr_config['depends_on'] );
                    $section_classes = array(
                        'filter-woocommerce-section',
                        'filter-woocommerce-' . esc_attr( $attr_slug ),
                    );
                    if ( $atts['collapsible'] === 'yes' ) {
                        $section_classes[] = 'collapsible';
                    }
                    if ( $has_dependency ) {
                        $section_classes[] = 'has-dependency';
                    }
                    ?>

                    <div class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"
                         <?php if ( $has_dependency ) : ?>
                         data-depends-on="<?php echo esc_attr( $attr_config['depends_on'] ); ?>"
                         <?php endif; ?>>

                        <h4 class="filter-woocommerce-title"><?php echo esc_html( $attr_config['label'] ); ?></h4>

                        <?php if ( $is_range ) : ?>
                            <?php $this->render_range_filter( $attr_slug, $filter_data, $active_filters ); ?>
                        <?php else : ?>
                            <?php $this->render_select_filter( $attr_slug, $filter_data, $active_filters, $atts['show_count'] === 'yes' ); ?>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

                <div class="filter-woocommerce-actions">
                    <button type="submit" class="filter-woocommerce-submit button">
                        <?php esc_html_e( 'Search Vehicles', 'filter-woocommerce' ); ?>
                    </button>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="filter-woocommerce-reset">
                        <?php esc_html_e( 'Reset', 'filter-woocommerce' ); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a select/dropdown filter
     *
     * @param string $attr_slug    Attribute slug.
     * @param array  $filter_data  Filter data.
     * @param array  $active_filters Active filter values.
     * @param bool   $show_count   Whether to show product count.
     */
    private function render_select_filter( $attr_slug, $filter_data, $active_filters, $show_count = true ) {
        $active_value = $active_filters[ $attr_slug ] ?? array();
        if ( ! is_array( $active_value ) ) {
            $active_value = array( $active_value );
        }
        ?>
        <div class="filter-woocommerce-select-wrapper">
            <select name="filter_<?php echo esc_attr( $attr_slug ); ?>"
                    class="filter-woocommerce-select"
                    data-attribute="<?php echo esc_attr( $attr_slug ); ?>">
                <option value=""><?php printf( esc_html__( 'Any %s', 'filter-woocommerce' ), esc_html( $filter_data['label'] ) ); ?></option>
                <?php foreach ( $filter_data['terms'] as $term ) : ?>
                    <option value="<?php echo esc_attr( $term->slug ); ?>"
                            <?php selected( in_array( $term->slug, $active_value, true ) ); ?>>
                        <?php echo esc_html( $term->name ); ?>
                        <?php if ( $show_count ) : ?>
                            (<?php echo esc_html( $term->count ); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Render a range filter (min/max inputs)
     *
     * @param string $attr_slug    Attribute slug.
     * @param array  $filter_data  Filter data.
     * @param array  $active_filters Active filter values.
     */
    private function render_range_filter( $attr_slug, $filter_data, $active_filters ) {
        $active_value = $active_filters[ $attr_slug ] ?? array();
        $min_value = $active_value['min'] ?? '';
        $max_value = $active_value['max'] ?? '';
        ?>
        <div class="filter-woocommerce-range-wrapper">
            <div class="filter-woocommerce-range-inputs">
                <input type="number"
                       name="filter_<?php echo esc_attr( $attr_slug ); ?>_min"
                       class="filter-woocommerce-range-min"
                       placeholder="<?php esc_attr_e( 'Min', 'filter-woocommerce' ); ?>"
                       value="<?php echo esc_attr( $min_value ); ?>"
                       min="<?php echo esc_attr( $filter_data['min'] ); ?>"
                       max="<?php echo esc_attr( $filter_data['max'] ); ?>">
                <span class="filter-woocommerce-range-separator">-</span>
                <input type="number"
                       name="filter_<?php echo esc_attr( $attr_slug ); ?>_max"
                       class="filter-woocommerce-range-max"
                       placeholder="<?php esc_attr_e( 'Max', 'filter-woocommerce' ); ?>"
                       value="<?php echo esc_attr( $max_value ); ?>"
                       min="<?php echo esc_attr( $filter_data['min'] ); ?>"
                       max="<?php echo esc_attr( $filter_data['max'] ); ?>">
            </div>
            <div class="filter-woocommerce-range-info">
                <?php printf(
                    esc_html__( 'Range: %s - %s', 'filter-woocommerce' ),
                    esc_html( number_format( $filter_data['min'] ) ),
                    esc_html( number_format( $filter_data['max'] ) )
                ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render active filters shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render_active_filters( $atts ) {
        $atts = shortcode_atts(
            array(
                'title' => __( 'Active Filters', 'filter-woocommerce' ),
            ),
            $atts,
            'wc_active_filters'
        );

        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $active_filters = $filter_handler->get_active_filters();
        $vehicle_attributes = $filter_handler->get_vehicle_attributes();

        if ( empty( $active_filters ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="filter-woocommerce-active-filters">
            <?php if ( ! empty( $atts['title'] ) ) : ?>
            <h4 class="filter-woocommerce-active-title"><?php echo esc_html( $atts['title'] ); ?></h4>
            <?php endif; ?>

            <ul class="filter-woocommerce-active-list">
                <?php foreach ( $active_filters as $attr_slug => $value ) : ?>
                    <?php
                    $config = $vehicle_attributes[ $attr_slug ] ?? null;
                    if ( ! $config ) {
                        continue;
                    }

                    $label = $config['label'];
                    $is_range = $config['type'] === 'range';
                    ?>

                    <?php if ( $is_range ) : ?>
                        <?php if ( isset( $value['min'] ) ) : ?>
                        <li>
                            <a href="<?php echo esc_url( remove_query_arg( 'filter_' . $attr_slug . '_min' ) ); ?>"
                               class="filter-woocommerce-remove">
                                <?php printf(
                                    esc_html__( '%s from: %s', 'filter-woocommerce' ),
                                    esc_html( $label ),
                                    esc_html( number_format( $value['min'] ) )
                                ); ?>
                                <span class="remove">&times;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ( isset( $value['max'] ) ) : ?>
                        <li>
                            <a href="<?php echo esc_url( remove_query_arg( 'filter_' . $attr_slug . '_max' ) ); ?>"
                               class="filter-woocommerce-remove">
                                <?php printf(
                                    esc_html__( '%s to: %s', 'filter-woocommerce' ),
                                    esc_html( $label ),
                                    esc_html( number_format( $value['max'] ) )
                                ); ?>
                                <span class="remove">&times;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php
                        $term_names = array();
                        foreach ( (array) $value as $term_slug ) {
                            $term = get_term_by( 'slug', $term_slug, 'pa_' . $attr_slug );
                            if ( $term ) {
                                $term_names[] = $term->name;
                            }
                        }
                        ?>
                        <li>
                            <a href="<?php echo esc_url( remove_query_arg( 'filter_' . $attr_slug ) ); ?>"
                               class="filter-woocommerce-remove">
                                <?php printf(
                                    esc_html__( '%s: %s', 'filter-woocommerce' ),
                                    esc_html( $label ),
                                    esc_html( implode( ', ', $term_names ) )
                                ); ?>
                                <span class="remove">&times;</span>
                            </a>
                        </li>
                    <?php endif; ?>

                <?php endforeach; ?>
            </ul>

            <a href="<?php echo esc_url( $filter_handler->get_clear_url() ); ?>" class="filter-woocommerce-clear-all">
                <?php esc_html_e( 'Clear All', 'filter-woocommerce' ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
