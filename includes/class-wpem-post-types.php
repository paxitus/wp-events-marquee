<?php
/**
 * Registers the `event` custom post type and (optionally) the `item_type`
 * taxonomy.
 *
 * Phase 2 spec Q5: Events CPT is always registered (the plugin queries
 * `post_type = event` so it MUST exist). Item Types taxonomy is bundled
 * but opt-in via `wp_events_marquee_register_item_types` filter, default
 * false.
 *
 * If a site already registers `event` via another mechanism (theme,
 * functions.php, snippet), they can opt out via
 * `wp_events_marquee_register_event_cpt` filter to avoid double-registration.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Post_Types {

	/**
	 * @var WPEM_Post_Types|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		add_action( 'init', array( $this, 'register_event_cpt' ), 5 );
		add_action( 'init', array( $this, 'maybe_register_item_types' ), 6 );
	}

	/**
	 * Register the Events custom post type.
	 *
	 * Mandatory: the carousel queries post_type=event, so the plugin needs
	 * the post type to exist. Sites that already register `event` themselves
	 * can opt out via filter to avoid clobbering custom labels/supports.
	 */
	public function register_event_cpt() {
		if ( ! apply_filters( 'wp_events_marquee_register_event_cpt', true ) ) {
			return;
		}
		if ( post_type_exists( 'event' ) ) {
			return;
		}

		$args = array(
			'labels'              => array(
				'name'                  => __( 'Events', 'wp-events-marquee' ),
				'singular_name'         => __( 'Event', 'wp-events-marquee' ),
				'add_new'               => __( 'Add New Event', 'wp-events-marquee' ),
				'add_new_item'          => __( 'Add New Event', 'wp-events-marquee' ),
				'edit_item'             => __( 'Edit Event', 'wp-events-marquee' ),
				'new_item'              => __( 'New Event', 'wp-events-marquee' ),
				'view_item'             => __( 'View Event', 'wp-events-marquee' ),
				'view_items'            => __( 'View Events', 'wp-events-marquee' ),
				'search_items'          => __( 'Search Events', 'wp-events-marquee' ),
				'not_found'             => __( 'No events found.', 'wp-events-marquee' ),
				'not_found_in_trash'    => __( 'No events found in Trash.', 'wp-events-marquee' ),
				'all_items'             => __( 'All Events', 'wp-events-marquee' ),
				'menu_name'             => __( 'Events', 'wp-events-marquee' ),
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'event' ),
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-calendar-alt',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'capability_type'     => 'post',
		);

		/**
		 * Filters the Events CPT registration args.
		 *
		 * @param array $args register_post_type args.
		 */
		$args = apply_filters( 'wp_events_marquee_event_cpt_args', $args );

		register_post_type( 'event', $args );
	}

	/**
	 * Optionally register the Item Types taxonomy.
	 *
	 * Bundled-but-off: returns early unless a site explicitly opts in via
	 * the `wp_events_marquee_register_item_types` filter. Useful for sites
	 * that want to categorize events by type (concert, fundraiser, etc.).
	 */
	public function maybe_register_item_types() {
		if ( ! apply_filters( 'wp_events_marquee_register_item_types', false ) ) {
			return;
		}
		if ( taxonomy_exists( 'item_type' ) ) {
			return;
		}

		$args = array(
			'labels'            => array(
				'name'              => __( 'Item Types', 'wp-events-marquee' ),
				'singular_name'     => __( 'Item Type', 'wp-events-marquee' ),
				'search_items'      => __( 'Search Item Types', 'wp-events-marquee' ),
				'all_items'         => __( 'All Item Types', 'wp-events-marquee' ),
				'edit_item'         => __( 'Edit Item Type', 'wp-events-marquee' ),
				'update_item'       => __( 'Update Item Type', 'wp-events-marquee' ),
				'add_new_item'      => __( 'Add New Item Type', 'wp-events-marquee' ),
				'new_item_name'     => __( 'New Item Type Name', 'wp-events-marquee' ),
				'menu_name'         => __( 'Item Types', 'wp-events-marquee' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'item-type' ),
		);

		/**
		 * Filters the Item Types taxonomy registration args.
		 *
		 * @param array $args register_taxonomy args.
		 */
		$args = apply_filters( 'wp_events_marquee_item_types_args', $args );

		register_taxonomy( 'item_type', array( 'event' ), $args );
	}
}
