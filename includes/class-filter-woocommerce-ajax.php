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
 * Handles AJAX requests for vehicle product filtering
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

        // AJAX get dependent terms (e.g., models for a specific make)
        add_action( 'wp_ajax_filter_woocommerce_get_terms', array( $this, 'get_dependent_terms' ) );
        add_action( 'wp_ajax_nopriv_filter_woocommerce_get_terms', array( $this, 'get_dependent_terms' ) );

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

        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $vehicle_attributes = $filter_handler->get_vehicle_attributes();

        // Build query args
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 12,
            'paged'          => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
        );

        $tax_query = array();

        // Process each vehicle attribute filter
        foreach ( $vehicle_attributes as $attr_slug => $config ) {
            if ( $config['type'] === 'range' ) {
                // Range filter
                $min_key = 'filter_' . $attr_slug . '_min';
                $max_key = 'filter_' . $attr_slug . '_max';

                $min_val = isset( $_POST[ $min_key ] ) ? sanitize_text_field( $_POST[ $min_key ] ) : null;
                $max_val = isset( $_POST[ $max_key ] ) ? sanitize_text_field( $_POST[ $max_key ] ) : null;

                if ( $min_val !== null || $max_val !== null ) {
                    $matching_terms = $this->get_terms_in_range(
                        'pa_' . $attr_slug,
                        $min_val,
                        $max_val
                    );

                    if ( ! empty( $matching_terms ) ) {
                        $tax_query[] = array(
                            'taxonomy' => 'pa_' . $attr_slug,
                            'field'    => 'slug',
                            'terms'    => $matching_terms,
                            'operator' => 'IN',
                        );
                    } else {
                        // No matching terms, return empty
                        $args['post__in'] = array( 0 );
                    }
                }
            } else {
                // Select filter
                $param_key = 'filter_' . $attr_slug;

                if ( isset( $_POST[ $param_key ] ) && $_POST[ $param_key ] !== '' ) {
                    $values = is_array( $_POST[ $param_key ] )
                        ? $_POST[ $param_key ]
                        : explode( ',', $_POST[ $param_key ] );
                    $values = array_map( 'sanitize_text_field', $values );

                    $tax_query[] = array(
                        'taxonomy' => 'pa_' . $attr_slug,
                        'field'    => 'slug',
                        'terms'    => $values,
                        'operator' => 'IN',
                    );
                }
            }
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
                default:
                    $args['orderby'] = 'menu_order title';
                    $args['order']   = 'ASC';
            }
        }

        if ( ! empty( $tax_query ) ) {
            $tax_query['relation'] = 'AND';
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
            echo '<p class="woocommerce-info">' . esc_html__( 'No vehicles found matching your criteria.', 'filter-woocommerce' ) . '</p>';
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
     * Get terms that fall within a numeric range
     *
     * @param string      $taxonomy Taxonomy name.
     * @param string|null $min      Minimum value.
     * @param string|null $max      Maximum value.
     * @return array Array of term slugs.
     */
    private function get_terms_in_range( $taxonomy, $min, $max ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array();
        }

        $matching_slugs = array();

        foreach ( $terms as $term ) {
            $numeric_value = preg_replace( '/[^0-9]/', '', $term->name );

            if ( $numeric_value === '' ) {
                continue;
            }

            $numeric_value = intval( $numeric_value );
            $include = true;

            if ( $min !== null && $min !== '' && $numeric_value < intval( $min ) ) {
                $include = false;
            }
            if ( $max !== null && $max !== '' && $numeric_value > intval( $max ) ) {
                $include = false;
            }

            if ( $include ) {
                $matching_slugs[] = $term->slug;
            }
        }

        return $matching_slugs;
    }

    /**
     * Get dependent terms via AJAX (e.g., models for a selected make)
     */
    public function get_dependent_terms() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'filter_woocommerce_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'filter-woocommerce' ) ) );
        }

        $attribute = isset( $_POST['attribute'] ) ? sanitize_text_field( $_POST['attribute'] ) : '';
        $parent_attribute = isset( $_POST['parent_attribute'] ) ? sanitize_text_field( $_POST['parent_attribute'] ) : '';
        $parent_value = isset( $_POST['parent_value'] ) ? sanitize_text_field( $_POST['parent_value'] ) : '';

        if ( empty( $attribute ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid attribute.', 'filter-woocommerce' ) ) );
        }

        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $terms = $filter_handler->get_attribute_terms( $attribute, $parent_value );

        $options = array();
        foreach ( $terms as $term ) {
            $options[] = array(
                'slug'  => $term->slug,
                'name'  => $term->name,
                'count' => $term->count,
            );
        }

        wp_send_json_success( array(
            'terms' => $options,
        ) );
    }

    /**
     * Get filter counts via AJAX
     */
    public function get_filter_counts() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'filter_woocommerce_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'filter-woocommerce' ) ) );
        }

        $filter_handler = Filter_WooCommerce::get_instance()->filter_handler;
        $vehicle_attributes = $filter_handler->get_vehicle_attributes();
        $counts = array();

        foreach ( $vehicle_attributes as $attr_slug => $config ) {
            $taxonomy = 'pa_' . $attr_slug;
            $terms = get_terms( array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
            ) );

            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $counts[ $attr_slug ][ $term->slug ] = $term->count;
                }
            }
        }

        wp_send_json_success( $counts );
    }
}
