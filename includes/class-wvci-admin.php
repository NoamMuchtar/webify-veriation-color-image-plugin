<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin: adds swatch type, color picker, and image upload fields to WooCommerce attribute terms.
 */
class WVCI_Admin {

    public function __construct() {
        add_action( 'admin_init', array( $this, 'register_term_meta_fields' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Hook into every registered product attribute taxonomy to add our custom fields.
     */
    public function register_term_meta_fields() {
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ( empty( $attribute_taxonomies ) ) {
            return;
        }

        foreach ( $attribute_taxonomies as $attribute ) {
            $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

            // Add term fields
            add_action( $taxonomy . '_add_form_fields', array( $this, 'add_term_fields' ) );
            add_action( $taxonomy . '_edit_form_fields', array( $this, 'edit_term_fields' ), 10, 2 );

            // Save term fields
            add_action( 'created_' . $taxonomy, array( $this, 'save_term_fields' ) );
            add_action( 'edited_' . $taxonomy, array( $this, 'save_term_fields' ) );

            // Add column to term list table
            add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'add_swatch_column' ) );
            add_filter( 'manage_' . $taxonomy . '_custom_column', array( $this, 'render_swatch_column' ), 10, 3 );
        }
    }

    /**
     * Enqueue admin styles and scripts (color picker, media uploader).
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
            return;
        }

        // Only load on product attribute taxonomy screens
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->taxonomy, 'pa_' ) !== 0 ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script(
            'wvci-admin',
            WVCI_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker' ),
            WVCI_VERSION,
            true
        );
        wp_enqueue_style(
            'wvci-admin',
            WVCI_PLUGIN_URL . 'assets/css/admin.css',
            array( 'wp-color-picker' ),
            WVCI_VERSION
        );
    }

    /**
     * Fields shown when ADDING a new term.
     */
    public function add_term_fields( $taxonomy ) {
        ?>
        <div class="form-field">
            <label><?php esc_html_e( 'Swatch Type', 'webify-variation-color-image' ); ?></label>
            <select name="wvci_swatch_type" id="wvci_swatch_type">
                <option value=""><?php esc_html_e( 'None (default dropdown)', 'webify-variation-color-image' ); ?></option>
                <option value="color"><?php esc_html_e( 'Color', 'webify-variation-color-image' ); ?></option>
                <option value="image"><?php esc_html_e( 'Image', 'webify-variation-color-image' ); ?></option>
            </select>
        </div>

        <div class="form-field wvci-field-color" style="display:none;">
            <label><?php esc_html_e( 'Swatch Color', 'webify-variation-color-image' ); ?></label>
            <input type="text" name="wvci_swatch_color" class="wvci-color-picker" value="" />
        </div>

        <div class="form-field wvci-field-image" style="display:none;">
            <label><?php esc_html_e( 'Swatch Image', 'webify-variation-color-image' ); ?></label>
            <div class="wvci-image-preview"></div>
            <input type="hidden" name="wvci_swatch_image" class="wvci-image-id" value="" />
            <button type="button" class="button wvci-upload-image"><?php esc_html_e( 'Upload Image', 'webify-variation-color-image' ); ?></button>
            <button type="button" class="button wvci-remove-image" style="display:none;"><?php esc_html_e( 'Remove Image', 'webify-variation-color-image' ); ?></button>
        </div>
        <?php
    }

    /**
     * Fields shown when EDITING an existing term.
     */
    public function edit_term_fields( $term, $taxonomy ) {
        $swatch_type  = get_term_meta( $term->term_id, 'wvci_swatch_type', true );
        $swatch_color = get_term_meta( $term->term_id, 'wvci_swatch_color', true );
        $swatch_image = get_term_meta( $term->term_id, 'wvci_swatch_image', true );
        $image_url    = $swatch_image ? wp_get_attachment_image_url( $swatch_image, 'thumbnail' ) : '';
        ?>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Swatch Type', 'webify-variation-color-image' ); ?></label></th>
            <td>
                <select name="wvci_swatch_type" id="wvci_swatch_type">
                    <option value="" <?php selected( $swatch_type, '' ); ?>><?php esc_html_e( 'None (default dropdown)', 'webify-variation-color-image' ); ?></option>
                    <option value="color" <?php selected( $swatch_type, 'color' ); ?>><?php esc_html_e( 'Color', 'webify-variation-color-image' ); ?></option>
                    <option value="image" <?php selected( $swatch_type, 'image' ); ?>><?php esc_html_e( 'Image', 'webify-variation-color-image' ); ?></option>
                </select>
            </td>
        </tr>

        <tr class="form-field wvci-field-color" <?php echo $swatch_type !== 'color' ? 'style="display:none;"' : ''; ?>>
            <th><label><?php esc_html_e( 'Swatch Color', 'webify-variation-color-image' ); ?></label></th>
            <td>
                <input type="text" name="wvci_swatch_color" class="wvci-color-picker" value="<?php echo esc_attr( $swatch_color ); ?>" />
            </td>
        </tr>

        <tr class="form-field wvci-field-image" <?php echo $swatch_type !== 'image' ? 'style="display:none;"' : ''; ?>>
            <th><label><?php esc_html_e( 'Swatch Image', 'webify-variation-color-image' ); ?></label></th>
            <td>
                <div class="wvci-image-preview">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" width="60" height="60" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="wvci_swatch_image" class="wvci-image-id" value="<?php echo esc_attr( $swatch_image ); ?>" />
                <button type="button" class="button wvci-upload-image"><?php esc_html_e( 'Upload Image', 'webify-variation-color-image' ); ?></button>
                <button type="button" class="button wvci-remove-image" <?php echo ! $swatch_image ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove Image', 'webify-variation-color-image' ); ?></button>
            </td>
        </tr>
        <?php
    }

    /**
     * Save term meta on create/edit.
     */
    public function save_term_fields( $term_id ) {
        if ( ! current_user_can( 'manage_product_terms' ) ) {
            return;
        }

        if ( isset( $_POST['wvci_swatch_type'] ) ) {
            $type = sanitize_text_field( wp_unslash( $_POST['wvci_swatch_type'] ) );
            update_term_meta( $term_id, 'wvci_swatch_type', $type );
        }

        if ( isset( $_POST['wvci_swatch_color'] ) ) {
            $color = sanitize_hex_color( wp_unslash( $_POST['wvci_swatch_color'] ) );
            update_term_meta( $term_id, 'wvci_swatch_color', $color ? $color : '' );
        }

        if ( isset( $_POST['wvci_swatch_image'] ) ) {
            $image_id = absint( $_POST['wvci_swatch_image'] );
            update_term_meta( $term_id, 'wvci_swatch_image', $image_id ? $image_id : '' );
        }
    }

    /**
     * Add "Swatch" column to the attribute terms list table.
     */
    public function add_swatch_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            if ( 'name' === $key ) {
                $new_columns[ $key ] = $value;
                $new_columns['wvci_swatch'] = __( 'Swatch', 'webify-variation-color-image' );
            } else {
                $new_columns[ $key ] = $value;
            }
        }
        return $new_columns;
    }

    /**
     * Render the swatch preview in the list table column.
     */
    public function render_swatch_column( $content, $column_name, $term_id ) {
        if ( 'wvci_swatch' !== $column_name ) {
            return $content;
        }

        $type = get_term_meta( $term_id, 'wvci_swatch_type', true );

        if ( 'color' === $type ) {
            $color = get_term_meta( $term_id, 'wvci_swatch_color', true );
            if ( $color ) {
                return '<span class="wvci-swatch-preview wvci-swatch-color" style="background-color:' . esc_attr( $color ) . ';"></span>';
            }
        } elseif ( 'image' === $type ) {
            $image_id = get_term_meta( $term_id, 'wvci_swatch_image', true );
            if ( $image_id ) {
                $url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
                if ( $url ) {
                    return '<img class="wvci-swatch-preview wvci-swatch-image" src="' . esc_url( $url ) . '" width="30" height="30" />';
                }
            }
        }

        return '&mdash;';
    }
}
