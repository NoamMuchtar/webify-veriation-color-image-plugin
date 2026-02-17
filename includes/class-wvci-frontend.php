<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend: replaces default WooCommerce dropdown selects with color/image swatches
 * and swaps the main product image when a variation is selected.
 */
class WVCI_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_swatches' ), 10, 2 );
        add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_image_data' ), 10, 3 );
    }

    /**
     * Enqueue frontend CSS and JS on single product pages.
     */
    public function enqueue_frontend_assets() {
        if ( ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'wvci-frontend',
            WVCI_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WVCI_VERSION
        );

        wp_enqueue_script(
            'wvci-frontend',
            WVCI_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            WVCI_VERSION,
            true
        );
    }

    /**
     * Replace the default <select> dropdown with swatches if the attribute terms have swatch data.
     */
    public function render_swatches( $html, $args ) {
        $attribute = $args['attribute'];
        $product   = $args['product'];
        $options   = $args['options'];

        if ( empty( $options ) ) {
            return $html;
        }

        // Check if this taxonomy has any terms with swatch data
        if ( ! taxonomy_exists( $attribute ) ) {
            return $html;
        }

        $has_swatches = false;
        $terms_data   = array();

        foreach ( $options as $option ) {
            $term = get_term_by( 'slug', $option, $attribute );
            if ( ! $term ) {
                continue;
            }

            $swatch_type = get_term_meta( $term->term_id, 'wvci_swatch_type', true );
            if ( $swatch_type ) {
                $has_swatches = true;
            }

            $terms_data[] = array(
                'slug'  => $option,
                'name'  => $term->name,
                'type'  => $swatch_type ? $swatch_type : '',
                'color' => get_term_meta( $term->term_id, 'wvci_swatch_color', true ),
                'image' => get_term_meta( $term->term_id, 'wvci_swatch_image', true ),
            );
        }

        if ( ! $has_swatches ) {
            return $html;
        }

        // Build swatch HTML – keep original select hidden for WooCommerce JS compatibility
        $swatch_html = '<div class="wvci-swatches" data-attribute="' . esc_attr( sanitize_title( $attribute ) ) . '">';

        foreach ( $terms_data as $td ) {
            $active_class = '';
            $swatch_inner = '';

            if ( 'color' === $td['type'] && $td['color'] ) {
                $swatch_inner = '<span class="wvci-swatch wvci-swatch--color" data-value="' . esc_attr( $td['slug'] ) . '" title="' . esc_attr( $td['name'] ) . '" style="background-color:' . esc_attr( $td['color'] ) . ';"></span>';
            } elseif ( 'image' === $td['type'] && $td['image'] ) {
                $img_url = wp_get_attachment_image_url( $td['image'], 'thumbnail' );
                if ( $img_url ) {
                    $swatch_inner = '<span class="wvci-swatch wvci-swatch--image" data-value="' . esc_attr( $td['slug'] ) . '" title="' . esc_attr( $td['name'] ) . '"><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $td['name'] ) . '" /></span>';
                }
            }

            // Fallback: if no swatch data, show label
            if ( ! $swatch_inner ) {
                $swatch_inner = '<span class="wvci-swatch wvci-swatch--label" data-value="' . esc_attr( $td['slug'] ) . '" title="' . esc_attr( $td['name'] ) . '">' . esc_html( $td['name'] ) . '</span>';
            }

            $swatch_html .= $swatch_inner;
        }

        $swatch_html .= '</div>';

        // Wrap original select to hide it but keep it functional
        $html = '<div class="wvci-select-wrapper" style="position:absolute;overflow:hidden;clip:rect(0,0,0,0);width:1px;height:1px;">' . $html . '</div>';

        return $swatch_html . $html;
    }

    /**
     * Attach image data to each variation for the frontend JS.
     */
    public function add_variation_image_data( $variation_data, $product, $variation ) {
        $image_id = $variation->get_image_id();
        if ( $image_id ) {
            $full_src  = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
            $thumb_src = wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' );
            $srcset    = wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' );
            $sizes     = wp_get_attachment_image_sizes( $image_id, 'woocommerce_single' );

            $variation_data['wvci_image'] = array(
                'full_src'  => $full_src ? $full_src : '',
                'thumb_src' => $thumb_src ? $thumb_src : '',
                'srcset'    => $srcset ? $srcset : '',
                'sizes'     => $sizes ? $sizes : '',
            );
        }
        return $variation_data;
    }
}
