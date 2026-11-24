/**
 * Home page: concepts — Slick slider driven by the section's own arrows and
 * dots. The counter, title and active dot follow the current slide (the
 * counter and title roll to their new values), and the section reveals
 * itself on scroll with a wipe across the stage.
 */
(function ($) {
	'use strict';

	var section = document.querySelector('.concepts');
	if (!section) return;

	/**
	 * The slides' images are rendered with data-src/srcset/sizes (see
	 * lofu_defer_image_attributes()), Slick's clones included; set the real
	 * attributes so they load. Runs once the section is within half a screen
	 * of the viewport (so the first image is in by the time it shows), or on
	 * the first slide change if that comes sooner. Set up before the slider,
	 * so the images still load if Slick is missing or there is one slide.
	 */
	var deferredLoaded = false;

	function loadDeferredImages() {
		if (deferredLoaded) return;
		deferredLoaded = true;

		section.querySelectorAll('img[data-src]').forEach(function (img) {
			if (img.dataset.sizes) img.sizes = img.dataset.sizes;
			if (img.dataset.srcset) img.srcset = img.dataset.srcset;
			img.src = img.dataset.src;
			img.removeAttribute('data-sizes');
			img.removeAttribute('data-srcset');
			img.removeAttribute('data-src');
		});
	}

	if ('IntersectionObserver' in window) {
		var imageObserver = new IntersectionObserver(function (entries) {
			if (!entries.some(function (entry) { return entry.isIntersecting; })) return;
			imageObserver.disconnect();
			loadDeferredImages();
		}, { rootMargin: '50% 0px' });
		imageObserver.observe(section);
	} else {
		loadDeferredImages();
	}

	if (!$ || !$.fn.slick) return;

	var track = section.querySelector('.concepts__track');
	if (!track || track.children.length < 2) return;

	var dots = section.querySelectorAll('.concepts__dot');
	var counter = section.querySelector('.concepts__counter-current');
	var name = section.querySelector('.concepts__name-text');
	var titles = Array.prototype.map.call(track.children, function (slide) {
		return slide.getAttribute('data-title');
	});
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var $track = $(track);

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	/**
	 * Rolls an element's text: the old value slides up out of its mask, the
	 * new one slides up in from below. A newer roll cancels a running one.
	 */
	function roll(el, text) {
		if (!el) return;
		if ((el.lofuTarget || el.textContent) === text) return;
		el.lofuTarget = text;

		if (el.lofuRoll) el.lofuRoll.forEach(function (a) { a.cancel(); });

		if (reduceMotion || !el.animate) {
			el.textContent = text;
			return;
		}

		var out = el.animate(
			[{ transform: 'none' }, { transform: 'translateY(-110%)' }],
			{ duration: 280, easing: 'cubic-bezier(0.5, 0, 0.75, 0)', fill: 'forwards' }
		);
		el.lofuRoll = [out];

		out.onfinish = function () {
			el.textContent = text;
			var inAnim = el.animate(
				[{ transform: 'translateY(110%)' }, { transform: 'none' }],
				{ duration: 600, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' }
			);
			out.cancel();
			el.lofuRoll = [inAnim];
		};
	}

	function sync(index) {
		dots.forEach(function (dot, i) {
			dot.setAttribute('aria-current', i === index ? 'true' : 'false');
		});
		roll(counter, pad(index + 1));
		roll(name, titles[index]);
	}

	// Update the labels as the slide starts moving, not when it lands.
	$track.on('beforeChange', function (event, slick, current, next) {
		loadDeferredImages();
		sync(next);
	});

	$track.slick({
		prevArrow: section.querySelector('.concepts__arrow--prev'),
		nextArrow: section.querySelector('.concepts__arrow--next'),
		dots: false,
		infinite: true,
		speed: reduceMotion ? 0 : 800,
		cssEase: 'cubic-bezier(0.65, 0, 0.35, 1)',
		accessibility: true
	});

	dots.forEach(function (dot, i) {
		dot.addEventListener('click', function () {
			$track.slick('slickGoTo', i);
		});
	});

	// Scroll reveal: head text slides up, the stage wipes in and the foot
	// (title + dots) follows it via CSS sibling selectors.
	if (!reduceMotion && 'IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				entry.target.classList.add('is-visible');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -10% 0px', threshold: 0.25 });

		section.classList.add('concepts--reveal');
		section.querySelectorAll('.concepts__head, .concepts__stage').forEach(function (el) {
			observer.observe(el);
		});
	}

	// Arrow keys anywhere in the section (Slick's own handler only fires
	// while its list has focus, so skip those to avoid a double move).
	section.addEventListener('keydown', function (event) {
		if (event.target.closest('.slick-list')) return;
		if (event.key === 'ArrowLeft') $track.slick('slickPrev');
		else if (event.key === 'ArrowRight') $track.slick('slickNext');
		else return;
		event.preventDefault();
	});
})(window.jQuery);
