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
     * Mark unavailable combination swatches as disabled,
     * and out-of-stock swatches with a visual indicator (still clickable).
     */
    function initAvailabilityCheck() {
        var $form = $('form.variations_form');

        if (!$form.length) {
            return;
        }

        var allVariations = $form.data('product_variations');

        $form.on('update_variation_values', function () {
            $form.find('.wvci-swatches').each(function () {
                var $swatchContainer = $(this);
                var $select = $swatchContainer.siblings('.wvci-select-wrapper').find('select');
                var attrName = $select.data('attribute_name') || $select.attr('name');

                // Get current selections for other attributes
                var currentSelections = {};
                $form.find('.variations select').each(function () {
                    var name = $(this).data('attribute_name') || $(this).attr('name');
                    if (name !== attrName) {
                        currentSelections[name] = $(this).val();
                    }
                });

                $swatchContainer.find('.wvci-swatch').each(function () {
                    var $swatch = $(this);
                    var val = String($swatch.data('value'));
                    var $option = $select.find('option[value="' + val + '"]');

                    // Reset classes
                    $swatch.removeClass('wvci-disabled wvci-out-of-stock');

                    // Disabled: combination doesn't exist
                    if (!$option.length || $option.is(':disabled')) {
                        $swatch.addClass('wvci-disabled');
                        return;
                    }

                    // Out-of-stock check: enabled but all matching variations are out of stock
                    if (allVariations && allVariations.length) {
                        var allOutOfStock = true;
                        var hasMatch = false;

                        for (var i = 0; i < allVariations.length; i++) {
                            var variation = allVariations[i];
                            var attrs = variation.attributes;

                            // Must match this attribute value (or be "any")
                            if (attrs[attrName] !== '' && attrs[attrName] !== val) {
                                continue;
                            }

                            // Must match other currently selected attributes
                            var matchesOthers = true;
                            for (var key in currentSelections) {
                                if (!currentSelections[key]) continue;
                                if (attrs[key] !== '' && attrs[key] !== currentSelections[key]) {
                                    matchesOthers = false;
                                    break;
                                }
                            }

                            if (!matchesOthers) continue;

                            hasMatch = true;
                            if (variation.is_in_stock) {
                                allOutOfStock = false;
                                break;
                            }
                        }

                        if (hasMatch && allOutOfStock) {
                            $swatch.addClass('wvci-out-of-stock');
                        }
                    }
                });
            });
        });
    }

    /**
     * Auto-select the first swatch in each attribute group on product page load.
     */
    function initAutoSelectFirst() {
        var $form = $('form.variations_form');

        if (!$form.length) {
            return;
        }

        // Wait for WooCommerce to fully initialize the variation form
        $form.on('wc_variation_form', function () {
            setTimeout(function () {
                $form.find('.wvci-swatches').each(function () {
                    var $firstSwatch = $(this).find('.wvci-swatch:not(.wvci-disabled):first');
                    if ($firstSwatch.length && !$firstSwatch.hasClass('wvci-selected')) {
                        $firstSwatch.trigger('click');
                    }
                });
            }, 50);
        });
    }

    /**
     * Find the main product image inside a product card.
     * Uses multiple fallback selectors for maximum theme compatibility.
     */
    function findProductImage($productCard) {
        var $img = $productCard.find('img.wp-post-image').first();
        if (!$img.length) {
            $img = $productCard.find('img.attachment-woocommerce_thumbnail, img.attachment-medium_large, img.woocommerce-placeholder').first();
        }
        if (!$img.length) {
            $img = $productCard.find('.woocommerce-loop-product__link img').first();
        }
        if (!$img.length) {
            $img = $productCard.find('a > img').first();
        }
        return $img;
    }

    /**
     * Smoothly swap an archive product image: fade out → swap src → fade in.
     */
    function fadeSwapImage($img, newSrc) {
        if (!newSrc || $img.attr('src') === newSrc) {
            return;
        }

        // Preload the new image first to avoid flicker
        var preload = new Image();
        preload.onload = function () {
            // Fade out current image
            $img.css('opacity', '0');

            // After CSS transition completes, swap src and fade in
            setTimeout(function () {
                $img.attr('src', newSrc);
                $img.removeAttr('srcset');
                $img.css('opacity', '1');
            }, 200);
        };
        preload.src = newSrc;
    }

    /**
     * Archive / shop page: handle swatch clicks to swap product thumbnail image.
     */
    function initArchiveSwatches() {
        $(document).on('click', '.wvci-archive-swatch', function (e) {
            e.preventDefault();
            e.stopPropagation(); // prevent navigating to product page

            var $swatch = $(this);
            var $container = $swatch.closest('.wvci-archive-swatches');
            var variationImg = $swatch.data('variation-img');

            // Find the product card wrapper
            var $productCard = $container.closest('li.product, .product');
            if (!$productCard.length) {
                return;
            }

            var $img = findProductImage($productCard);
            if (!$img.length) {
                return;
            }

            // Store original image on first interaction
            if (!$img.data('wvci-original-src')) {
                $img.data('wvci-original-src', $img.attr('src'));
                $img.data('wvci-original-srcset', $img.attr('srcset') || '');
            }

            // Toggle: if already selected, deselect and restore original image
            if ($swatch.hasClass('wvci-selected')) {
                $swatch.removeClass('wvci-selected');
                fadeSwapImage($img, $img.data('wvci-original-src'));
                return;
            }

            // Select this swatch
            $container.find('.wvci-archive-swatch').removeClass('wvci-selected');
            $swatch.addClass('wvci-selected');

            // Swap the product image if variation has an image
            if (variationImg) {
                fadeSwapImage($img, variationImg);
            }
        });
    }

    $(document).ready(function () {
        initSwatchClicks();
        initVariationImageSwap();
        syncSwatchesWithSelects();
        initAvailabilityCheck();
        initAutoSelectFirst();
        initArchiveSwatches();
    });
})(jQuery);
