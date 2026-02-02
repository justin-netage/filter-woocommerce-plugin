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
 * Handles shortcodes for the filter plugin
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
                'show_price'      => 'yes',
                'show_categories' => 'yes',
                'show_attributes' => 'yes',
                'show_rating'     => 'yes',
                'show_stock'      => 'yes',
                'show_sale'       => 'yes',
                'ajax'            => 'yes',
                'layout'          => 'vertical',
            ),
            $atts,
            'wc_product_filter'
        );

        // Get filter handler instance
        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $available_filters = $filter_handler->get_available_filters();
        $active_filters = $filter_handler->get_active_filters();

        ob_start();
        ?>
        <div class="filter-woocommerce-container" data-ajax="<?php echo esc_attr( $atts['ajax'] ); ?>" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
            <form class="filter-woocommerce-form" method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">

                <?php if ( $atts['show_price'] === 'yes' && ! empty( $available_filters['price'] ) ) : ?>
                <div class="filter-woocommerce-section filter-woocommerce-price">
                    <h4 class="filter-woocommerce-title"><?php esc_html_e( 'Price', 'filter-woocommerce' ); ?></h4>
                    <div class="filter-woocommerce-price-slider">
                        <input type="number"
                               name="min_price"
                               class="filter-woocommerce-min-price"
                               placeholder="<?php esc_attr_e( 'Min', 'filter-woocommerce' ); ?>"
                               value="<?php echo isset( $active_filters['min_price'] ) ? esc_attr( $active_filters['min_price'] ) : ''; ?>"
                               min="<?php echo esc_attr( $available_filters['price']['min'] ); ?>"
                               max="<?php echo esc_attr( $available_filters['price']['max'] ); ?>">
                        <span class="filter-woocommerce-price-separator">-</span>
                        <input type="number"
                               name="max_price"
                               class="filter-woocommerce-max-price"
                               placeholder="<?php esc_attr_e( 'Max', 'filter-woocommerce' ); ?>"
                               value="<?php echo isset( $active_filters['max_price'] ) ? esc_attr( $active_filters['max_price'] ) : ''; ?>"
                               min="<?php echo esc_attr( $available_filters['price']['min'] ); ?>"
                               max="<?php echo esc_attr( $available_filters['price']['max'] ); ?>">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $atts['show_categories'] === 'yes' && ! empty( $available_filters['categories'] ) ) : ?>
                <div class="filter-woocommerce-section filter-woocommerce-categories">
                    <h4 class="filter-woocommerce-title"><?php esc_html_e( 'Categories', 'filter-woocommerce' ); ?></h4>
                    <ul class="filter-woocommerce-list">
                        <?php foreach ( $available_filters['categories'] as $category ) : ?>
                        <li>
                            <label>
                                <input type="checkbox"
                                       name="product_cat[]"
                                       value="<?php echo esc_attr( $category->slug ); ?>"
                                       <?php checked( is_product_category( $category->term_id ) ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                                <span class="count">(<?php echo esc_html( $category->count ); ?>)</span>
                            </label>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( $atts['show_attributes'] === 'yes' && ! empty( $available_filters['attributes'] ) ) : ?>
                    <?php foreach ( $available_filters['attributes'] as $attribute_name => $attribute_data ) : ?>
                    <div class="filter-woocommerce-section filter-woocommerce-attribute filter-woocommerce-<?php echo esc_attr( $attribute_name ); ?>">
                        <h4 class="filter-woocommerce-title"><?php echo esc_html( $attribute_data['label'] ); ?></h4>
                        <ul class="filter-woocommerce-list">
                            <?php foreach ( $attribute_data['terms'] as $term ) : ?>
                            <li>
                                <label>
                                    <input type="checkbox"
                                           name="filter_<?php echo esc_attr( $attribute_name ); ?>[]"
                                           value="<?php echo esc_attr( $term->slug ); ?>"
                                           <?php checked( isset( $active_filters['attributes'][ $attribute_name ] ) && in_array( $term->slug, $active_filters['attributes'][ $attribute_name ], true ) ); ?>>
                                    <?php echo esc_html( $term->name ); ?>
                                    <span class="count">(<?php echo esc_html( $term->count ); ?>)</span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ( $atts['show_rating'] === 'yes' ) : ?>
                <div class="filter-woocommerce-section filter-woocommerce-rating">
                    <h4 class="filter-woocommerce-title"><?php esc_html_e( 'Rating', 'filter-woocommerce' ); ?></h4>
                    <ul class="filter-woocommerce-list">
                        <?php for ( $rating = 5; $rating >= 1; $rating-- ) : ?>
                        <li>
                            <label>
                                <input type="checkbox"
                                       name="rating_filter[]"
                                       value="<?php echo esc_attr( $rating ); ?>"
                                       <?php checked( isset( $active_filters['rating'] ) && in_array( $rating, $active_filters['rating'], true ) ); ?>>
                                <?php echo str_repeat( '&#9733;', $rating ) . str_repeat( '&#9734;', 5 - $rating ); ?>
                                <span class="filter-woocommerce-rating-text"><?php printf( esc_html__( '%d & up', 'filter-woocommerce' ), $rating ); ?></span>
                            </label>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( $atts['show_stock'] === 'yes' ) : ?>
                <div class="filter-woocommerce-section filter-woocommerce-stock">
                    <label>
                        <input type="checkbox"
                               name="in_stock"
                               value="1"
                               <?php checked( ! empty( $active_filters['in_stock'] ) ); ?>>
                        <?php esc_html_e( 'In Stock Only', 'filter-woocommerce' ); ?>
                    </label>
                </div>
                <?php endif; ?>

                <?php if ( $atts['show_sale'] === 'yes' ) : ?>
                <div class="filter-woocommerce-section filter-woocommerce-sale">
                    <label>
                        <input type="checkbox"
                               name="on_sale"
                               value="1"
                               <?php checked( ! empty( $active_filters['on_sale'] ) ); ?>>
                        <?php esc_html_e( 'On Sale', 'filter-woocommerce' ); ?>
                    </label>
                </div>
                <?php endif; ?>

                <div class="filter-woocommerce-actions">
                    <button type="submit" class="filter-woocommerce-submit button">
                        <?php esc_html_e( 'Apply Filters', 'filter-woocommerce' ); ?>
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
                <?php foreach ( $active_filters as $key => $value ) : ?>
                    <?php if ( $key === 'min_price' ) : ?>
                    <li>
                        <a href="<?php echo esc_url( remove_query_arg( 'min_price' ) ); ?>" class="filter-woocommerce-remove">
                            <?php printf( esc_html__( 'Min Price: %s', 'filter-woocommerce' ), wc_price( $value ) ); ?>
                            <span class="remove">&times;</span>
                        </a>
                    </li>
                    <?php elseif ( $key === 'max_price' ) : ?>
                    <li>
                        <a href="<?php echo esc_url( remove_query_arg( 'max_price' ) ); ?>" class="filter-woocommerce-remove">
                            <?php printf( esc_html__( 'Max Price: %s', 'filter-woocommerce' ), wc_price( $value ) ); ?>
                            <span class="remove">&times;</span>
                        </a>
                    </li>
                    <?php elseif ( $key === 'on_sale' ) : ?>
                    <li>
                        <a href="<?php echo esc_url( remove_query_arg( 'on_sale' ) ); ?>" class="filter-woocommerce-remove">
                            <?php esc_html_e( 'On Sale', 'filter-woocommerce' ); ?>
                            <span class="remove">&times;</span>
                        </a>
                    </li>
                    <?php elseif ( $key === 'in_stock' ) : ?>
                    <li>
                        <a href="<?php echo esc_url( remove_query_arg( 'in_stock' ) ); ?>" class="filter-woocommerce-remove">
                            <?php esc_html_e( 'In Stock', 'filter-woocommerce' ); ?>
                            <span class="remove">&times;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="filter-woocommerce-clear-all">
                <?php esc_html_e( 'Clear All', 'filter-woocommerce' ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
