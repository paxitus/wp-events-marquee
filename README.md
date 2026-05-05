# WP Events Marquee

Drop-in `[wpem_carousel]` shortcode that renders an upcoming-events carousel for any WordPress site, using ACF event data and a bundled Swiper. No Elementor, no DCE, no page-builder dependency.

Originally built for a small portfolio of WordPress sites that needed a lightweight, drop-in events carousel without page-builder coupling. Released publicly to share the pattern. v2 is the standalone shortcode rewrite that replaces the v1 rewriter pattern.

## What this plugin does

- **Registers** the `[wpem_carousel]` shortcode. Drop it into any post, page, widget, or template; the plugin renders a Swiper carousel of upcoming events server-side, with all markup ready at first paint.
- **Queries** `event` post-type posts where ACF `show_in_carousel = Yes` and ACF `end_date >= today`, ordered by ACF `date` ascending.
- **Renders** each card as: date pill, featured image, ticket-type-colored button. The button href points to the event detail page (or to the `link` ACF field for `Advertisement` cards).
- **Bundles** Swiper 11.x in `assets/vendor/swiper/`. Self-contained, no CDN.
- **Conditionally enqueues** assets only when the shortcode is present in the current post's content (or via the `wp_events_marquee_enqueue_assets` filter for non-standard contexts like block content or builders).
- **Registers** the "Events" ACF field group as a code-defined local group (`type_of_ticket`, `show_in_carousel`, `date`, `end_date`, `link`, recurrence fields, etc.) so the contract is stable across every site.
- **Ships** the `event` CPT registration (gated by filter for sites that already register it).

## Architecture (v2)

Server-side render of card markup via PHP template. No client-side rewriting, no opacity-then-reveal pattern, no Elementor template entanglement. The shortcode renders a complete, visible carousel in the initial HTML response; Swiper attaches behavior on top.

This is a deliberate pivot from v1 (see "Migration from v1" below).

## Usage

```
[wpem_carousel]
```

### Shortcode attributes

| Attribute | Default | What it does |
|---|---|---|
| `limit` | `12` | Max events to query. |
| `desktop_slides` | `3` | Slides visible at >=1025px viewport. |
| `tablet_slides` | `2` | Slides visible at 768–1024px. |
| `mobile_slides` | `1` | Slides visible below 768px. |
| `desktop_space` | `16` | Pixel gap between slides at >=1025px. |
| `tablet_space` | `4` | Pixel gap between slides at 768–1024px. |
| `mobile_space` | `4` | Pixel gap between slides below 768px. |
| `autoplay` | `4000` | Autoplay interval in ms. Set `0` to disable. |
| `loop` | `yes` | Swiper loop mode. |
| `centered_slides` | `yes` | Swiper centeredSlides mode. |

Example with overrides:

```
[wpem_carousel limit="6" desktop_slides="4" autoplay="0"]
```

## Recurring events

A recurring event is a single `event` post that repeats on a schedule (every Wednesday, every 15th, etc.) rather than a separate post per occurrence. The plugin keeps the post in the carousel for the duration of the recurrence and shows the event's **next occurrence** as the date pill.

### Required ACF fields

These fields ship in the bundled "Events" field group:

