<?php
/**
 * Plugin Name: Webify Variation Color & Image
 * Plugin URI: https://webify.co.il
 * Description: Adds color and image swatches to WooCommerce product attribute terms, and displays them on the product page instead of default dropdowns. Selecting a variation swaps the product image.
 * Version: 1.0.0
 * Author: Webify
 * Author URI: https://webify.co.il
 * Text Domain: webify-variation-color-image
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WVCI_VERSION', '1.0.0' );
define( 'WVCI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVCI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 */
function wvci_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p>';
            esc_html_e( 'Webify Variation Color & Image requires WooCommerce to be installed and active.', 'webify-variation-color-image' );
            echo '</p></div>';
        } );
        return false;
    }
    return true;
}

add_action( 'plugins_loaded', 'wvci_init' );

function wvci_init() {
    if ( ! wvci_check_woocommerce() ) {
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'webify-variation-color-image', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Admin: add fields to attribute terms
    require_once WVCI_PLUGIN_DIR . 'includes/class-wvci-admin.php';

    // Frontend: render swatches on product page
    require_once WVCI_PLUGIN_DIR . 'includes/class-wvci-frontend.php';

    new WVCI_Admin();
    new WVCI_Frontend();
}
