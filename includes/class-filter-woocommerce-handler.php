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
 * Handles the main filtering logic for vehicle product attributes
 */
class Filter_WooCommerce_Handler {

    /**
     * Vehicle attributes configuration
     * Maps display names to WooCommerce attribute slugs
     *
     * @var array
     */
    private $vehicle_attributes = array(
        'new-or-used' => array(
            'label'      => 'New or Used',
            'type'       => 'select',
            'query_type' => 'or',
        ),
        'make' => array(
            'label'      => 'Make',
            'type'       => 'select',
            'query_type' => 'or',
        ),
        'model' => array(
            'label'      => 'Model',
            'type'       => 'select',
            'query_type' => 'or',
            'depends_on' => 'make',
        ),
        'region' => array(
            'label'      => 'Region',
            'type'       => 'select',
            'query_type' => 'or',
        ),
        'transmission' => array(
            'label'      => 'Transmission',
            'type'       => 'select',
            'query_type' => 'or',
        ),
        'body-type' => array(
            'label'      => 'Body Type',
            'type'       => 'select',
            'query_type' => 'or',
        ),
        'kilometers' => array(
            'label'      => 'Kilometers',
            'type'       => 'range',
            'query_type' => 'range',
        ),
        'vehicle-year' => array(
            'label'      => 'Year Model',
            'type'       => 'range',
            'query_type' => 'range',
        ),
    );

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
        // Allow customization of vehicle attributes via filter
        $this->vehicle_attributes = apply_filters(
            'filter_woocommerce_vehicle_attributes',
            $this->vehicle_attributes
        );

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
     * Get configured vehicle attributes
     *
     * @return array
     */
    public function get_vehicle_attributes() {
        return $this->vehicle_attributes;
    }

