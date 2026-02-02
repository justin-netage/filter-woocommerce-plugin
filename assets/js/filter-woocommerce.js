/**
 * Filter for WooCommerce - Vehicle Filter JavaScript
 *
 * @package FilterWooCommerce
 */

(function($) {
    'use strict';

    /**
     * Vehicle Filter main object
     */
    var VehicleFilter = {
        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initDependentFilters();
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

            // Select dropdown changes
            this.$form.on('change', '.filter-woocommerce-select', function() {
                var $select = $(this);
                var attribute = $select.data('attribute');

                // Handle dependent filters
                self.handleDependentFilters(attribute, $select.val());

                // Auto-submit if AJAX enabled
                if (self.isAjaxEnabled()) {
                    self.filterProducts();
                }
            });

            // Range input changes (with debounce)
            var rangeTimeout;
            this.$form.on('input', '.filter-woocommerce-range-min, .filter-woocommerce-range-max', function() {
                if (self.isAjaxEnabled()) {
                    clearTimeout(rangeTimeout);
                    rangeTimeout = setTimeout(function() {
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
         * Initialize dependent filters (e.g., Model depends on Make)
         */
        initDependentFilters: function() {
            var self = this;

            this.$form.find('.filter-woocommerce-section[data-depends-on]').each(function() {
                var $section = $(this);
                var dependsOn = $section.data('depends-on');
                var $parentSelect = self.$form.find('.filter-woocommerce-select[data-attribute="' + dependsOn + '"]');

                if ($parentSelect.length && !$parentSelect.val()) {
                    $section.find('.filter-woocommerce-select').prop('disabled', true);
                }
            });
        },

        /**
         * Handle dependent filter updates
         */
        handleDependentFilters: function(parentAttribute, parentValue) {
            var self = this;

            // Find sections that depend on this attribute
            this.$form.find('.filter-woocommerce-section[data-depends-on="' + parentAttribute + '"]').each(function() {
                var $section = $(this);
                var $select = $section.find('.filter-woocommerce-select');
                var attribute = $select.data('attribute');

                if (!parentValue) {
                    // Parent cleared - disable and reset dependent
                    $select.prop('disabled', true).val('');
                    return;
                }

                // Enable and load filtered options
                $select.prop('disabled', false);

                // Load filtered terms via AJAX
                self.loadDependentTerms(attribute, parentAttribute, parentValue, $select);
            });
        },

        /**
         * Load dependent terms via AJAX
         */
        loadDependentTerms: function(attribute, parentAttribute, parentValue, $select) {
            var currentValue = $select.val();

            $.ajax({
                url: filterWooCommerce.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'filter_woocommerce_get_terms',
                    nonce: filterWooCommerce.nonce,
                    attribute: attribute,
                    parent_attribute: parentAttribute,
                    parent_value: parentValue
                },
                beforeSend: function() {
                    $select.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        var terms = response.data.terms;
                        var placeholder = $select.find('option:first').text();

                        // Rebuild select options
                        $select.empty();
                        $select.append('<option value="">' + placeholder + '</option>');

                        $.each(terms, function(index, term) {
                            $select.append(
                                '<option value="' + term.slug + '">' +
                                term.name + ' (' + term.count + ')' +
                                '</option>'
                            );
                        });

                        // Try to restore previous value
                        if (currentValue) {
                            $select.val(currentValue);
                        }
                    }
                },
                complete: function() {
                    $select.prop('disabled', false);
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

            // Collect select filters
            this.$form.find('.filter-woocommerce-select').each(function() {
                var $select = $(this);
                var value = $select.val();
                if (value) {
                    data[$select.attr('name')] = value;
                }
            });

            // Collect range filters
            this.$form.find('.filter-woocommerce-range-min').each(function() {
                var $input = $(this);
                var value = $input.val();
                if (value) {
                    data[$input.attr('name')] = value;
                }
            });

            this.$form.find('.filter-woocommerce-range-max').each(function() {
                var $input = $(this);
                var value = $input.val();
                if (value) {
                    data[$input.attr('name')] = value;
                }
            });

            // Sorting
            var orderby = $('.woocommerce-ordering select').val();
            if (orderby) {
                data.orderby = orderby;
            }

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
            var keysToDelete = [];
            url.searchParams.forEach(function(value, key) {
                if (key.indexOf('filter_') === 0) {
                    keysToDelete.push(key);
                }
            });
            keysToDelete.forEach(function(key) {
                url.searchParams.delete(key);
            });

            // Add new params
            for (var key in data) {
                if (key.indexOf('filter_') === 0 && data[key]) {
                    url.searchParams.set(key, data[key]);
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

            // If AJAX enabled, parse the param and clear it from form
            if (this.isAjaxEnabled()) {
                var url = new URL(href, window.location.origin);

                // Find which parameter was removed by comparing current URL params
                var currentUrl = new URL(window.location.href);
                var self = this;

                currentUrl.searchParams.forEach(function(value, key) {
                    if (!url.searchParams.has(key)) {
                        // This param was removed
                        var $input = self.$form.find('[name="' + key + '"]');
                        if ($input.length) {
                            $input.val('');
                        }
                    }
                });

                this.filterProducts();
            } else {
                window.location.href = href;
            }
        },

        /**
         * Clear all filters
         */
        clearAllFilters: function() {
            // Reset all selects
            this.$form.find('.filter-woocommerce-select').val('');

            // Reset all range inputs
            this.$form.find('.filter-woocommerce-range-min, .filter-woocommerce-range-max').val('');

            // Reset dependent filter states
            this.$form.find('.filter-woocommerce-section[data-depends-on]').each(function() {
                $(this).find('.filter-woocommerce-select').prop('disabled', true);
            });

            // Reload products
            this.filterProducts();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        VehicleFilter.init();
    });

})(jQuery);
