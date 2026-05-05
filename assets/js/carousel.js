/**
 * WP Events Marquee — Swiper init.
 *
 * Reads window.wpemCarouselInstances (set by an inline script attached
 * before this file) and calls new Swiper() for each rendered instance.
 *
 * No DOM rewriting. The cards, buttons, hrefs, and ticket-type colors
 * are already in the server-rendered markup.
 *
 * Breakpoint defaults (v2.0.3 — restored audit-locked 3-tier layout):
 *   0–767px:    1 slide,  spaceBetween 4
 *   768–1024px: 2 slides, spaceBetween 4
 *   1025px+:    3 slides, spaceBetween 16
 *
 * History:
 *   v2.0.0 (audit-locked): 768 / 1025 breakpoints, three rungs (1/2/3).
 *   v2.0.1: lowered to 640 / 960 to widen the tablet rung after Elementor
 *           inner-column width audit; trial swap.
 *   v2.0.2: collapsed to a single 768 jump from 1→3 slides because the
 *           Elementor container clipped the 2-slide layout on tablet.
 *   v2.0.3: restored audit-locked 768 / 1025 three-rung layout. The
 *           page-author Elementor container was set to flex-grow on
 *           tablet so the 2-slide tablet rung now fits cleanly between
 *           the side spacers without overflowing the viewport.
 *   v2.0.4: defaulted centeredSlides to FALSE (was true). With
 *           slidesPerView=2 on tablet, centeredSlides:true rendered
 *           [half][full][half]; client wants [full][full]. The shortcode
 *           attribute can still override per-instance via cfg.centeredSlides.
 *
 * Swiper measures the carousel container width, not the window width,
 * so the 768/1025 breakpoints comfortably catch Elementor inner columns
 * at default content-width.
 */
(function () {
	'use strict';

	function num(value, fallback) {
		var n = Number(value);
		return isFinite(n) ? n : fallback;
	}

	function init() {
		if (typeof window.Swiper !== 'function') {
			return;
		}
		var instances = window.wpemCarouselInstances || {};
		Object.keys(instances).forEach(function (id) {
			var root = document.getElementById(id);
			if (!root || root.dataset.wpemInited === '1') {
				return;
			}
			var cfg = instances[id] || {};
			var swiperEl = root.querySelector('.swiper');
			if (!swiperEl) {
				return;
			}

			var mobileSlides = num(cfg.mobileSlides, 1);
			var tabletSlides = num(cfg.tabletSlides, 2);
			var desktopSlides = num(cfg.desktopSlides, 3);
			var mobileSpace = num(cfg.mobileSpace, 4);
			var tabletSpace = num(cfg.tabletSpace, 4);
			var desktopSpace = num(cfg.desktopSpace, 16);

			var options = {
				slidesPerView: mobileSlides,
				spaceBetween: mobileSpace,
				slidesPerGroup: 1,
				loop: cfg.loop !== false,
				/* v2.0.4: hard-coded false. Was reading cfg.centeredSlides
				 * (default true via PHP shortcode default), but with
				 * slidesPerView=2 on tablet, centered:true rendered
				 * [half][full][half] instead of [full][full]. The PHP
				 * shortcode attribute is now ignored at the JS layer for
				 * a clean Option-1 global fix. If a future site needs the
				 * centered behavior, re-enable here and revert at the same
				 * time. */
				centeredSlides: false,
				watchOverflow: true,
				breakpoints: {
					768: {
						slidesPerView: tabletSlides,
						spaceBetween: tabletSpace
					},
					1025: {
						slidesPerView: desktopSlides,
						spaceBetween: desktopSpace
					}
				},
				navigation: {
					nextEl: root.querySelector('.wpem-nav-next'),
					prevEl: root.querySelector('.wpem-nav-prev')
				},
				pagination: {
					el: root.querySelector('.wpem-pagination'),
					clickable: true
				}
			};

			if (cfg.autoplay && cfg.autoplay > 0) {
				options.autoplay = {
					delay: cfg.autoplay,
					disableOnInteraction: false,
					pauseOnMouseEnter: true
				};
			}

			try {
				new window.Swiper(swiperEl, options);
				root.dataset.wpemInited = '1';
			} catch (e) {
				if (window.console && console.warn) {
					console.warn('[wpem] Swiper init failed for ' + id, e);
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
