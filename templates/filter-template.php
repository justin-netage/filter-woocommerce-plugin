<?php
/**
 * Product filter template
 *
 * This template can be overridden by copying it to yourtheme/filter-woocommerce/filter-template.php.
 *
 * @package FilterWooCommerce
 * @version 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Variables available in this template:
 *
 * @var array $available_filters Available filter options
 * @var array $active_filters    Currently active filters
 * @var array $atts              Shortcode attributes
 */
?>

<div class="filter-woocommerce-container filter-woocommerce-template" data-ajax="<?php echo esc_attr( $atts['ajax'] ); ?>" data-layout="<?php echo esc_attr( $atts['layout'] ); ?>">
    <?php
    /**
     * Hook: filter_woocommerce_before_filters
     */
    do_action( 'filter_woocommerce_before_filters' );
    ?>

    <form class="filter-woocommerce-form" method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
        <?php
        /**
         * Hook: filter_woocommerce_filter_sections
         *
         * @hooked filter_woocommerce_price_filter - 10
         * @hooked filter_woocommerce_category_filter - 20
         * @hooked filter_woocommerce_attribute_filters - 30
         * @hooked filter_woocommerce_rating_filter - 40
         * @hooked filter_woocommerce_stock_filter - 50
         * @hooked filter_woocommerce_sale_filter - 60
         */
        do_action( 'filter_woocommerce_filter_sections', $available_filters, $active_filters, $atts );
        ?>

        <?php
        /**
         * Hook: filter_woocommerce_after_filter_sections
         */
        do_action( 'filter_woocommerce_after_filter_sections' );
        ?>

        <div class="filter-woocommerce-actions">
            <?php
            /**
             * Hook: filter_woocommerce_filter_actions
             *
             * @hooked filter_woocommerce_submit_button - 10
             * @hooked filter_woocommerce_reset_button - 20
             */
            do_action( 'filter_woocommerce_filter_actions' );
            ?>
        </div>
    </form>

    <?php
    /**
     * Hook: filter_woocommerce_after_filters
     */
    do_action( 'filter_woocommerce_after_filters' );
    ?>
</div>
