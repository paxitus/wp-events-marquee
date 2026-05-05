<?php
/**
 * Single event card template.
 *
 * Per v2-visual-audit.md the card has exactly three blocks stacked
 * vertically: date pill, featured image, ticket button. No title text.
 *
 * Themes can override by placing a copy at:
 *   wp-content/themes/<your-theme>/wp-events-marquee/card.php
 *
 * Available context (injected by WPEM_Carousel::load_card_template):
 *   $wpem['post_id']     int     Event post ID.
 *   $wpem['slug']        string  Resolved ticket-type slug.
 *   $wpem['ticket_data'] array   Map entry for the slug (label/bg_color/text_color/...).
 *   $wpem['button_href'] string  Pre-escaped href.
 *   $wpem['button_text'] string  Button label (Advertisement uses ad_label).
 *   $wpem['event_date']  string  Pre-formatted date string ("F j, Y") — formatted by WPEM_Carousel::format_event_date() from the ACF Ymd meta value before reaching the template.
 *   $wpem['permalink']   string  Event detail page URL.
 *   $wpem['title']       string  Event title (used only for image alt fallback).
 *
 * @package WPEventsMarquee
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $wpem ) || ! is_array( $wpem ) ) {
	return;
}

$thumb_id  = get_post_thumbnail_id( $wpem['post_id'] );
$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '';
$thumb_alt = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
if ( '' === $thumb_alt ) {
	$thumb_alt = $wpem['title'];
}

$bg_color   = isset( $wpem['ticket_data']['bg_color'] ) ? $wpem['ticket_data']['bg_color'] : '#dc3545';
$text_color = isset( $wpem['ticket_data']['text_color'] ) ? $wpem['ticket_data']['text_color'] : '#ffffff';

$show_date = ( 'advertisement' !== $wpem['slug'] ) && '' !== $wpem['event_date'];
?>
<article
	class="swiper-slide wpem-card"
	data-ticket-type="<?php echo esc_attr( $wpem['slug'] ); ?>"
	data-post-id="<?php echo esc_attr( $wpem['post_id'] ); ?>"
>
	<?php if ( $show_date ) : ?>
		<a class="wpem-card__date" href="<?php echo esc_url( $wpem['permalink'] ); ?>">
			<span class="wpem-card__date-text"><?php echo esc_html( $wpem['event_date'] ); ?></span>
		</a>
	<?php endif; ?>

	<a class="wpem-card__media" href="<?php echo esc_url( $wpem['permalink'] ); ?>" aria-label="<?php echo esc_attr( $wpem['title'] ); ?>">
		<?php if ( $thumb_url ) : ?>
			<img
				class="wpem-card__image"
				src="<?php echo esc_url( $thumb_url ); ?>"
				alt="<?php echo esc_attr( $thumb_alt ); ?>"
				loading="lazy"
				decoding="async"
			/>
		<?php else : ?>
			<span class="wpem-card__image wpem-card__image--placeholder" aria-hidden="true"></span>
		<?php endif; ?>
	</a>

	<div class="wpem-card__button-wrap">
		<a
			class="wpem-card__button"
			href="<?php echo esc_url( $wpem['button_href'] ); ?>"
			style="background-color: <?php echo esc_attr( $bg_color ); ?>; color: <?php echo esc_attr( $text_color ); ?>;"
		>
			<?php echo esc_html( $wpem['button_text'] ); ?>
		</a>
	</div>
</article>
