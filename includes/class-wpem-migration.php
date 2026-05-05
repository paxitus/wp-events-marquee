<?php
/**
 * One-time migration: copy legacy `event_brite_link` post_meta to `link`.
 *
 * Phase 2 spec Q1-sub: the plugin now bundles the ACF field as `link`
 * (generic, platform-agnostic) instead of `event_brite_link`. Sites that
 * upgrade from a pre-plugin FluentSnippet world will have their ACF data
 * stored under the old `event_brite_link` post_meta key. This migration
 * copies the value across one time per site and records a flag option so
 * subsequent plugin loads no-op.
 *
 * Migration is conservative:
 *   - Never overwrites a populated `link` value.
 *   - Never deletes the legacy `event_brite_link` value (rollback safety).
 *   - Idempotent: protected by an option flag.
 *
 * Hook timing: runs on `init` priority 99 the first time the plugin loads
 * AFTER a deploy. Activation hooks don't fire for MU plugins, so we use
 * the option-flag-on-init pattern instead.
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPEM_Migration {

	const FLAG_OPTION = 'wpem_link_field_migration_v1';

	/**
	 * @var WPEM_Migration|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register() {
		add_action( 'init', array( $this, 'maybe_migrate_link_field' ), 99 );
	}

	/**
	 * Run the legacy event_brite_link -> link copy if not yet run on this site.
	 */
	public function maybe_migrate_link_field() {
		if ( get_option( self::FLAG_OPTION ) === 'done' ) {
			return;
		}

		// Only run once admin has loaded enough that meta queries are safe.
		// We don't hard-require ACF — we read post_meta directly so the
		// migration works even if ACF hasn't fully booted yet.

		$post_ids = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => 'event_brite_link',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$copied = 0;
		foreach ( $post_ids as $post_id ) {
			$legacy = get_post_meta( $post_id, 'event_brite_link', true );
			if ( '' === $legacy || null === $legacy ) {
				continue;
			}
			$current = get_post_meta( $post_id, 'link', true );
			if ( '' !== $current && null !== $current ) {
				// Already migrated or hand-edited; never overwrite.
				continue;
			}
			update_post_meta( $post_id, 'link', $legacy );
			++$copied;
		}

		update_option( self::FLAG_OPTION, 'done', false );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[wpem] link-field migration: copied %d post_meta values', $copied ) );
		}
	}
}
