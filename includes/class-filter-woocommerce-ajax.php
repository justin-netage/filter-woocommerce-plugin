<?php
/**
 * AJAX handler class
 *
 * @package FilterWooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filter_WooCommerce_Ajax class
 *
 * Handles AJAX requests for product filtering
 */
class Filter_WooCommerce_Ajax {

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
        // AJAX filter products
        add_action( 'wp_ajax_filter_woocommerce_products', array( $this, 'filter_products' ) );
        add_action( 'wp_ajax_nopriv_filter_woocommerce_products', array( $this, 'filter_products' ) );

        // AJAX get filter counts
        add_action( 'wp_ajax_filter_woocommerce_counts', array( $this, 'get_filter_counts' ) );
        add_action( 'wp_ajax_nopriv_filter_woocommerce_counts', array( $this, 'get_filter_counts' ) );
    }

    /**
     * Filter products via AJAX
     */
    public function filter_products() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'filter_woocommerce_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'filter-woocommerce' ) ) );
        }

        // Build query args
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 12,
            'paged'          => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
        );

        // Apply filters from POST data
        $meta_query = array();
        $tax_query  = array();

        // Price filter
        if ( isset( $_POST['min_price'] ) || isset( $_POST['max_price'] ) ) {
            $meta_query[] = array(
                'key'     => '_price',
                'type'    => 'DECIMAL(10,2)',
                'compare' => 'BETWEEN',
                'value'   => array(
                    isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0,
                    isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : PHP_INT_MAX,
                ),
            );
        }

        // Category filter
        if ( ! empty( $_POST['categories'] ) ) {
            $categories = array_map( 'absint', (array) $_POST['categories'] );
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $categories,
                'operator' => 'IN',
            );
        }

        // Attribute filters
        if ( ! empty( $_POST['attributes'] ) && is_array( $_POST['attributes'] ) ) {
            foreach ( $_POST['attributes'] as $attribute => $terms ) {
                $attribute = sanitize_text_field( $attribute );
                $terms     = array_map( 'sanitize_text_field', (array) $terms );

                $tax_query[] = array(
                    'taxonomy' => 'pa_' . $attribute,
                    'field'    => 'slug',
                    'terms'    => $terms,
                    'operator' => 'IN',
                );
            }
        }

        // Rating filter
        if ( ! empty( $_POST['rating'] ) ) {
            $ratings = array_map( 'absint', (array) $_POST['rating'] );
            $rating_terms = array();
            foreach ( $ratings as $rating ) {
                $rating_terms[] = 'rated-' . $rating;
            }
            $tax_query[] = array(
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => $rating_terms,
                'operator' => 'IN',
            );
        }

        // On sale filter
        if ( ! empty( $_POST['on_sale'] ) ) {
            $product_ids_on_sale = wc_get_product_ids_on_sale();
            $args['post__in'] = array_merge( array( 0 ), $product_ids_on_sale );
        }

        // In stock filter
        if ( ! empty( $_POST['in_stock'] ) ) {
            $meta_query[] = array(
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '=',
            );
        }

        // Sorting
        if ( ! empty( $_POST['orderby'] ) ) {
            $orderby = sanitize_text_field( $_POST['orderby'] );
            switch ( $orderby ) {
                case 'price':
                    $args['meta_key'] = '_price';
                    $args['orderby']  = 'meta_value_num';
                    $args['order']    = 'ASC';
                    break;
                case 'price-desc':
                    $args['meta_key'] = '_price';
                    $args['orderby']  = 'meta_value_num';
                    $args['order']    = 'DESC';
                    break;
                case 'date':
                    $args['orderby'] = 'date';
                    $args['order']   = 'DESC';
                    break;
                case 'popularity':
                    $args['meta_key'] = 'total_sales';
                    $args['orderby']  = 'meta_value_num';
                    $args['order']    = 'DESC';
                    break;
                case 'rating':
                    $args['meta_key'] = '_wc_average_rating';
                    $args['orderby']  = 'meta_value_num';
                    $args['order']    = 'DESC';
                    break;
                default:
                    $args['orderby'] = 'menu_order title';
                    $args['order']   = 'ASC';
            }
        }

        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        // Run query
        $query = new WP_Query( $args );

        // Start output buffering
        ob_start();

        if ( $query->have_posts() ) {
            woocommerce_product_loop_start();

            while ( $query->have_posts() ) {
                $query->the_post();
                wc_get_template_part( 'content', 'product' );
            }

            woocommerce_product_loop_end();
        } else {
            echo '<p class="woocommerce-info">' . esc_html__( 'No products found matching your criteria.', 'filter-woocommerce' ) . '</p>';
        }

        $html = ob_get_clean();

        wp_reset_postdata();

        // Build pagination
        $pagination = paginate_links(
            array(
                'total'     => $query->max_num_pages,
                'current'   => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
                'format'    => '?paged=%#%',
                'type'      => 'array',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            )
        );

        wp_send_json_success(
            array(
                'html'        => $html,
                'pagination'  => $pagination,
                'found_posts' => $query->found_posts,
                'max_pages'   => $query->max_num_pages,
            )
        );
    }

    /**
     * Get filter counts via AJAX
     */
    public function get_filter_counts() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'filter_woocommerce_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'filter-woocommerce' ) ) );
        }

        $counts = array();

        // Get category counts
        $categories = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
            )
        );

        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $counts['categories'][ $category->term_id ] = $category->count;
            }
        }

        // Get attribute counts
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        foreach ( $attribute_taxonomies as $attribute ) {
            $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
            $terms    = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                )
            );

            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $counts['attributes'][ $attribute->attribute_name ][ $term->slug ] = $term->count;
                }
            }
        }

        wp_send_json_success( $counts );
    }
}