| Field | Type | What it does |
|---|---|---|
| `is_recurring` | true/false | Marks the event as recurring. When truthy, the carousel keeps the event visible past its `end_date` (until the recurrence itself expires) and recomputes the displayed date. |
| `recurrence_type` | select (`weekly` \| `monthly`) | Drives which schedule rule applies. Shown in the editor only when `is_recurring` is on. |
| `recurrence_day` | select (`Sunday` … `Saturday`) | Day of the week the event repeats. Shown only when `recurrence_type = weekly`. Stored as the label string (the field's `return_format` is `label`). |
| `monthly_day` | number 1–31 | Day of the month the event repeats. Shown only when `recurrence_type = monthly`. |
| `end_date` | date | Last day the recurrence is active. After this date the recurring event drops out of the carousel automatically. |

### Behavior rules

1. **Past `end_date` is okay if the recurrence is still active.** The carousel query lets recurring events bypass the `end_date >= today` gate so a "every Wednesday" event stays visible even if its `end_date` is yesterday — provided the recurrence's final `end_date` is still in the future.
2. **The date pill shows the next occurrence**, computed at render time:
   - `weekly` — advances from today to the next match of `recurrence_day` (returning today if today is already that weekday).
   - `monthly` — advances to `monthly_day` this month, or to next month if it has already passed. Months that are too short clamp to the month's last day, so `monthly_day = 31` shows Feb 28 (or Feb 29 in a leap year).
3. **When the next occurrence falls past `end_date`**, the event is considered fully expired and drops from the carousel.
4. **Single-occurrence events** (where `is_recurring` is off) behave exactly as before: the date pill renders the ACF `date` value, and the post leaves the carousel once `end_date` passes.

### Authoring guidance for editors

- Always fill in `date`, `start_time`, and `end_date` — `end_date` is required and acts as the recurrence's final boundary.
- For weekly events, set `is_recurring` on, choose `weekly`, and pick the `recurrence_day`.
- For monthly events, set `is_recurring` on, choose `monthly`, and set `monthly_day` (1–31).
- The ACF UI hides the recurrence-specific fields until you turn `is_recurring` on; you don't need to clear them when switching back to a single-occurrence event.

## File breakdown

| Path | Purpose |
|---|---|
| `wp-events-marquee.php` | Plugin header (v2.0.0), constants, requires, single-line bootstrap. |
| `includes/class-wpem-plugin.php` | Bootstrap; wires up modules. |
| `includes/class-wpem-carousel.php` | `[wpem_carousel]` shortcode. Builds the query, resolves ticket-type slug + button text + button href per card, renders via `templates/card.php`, tracks rendered instances for the JS initializer. |
| `includes/class-wpem-assets.php` | Conditional enqueue of bundled Swiper 11 + plugin CSS + plugin JS, keyed off `has_shortcode()`. Inline initializer payload via `wp_add_inline_script()`. |
| `includes/class-wpem-ticket-types.php` | Slug → label/colors/match-tokens catalog. Single source of truth; per-site overrides via filter. |
| `includes/class-wpem-post-types.php` | Registers `event` CPT (mandatory; gated by filter) and `item_type` taxonomy (opt-in via filter). |
| `includes/class-wpem-acf-fields.php` | `acf_add_local_field_group()` for the Events group. Field keys preserved verbatim from the 134main baseline. |
| `includes/class-wpem-settings.php` | Settings → Events Marquee admin page for the per-site empty-state placeholder image. ACF Pro options page with native Settings API fallback. |
| `includes/class-wpem-migration.php` | One-time `event_brite_link → link` post_meta migration, idempotent. |
| `templates/card.php` | Single event card template. Date pill + featured image + ticket button. Themes can override at `wp-content/themes/<theme>/wp-events-marquee/card.php`. |
| `assets/css/carousel.css` | Plugin stylesheet, `.wpem-` prefixed. No Elementor selectors. Buttons visible from first paint. |
| `assets/js/carousel.js` | Swiper init. Reads the `window.wpemCarouselInstances` map and calls `new Swiper()` per rendered instance. No DOM rewriting. |
| `assets/vendor/swiper/swiper-bundle.min.css` | Bundled Swiper 11 stylesheet. |
| `assets/vendor/swiper/swiper-bundle.min.js` | Bundled Swiper 11 script. |

## Filter hooks reference

| Filter | What it controls |
|---|---|
| `wp_events_marquee_query_args` | The `WP_Query` args used to fetch events for the carousel. Accepts `(array $args, int $limit)`. |
| `wp_events_marquee_ticket_type_map` | The slug → `[label, bg_color, text_color, match_tokens, ad_label?]` catalog. Per-site copy and color overrides go here. |
| `wp_events_marquee_default_ticket_type` | Slug used when a post has no recognizable `type_of_ticket` value. Default: `free`. |
| `wp_events_marquee_enqueue_assets` | Force enqueue Swiper + plugin assets in non-standard contexts (archives, block-only content, builders). Default: result of singular `has_shortcode()` scan. |
| `wp_events_marquee_register_acf_fields` | Return false to skip ACF field-group registration on a site that defines fields elsewhere. |
| `wp_events_marquee_acf_field_group` | Mutate the field group definition before registration. Use to override individual fields per-site. |
| `wp_events_marquee_register_event_cpt` | Return false to skip `event` CPT registration on a site that already registers it. |
| `wp_events_marquee_event_cpt_args` | Mutate the `register_post_type` args for the Events CPT. |
| `wp_events_marquee_register_item_types` | Return true to opt into the bundled `item_type` taxonomy. Default: false. |
| `wp_events_marquee_item_types_args` | Mutate the `register_taxonomy` args for `item_type`. |
| `wp_events_marquee_empty_state_image` | URL for the empty-state image when no events match. Default: empty (text-only). |
| `wp_events_marquee_empty_state_message` | Empty-state message text. |

## Activation behavior

On activation:

1. The `event` CPT registers (unless filtered off).
2. The ACF "Events" field group registers via `acf/init` (only if ACF is loaded). Fields appear read-only in the ACF UI because they're code-defined.
3. Existing post meta on already-populated sites continues to resolve unchanged because the field keys are preserved verbatim from the 134main baseline.
4. The `event_brite_link → link` one-time post_meta migration runs once and records a flag option so subsequent loads no-op. Legacy `event_brite_link` post_meta is preserved for rollback.
5. No assets enqueue until the shortcode is detected on a singular request.

The plugin is **stateless** beyond the migration flag option and the placeholder-image setting; deactivating it leaves no other data residue.

## Migration from v1

v1 (0.x series) was a JS rewriter pattern that walked Elementor + DCE Listing Grid markup and swapped button text/colors/href client-side. It depended on a specific Elementor Loop Item template ID (`elementor-element-4bb8800`) and used an `opacity:0` + reveal pattern to hide unprocessed buttons during the rewrite. That pattern produced a visible flash and broke when sites had legacy Elementor Custom CSS rules on the loop item.

v2 (this version) is a fresh build:

| v1 (deprecated) | v2 |
|---|---|
| Rewriter pattern over Elementor + DCE markup | Standalone shortcode, server-side render |
| Required Elementor + DCE Pro | Zero page-builder dependency |
| Buttons hidden until JS rewrites them (`opacity:0 → 1`) | Buttons rendered visible at first paint |
| Carousel was Elementor's built-in widget | Carousel is bundled Swiper 11.x, plugin-owned |
| Per-site Elementor template surgery to roll out | Drop in shortcode, done |
| `wp_events_marquee_should_run` filter (front-page guard) | Removed; the shortcode runs wherever it's placed |
| `[wpem_mobile_carousel]` shortcode + mobile-only renderer | Removed; one carousel, one shortcode, fully responsive |
| `class-wpem-rewriter.php`, `class-wpem-mobile-carousel.php`, `assets/js/rewriter.js` | Deleted |

If you're upgrading from v1: deactivate v1, activate v2, place `[wpem_carousel]` where the carousel should appear (typically wherever the v1 Elementor widget was), remove the v1 Elementor widget.

## Compatibility

- **WordPress:** 6.4+
- **PHP:** 7.4+
- **ACF:** 5.x or 6.x (free or Pro). Pro is recommended; Settings page falls back to native Settings API + media uploader if Pro is absent.

## Versioning

Plugin version is in the header (`Version: 2.1.4`) and the `WPEM_VERSION` constant. Asset versions key off `WPEM_VERSION` so cache-busting flows automatically when the plugin bumps.

## Changelog

### v2.1.4

Card CTA button font family swapped from hardcoded `"EB Garamond"` to the Elementor primary-typography site token (`--e-global-typography-primary-font-family`) with safe fallback. Completes the date-pill + button portability work begun in v2.1.3. No visible change on 134main; portfolio sites (Brulee La Grange, Cafe Brulee, etc.) will inherit their own primary heading font on rollout.

### v2.1.3

Date pill color and font family swapped from hardcoded values to Elementor site tokens (`--e-global-color-primary`, `--e-global-typography-text-font-family`) with safe fallbacks. Hover state now uses `filter: brightness(1.15)` so it tracks the resolved primary color instead of a hardcoded hex. No visible change on 134main; portfolio sites will inherit their own brand tokens on rollout.

### v2.1.2

Empty-state message typography (bigger, lighter) so it reads as a soft secondary message rather than tiny body copy.

### v2.1.1

Fix duplicate-card render in low-count carousels.

### v2.1.0

Native recurring-event support in the carousel (weekly + monthly schedules with next-occurrence date).

### v2.0.6

First public release of the v2 standalone shortcode events carousel plugin.

## Repository

GitHub: `paxitus/wp-events-marquee` (created 2026-04-28).

## License

GPL-2.0-or-later. WordPress-compatible.
