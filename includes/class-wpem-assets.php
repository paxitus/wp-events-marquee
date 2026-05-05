<?php
/**
 * Front-end asset registration.
 *
 * Enqueues bundled Swiper 11 + the plugin's stylesheet + the plugin's
 * carousel init script ONLY when the [wpem_carousel] shortcode is present
 * in the current post's content (or filterable for non-standard contexts).
 *
 * Inline footer script consumes `WPEM_Carousel::get_rendered_instances()`
 * to call new Swiper(...) for each rendered instance with its config.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Assets {

	const HANDLE_SWIPER_CSS = 'wpem-swiper';
	const HANDLE_SWIPER_JS  = 'wpem-swiper';
	const HANDLE_PLUGIN_CSS = 'wpem-carousel';
	const HANDLE_PLUGIN_JS  = 'wpem-carousel';

	/**
	 * @var WPEM_Assets|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		// Register early so anything (widgets, blocks, page builders) can opt in.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		// Conditional enqueue at default priority once the_post() context is settled.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 10 );
		// Footer initializer reads rendered instances from WPEM_Carousel and
		// emits the wpemCarouselInstances payload BEFORE wp_print_footer_scripts
		// (which runs at wp_footer:20). v2.0.1: priority 5 — earlier than 20
		// is required so wp_add_inline_script() with strategy='before' actually
		// attaches before the script tag is rendered.
		add_action( 'wp_footer', array( $this, 'maybe_print_initializer' ), 5 );
	}

	/**
	 * Register handles up front. Enqueue happens in maybe_enqueue().
	 */
	public function register_assets() {
		wp_register_style(
			self::HANDLE_SWIPER_CSS,
			WPEM_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css',
			array(),
			'11.x'
		);
		wp_register_script(
			self::HANDLE_SWIPER_JS,
			WPEM_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js',
			array(),
			'11.x',
			true
		);
		wp_register_style(
			self::HANDLE_PLUGIN_CSS,
			WPEM_PLUGIN_URL . 'assets/css/carousel.css',
			array( self::HANDLE_SWIPER_CSS ),
			WPEM_VERSION
		);
		wp_register_script(
			self::HANDLE_PLUGIN_JS,
			WPEM_PLUGIN_URL . 'assets/js/carousel.js',
			array( self::HANDLE_SWIPER_JS ),
			WPEM_VERSION,
			true
		);
	}

	/**
	 * Decide whether to enqueue. Default rule: shortcode present in the
	 * current main-query post's content. Filterable for non-standard cases
	 * (block content, reusable widgets, custom builders).
	 */
	public function maybe_enqueue() {
		if ( ! $this->should_enqueue() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE_SWIPER_CSS );
		wp_enqueue_script( self::HANDLE_SWIPER_JS );
		wp_enqueue_style( self::HANDLE_PLUGIN_CSS );
		wp_enqueue_script( self::HANDLE_PLUGIN_JS );

		// Inline ticket-type CSS custom properties so per-site filter
		// overrides flow into the stylesheet without forking it.
		wp_add_inline_style( self::HANDLE_PLUGIN_CSS, $this->build_inline_color_overrides() );
	}

	/**
	 * Check the singular post and any in-scope context for the shortcode.
	 *
	 * @return bool
	 */
	public function should_enqueue() {
		$default = false;

		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, WPEM_Carousel::SHORTCODE ) ) {
				$default = true;
			}
		}

		// Front page might be a page-on-front; the singular check above
		// already covers that. Archive contexts don't auto-load shortcodes,
		// but a theme can opt in via the filter below.

		/**
		 * Filters the enqueue decision. Use to force-enqueue on archive
		 * pages, widget areas, block-only contexts, or for testing.
		 *
		 * @param bool $default Result of the singular post-content scan.
		 */
		return (bool) apply_filters( 'wp_events_marquee_enqueue_assets', $default );
	}

	/**
	 * Print the Swiper init script in the footer, only if a carousel
	 * actually rendered (the shortcode runs during the_content()). This
	 * also catches cases where a builder produced markup we couldn't
	 * detect during should_enqueue().
	 */
	public function maybe_print_initializer() {
		$instances = WPEM_Carousel::instance()->get_rendered_instances();
		if ( empty( $instances ) ) {
			return;
		}

		// If the shortcode rendered without should_enqueue() returning true
		// (force-enqueue filter, builder embed, etc.), the JS may not have
		// been enqueued. Belt-and-suspenders: ensure it's enqueued now.
		if ( ! wp_script_is( self::HANDLE_PLUGIN_JS, 'enqueued' ) ) {
			wp_enqueue_style( self::HANDLE_SWIPER_CSS );
			wp_enqueue_script( self::HANDLE_SWIPER_JS );
			wp_enqueue_style( self::HANDLE_PLUGIN_CSS );
			wp_enqueue_script( self::HANDLE_PLUGIN_JS );
			wp_add_inline_style( self::HANDLE_PLUGIN_CSS, $this->build_inline_color_overrides() );
		}

		$payload = wp_json_encode( $instances );
		if ( false === $payload ) {
			$payload = '{}';
		}

		// Inline-attach to the carousel.js handle so the global lands before
		// the init script evaluates. Hooked at wp_footer:5 so this runs
		// before wp_print_footer_scripts (default :20).
		wp_add_inline_script(
			self::HANDLE_PLUGIN_JS,
			'window.wpemCarouselInstances = ' . $payload . ';',
			'before'
		);

		// Belt-and-suspenders: also print the global inline at wp_footer:5
		// directly, in case the dependency graph or print-order on a given
		// theme drops the inline-before payload. carousel.js reads
		// window.wpemCarouselInstances on init; multiple writes are
		// harmless — last wins, and the value is identical.
		printf(
			"<script id=\"wpem-instances-data\">window.wpemCarouselInstances = %s;</script>\n",
			$payload // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_json_encode output is safe.
		);
	}

	/**
	 * Builds a `:root { --wpem-... }` block from the current ticket-type map.
	 * Lets the static stylesheet reference colors via var() with safe fallbacks.
	 *
	 * @return string
	 */
	private function build_inline_color_overrides() {
		$map   = WPEM_Ticket_Types::instance()->get_map();
		$lines = array();
		foreach ( $map as $slug => $data ) {
			$slug_css   = sanitize_html_class( $slug );
			$bg_color   = isset( $data['bg_color'] ) ? $data['bg_color'] : '#007cba';
			$text_color = isset( $data['text_color'] ) ? $data['text_color'] : '#ffffff';
			$lines[]    = sprintf(
				'--wpem-bg-%1$s: %2$s; --wpem-text-%1$s: %3$s;',
				$slug_css,
				$this->sanitize_color( $bg_color ),
				$this->sanitize_color( $text_color )
			);
		}
		return ":root {\n\t" . implode( "\n\t", $lines ) . "\n}\n";
	}

	/**
	 * Defensive: only allow #hex / rgb()/rgba()/hsl()/hsla() / named values.
	 *
	 * @param string $color
	 * @return string
	 */
	private function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)$/i', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^[a-zA-Z]+$/', $color ) ) {
			return $color;
		}
		return '#007cba';
	}
}
