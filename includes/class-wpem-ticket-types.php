<?php
/**
 * Ticket-type catalog. Single source of truth for the slug => label/colors map.
 *
 * Defaults preserve the colors and labels that shipped in FluentSnippet 33 on 134main
 * (the Abbott BG reference site as of 2026-04-30). Per-site overrides go through
 * the wp_events_marquee_ticket_type_map filter.
 *
 * The "advertisement" entry is included defensively because the live rewriter
 * matched it even though the ACF schema does not list "Advertisement" among the
 * 6 radio choices for type_of_ticket. Documented gap; remove when ACF is updated.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Ticket_Types {

	/**
	 * @var WPEM_Ticket_Types|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Returns the full ticket-type map, post-filter.
	 *
	 * Each entry:
	 *   slug => array(
	 *     'label'        => Button text shown to users.
	 *     'bg_color'     => CSS background-color hex.
	 *     'text_color'   => CSS color hex (default: white).
	 *     'match_tokens' => Lowercased substring tokens that should map to this slug
	 *                       when found inside the raw ACF type_of_ticket value.
	 *                       First token-match wins (order in the map matters).
	 *   )
	 *
	 * @return array
	 */
	public function get_map() {
		$defaults = array(
			'online'        => array(
				'label'        => 'Tickets Online',
				'bg_color'     => '#dc3545',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'purchase tickets online' ),
			),
			'door'          => array(
				'label'        => 'Tickets at the Door',
				'bg_color'     => '#6f42c1',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'tickets at the door' ),
			),
			'private'       => array(
				'label'        => 'Private Event',
				'bg_color'     => '#28a745',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'private event' ),
			),
			'coming-soon'   => array(
				'label'        => 'Coming Soon',
				'bg_color'     => '#b8860b',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'coming soon' ),
			),
			'upcoming'      => array(
				'label'        => 'Upcoming Events',
				'bg_color'     => '#17a2b8',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'upcoming events' ),
			),
			/*
			 * Q1 spec: Advertisement type uses 'ad_label' as the button text
			 * (default "More") instead of 'label'. The card also reads its
			 * href from the per-post `link` ACF field (not the permalink).
			 * Date display is suppressed in CSS via [data-ticket-type="advertisement"].
			 */
			'advertisement' => array(
				'label'        => 'Advertisement',
				'ad_label'     => 'More',
				'bg_color'     => '#69727d',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'advertisement' ),
			),
			// Fallback. Keep last; matched via wp_events_marquee_default_ticket_type.
			'free'          => array(
				'label'        => 'Free Event',
				'bg_color'     => '#007cba',
				'text_color'   => '#ffffff',
				'match_tokens' => array( 'free event' ),
			),
		);

		/**
		 * Filters the ticket-type map.
		 *
		 * Per-site theme/plugin can override colors, labels, or match tokens by
		 * returning a merged array. Order of entries matters because match_tokens
		 * are evaluated in iteration order; the first token match wins.
		 *
		 * @param array $defaults Default map (slug => label/colors/tokens).
		 */
		return apply_filters( 'wp_events_marquee_ticket_type_map', $defaults );
	}

	/**
	 * Returns the slug used when a post has no recognizable type_of_ticket value.
	 *
	 * @return string
	 */
	public function get_default_slug() {
		/**
		 * Filters the fallback ticket-type slug.
		 *
		 * @param string $slug Default 'free'.
		 */
		return (string) apply_filters( 'wp_events_marquee_default_ticket_type', 'free' );
	}
}
