<?php
/**
 * Registers the Events ACF field group as code-defined (local) fields.
 *
 * Only the Events field group is registered here. Special Edits, the event
 * post type, and the item-type taxonomy are intentionally out of scope —
 * those are per-site decisions that vary across the Abbott BG fleet and
 * shouldn't be force-shipped by an events-marquee plugin.
 *
 * Code-defined groups appear read-only in the ACF UI, which is what we want
 * for cross-site portability. If a site needs to override a field locally,
 * use the wp_events_marquee_acf_field_group filter rather than editing the
 * group in wp-admin.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_ACF_Fields {

	/**
	 * @var WPEM_ACF_Fields|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
	}

	public function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Allow per-site opt-out.
		if ( ! apply_filters( 'wp_events_marquee_register_acf_fields', true ) ) {
			return;
		}

		$group = $this->get_field_group_definition();

		/**
		 * Filters the ACF field group definition before registration.
		 * Use to override individual fields on a per-site basis.
		 *
		 * @param array $group Field group array.
		 */
		$group = apply_filters( 'wp_events_marquee_acf_field_group', $group );

		acf_add_local_field_group( $group );
	}

	/**
	 * Field group derived from the 134main acf-export-2026-05-01.json baseline.
	 * Field keys preserved verbatim so existing post meta on already-populated
	 * sites continues to resolve through ACF.
	 *
	 * @return array
	 */
	private function get_field_group_definition() {
		return array(
			'key'                   => 'group_682254607fffb',
			'title'                 => 'Events',
			'fields'                => array(
				array(
					'key'                => 'field_685f152eb2dd0',
					'label'              => 'Type of ticket',
					'name'               => 'type_of_ticket',
					'type'               => 'radio',
					'required'           => 1,
					'choices'            => array(
						'Purchase Tickets Online' => 'Purchase Tickets Online',
						'Tickets at the Door'     => 'Tickets at the Door',
						'Free Event'              => 'Free Event',
						'Private Event'           => 'Private Event',
						'Coming Soon'             => 'Coming Soon',
						'Upcoming Events'         => 'Upcoming Events',
						'Advertisement'           => 'Advertisement',
					),
					'default_value'      => '',
					'return_format'      => 'value',
					'allow_null'         => 0,
					'other_choice'       => 1,
					'save_other_choice'  => 1,
					'layout'             => 'vertical',
				),
				/*
				 * Q1-sub (Phase 2 spec): renamed 'event_brite_link' -> 'link'.
				 * Generic, platform-agnostic; conditional logic broadened so the
				 * field shows for "Purchase Tickets Online" AND "Advertisement"
				 * (Advertisement uses link as its destination URL per Q1).
				 *
				 * Field key preserved (field_682647e582994) so existing post_meta
				 * keyed under that field key continues to resolve through ACF.
				 * Activation hook in WPEM_Plugin migrates legacy 'event_brite_link'
				 * post_meta -> 'link' post_meta one time per site, idempotent.
				 */
				array(
					'key'              => 'field_682647e582994',
					'label'            => 'Link',
					'name'             => 'link',
					'instructions'     => 'Destination URL for "Purchase Tickets Online" buttons or for "Advertisement" cards.',
					'type'             => 'url',
					'required'         => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_685f152eb2dd0',
								'operator' => '==',
								'value'    => 'Purchase Tickets Online',
							),
						),
						array(
							array(
								'field'    => 'field_685f152eb2dd0',
								'operator' => '==',
								'value'    => 'Advertisement',
							),
						),
					),
					'wrapper'          => array( 'width' => '100' ),
				),
				array(
					'key'              => 'field_68225515eb832',
					'label'            => 'Ages',
					'name'             => 'ages',
					'type'             => 'checkbox',
					'required'         => 0,
					'wrapper'          => array( 'width' => '15' ),
					'choices'          => array(
						'All Ages' => 'All Ages',
						'21+'      => '21+',
					),
					'default_value'    => array( '21+' ),
					'return_format'    => 'value',
					'layout'           => 'vertical',
				),
				array(
					'key'              => 'field_685ebedf89566',
					'label'            => 'Show in Carousel',
					'name'             => 'show_in_carousel',
					'type'             => 'radio',
					'required'         => 0,
					'wrapper'          => array( 'width' => '15' ),
					'choices'          => array(
						'Yes' => 'Yes',
						'No'  => 'No',
					),
					'default_value'    => 'No',
					'return_format'    => 'value',
					'layout'           => 'vertical',
				),
				array(
					'key'            => 'field_68225461d0f9e',
					'label'          => 'Date',
					'name'           => 'date',
					'type'           => 'date_picker',
					'required'       => 0,
					'wrapper'        => array( 'width' => '25' ),
					'display_format' => 'F j, Y',
					'return_format'  => 'F j, Y',
					'first_day'      => 0,
				),
				array(
					'key'            => 'field_68264a2650f35',
					'label'          => 'End Date',
					'name'           => 'end_date',
					'type'           => 'date_picker',
					'instructions'   => 'This is the last day of a single event, or the last date of a recurring event. It should be filled in for all events.',
					'required'       => 1,
					'wrapper'        => array( 'width' => '25' ),
					'display_format' => 'F j, Y',
					'return_format'  => 'F j, Y',
					'first_day'      => 1,
				),
				array(
					'key'            => 'field_68225486d0f9f',
					'label'          => 'Start Time',
					'name'           => 'start_time',
					'type'           => 'time_picker',
					'instructions'   => 'If you add a recurring event, you still have to add a start date of the event.',
					'required'       => 0,
					'wrapper'        => array( 'width' => '25' ),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'            => 'field_682254a0d0fa0',
					'label'          => 'End Time',
					'name'           => 'end_time',
					'type'           => 'time_picker',
					'required'       => 0,
					'wrapper'        => array( 'width' => '25' ),
					'display_format' => 'g:i a',
					'return_format'  => 'g:i a',
				),
				array(
					'key'           => 'field_685ef9c8d9025',
					'label'         => 'Is Recurring Event',
					'name'          => 'is_recurring',
					'type'          => 'true_false',
					'required'      => 0,
					'default_value' => 0,
				),
				array(
					'key'              => 'field_685efa3a6a980',
					'label'            => 'Recurrence Type',
					'name'             => 'recurrence_type',
					'type'             => 'select',
					'required'         => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_685ef9c8d9025',
								'operator' => '==',
								'value'    => '1',
							),
						),
					),
					'choices'          => array(
						'weekly'  => 'Weekly',
						'monthly' => 'Monthly',
					),
					'default_value'    => 'weekly',
					'return_format'    => 'value',
					'multiple'         => 0,
					'allow_null'       => 0,
				),
				array(
					'key'              => 'field_685efa756a981',
					'label'            => 'Day of Week',
					'name'             => 'recurrence_day',
					'type'             => 'select',
					'required'         => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_685efa3a6a980',
								'operator' => '==',
								'value'    => 'weekly',
							),
						),
					),
					'choices'          => array(
						'Sunday'    => 'Sunday',
						'Monday'    => 'Monday',
						'Tuesday'   => 'Tuesday',
						'Wednesday' => 'Wednesday',
						'Thursday'  => 'Thursday',
						'Friday'    => 'Friday',
						'Saturday'  => 'Saturday',
					),
					'default_value'    => 5,
					'return_format'    => 'label',
					'multiple'         => 0,
					'allow_null'       => 0,
				),
				array(
					'key'              => 'field_685efc6dba395',
					'label'            => 'Day of Month',
					'name'             => 'monthly_day',
					'type'             => 'number',
					'required'         => 0,
					'conditional_logic' => array(
						array(
							array(
								'field'    => 'field_685efa3a6a980',
								'operator' => '==',
								'value'    => 'monthly',
							),
						),
					),
					'default_value'    => 1,
					'min'              => 1,
					'max'              => 31,
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'event',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 0,
		);
	}
}
