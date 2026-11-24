/**
 * Home page: products — scroll reveal of the series cards, the series window
 * and the product window over it, and the product photo slider.
 *
 * Each series' content sits in a <template id="series-{slug}-modal"> and
 * each product's in a <template id="product-{slug}-modal" data-series="…">,
 * loaded into the section after the page by lazy-templates.js; anything that
 * needs one before then waits for it. A series card opens its series in `#series-modal`; a product card there
 * opens the product in `#product-modal` on top. The URL hash follows what is
 * open, so `/#series-{slug}` and `/#product-{slug}` (where series and product
 * URLs redirect) open them on load; a product hash opens its series under it.
 * Opening from a card adds a history entry, so Back closes one window at a
 * time.
 */
(function () {
	'use strict';

	var section = document.querySelector('.products');
	if (!section) return;

	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Scroll reveal: the title slides up from its mask, cards rise in.
	if (!reduceMotion && 'IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				entry.target.classList.add('is-visible');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

		section.classList.add('products--reveal');
		section.querySelectorAll('.products__head, .products__item').forEach(function (el) {
			observer.observe(el);
		});
	}

	var seriesDialog = document.getElementById('series-modal');
	var productDialog = document.getElementById('product-modal');
	if (!seriesDialog || !productDialog || typeof seriesDialog.showModal !== 'function') return;

	var root = document.documentElement;
	var current = { series: null, product: null };
	// Set before a close the URL already reflects (Back, a hash link).
	var silent = { series: false, product: false };

	function hashAnchor() {
		var hash = window.location.hash.slice(1);
		try {
			return decodeURIComponent(hash);
		} catch (e) {
			return hash;
		}
	}

	function templateFor(anchor, prefix) {
		if (!anchor || anchor.indexOf(prefix) !== 0) return null;
		var template = document.getElementById(anchor + '-modal');
		return template && template.content ? template : null;
	}

	function isWindowAnchor(anchor) {
		return /^(series|product)-/.test(anchor);
	}

	// The templates are still to come (lazy-templates.js).
	function templatesPending() {
		return section.hasAttribute('data-templates-src') && !!(window.lofu && window.lofu.loadTemplates);
	}

	function loadTemplates() {
		return templatesPending() ? window.lofu.loadTemplates(section) : Promise.resolve();
	}

	// The entry was added by us (opened from a card), so Back undoes it.
	function isOwnEntry() {
		return !!(history.state && history.state.lofuProducts);
	}

	function focusIn(scope, anchor) {
		var card = anchor && scope.querySelector('#' + CSS.escape(anchor));
		if (card) card.focus({ preventScroll: true });
	}

	function focusClose(dialog) {
		var close = dialog.querySelector('[data-modal-close]');
		if (close) close.focus({ preventScroll: true });
	}

	function closeSilently(dialog, key) {
		if (!dialog.open) return;
		silent[key] = true;
		dialog.close();
	}

	/**
	 * Product photo slider: arrows, thumbnails, swipe and ← → keys. Lives on
	 * the cloned content, so it goes away with it.
	 */
	function initSlider(scope) {
		var gallery = scope.querySelector('[data-product-slider]');
		if (!gallery) return;

		var slides = gallery.querySelectorAll('.product-modal__slide');
		var thumbs = gallery.querySelectorAll('[data-slide-to]');
		var counter = gallery.querySelector('.product-modal__counter');
		var stage = gallery.querySelector('.product-modal__stage');
		var count = slides.length;
		var index = 0;

		if (count < 2) return;

		function go(i) {
			var video = slides[index].querySelector('video');
			if (video) video.pause();

			index = (i + count) % count;
			slides.forEach(function (slide, k) {
				slide.hidden = k !== index;
			});
			thumbs.forEach(function (thumb) {
				if (Number(thumb.getAttribute('data-slide-to')) === index) {
					thumb.setAttribute('aria-current', 'true');
				} else {
					thumb.removeAttribute('aria-current');
				}
			});
			if (counter) counter.textContent = (index + 1) + ' / ' + count;
		}

		gallery.addEventListener('click', function (event) {
			var step = event.target.closest('[data-slide-step]');
			var thumb = event.target.closest('[data-slide-to]');

			if (step) go(index + Number(step.getAttribute('data-slide-step')));
			else if (thumb) go(Number(thumb.getAttribute('data-slide-to')));
		});

		gallery.addEventListener('keydown', function (event) {
			// The video's own controls use the arrow keys for seeking.
			if (event.target.closest('video')) return;
			if (event.key === 'ArrowLeft') go(index - 1);
			else if (event.key === 'ArrowRight') go(index + 1);
		});

		var touchX = null;
		var touchY = null;

		stage.addEventListener('touchstart', function (event) {
			touchX = event.touches[0].clientX;
			touchY = event.touches[0].clientY;
		}, { passive: true });

		stage.addEventListener('touchend', function (event) {
			if (touchX === null) return;
			var dx = event.changedTouches[0].clientX - touchX;
			var dy = event.changedTouches[0].clientY - touchY;
			touchX = null;
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(index + (dx < 0 ? 1 : -1));
		});
	}

	function openSeries(anchor) {
		var template = templateFor(anchor, 'series-');
		if (!template) return false;

		closeSilently(productDialog, 'product');
		if (current.series === anchor && seriesDialog.open) return true;

		seriesDialog.replaceChildren(template.content.cloneNode(true));
		current.series = anchor;

		if (!seriesDialog.open) {
			seriesDialog.showModal();
			root.classList.add('has-products-modal');
		}

		seriesDialog.scrollTop = 0;
		focusClose(seriesDialog);
		return true;
	}

	function openProduct(anchor) {
		var template = templateFor(anchor, 'product-');
		if (!template) return false;

		var seriesAnchor = template.getAttribute('data-series');

		// The series window goes under the product one.
		if (current.series !== seriesAnchor || !seriesDialog.open) {
			if (!openSeries(seriesAnchor)) return false;
		}

		productDialog.replaceChildren(template.content.cloneNode(true));
		current.product = anchor;

		if (!productDialog.open) productDialog.showModal();

		productDialog.scrollTop = 0;
		initSlider(productDialog);
		focusClose(productDialog);
		return true;
	}

	// Cards: a series card on the page, a product card in the series window.
	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-series], [data-product]');
		if (!link || link.tagName !== 'A') return;

		// Let new-tab / new-window clicks follow the real URL.
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

		var isProduct = link.hasAttribute('data-product');
		var anchor = link.getAttribute(isProduct ? 'data-product' : 'data-series');

		function open() {
			if (!(isProduct ? openProduct(anchor) : openSeries(anchor))) return false;
			history.pushState({ lofuProducts: true }, '', '#' + anchor);
			return true;
		}

		if (!templatesPending()) {
			if (open()) event.preventDefault();
			return;
		}

		// Clicked before the templates arrived: open once they do, or follow
		// the series' URL if they can't be loaded.
		event.preventDefault();
		loadTemplates().then(function () {
			if (!open()) window.location.href = link.href;
		});
	});

	[seriesDialog, productDialog].forEach(function (dialog) {
		dialog.addEventListener('click', function (event) {
			if (event.target.closest('[data-modal-close]')) dialog.close();
		});
	});

	productDialog.addEventListener('close', function () {
		var closed = current.product;
		var wasSilent = silent.product;

		silent.product = false;
		// "close" fires a task later; the window may have been reopened since.
		if (productDialog.open) return;
		productDialog.replaceChildren();
		current.product = null;

		if (wasSilent) return;

		// Closed by the user (button / Esc): step the URL back to the series.
		if (hashAnchor() === closed) {
			if (isOwnEntry()) history.back();
			else history.replaceState(null, '', '#' + current.series);
		}

		focusIn(seriesDialog, closed);
	});

	seriesDialog.addEventListener('close', function () {
		var closed = current.series;
		var wasSilent = silent.series;

		silent.series = false;
		if (seriesDialog.open) return;
		closeSilently(productDialog, 'product');
		root.classList.remove('has-products-modal');
		seriesDialog.replaceChildren();
		current.series = null;

		if (wasSilent) return;

		if (hashAnchor() === closed) {
			if (isOwnEntry()) history.back();
			else history.replaceState(null, '', window.location.pathname + window.location.search);
		}

		focusIn(section, closed);
	});

	// Back / Forward, and plain hash links (e.g. the "contact us" button).
	window.addEventListener('popstate', function () {
		if (isWindowAnchor(hashAnchor()) && templatesPending()) {
			loadTemplates().then(onHistoryChange);
		} else {
			onHistoryChange();
		}
	});

	function onHistoryChange() {
		var anchor = hashAnchor();

		if (templateFor(anchor, 'product-')) {
			// Same product: e.g. Back just closed the photo viewer on top.
			if (anchor !== current.product) openProduct(anchor);
		} else if (templateFor(anchor, 'series-')) {
			if (anchor !== current.series || productDialog.open) {
				var closedProduct = current.product;
				openSeries(anchor);
				if (closedProduct) focusIn(seriesDialog, closedProduct);
			}
		} else if (seriesDialog.open) {
			var closedSeries = current.series;
			closeSilently(productDialog, 'product');
			closeSilently(seriesDialog, 'series');
			root.classList.remove('has-products-modal');
			// Back to the page itself: return focus to the card. For a link
			// to another section, leave the browser's scroll to it alone.
			if (!anchor) focusIn(section, closedSeries);
		}
	}

	// Arrived through a series / product URL (redirected here) or a shared link.
	var initial = hashAnchor();
	if (isWindowAnchor(initial)) {
		loadTemplates().then(function () {
			if (hashAnchor() !== initial || seriesDialog.open) return;
			if (!openProduct(initial)) openSeries(initial);
		});
	}
})();
