/**
 * Home page: about — reveal the facts row by row and the intro line by line,
 * and fade the pillars in, as they scroll into view.
 */
(function () {
	'use strict';

	var section = document.querySelector('.about');
	if (!section) return;

	var items = section.querySelectorAll('.about__reveal, .about__lines, .about__rows');
	if (!items.length || !('IntersectionObserver' in window)) return;

	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	// Line breaks are recomputed by splitLines on resize and after the web font loads.
	if (window.lofu && window.lofu.splitLines) {
		section.querySelectorAll('.about__lines').forEach(function (el) {
			window.lofu.splitLines(el, { lineClass: 'about__line', innerClass: 'about__line-inner' });
		});
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) return;
			entry.target.classList.add('is-visible');
			observer.unobserve(entry.target);
		});
	}, { rootMargin: '0px 0px -10% 0px', threshold: 0.15 });

	section.classList.add('about--reveal');
	items.forEach(function (item) {
		observer.observe(item);
	});
})();
