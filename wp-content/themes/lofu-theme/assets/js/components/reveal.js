/**
 * Scroll reveal for `.reveal` headings (global/components/section-heading.scss):
 * the text slides up from its mask when the heading scrolls into view.
 *
 * Only for headings whose section has no script of its own; sections such as
 * projects or products reveal their whole head in their own JS.
 */
(function () {
	var items = document.querySelectorAll('.reveal');
	if (!items.length || !('IntersectionObserver' in window)) return;
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) return;
			entry.target.classList.add('is-visible');
			observer.unobserve(entry.target);
		});
	}, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

	items.forEach(function (el) {
		el.classList.add('is-armed');
		observer.observe(el);
	});
})();