    /**
     * Parse filter parameters from URL
     */
    public function parse_filter_params() {
        foreach ( $this->vehicle_attributes as $slug => $config ) {
            $param_name = 'filter_' . $slug;

            if ( $config['type'] === 'range' ) {
                // Range filter (min/max)
                $min_param = $param_name . '_min';
                $max_param = $param_name . '_max';

                if ( isset( $_GET[ $min_param ] ) && $_GET[ $min_param ] !== '' ) {
                    $this->active_filters[ $slug ]['min'] = sanitize_text_field( $_GET[ $min_param ] );
                }
                if ( isset( $_GET[ $max_param ] ) && $_GET[ $max_param ] !== '' ) {
                    $this->active_filters[ $slug ]['max'] = sanitize_text_field( $_GET[ $max_param ] );
                }
            } else {
                // Select/checkbox filter
                if ( isset( $_GET[ $param_name ] ) && $_GET[ $param_name ] !== '' ) {
                    $values = is_array( $_GET[ $param_name ] )
                        ? $_GET[ $param_name ]
                        : explode( ',', $_GET[ $param_name ] );
                    $this->active_filters[ $slug ] = array_map( 'sanitize_text_field', $values );
                }
            }
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

        $tax_query  = $query->get( 'tax_query', array() );
        $meta_query = $query->get( 'meta_query', array() );

        foreach ( $this->active_filters as $attribute_slug => $filter_value ) {
            $config   = $this->vehicle_attributes[ $attribute_slug ] ?? null;
            $taxonomy = 'pa_' . $attribute_slug;

            if ( ! $config ) {
                continue;
            }

            if ( $config['type'] === 'range' ) {
                // Range filter using taxonomy term values
                // For range attributes, we need to filter by the numeric value of terms
                if ( isset( $filter_value['min'] ) || isset( $filter_value['max'] ) ) {
                    $matching_terms = $this->get_terms_in_range(
                        $taxonomy,
                        $filter_value['min'] ?? null,
                        $filter_value['max'] ?? null
                    );

                    if ( ! empty( $matching_terms ) ) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $matching_terms,
                            'operator' => 'IN',
                        );
                    } else {
                        // No matching terms, ensure no products are returned
                        $query->set( 'post__in', array( 0 ) );
                    }
                }
            } else {
                // Standard select/checkbox filter
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => (array) $filter_value,
                    'operator' => 'IN',
                );
            }
        }

        if ( ! empty( $tax_query ) ) {
            $tax_query['relation'] = 'AND';
            $query->set( 'tax_query', $tax_query );
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Get taxonomy terms that fall within a numeric range
     *
     * @param string     $taxonomy Taxonomy name.
     * @param string|null $min     Minimum value.
     * @param string|null $max     Maximum value.
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
            // Extract numeric value from term name/slug
            $numeric_value = preg_replace( '/[^0-9]/', '', $term->name );

            if ( $numeric_value === '' ) {
                continue;
            }

            $numeric_value = intval( $numeric_value );
            $include = true;

            if ( $min !== null && $numeric_value < intval( $min ) ) {
                $include = false;
            }
            if ( $max !== null && $numeric_value > intval( $max ) ) {
                $include = false;
            }

            if ( $include ) {
                $matching_slugs[] = $term->slug;
            }
        }

        return $matching_slugs;
    }

    /**
     * Get available filter options for all vehicle attributes
     *
     * @return array
     */
    public function get_available_filters() {
        $filters = array();

        foreach ( $this->vehicle_attributes as $slug => $config ) {
            $taxonomy = 'pa_' . $slug;
            $terms    = get_terms( array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ) );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            $filter_data = array(
                'label'  => $config['label'],
                'slug'   => $slug,
                'type'   => $config['type'],
                'terms'  => $terms,
            );

            // For range filters, also get min/max values
            if ( $config['type'] === 'range' ) {
                $range = $this->get_attribute_range( $terms );
                $filter_data['min'] = $range['min'];
                $filter_data['max'] = $range['max'];
            }

            // Add dependency info if exists
            if ( isset( $config['depends_on'] ) ) {
                $filter_data['depends_on'] = $config['depends_on'];
            }

            $filters[ $slug ] = $filter_data;
        }

        return $filters;
    }

    /**
     * Get min/max range from terms
     *
     * @param array $terms Array of term objects.
     * @return array
     */
    private function get_attribute_range( $terms ) {
        $values = array();

        foreach ( $terms as $term ) {
            $numeric = preg_replace( '/[^0-9]/', '', $term->name );
            if ( $numeric !== '' ) {
                $values[] = intval( $numeric );
            }
        }

        if ( empty( $values ) ) {
            return array( 'min' => 0, 'max' => 0 );
        }

        return array(
            'min' => min( $values ),
            'max' => max( $values ),
        );
    }

    /**
     * Get terms for a specific attribute, optionally filtered by parent attribute
     *
     * @param string $attribute_slug The attribute slug.
     * @param string $parent_value   Optional parent filter value (e.g., make for model).
     * @return array
     */
    public function get_attribute_terms( $attribute_slug, $parent_value = '' ) {
        $taxonomy = 'pa_' . $attribute_slug;
        $config   = $this->vehicle_attributes[ $attribute_slug ] ?? null;

        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $terms ) ) {
            return array();
        }

        // If this attribute depends on another and a parent value is provided
        if ( $config && isset( $config['depends_on'] ) && ! empty( $parent_value ) ) {
            $terms = $this->filter_terms_by_parent( $terms, $attribute_slug, $config['depends_on'], $parent_value );
        }

        return $terms;
    }

    /**
     * Filter terms based on parent attribute value
     * Only returns terms that exist on products with the given parent attribute value
     *
     * @param array  $terms           Terms to filter.
     * @param string $attribute_slug  Current attribute slug.
     * @param string $parent_slug     Parent attribute slug.
     * @param string $parent_value    Parent attribute value.
     * @return array Filtered terms.
     */
    private function filter_terms_by_parent( $terms, $attribute_slug, $parent_slug, $parent_value ) {
        // Get product IDs that have the parent attribute value
        $product_ids = wc_get_products( array(
            'limit'  => -1,
            'return' => 'ids',
            'status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'pa_' . $parent_slug,
                    'field'    => 'slug',
                    'terms'    => $parent_value,
                ),
            ),
        ) );

        if ( empty( $product_ids ) ) {
            return array();
        }

        // Get terms that exist on these products
        $valid_term_ids = array();
        foreach ( $product_ids as $product_id ) {
            $product_terms = wp_get_post_terms( $product_id, 'pa_' . $attribute_slug, array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $product_terms ) ) {
                $valid_term_ids = array_merge( $valid_term_ids, $product_terms );
            }
        }

        $valid_term_ids = array_unique( $valid_term_ids );

        // Filter the terms
        return array_filter( $terms, function( $term ) use ( $valid_term_ids ) {
            return in_array( $term->term_id, $valid_term_ids );
        } );
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
     * Check if a specific filter is active
     *
     * @param string $attribute_slug Attribute slug.
     * @param string $term_slug      Optional specific term slug.
     * @return bool
     */
    public function is_filter_active( $attribute_slug, $term_slug = '' ) {
        if ( ! isset( $this->active_filters[ $attribute_slug ] ) ) {
            return false;
        }

        if ( empty( $term_slug ) ) {
            return true;
        }

        $active = $this->active_filters[ $attribute_slug ];

        // For range filters
        if ( is_array( $active ) && ( isset( $active['min'] ) || isset( $active['max'] ) ) ) {
            return true;
        }

        // For select filters
        return in_array( $term_slug, (array) $active );
    }

    /**
     * Get the active value for a filter
     *
     * @param string $attribute_slug Attribute slug.
     * @return mixed
     */
    public function get_active_filter_value( $attribute_slug ) {
        return $this->active_filters[ $attribute_slug ] ?? null;
    }

    /**
     * Build filter URL with given parameters
     *
     * @param array $filters Filter parameters to add/modify.
     * @param array $remove  Filter parameters to remove.
     * @return string
     */
    public function build_filter_url( $filters = array(), $remove = array() ) {
        $base_url = is_shop() ? wc_get_page_permalink( 'shop' ) : get_permalink();

        // Start with current filters
        $params = array();
        foreach ( $this->active_filters as $slug => $value ) {
            $config = $this->vehicle_attributes[ $slug ] ?? null;

            if ( $config && $config['type'] === 'range' ) {
                if ( isset( $value['min'] ) ) {
                    $params[ 'filter_' . $slug . '_min' ] = $value['min'];
                }
                if ( isset( $value['max'] ) ) {
                    $params[ 'filter_' . $slug . '_max' ] = $value['max'];
                }
            } else {
                $params[ 'filter_' . $slug ] = implode( ',', (array) $value );
            }
        }

        // Add new filters
        foreach ( $filters as $key => $value ) {
            $params[ $key ] = $value;
        }

        // Remove specified filters
        foreach ( $remove as $key ) {
            unset( $params[ $key ] );
        }

        if ( empty( $params ) ) {
            return $base_url;
        }

        return add_query_arg( $params, $base_url );
    }

    /**
     * Get URL to clear all filters
     *
     * @return string
     */
    public function get_clear_url() {
        return is_shop() ? wc_get_page_permalink( 'shop' ) : get_permalink();
    }
}
