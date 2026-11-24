/**
 * Loads a section's window <template>s after the page.
 *
 * A section with `data-templates-src` gets the HTML from that URL appended
 * to it, once. Loading starts when the section comes within a screen of the
 * viewport, or the pointer or keyboard focus reaches it, so the templates are
 * normally there before a card is clicked. Section scripts that need a
 * template sooner (a click, or a URL hash on load) call
 * `window.lofu.loadTemplates(section)`, which returns a promise that settles
 * once the templates are in (or the request failed; a later call retries).
 */
(function () {
	'use strict';

	var loads = new WeakMap();

	function load(section) {
		var src = section && section.getAttribute('data-templates-src');
		if (!src) return Promise.resolve();
		if (loads.has(section)) return loads.get(section);

		var promise = fetch(src, { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) throw new Error('HTTP ' + response.status);
				return response.text();
			})
			.then(function (html) {
				section.insertAdjacentHTML('beforeend', html);
				section.removeAttribute('data-templates-src');
			})
			.catch(function () {
				loads.delete(section);
			});

		loads.set(section, promise);
		return promise;
	}

	window.lofu = window.lofu || {};
	window.lofu.loadTemplates = load;

	var sections = document.querySelectorAll('[data-templates-src]');
	if (!sections.length) return;

	sections.forEach(function (section) {
		function early() {
			load(section);
		}

		section.addEventListener('pointerover', early, { once: true });
		section.addEventListener('focusin', early, { once: true });
	});

	if (!('IntersectionObserver' in window)) {
		sections.forEach(load);
		return;
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (!entry.isIntersecting) return;
			observer.unobserve(entry.target);
			load(entry.target);
		});
	}, { rootMargin: '100% 0px' });

	sections.forEach(function (section) {
		observer.observe(section);
	});
})();
