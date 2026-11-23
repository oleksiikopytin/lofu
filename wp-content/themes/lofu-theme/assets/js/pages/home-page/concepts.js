/**
 * Home page: concepts — Slick slider driven by the section's own arrows and
 * dots. The counter, title and active dot follow the current slide.
 */
(function ($) {
	'use strict';

	var section = document.querySelector('.concepts');
	if (!section || !$ || !$.fn.slick) return;

	var track = section.querySelector('.concepts__track');
	if (!track || track.children.length < 2) return;

	var dots = section.querySelectorAll('.concepts__dot');
	var counter = section.querySelector('.concepts__counter-current');
	var name = section.querySelector('.concepts__name');
	var titles = Array.prototype.map.call(track.children, function (slide) {
		return slide.getAttribute('data-title');
	});
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var $track = $(track);

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function sync(index) {
		dots.forEach(function (dot, i) {
			dot.setAttribute('aria-current', i === index ? 'true' : 'false');
		});
		if (counter) counter.textContent = pad(index + 1);
		if (name) name.textContent = titles[index];
	}

	// Update the labels as the slide starts moving, not when it lands.
	$track.on('beforeChange', function (event, slick, current, next) {
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
