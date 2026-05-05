<?php
/**
 * Settings page (Q3): per-site empty-state placeholder image.
 *
 * Phase 2 spec Q3 calls for a dedicated "Events Marquee" sub-page under
 * Settings with a single image field. Implementation supports two paths:
 *
 *   1. ACF Pro is active (Abbott baseline): register an ACF options page
 *      with the wp_events_marquee_empty_state_image_id field. ACF UI gives
 *      the merchant a media-library picker out of the box.
 *
 *   2. ACF Pro is NOT active (defensive fallback for non-Abbott deploys):
 *      register a Settings API page with the WP media uploader integration.
 *      Stores the attachment ID in the `wpem_empty_state_image_id` option.
 *
 * The `wp_events_marquee_empty_state_image` filter (consumed by the
 * carousel renderer's empty-state branch) is the public surface. This
 * class provides the storage; the filter wires storage -> render path.
 * Filter overrides take precedence so a code-level override always wins.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Settings {

	const OPTION_KEY = 'wpem_empty_state_image_id';
	const PAGE_SLUG  = 'wp-events-marquee';

	/**
	 * @var WPEM_Settings|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		// Wire the empty-state-image filter so the stored setting becomes
		// the default URL when no other override is in play.
		add_filter( 'wp_events_marquee_empty_state_image', array( $this, 'apply_stored_image' ), 5 );

		// Register the admin UI on init so ACF options page can hook in early.
		add_action( 'acf/init', array( $this, 'maybe_register_acf_options_page' ) );
		add_action( 'acf/init', array( $this, 'maybe_register_acf_settings_field' ) );

		// Native Settings API fallback (only registers if ACF Pro absent).
		add_action( 'admin_menu', array( $this, 'maybe_register_native_settings_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_register_native_settings_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_media_uploader' ) );
	}

	/**
	 * Resolve the stored placeholder image to a URL and feed the existing
	 * empty-state filter.
	 *
	 * @param string $current Filter value upstream.
	 * @return string URL or empty string.
	 */
	public function apply_stored_image( $current ) {
		// If something earlier in the filter chain already supplied a value
		// (filter-priority < 5 or code-level override at PHP_INT_MAX), respect it.
		if ( ! empty( $current ) ) {
			return $current;
		}

		// Prefer ACF stored value if available, else native option.
		$attachment_id = 0;
		if ( function_exists( 'get_field' ) ) {
			$acf_value = get_field( 'wpem_empty_state_image_id', 'option' );
			if ( ! empty( $acf_value ) ) {
				if ( is_array( $acf_value ) && isset( $acf_value['ID'] ) ) {
					$attachment_id = (int) $acf_value['ID'];
				} elseif ( is_numeric( $acf_value ) ) {
					$attachment_id = (int) $acf_value;
				}
			}
		}
		if ( ! $attachment_id ) {
			$attachment_id = (int) get_option( self::OPTION_KEY, 0 );
		}

		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'medium_large' );
		return $url ? $url : '';
	}

	/**
	 * Register the ACF options page IF ACF Pro is loaded.
	 */
	public function maybe_register_acf_options_page() {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Events Marquee Settings', 'wp-events-marquee' ),
				'menu_title'  => __( 'Events Marquee', 'wp-events-marquee' ),
				'parent_slug' => 'options-general.php',
				'menu_slug'   => self::PAGE_SLUG,
				'capability'  => 'manage_options',
			)
		);
	}

	/**
	 * Register the ACF image field on the options page.
	 */
	public function maybe_register_acf_settings_field() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_wpem_settings',
				'title'    => __( 'Empty-State Placeholder', 'wp-events-marquee' ),
				'fields'   => array(
					array(
						'key'           => 'field_wpem_empty_state_image',
						'label'         => __( 'Empty-State Placeholder Image', 'wp-events-marquee' ),
						'name'          => 'wpem_empty_state_image_id',
						'type'          => 'image',
						'instructions'  => __( 'Shown when no upcoming events match the show-in-carousel filter. Leave blank for a text-only fallback.', 'wp-events-marquee' ),
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => self::PAGE_SLUG,
						),
					),
				),
				'active'   => true,
			)
		);
	}

	/**
	 * Register the native settings page IF ACF Pro is NOT available.
	 */
	public function maybe_register_native_settings_page() {
		if ( function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		add_options_page(
			__( 'Events Marquee Settings', 'wp-events-marquee' ),
			__( 'Events Marquee', 'wp-events-marquee' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_native_settings_page' )
		);
	}

	/**
	 * Register the native option + section + field IF ACF Pro is NOT available.
	 */
	public function maybe_register_native_settings_field() {
		if ( function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		register_setting(
			'wpem_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		add_settings_section(
			'wpem_settings_section_main',
			__( 'Empty-State Placeholder', 'wp-events-marquee' ),
			'__return_false',
			self::PAGE_SLUG
		);
		add_settings_field(
			'wpem_empty_state_image_field',
			__( 'Placeholder Image', 'wp-events-marquee' ),
			array( $this, 'render_native_image_field' ),
			self::PAGE_SLUG,
			'wpem_settings_section_main'
		);
	}

	public function render_native_image_field() {
		$attachment_id = (int) get_option( self::OPTION_KEY, 0 );
		$url           = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<div class="wpem-image-field">
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>" id="wpem_empty_state_image_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
			<div class="wpem-image-preview" style="margin-bottom: 8px;">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width: 240px; height: auto;" />
				<?php endif; ?>
			</div>
			<button type="button" class="button wpem-upload-button"><?php esc_html_e( 'Choose Image', 'wp-events-marquee' ); ?></button>
			<button type="button" class="button wpem-remove-button" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'wp-events-marquee' ); ?></button>
			<p class="description"><?php esc_html_e( 'Shown when no upcoming events match the show-in-carousel filter.', 'wp-events-marquee' ); ?></p>
		</div>
		<script>
		(function($) {
			$(function() {
				var frame;
				$('.wpem-upload-button').on('click', function(e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({
						title: '<?php echo esc_js( __( 'Choose Placeholder Image', 'wp-events-marquee' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Use this image', 'wp-events-marquee' ) ); ?>' },
						multiple: false
					});
					frame.on('select', function() {
						var att = frame.state().get('selection').first().toJSON();
						$('#wpem_empty_state_image_id').val(att.id);
						$('.wpem-image-preview').html('<img src="' + att.url + '" style="max-width: 240px; height: auto;" />');
						$('.wpem-remove-button').show();
					});
					frame.open();
				});
				$('.wpem-remove-button').on('click', function(e) {
					e.preventDefault();
					$('#wpem_empty_state_image_id').val(0);
					$('.wpem-image-preview').empty();
					$(this).hide();
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	public function render_native_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpem_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function maybe_enqueue_media_uploader( $hook ) {
		if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		if ( function_exists( 'acf_add_options_page' ) ) {
			return;
		}
		wp_enqueue_media();
	}
}
