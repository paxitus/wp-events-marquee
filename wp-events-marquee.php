<?php
/**
 * Plugin Name:       WP Events Marquee
 * Plugin URI:        https://github.com/paxitus/wp-events-marquee
 * Description:       Drop-in [wpem_carousel] shortcode that renders an upcoming-events carousel using ACF event data and a bundled Swiper. No Elementor or page-builder dependency.
 * Version:           2.1.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Lowthian Design
 * Author URI:        https://lowthiandesign.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-events-marquee
 * Update URI:        false
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPEM_VERSION', '2.1.2' );
define( 'WPEM_PLUGIN_FILE', __FILE__ );
define( 'WPEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-plugin.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-ticket-types.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-assets.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-carousel.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-post-types.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-acf-fields.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-settings.php';
require_once WPEM_PLUGIN_DIR . 'includes/class-wpem-migration.php';

WPEM_Plugin::instance()->boot();
