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

        // Archive / shop page swatches
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_archive_swatches' ), 7 );
    }

    /**
     * Enqueue frontend CSS and JS on product and archive/shop pages.
     */
    public function enqueue_frontend_assets() {
        if ( ! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag() && ! is_product_taxonomy() ) {
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

    /**
     * Render color/image swatches under each product on archive/shop pages.
     * Each swatch carries the variation image URL so JS can swap the product thumbnail.
     */
    public function render_archive_swatches() {
        global $product;

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        $attributes = $product->get_variation_attributes();

        if ( empty( $attributes ) ) {
            return;
        }

        // Build a map: attribute_slug => variation image (woocommerce_thumbnail size)
        $variation_image_map = $this->build_variation_image_map( $product );

        foreach ( $attributes as $attribute_name => $options ) {
            if ( ! taxonomy_exists( $attribute_name ) ) {
                continue;
            }

            $has_swatches = false;
            $swatches     = array();

            foreach ( $options as $option ) {
                $term = get_term_by( 'slug', $option, $attribute_name );
                if ( ! $term ) {
                    continue;
                }

                $swatch_type = get_term_meta( $term->term_id, 'wvci_swatch_type', true );
                if ( ! $swatch_type ) {
                    continue;
                }

                $has_swatches = true;

                // Find the variation image for this attribute value
                $var_image_url = '';
                $attr_key      = sanitize_title( $attribute_name );
                if ( isset( $variation_image_map[ $attr_key ][ $option ] ) ) {
                    $var_image_url = $variation_image_map[ $attr_key ][ $option ];
                }

                $swatches[] = array(
                    'slug'      => $option,
                    'name'      => $term->name,
                    'type'      => $swatch_type,
                    'color'     => get_term_meta( $term->term_id, 'wvci_swatch_color', true ),
                    'image'     => get_term_meta( $term->term_id, 'wvci_swatch_image', true ),
                    'var_image' => $var_image_url,
                );
            }

            if ( ! $has_swatches ) {
                continue;
            }

            echo '<div class="wvci-archive-swatches" data-product-id="' . esc_attr( $product->get_id() ) . '">';

            foreach ( $swatches as $sw ) {
                $data_var_img = $sw['var_image'] ? ' data-variation-img="' . esc_url( $sw['var_image'] ) . '"' : '';

                if ( 'color' === $sw['type'] && $sw['color'] ) {
                    echo '<span class="wvci-swatch wvci-swatch--color wvci-archive-swatch" data-value="' . esc_attr( $sw['slug'] ) . '" title="' . esc_attr( $sw['name'] ) . '" style="background-color:' . esc_attr( $sw['color'] ) . ';"' . $data_var_img . '></span>';
                } elseif ( 'image' === $sw['type'] && $sw['image'] ) {
                    $img_url = wp_get_attachment_image_url( $sw['image'], 'thumbnail' );
                    if ( $img_url ) {
                        echo '<span class="wvci-swatch wvci-swatch--image wvci-archive-swatch" data-value="' . esc_attr( $sw['slug'] ) . '" title="' . esc_attr( $sw['name'] ) . '"' . $data_var_img . '><img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $sw['name'] ) . '" /></span>';
                    }
                }
            }

            echo '</div>';
        }
    }

    /**
     * Build a map of attribute_value => variation thumbnail URL for a variable product.
     *
     * @param WC_Product_Variable $product
     * @return array  [ 'pa_color' => [ 'red' => 'http://...jpg', 'blue' => '...' ] ]
     */
    private function build_variation_image_map( $product ) {
        $map        = array();
        $variations = $product->get_available_variations();

        foreach ( $variations as $variation ) {
            $image_id = isset( $variation['image_id'] ) ? $variation['image_id'] : 0;
            if ( ! $image_id ) {
                continue;
            }

            $image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
            if ( ! $image_url ) {
                continue;
            }

            $attrs = isset( $variation['attributes'] ) ? $variation['attributes'] : array();
            foreach ( $attrs as $attr_key => $attr_value ) {
                if ( empty( $attr_value ) ) {
                    continue; // "any" value
                }
                // $attr_key is like "attribute_pa_color"
                $clean_key = str_replace( 'attribute_', '', $attr_key );
                if ( ! isset( $map[ $clean_key ][ $attr_value ] ) ) {
                    $map[ $clean_key ][ $attr_value ] = $image_url;
                }
            }
        }

        return $map;
    }
}
