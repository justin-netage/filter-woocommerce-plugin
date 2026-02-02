<?php
/**
 * Filter handler class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Handler class
 *
 * Handles the main filtering logic for WooCommerce products
 */
class Filter_WooCommerce_Handler {

    /**
     * Active filters
     *
     * @var array
     */
    private $active_filters = array();

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
        // Modify product query based on filters
        add_action( 'woocommerce_product_query', array( $this, 'apply_filters_to_query' ), 10, 2 );

        // Parse filter parameters from URL
        add_action( 'wp', array( $this, 'parse_filter_params' ) );
    }

    /**
     * Parse filter parameters from URL
     */
    public function parse_filter_params() {
        // Price filter
        if ( isset( $_GET['min_price'] ) ) {
            $this->active_filters['min_price'] = floatval( $_GET['min_price'] );
        }
        if ( isset( $_GET['max_price'] ) ) {
            $this->active_filters['max_price'] = floatval( $_GET['max_price'] );
        }

        // Attribute filters
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'filter_' ) === 0 ) {
                $attribute = str_replace( 'filter_', '', $key );
                $this->active_filters['attributes'][ $attribute ] = array_map( 'sanitize_text_field', explode( ',', $value ) );
            }
        }

        // Rating filter
        if ( isset( $_GET['rating_filter'] ) ) {
            $this->active_filters['rating'] = array_map( 'absint', explode( ',', $_GET['rating_filter'] ) );
        }

        // On sale filter
        if ( isset( $_GET['on_sale'] ) && $_GET['on_sale'] === '1' ) {
            $this->active_filters['on_sale'] = true;
        }

        // In stock filter
        if ( isset( $_GET['in_stock'] ) && $_GET['in_stock'] === '1' ) {
            $this->active_filters['in_stock'] = true;
        }
    }

    /**
     * Apply filters to WooCommerce product query
     *
     * @param WP_Query $query    The WP_Query instance.
     * @param object   $wc_query The WC_Query instance.
     */
    public function apply_filters_to_query( $query, $wc_query ) {
        if ( empty( $this->active_filters ) ) {
            return;
        }

        $meta_query = $query->get( 'meta_query', array() );
        $tax_query  = $query->get( 'tax_query', array() );

        // Price filter
        if ( isset( $this->active_filters['min_price'] ) || isset( $this->active_filters['max_price'] ) ) {
            $price_meta = array(
                'key'     => '_price',
                'type'    => 'DECIMAL(10,2)',
                'compare' => 'BETWEEN',
                'value'   => array(
                    isset( $this->active_filters['min_price'] ) ? $this->active_filters['min_price'] : 0,
                    isset( $this->active_filters['max_price'] ) ? $this->active_filters['max_price'] : PHP_INT_MAX,
                ),
            );
            $meta_query[] = $price_meta;
        }

        // On sale filter
        if ( ! empty( $this->active_filters['on_sale'] ) ) {
            $product_ids_on_sale = wc_get_product_ids_on_sale();
            $query->set( 'post__in', array_merge( array( 0 ), $product_ids_on_sale ) );
        }

        // In stock filter
        if ( ! empty( $this->active_filters['in_stock'] ) ) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            );
        }

        // Attribute filters
        if ( ! empty( $this->active_filters['attributes'] ) ) {
            foreach ( $this->active_filters['attributes'] as $attribute => $terms ) {
                $tax_query[] = array(
                    'taxonomy' => 'pa_' . $attribute,
                    'field'    => 'slug',
                    'terms'    => $terms,
                    'operator' => 'IN',
                );
            }
        }

        // Rating filter
        if ( ! empty( $this->active_filters['rating'] ) ) {
            $rating_terms = array();
            foreach ( $this->active_filters['rating'] as $rating ) {
                $rating_terms[] = 'rated-' . $rating;
            }
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => $rating_terms,
                'operator' => 'IN',
            );
        }

        $query->set( 'meta_query', $meta_query );
        $query->set( 'tax_query', $tax_query );
    }

    /**
     * Get available filters for products
     *
     * @return array
     */
    public function get_available_filters() {
        $filters = array();

        // Price range
        $filters['price'] = $this->get_price_range();

        // Product attributes
        $filters['attributes'] = $this->get_product_attributes();

        // Categories
        $filters['categories'] = $this->get_product_categories();

        // Rating
        $filters['rating'] = array( 1, 2, 3, 4, 5 );

        return $filters;
    }

    /**
     * Get price range for products
     *
     * @return array
     */
    public function get_price_range() {
        global $wpdb;

        $min = $wpdb->get_var(
            "SELECT MIN( CAST( meta_value AS DECIMAL(10,2) ) )
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_price'
            AND meta_value != ''"
        );

        $max = $wpdb->get_var(
            "SELECT MAX( CAST( meta_value AS DECIMAL(10,2) ) )
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_price'
            AND meta_value != ''"
        );

        return array(
            'min' => floor( floatval( $min ) ),
            'max' => ceil( floatval( $max ) ),
        );
    }

    /**
     * Get product attributes for filtering
     *
     * @return array
     */
    public function get_product_attributes() {
        $attributes = array();
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        foreach ( $attribute_taxonomies as $attribute ) {
            $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
            $terms    = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                )
            );

            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $attributes[ $attribute->attribute_name ] = array(
                    'label' => $attribute->attribute_label,
                    'terms' => $terms,
                );
            }
        }

        return $attributes;
    }

    /**
     * Get product categories for filtering
     *
     * @return array
     */
    public function get_product_categories() {
        return get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
            )
        );
    }

    /**
     * Get active filters
     *
     * @return array
     */
    public function get_active_filters() {
        return $this->active_filters;
    }

    /**
     * Build filter URL
     *
     * @param array $filters Filter parameters.
     * @return string
     */
    public function build_filter_url( $filters = array() ) {
        $base_url = wc_get_page_permalink( 'shop' );

        if ( empty( $filters ) ) {
            return $base_url;
        }

        return add_query_arg( $filters, $base_url );
    }
}
