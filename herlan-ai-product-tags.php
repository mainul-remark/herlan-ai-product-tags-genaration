<?php
/**
 * Plugin Name: Herlan AI Product Tags
 * Description: Generates WooCommerce product tags from the product title, descriptions and other product fields using Google AI Studio (Gemini).
 * Version: 1.1.0
 * Author: Herlan
 * Text Domain: herlan-ai-product-tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAIPT_VERSION', '1.1.0' );
define( 'HAIPT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HAIPT_URL', plugin_dir_url( __FILE__ ) );

require_once HAIPT_PATH . 'includes/class-haipt-settings.php';
require_once HAIPT_PATH . 'includes/class-haipt-admin.php';
require_once HAIPT_PATH . 'includes/class-haipt-ajax.php';

new HAIPT_Settings();
new HAIPT_Admin();
new HAIPT_Ajax();
