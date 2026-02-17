(function ($) {
    'use strict';

    /**
     * Toggle color / image fields based on swatch type selection.
     */
    function toggleSwatchFields() {
        var type = $('#wvci_swatch_type').val();

        $('.wvci-field-color').toggle(type === 'color');
        $('.wvci-field-image').toggle(type === 'image');
    }

    /**
     * Initialize the WordPress color picker.
     */
    function initColorPicker() {
        if ($.fn.wpColorPicker) {
            $('.wvci-color-picker').wpColorPicker();
        }
    }

    /**
     * Image upload via WordPress Media Library.
     */
    function initImageUploader() {
        var frame;

        $(document).on('click', '.wvci-upload-image', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $container = $button.closest('.form-field, td');
            var $input = $container.find('.wvci-image-id');
            var $preview = $container.find('.wvci-image-preview');
            var $remove = $container.find('.wvci-remove-image');

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Select Swatch Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var thumbUrl = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;

                $input.val(attachment.id);
                $preview.html('<img src="' + thumbUrl + '" width="60" height="60" />');
                $remove.show();
            });

            frame.open();
        });

        $(document).on('click', '.wvci-remove-image', function (e) {
            e.preventDefault();
            var $button = $(this);
            var $container = $button.closest('.form-field, td');

            $container.find('.wvci-image-id').val('');
            $container.find('.wvci-image-preview').html('');
            $button.hide();
        });
    }

    $(document).ready(function () {
        toggleSwatchFields();
        initColorPicker();
        initImageUploader();

        $(document).on('change', '#wvci_swatch_type', toggleSwatchFields);

        // After adding a new term (AJAX), reset fields
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (
                settings.data &&
                typeof settings.data === 'string' &&
                settings.data.indexOf('action=add-tag') !== -1
            ) {
                // Check for success
                var res = xhr.responseXML;
                if (res && $('wp_error', res).length === 0) {
                    $('#wvci_swatch_type').val('');
                    toggleSwatchFields();

                    // Reset color picker
                    $('.wvci-color-picker').val('');
                    if ($.fn.wpColorPicker) {
                        $('.wvci-color-picker').wpColorPicker('color', '');
                    }

                    // Reset image
                    $('.wvci-image-id').val('');
                    $('.wvci-image-preview').html('');
                    $('.wvci-remove-image').hide();
                }
            }
        });
    });
})(jQuery);
