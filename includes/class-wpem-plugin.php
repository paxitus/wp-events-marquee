<?php
/**
 * Bootstrap class. Wires up the feature modules.
 *
 * v2: shortcode-only architecture. No is_front_page() guard, no rewriter,
 * no mobile-carousel module. The carousel renders wherever the shortcode
 * is placed; assets enqueue conditionally based on shortcode presence.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Plugin {

	/**
	 * @var WPEM_Plugin|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up hooks. Heavy lifting lives in feature modules so this stays readable.
	 */
	public function boot() {
		WPEM_Post_Types::instance()->register();
		WPEM_Carousel::instance()->register();
		WPEM_Assets::instance()->register();
		WPEM_ACF_Fields::instance()->register();
		WPEM_Settings::instance()->register();
		WPEM_Migration::instance()->register();
	}
}
