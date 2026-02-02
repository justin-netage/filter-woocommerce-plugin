/**
 * Filter for WooCommerce - Frontend JavaScript
 *
 * @package FilterWooCommerce
 */

(function($) {
    'use strict';

    /**
     * Filter WooCommerce main object
     */
    var FilterWooCommerce = {
        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$container = $('.filter-woocommerce-container');
            this.$form = $('.filter-woocommerce-form');
            this.$productsContainer = $('ul.products').parent();
            this.$pagination = $('.woocommerce-pagination');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            this.$form.on('submit', function(e) {
                if (self.isAjaxEnabled()) {
                    e.preventDefault();
                    self.filterProducts();
                }
            });

            // Checkbox changes for AJAX filtering
            this.$form.on('change', 'input[type="checkbox"]', function() {
                if (self.isAjaxEnabled()) {
                    self.filterProducts();
                }
            });

            // Price input changes (with debounce)
            var priceTimeout;
            this.$form.on('input', '.filter-woocommerce-min-price, .filter-woocommerce-max-price', function() {
                if (self.isAjaxEnabled()) {
                    clearTimeout(priceTimeout);
                    priceTimeout = setTimeout(function() {
                        self.filterProducts();
                    }, 500);
                }
            });

            // Collapsible sections
            this.$container.on('click', '.collapsible .filter-woocommerce-title', function() {
                $(this).closest('.filter-woocommerce-section').toggleClass('collapsed');
            });

            // Active filter removal
            $(document).on('click', '.filter-woocommerce-remove', function(e) {
                if (self.isAjaxEnabled()) {
                    e.preventDefault();
                    self.removeFilter($(this));
                }
            });

            // Clear all filters
            $(document).on('click', '.filter-woocommerce-clear-all', function(e) {
                if (self.isAjaxEnabled()) {
                    e.preventDefault();
                    self.clearAllFilters();
                }
            });

            // Pagination handling
            $(document).on('click', '.woocommerce-pagination a', function(e) {
                if (self.isAjaxEnabled()) {
                    e.preventDefault();
                    var page = self.getPageFromUrl($(this).attr('href'));
                    self.filterProducts(page);
                }
            });
        },

        /**
         * Check if AJAX is enabled
         */
        isAjaxEnabled: function() {
            return this.$container.data('ajax') === 'yes';
        },

        /**
         * Filter products via AJAX
         */
        filterProducts: function(page) {
            var self = this;
            page = page || 1;

            // Show loading state
            this.showLoading();

            // Collect filter data
            var data = this.collectFilterData();
            data.action = 'filter_woocommerce_products';
            data.nonce = filterWooCommerce.nonce;
            data.page = page;

            // Make AJAX request
            $.ajax({
                url: filterWooCommerce.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.updateProducts(response.data);
                        self.updateUrl(data);
                    } else {
                        self.showError(response.data.message || filterWooCommerce.noResults);
                    }
                },
                error: function() {
                    self.showError('An error occurred. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * Collect filter data from form
         */
        collectFilterData: function() {
            var data = {};

            // Price
            var minPrice = this.$form.find('.filter-woocommerce-min-price').val();
            var maxPrice = this.$form.find('.filter-woocommerce-max-price').val();
            if (minPrice) data.min_price = minPrice;
            if (maxPrice) data.max_price = maxPrice;

            // Categories
            var categories = [];
            this.$form.find('input[name="product_cat[]"]:checked').each(function() {
                categories.push($(this).val());
            });
            if (categories.length) data.categories = categories;

            // Attributes
            var attributes = {};
            this.$form.find('.filter-woocommerce-attribute').each(function() {
                var $section = $(this);
                var attributeName = '';

                // Extract attribute name from section class
                var classes = $section.attr('class').split(' ');
                for (var i = 0; i < classes.length; i++) {
                    if (classes[i].indexOf('filter-woocommerce-') === 0 &&
                        classes[i] !== 'filter-woocommerce-section' &&
                        classes[i] !== 'filter-woocommerce-attribute') {
                        attributeName = classes[i].replace('filter-woocommerce-', '');
                        break;
                    }
                }

                if (attributeName) {
                    var selectedTerms = [];
                    $section.find('input[type="checkbox"]:checked').each(function() {
                        selectedTerms.push($(this).val());
                    });
                    if (selectedTerms.length) {
                        attributes[attributeName] = selectedTerms;
                    }
                }
            });
            if (Object.keys(attributes).length) data.attributes = attributes;

            // Rating
            var ratings = [];
            this.$form.find('input[name="rating_filter[]"]:checked').each(function() {
                ratings.push($(this).val());
            });
            if (ratings.length) data.rating = ratings;

            // Stock status
            if (this.$form.find('input[name="in_stock"]').is(':checked')) {
                data.in_stock = 1;
            }

            // On sale
            if (this.$form.find('input[name="on_sale"]').is(':checked')) {
                data.on_sale = 1;
            }

            // Sorting
            var orderby = $('.woocommerce-ordering select').val();
            if (orderby) data.orderby = orderby;

            return data;
        },

        /**
         * Update products display
         */
        updateProducts: function(data) {
            // Update products
            this.$productsContainer.html(data.html);

            // Update pagination
            if (data.pagination) {
                var paginationHtml = '<nav class="woocommerce-pagination">' +
                    '<ul class="page-numbers">';
                for (var i = 0; i < data.pagination.length; i++) {
                    paginationHtml += '<li>' + data.pagination[i] + '</li>';
                }
                paginationHtml += '</ul></nav>';

                if (this.$pagination.length) {
                    this.$pagination.replaceWith(paginationHtml);
                } else {
                    this.$productsContainer.after(paginationHtml);
                }
            } else {
                this.$pagination.remove();
            }

            // Update pagination reference
            this.$pagination = $('.woocommerce-pagination');

            // Update result count
            this.updateResultCount(data.found_posts);

            // Scroll to products
            this.scrollToProducts();

            // Trigger event for other scripts
            $(document).trigger('filter_woocommerce_updated', [data]);
        },

        /**
         * Update URL with filter parameters
         */
        updateUrl: function(data) {
            if (!window.history.pushState) return;

            var url = new URL(window.location.href);

            // Clear existing filter params
            url.searchParams.delete('min_price');
            url.searchParams.delete('max_price');
            url.searchParams.delete('on_sale');
            url.searchParams.delete('in_stock');
            url.searchParams.delete('rating_filter');

            // Remove attribute filters
            var keysToDelete = [];
            url.searchParams.forEach(function(value, key) {
                if (key.indexOf('filter_') === 0 || key === 'product_cat') {
                    keysToDelete.push(key);
                }
            });
            keysToDelete.forEach(function(key) {
                url.searchParams.delete(key);
            });

            // Add new params
            if (data.min_price) url.searchParams.set('min_price', data.min_price);
            if (data.max_price) url.searchParams.set('max_price', data.max_price);
            if (data.on_sale) url.searchParams.set('on_sale', '1');
            if (data.in_stock) url.searchParams.set('in_stock', '1');
            if (data.rating && data.rating.length) {
                url.searchParams.set('rating_filter', data.rating.join(','));
            }
            if (data.categories && data.categories.length) {
                url.searchParams.set('product_cat', data.categories.join(','));
            }
            if (data.attributes) {
                for (var attr in data.attributes) {
                    url.searchParams.set('filter_' + attr, data.attributes[attr].join(','));
                }
            }

            window.history.pushState({}, '', url.toString());
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            this.$container.addClass('filter-woocommerce-loading');
            this.$productsContainer.addClass('filter-woocommerce-products-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            this.$container.removeClass('filter-woocommerce-loading');
            this.$productsContainer.removeClass('filter-woocommerce-products-loading');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.$productsContainer.html(
                '<div class="filter-woocommerce-no-results">' +
                '<p>' + message + '</p>' +
                '</div>'
            );
        },

        /**
         * Update result count display
         */
        updateResultCount: function(count) {
            var $resultCount = $('.woocommerce-result-count');
            if ($resultCount.length && count !== undefined) {
                var text = count === 1
                    ? 'Showing the single result'
                    : 'Showing all ' + count + ' results';
                $resultCount.text(text);
            }
        },

        /**
         * Scroll to products
         */
        scrollToProducts: function() {
            var offset = this.$productsContainer.offset();
            if (offset) {
                $('html, body').animate({
                    scrollTop: offset.top - 100
                }, 300);
            }
        },

        /**
         * Get page number from URL
         */
        getPageFromUrl: function(url) {
            var match = url.match(/paged?=(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
            match = url.match(/page\/(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
            return 1;
        },

        /**
         * Remove a single filter
         */
        removeFilter: function($element) {
            var href = $element.attr('href');
            var url = new URL(href, window.location.origin);

            // Update form inputs based on removed parameter
            url.searchParams.forEach(function(value, key) {
                // This will be handled by page reload for non-AJAX
            });

            // Reload with updated URL
            window.location.href = href;
        },

        /**
         * Clear all filters
         */
        clearAllFilters: function() {
            // Reset form
            this.$form.find('input[type="checkbox"]').prop('checked', false);
            this.$form.find('input[type="number"]').val('');

            // Reload products
            this.filterProducts();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        FilterWooCommerce.init();
    });

})(jQuery);
