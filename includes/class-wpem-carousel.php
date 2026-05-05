<?php
/**
 * [wpem_carousel] shortcode. Renders a Swiper-wrapped block of event cards
 * server-side. No Elementor or DCE dependency, no JS rewriter pattern, no
 * opacity:0 reveal — buttons render visible from first paint.
 *
 * Query rules:
 *   - post_type = event
 *   - ACF show_in_carousel == 'Yes' (post_meta 'show_in_carousel')
 *   - AND one of:
 *       a) ACF end_date >= today (post_meta 'end_date', stored 'Ymd' by ACF)
 *       b) ACF is_recurring is truthy (post_meta 'is_recurring' = '1')
 *     so a recurring event whose end_date has passed but whose recurrence is
 *     still active stays in the carousel. The card's date pill displays the
 *     computed next occurrence (see format_event_date_for_card()).
 *   - Order: ACF date ASC (post_meta 'date', stored 'Ymd' by ACF)
 *
 * Shortcode attributes:
 *   limit            (int,    default 12)        Max events to query.
 *   desktop_slides   (int,    default 3)         Slides per view at >=1025px.
 *   tablet_slides    (int,    default 2)         Slides per view 768–1024px.
 *   mobile_slides    (int,    default 1)         Slides per view <768px.
 *   desktop_space    (int,    default 16)        Space between slides at >=1025px.
 *   tablet_space     (int,    default 4)         Space between slides 768–1024px.
 *   mobile_space     (int,    default 4)         Space between slides <768px.
 *   autoplay         (int,    default 4000)      Autoplay interval in ms; 0 disables.
 *   loop             (yes|no, default yes)       Swiper loop mode.
 *   centered_slides  (yes|no, default yes)       Swiper centeredSlides.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Carousel {

	const SHORTCODE = 'wpem_carousel';

	/**
	 * @var WPEM_Carousel|null
	 */
	private static $instance = null;

	/**
	 * Tracks settings for each rendered carousel instance on the page so
	 * the inline init script can configure each by id.
	 *
	 * @var array<string, array>
	 */
	private $rendered = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Default get_posts() args used by the carousel query.
	 *
	 * @param int $limit
	 * @return array
	 */
	private function build_query_args( $limit ) {
		$today = date_i18n( 'Ymd' );

		$args = array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $limit ),
			'meta_key'       => 'date',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'show_in_carousel',
					'value'   => 'Yes',
					'compare' => '=',
				),
				/*
				 * v2.1.0: a recurring event with end_date < today should stay
				 * visible in the carousel. The OR branch lets recurring posts
				 * bypass the end_date gate at query time. We then compute the
				 * next-occurrence date in PHP for the card's date pill, and if
				 * that next occurrence falls past end_date the event drops out
				 * of $cards (see render_shortcode()).
				 */
				array(
					'relation' => 'OR',
					array(
						'key'     => 'end_date',
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'CHAR',
					),
					array(
						'key'     => 'is_recurring',
						'value'   => '1',
						'compare' => '=',
					),
				),
			),
		);

		/**
		 * Filters the get_posts() args for the carousel query.
		 *
		 * @param array $args  WP_Query args.
		 * @param int   $limit Original limit attr.
		 */
		return apply_filters( 'wp_events_marquee_query_args', $args, $limit );
	}

	/**
	 * Resolve a post's ticket-type slug from its ACF type_of_ticket value.
	 *
	 * @param int $post_id
	 * @return string Ticket-type slug, falls back to default.
	 */
	private function resolve_ticket_slug( $post_id ) {
		$raw = (string) get_post_meta( $post_id, 'type_of_ticket', true );
		if ( '' === $raw ) {
			return WPEM_Ticket_Types::instance()->get_default_slug();
		}
		$lower = strtolower( $raw );
		$map   = WPEM_Ticket_Types::instance()->get_map();
		foreach ( $map as $slug => $data ) {
			if ( empty( $data['match_tokens'] ) || ! is_array( $data['match_tokens'] ) ) {
				continue;
			}
			foreach ( $data['match_tokens'] as $token ) {
				if ( '' === $token ) {
					continue;
				}
				if ( false !== strpos( $lower, strtolower( $token ) ) ) {
					return $slug;
				}
			}
		}
		return WPEM_Ticket_Types::instance()->get_default_slug();
	}

	/**
	 * Pick the button href. Advertisement type uses ACF 'link' field,
	 * everything else uses the post permalink (event detail page).
	 *
	 * @param int    $post_id
	 * @param string $slug
	 * @return string
	 */
	private function resolve_button_href( $post_id, $slug ) {
		if ( 'advertisement' === $slug ) {
			$link = (string) get_post_meta( $post_id, 'link', true );
			if ( '' !== $link ) {
				return esc_url( $link );
			}
		}
		return esc_url( get_permalink( $post_id ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'           => 12,
				'desktop_slides'  => 3,
				'tablet_slides'   => 2,
				'mobile_slides'   => 1,
				'desktop_space'   => 16,
				'tablet_space'    => 4,
				'mobile_space'    => 4,
				'autoplay'        => 4000,
				'loop'            => 'yes',
				'centered_slides' => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		$query = new WP_Query( $this->build_query_args( $atts['limit'] ) );
		if ( ! $query->have_posts() ) {
			return $this->render_empty_state();
		}

		// Unique instance id so multiple shortcodes on a page don't share Swiper state.
		$instance_id = 'wpem-carousel-' . wp_generate_password( 8, false, false );

		$desktop_slides = max( 1, (int) $atts['desktop_slides'] );
		$tablet_slides  = max( 1, (int) $atts['tablet_slides'] );
		$mobile_slides  = max( 1, (int) $atts['mobile_slides'] );

		// Stash settings for the inline init.
		$this->rendered[ $instance_id ] = array(
			'desktopSlides'  => $desktop_slides,
			'tabletSlides'   => $tablet_slides,
			'mobileSlides'   => $mobile_slides,
			'desktopSpace'   => max( 0, (int) $atts['desktop_space'] ),
			'tabletSpace'    => max( 0, (int) $atts['tablet_space'] ),
			'mobileSpace'    => max( 0, (int) $atts['mobile_space'] ),
			'autoplay'       => max( 0, (int) $atts['autoplay'] ),
			'loop'           => 'yes' === strtolower( (string) $atts['loop'] ),
			'centeredSlides' => 'yes' === strtolower( (string) $atts['centered_slides'] ),
		);

		// Build the card-context list once. We need the count to decide
		// whether to duplicate slides for the loop-math edge case
		// (e.g. 1 published event with desktopSlides=3 — Swiper requires
		// slides.length >= slidesPerView * 2 for loop:true to work).
		$cards = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id     = get_the_ID();
			$slug        = $this->resolve_ticket_slug( $post_id );
			$ticket_data = $this->get_ticket_type_data( $slug );

			$event_date = $this->format_event_date_for_card( $post_id );
			if ( null === $event_date ) {
				// Recurring event whose next occurrence is past end_date —
				// drop the card rather than carry a stale or empty pill.
				continue;
			}

			$cards[] = array(
				'post_id'     => $post_id,
				'slug'        => $slug,
				'ticket_data' => $ticket_data,
				'button_href' => $this->resolve_button_href( $post_id, $slug ),
				'button_text' => $this->resolve_button_text( $slug, $ticket_data ),
				'event_date'  => $event_date,
				'permalink'   => get_permalink( $post_id ),
				'title'       => get_the_title(),
			);
		}
		wp_reset_postdata();

		// All recurring events past their final end_date were dropped above —
		// re-check that anything is left to render before falling through.
		if ( empty( $cards ) ) {
			return $this->render_empty_state();
		}

		// v2.0.1: duplicate the slide list so Swiper loop math works at low
		// event counts AND so a single-event site still visually fills the
		// 3-up desktop layout. Threshold = desktopSlides * 2 (Swiper's
		// internal loop minimum). Duplicates are full re-renders of the
		// same cards in the same order — cheap, and Swiper's clone-on-loop
		// happens on top of this so the marquee stays seamless.
		$min_slides = max( $desktop_slides, $tablet_slides, $mobile_slides ) * 2;
		$render_cards = $cards;
		if ( count( $render_cards ) > 0 && count( $render_cards ) < $min_slides ) {
			$pool = $cards;
			while ( count( $render_cards ) < $min_slides ) {
				foreach ( $pool as $c ) {
					$render_cards[] = $c;
					if ( count( $render_cards ) >= $min_slides ) {
						break;
					}
				}
			}
		}

		ob_start();
		?>
		<div class="wpem-carousel" id="<?php echo esc_attr( $instance_id ); ?>" data-wpem-instance="<?php echo esc_attr( $instance_id ); ?>">
			<div class="swiper wpem-swiper">
				<div class="swiper-wrapper">
					<?php foreach ( $render_cards as $context ) : ?>
						<?php $this->load_card_template( $context ); ?>
					<?php endforeach; ?>
				</div>
				<?php if ( $this->rendered[ $instance_id ]['desktopSlides'] > 1 || $this->rendered[ $instance_id ]['tabletSlides'] > 1 ) : ?>
					<button type="button" class="swiper-button-prev wpem-nav-prev" aria-label="<?php esc_attr_e( 'Previous event', 'wp-events-marquee' ); ?>"></button>
					<button type="button" class="swiper-button-next wpem-nav-next" aria-label="<?php esc_attr_e( 'Next event', 'wp-events-marquee' ); ?>"></button>
				<?php endif; ?>
				<div class="swiper-pagination wpem-pagination"></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Format an ACF date-meta value for display.
	 *
	 * ACF stores the 'date' field as 'Ymd' (e.g. "20260508"). The card
	 * template renders the value verbatim as the pill text, so it must
	 * arrive pre-formatted ("F j, Y" — e.g. "May 8, 2026").
	 *
	 * Defensive: if the meta is empty, malformed, or already formatted,
	 * we return what we got rather than crash.
	 *
	 * @param string $raw Raw 'Ymd' meta value.
	 * @return string     Formatted date string, or '' if empty/invalid.
	 */
	private function format_event_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		// ACF Ymd shape — exactly 8 digits.
		if ( preg_match( '/^\d{8}$/', $raw ) ) {
			$ts = strtotime( $raw );
			if ( false !== $ts ) {
				return date_i18n( 'F j, Y', $ts );
			}
		}
		// Already a friendly string, or some other meta shape — pass through.
		return $raw;
	}

	/**
	 * Resolve the date string shown on a card.
	 *
	 * For non-recurring events, this is just the formatted ACF 'date' meta.
	 * For recurring events (is_recurring truthy + recognized recurrence_type),
	 * this is the computed next occurrence in 'F j, Y' format. If that
	 * computed next occurrence falls AFTER the recurrence's final end_date,
	 * the event is considered fully expired and we return null so the caller
	 * drops the card from the carousel.
	 *
	 * @param int $post_id Event post ID.
	 * @return string|null Display date string, or null if the event should be dropped.
	 */
	private function format_event_date_for_card( $post_id ) {
		$is_recurring = get_post_meta( $post_id, 'is_recurring', true );
		if ( ! $this->is_recurring_truthy( $is_recurring ) ) {
			return $this->format_event_date( (string) get_post_meta( $post_id, 'date', true ) );
		}

		$type = strtolower( (string) get_post_meta( $post_id, 'recurrence_type', true ) );
		if ( 'weekly' !== $type && 'monthly' !== $type ) {
			// Recurring flag set but type unrecognized — fall back to plain date.
			return $this->format_event_date( (string) get_post_meta( $post_id, 'date', true ) );
		}

		$next_ts = $this->compute_next_occurrence_ts( $post_id, $type );
		if ( null === $next_ts ) {
			return $this->format_event_date( (string) get_post_meta( $post_id, 'date', true ) );
		}

		// Honor the final-end-date ceiling. ACF 'end_date' is stored 'Ymd'.
		$end_raw = trim( (string) get_post_meta( $post_id, 'end_date', true ) );
		if ( preg_match( '/^\d{8}$/', $end_raw ) ) {
			$end_ts = strtotime( $end_raw );
			if ( false !== $end_ts && $next_ts > $end_ts ) {
				return null;
			}
		}

		return date_i18n( 'F j, Y', $next_ts );
	}

	/**
	 * Defensive truthy check for ACF is_recurring values.
	 *
	 * ACF true_false fields store '1' / '0' as post_meta strings, but
	 * historically the snippet pattern used 'Yes' / 'No' choice values too.
	 * Accept any reasonable shape so this works across portfolio sites.
	 *
	 * @param mixed $val Raw post_meta value.
	 * @return bool
	 */
	private function is_recurring_truthy( $val ) {
		if ( is_bool( $val ) ) {
			return $val;
		}
		if ( is_int( $val ) ) {
			return 1 === $val;
		}
		$str = strtolower( trim( (string) $val ) );
		if ( '' === $str ) {
			return false;
		}
		if ( '0' === $str || 'no' === $str || 'false' === $str ) {
			return false;
		}
		return true;
	}

	/**
	 * Compute the next occurrence timestamp for a recurring event,
	 * relative to today (date_i18n local time).
	 *
	 * weekly:  uses ACF 'recurrence_day' (label string "Sunday" .. "Saturday",
	 *          per the field's return_format=label). Advances from today to
	 *          the next matching weekday; if today already matches, returns
	 *          today.
	 * monthly: uses ACF 'monthly_day' (1–31). If the requested day-of-month
	 *          has already passed this month, advances to next month. Days
	 *          like 31 in February are clamped to the month's last day.
	 *
	 * @param int    $post_id
	 * @param string $type    'weekly' | 'monthly' (caller has lowercased + validated).
	 * @return int|null Unix timestamp of next occurrence, or null if the data is unusable.
	 */
	private function compute_next_occurrence_ts( $post_id, $type ) {
		// Anchor "today" using date_i18n so we honor the site's timezone.
		$today_ymd = date_i18n( 'Ymd' );
		$today_ts  = strtotime( $today_ymd );
		if ( false === $today_ts ) {
			return null;
		}

		if ( 'weekly' === $type ) {
			$label   = (string) get_post_meta( $post_id, 'recurrence_day', true );
			$weekday = $this->weekday_label_to_int( $label );
			if ( null === $weekday ) {
				return null;
			}
			$today_w = (int) date( 'w', $today_ts );
			$delta   = ( $weekday - $today_w + 7 ) % 7;
			return strtotime( '+' . $delta . ' days', $today_ts );
		}

		// monthly
		$day_raw = get_post_meta( $post_id, 'monthly_day', true );
		if ( '' === $day_raw || null === $day_raw ) {
			return null;
		}
		$day = (int) $day_raw;
		if ( $day < 1 ) {
			$day = 1;
		}
		if ( $day > 31 ) {
			$day = 31;
		}

		$year_now  = (int) date( 'Y', $today_ts );
		$month_now = (int) date( 'n', $today_ts );
		$dom_now   = (int) date( 'j', $today_ts );

		// First, try this month with the day clamped to the month's length.
		$dim_now    = (int) date( 't', mktime( 0, 0, 0, $month_now, 1, $year_now ) );
		$candidate  = min( $day, $dim_now );
		if ( $candidate >= $dom_now ) {
			return mktime( 0, 0, 0, $month_now, $candidate, $year_now );
		}

		// Otherwise advance to next month and clamp again.
		$next_month = $month_now + 1;
		$next_year  = $year_now;
		if ( $next_month > 12 ) {
			$next_month = 1;
			++$next_year;
		}
		$dim_next   = (int) date( 't', mktime( 0, 0, 0, $next_month, 1, $next_year ) );
		$candidate2 = min( $day, $dim_next );
		return mktime( 0, 0, 0, $next_month, $candidate2, $next_year );
	}

	/**
	 * Map an ACF recurrence_day label to a PHP weekday integer (0 = Sunday).
	 *
	 * The field stores labels because the field group sets return_format=label.
	 * We accept the canonical names + a few defensive variants.
	 *
	 * @param string $label
	 * @return int|null 0-6, or null if unrecognized.
	 */
	private function weekday_label_to_int( $label ) {
		$norm = strtolower( trim( (string) $label ) );
		if ( '' === $norm ) {
			return null;
		}
		// Accept a bare 0-6 in case a site overrides return_format=value.
		if ( ctype_digit( $norm ) ) {
			$n = (int) $norm;
			if ( $n >= 0 && $n <= 6 ) {
				return $n;
			}
		}
		$map = array(
			'sunday'    => 0,
			'sun'       => 0,
			'monday'    => 1,
			'mon'       => 1,
			'tuesday'   => 2,
			'tue'       => 2,
			'tues'      => 2,
			'wednesday' => 3,
			'wed'       => 3,
			'thursday'  => 4,
			'thu'       => 4,
			'thur'      => 4,
			'thurs'     => 4,
			'friday'    => 5,
			'fri'       => 5,
			'saturday'  => 6,
			'sat'       => 6,
		);
		return isset( $map[ $norm ] ) ? $map[ $norm ] : null;
	}

	/**
	 * Get the ticket type data for a slug, with safe defaults.
	 *
	 * @param string $slug
	 * @return array
	 */
	private function get_ticket_type_data( $slug ) {
		$map = WPEM_Ticket_Types::instance()->get_map();
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}
		return array(
			'label'      => __( 'View Event', 'wp-events-marquee' ),
			'bg_color'   => '#007cba',
			'text_color' => '#ffffff',
		);
	}

	/**
	 * Resolve the button label. Advertisement uses 'ad_label' (default "More").
	 *
	 * @param string $slug
	 * @param array  $ticket_data
	 * @return string
	 */
	private function resolve_button_text( $slug, $ticket_data ) {
		if ( 'advertisement' === $slug && ! empty( $ticket_data['ad_label'] ) ) {
			return (string) $ticket_data['ad_label'];
		}
		if ( ! empty( $ticket_data['label'] ) ) {
			return (string) $ticket_data['label'];
		}
		return __( 'View Event', 'wp-events-marquee' );
	}

	/**
	 * Load the card template, allowing themes to override via standard
	 * locate_template() lookup at `wp-events-marquee/card.php`.
	 *
	 * @param array $wpem Card context. Available inside the template as $wpem.
	 */
	private function load_card_template( $wpem ) {
		$override = locate_template( array( 'wp-events-marquee/card.php' ) );
		if ( $override ) {
			include $override;
			return;
		}
		include WPEM_PLUGIN_DIR . 'templates/card.php';
	}

	/**
	 * Render the empty state when no events match.
	 *
	 * @return string
	 */
	private function render_empty_state() {
		/**
		 * Filters the empty-state image URL.
		 *
		 * @param string $url Default empty.
		 */
		$image_url = (string) apply_filters( 'wp_events_marquee_empty_state_image', '' );

		/**
		 * Filters the empty-state message text.
		 *
		 * @param string $message
		 */
		$message = (string) apply_filters(
			'wp_events_marquee_empty_state_message',
			__( 'No upcoming events. Check back soon.', 'wp-events-marquee' )
		);

		ob_start();
		?>
		<div class="wpem-carousel wpem-carousel--empty">
			<?php if ( $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" class="wpem-empty-image" />
			<?php endif; ?>
			<p class="wpem-empty-message"><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Returns the rendered-instance map for the inline initializer.
	 *
	 * @return array<string, array>
	 */
	public function get_rendered_instances() {
		return $this->rendered;
	}
}
