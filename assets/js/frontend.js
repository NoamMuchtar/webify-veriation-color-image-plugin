(function ($) {
    'use strict';

    /**
     * Handle swatch click – update the hidden <select> and trigger WooCommerce change.
     */
    function initSwatchClicks() {
        $(document).on('click', '.wvci-swatch', function () {
            var $swatch = $(this);
            var $container = $swatch.closest('.wvci-swatches');
            var attribute = $container.data('attribute');
            var value = $swatch.data('value');

            // Find the corresponding hidden select
            var $select = $container.siblings('.wvci-select-wrapper').find('select');

            if (!$select.length) {
                return;
            }

            // If already selected, deselect (reset)
            if ($swatch.hasClass('wvci-selected')) {
                $swatch.removeClass('wvci-selected');
                $select.val('').trigger('change');
                return;
            }

            // Select this swatch
            $container.find('.wvci-swatch').removeClass('wvci-selected');
            $swatch.addClass('wvci-selected');

            // Update the hidden select
            $select.val(value).trigger('change');
        });
    }

    /**
     * Listen for WooCommerce variation events to swap the product image.
     */
    function initVariationImageSwap() {
        var $form = $('form.variations_form');

        if (!$form.length) {
            return;
        }

        // Store original image data on first load
        var $mainImage = $form.closest('.product').find('.woocommerce-product-gallery__image:first img, .wp-post-image').first();
        var originalSrc = $mainImage.attr('src');
        var originalSrcset = $mainImage.attr('srcset');
        var originalSizes = $mainImage.attr('sizes');

        // Also store the gallery wrapper link href
        var $mainLink = $mainImage.closest('a');
        var originalHref = $mainLink.attr('href');

        /**
         * When a variation is found, swap the product image.
         */
        $form.on('found_variation', function (event, variation) {
            if (!variation) {
                return;
            }

            var imageData = variation.image;

            if (imageData && imageData.full_src) {
                // Update main product image
                $mainImage.attr('src', imageData.full_src);

                if (imageData.srcset) {
                    $mainImage.attr('srcset', imageData.srcset);
                } else {
                    $mainImage.removeAttr('srcset');
                }

                if (imageData.sizes) {
                    $mainImage.attr('sizes', imageData.sizes);
                }

                // Update link
                if ($mainLink.length) {
                    $mainLink.attr('href', imageData.full_src);
                }

                // Update gallery image wrapper data attributes (for lightbox)
                var $galleryImg = $form.closest('.product').find('.woocommerce-product-gallery__image:first');
                if ($galleryImg.length) {
                    $galleryImg.attr('data-thumb', imageData.gallery_thumbnail_src || imageData.thumb_src || imageData.full_src);
                    $galleryImg.find('a').attr('href', imageData.full_src);
                }
            }
        });

        /**
         * Reset image when variation is cleared.
         */
        $form.on('reset_image', function () {
            if (originalSrc) {
                $mainImage.attr('src', originalSrc);
            }
            if (originalSrcset) {
                $mainImage.attr('srcset', originalSrcset);
            } else {
                $mainImage.removeAttr('srcset');
            }
            if (originalSizes) {
                $mainImage.attr('sizes', originalSizes);
            }
            if (originalHref && $mainLink.length) {
                $mainLink.attr('href', originalHref);
            }
        });

        /**
         * When variation selection is reset/cleared, also clear swatch highlights.
         */
        $form.on('reset_data', function () {
            $form.find('.wvci-swatch').removeClass('wvci-selected');
        });
    }

    /**
     * Keep swatch selection in sync when select changes externally.
     */
    function syncSwatchesWithSelects() {
        $(document).on('change', '.wvci-select-wrapper select', function () {
            var $select = $(this);
            var val = $select.val();
            var $swatches = $select.closest('.wvci-select-wrapper').siblings('.wvci-swatches');

            $swatches.find('.wvci-swatch').removeClass('wvci-selected');

            if (val) {
                $swatches.find('.wvci-swatch[data-value="' + val + '"]').addClass('wvci-selected');
            }
        });
    }

    /**
     * Mark out-of-stock / unavailable swatches.
     */
    function initAvailabilityCheck() {
        var $form = $('form.variations_form');

        if (!$form.length) {
            return;
        }

        $form.on('update_variation_values', function () {
            // After WooCommerce updates which options are available/greyed-out,
            // mirror that state onto our swatches.
            $form.find('.wvci-swatches').each(function () {
                var $swatchContainer = $(this);
                var $select = $swatchContainer.siblings('.wvci-select-wrapper').find('select');

                $swatchContainer.find('.wvci-swatch').each(function () {
                    var $swatch = $(this);
                    var val = $swatch.data('value');
                    var $option = $select.find('option[value="' + val + '"]');

                    if (!$option.length || $option.is(':disabled')) {
                        $swatch.addClass('wvci-disabled');
                    } else {
                        $swatch.removeClass('wvci-disabled');
                    }
                });
            });
        });
    }

    /**
     * Archive / shop page: handle swatch clicks to swap product thumbnail image.
     */
    function initArchiveSwatches() {
        $(document).on('click', '.wvci-archive-swatch', function (e) {
            e.preventDefault();
            e.stopPropagation(); // prevent navigating to product page

            var $swatch = $(this);

            // Out-of-stock swatches are not selectable
            if ($swatch.hasClass('wvci-out-of-stock')) {
                return;
            }
            var $container = $swatch.closest('.wvci-archive-swatches');
            var variationImg = $swatch.data('variation-img');

            // Find the product card wrapper (li.product)
            var $productCard = $container.closest('li.product, .product');
            if (!$productCard.length) {
                return;
            }

            // Store original image on first interaction
            var $img = $productCard.find('.attachment-woocommerce_thumbnail, .woocommerce-placeholder, .wp-post-image').first();
            if (!$img.length) {
                return;
            }

            if (!$img.data('wvci-original-src')) {
                $img.data('wvci-original-src', $img.attr('src'));
                $img.data('wvci-original-srcset', $img.attr('srcset') || '');
            }

            // Toggle: if already selected, deselect and restore original image
            if ($swatch.hasClass('wvci-selected')) {
                $swatch.removeClass('wvci-selected');
                $img.attr('src', $img.data('wvci-original-src'));
                if ($img.data('wvci-original-srcset')) {
                    $img.attr('srcset', $img.data('wvci-original-srcset'));
                } else {
                    $img.removeAttr('srcset');
                }
                return;
            }

            // Select this swatch
            $container.find('.wvci-archive-swatch').removeClass('wvci-selected');
            $swatch.addClass('wvci-selected');

            // Swap the product image if variation has an image
            if (variationImg) {
                $img.attr('src', variationImg);
                $img.removeAttr('srcset'); // avoid conflicting srcset
            }
        });
    }

    $(document).ready(function () {
        initSwatchClicks();
        initVariationImageSwap();
        syncSwatchesWithSelects();
        initAvailabilityCheck();
        initArchiveSwatches();
    });
})(jQuery);
