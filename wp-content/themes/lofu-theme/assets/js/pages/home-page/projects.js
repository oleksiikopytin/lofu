/**
 * Home page: projects — scroll reveal of the cards, and the project window.
 *
 * Each project's details sit in a <template id="project-{slug}-modal">.
 * Clicking a card (or a previous/next link inside the window) clones that
 * template into the `#project-modal` dialog, and the URL hash follows the
 * open project, so `/#project-{slug}` (where single project URLs redirect)
 * opens it on load. Opening from a card adds one history entry, so Back
 * closes the window; previous/next only replace it.
 */
(function () {
	'use strict';

	var section = document.querySelector('.projects');
	if (!section) return;

	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Scroll reveal: the title slides up from its mask, cards rise in.
	if (!reduceMotion && 'IntersectionObserver' in window) {
		var items = section.querySelectorAll('.projects__item');
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				entry.target.classList.add('is-visible');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

		section.classList.add('projects--reveal');
		section.querySelectorAll('.projects__head, .projects__item').forEach(function (el) {
			observer.observe(el);
		});

		// Cards off to the side in the slider view would never intersect,
		// so after any view change just show them all.
		section.addEventListener('lofu:viewchange', function () {
			items.forEach(function (item) {
				item.classList.add('is-visible');
			});
			observer.disconnect();
		});
	}

	var dialog = document.getElementById('project-modal');
	if (!dialog || typeof dialog.showModal !== 'function') return;

	var root = document.documentElement;
	var current = null;
	var openedWithPush = false;
	var closingFromHistory = false;
	var focusAfterPop = null;

	function hashAnchor() {
		var hash = window.location.hash.slice(1);
		try {
			return decodeURIComponent(hash);
		} catch (e) {
			return hash;
		}
	}

	function templateFor(anchor) {
		if (!anchor || anchor.indexOf('project-') !== 0) return null;
		var template = document.getElementById(anchor + '-modal');
		return template && template.content ? template : null;
	}

	function focusCard(anchor) {
		var card = anchor && document.getElementById(anchor);
		if (card) card.focus();
	}

	// Task / solution text: revealed line by line as it scrolls into view
	// inside the dialog, like the about section's intro.
	var lineSplits = [];
	var lineObserver = null;

	function teardownLines() {
		lineSplits.forEach(function (split) {
			split.destroy();
		});
		lineSplits = [];
		if (lineObserver) lineObserver.disconnect();
		lineObserver = null;
	}

	// Needs the dialog open: lines are measured from the laid-out text.
	function setupLines() {
		teardownLines();
		if (reduceMotion || !window.lofu || !window.lofu.splitLines || !('IntersectionObserver' in window)) return;

		var texts = dialog.querySelectorAll('.project-modal__text');
		if (!texts.length) return;

		lineObserver = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				entry.target.classList.add('is-visible');
				lineObserver.unobserve(entry.target);
			});
		}, { root: dialog, rootMargin: '0px 0px -10% 0px', threshold: 0.15 });

		texts.forEach(function (el) {
			lineSplits.push(window.lofu.splitLines(el, {
				lineClass: 'project-modal__line',
				innerClass: 'project-modal__line-inner'
			}));
			el.classList.add('project-modal__text--lines');
			lineObserver.observe(el);
		});
	}

	/**
	 * Fills the dialog with a project's details and opens it if needed.
	 * Returns false when there is no such project on the page.
	 */
	function show(anchor) {
		var template = templateFor(anchor);
		if (!template) return false;

		var content = template.content.cloneNode(true);
		var switching = dialog.open;

		if (switching) {
			var body = content.querySelector('.project-modal__body');
			if (body) body.classList.add('is-entering');
		}

		dialog.replaceChildren(content);
		current = anchor;

		if (!switching) {
			dialog.showModal();
			root.classList.add('has-project-modal');
		}

		dialog.scrollTop = 0;
		setupLines();

		// The previously focused element was just replaced; keep focus inside.
		var close = dialog.querySelector('[data-modal-close]');
		if (close) close.focus({ preventScroll: true });

		return true;
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-project]');
		if (!link) return;

		// Let new-tab / new-window clicks follow the real URL.
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

		var anchor = link.getAttribute('data-project');
		var wasOpen = dialog.open;
		if (!show(anchor)) return;

		event.preventDefault();

		if (wasOpen) {
			history.replaceState(null, '', '#' + anchor);
		} else {
			history.pushState(null, '', '#' + anchor);
			openedWithPush = true;
		}
	});

	dialog.addEventListener('click', function (event) {
		if (event.target.closest('[data-modal-close]')) dialog.close();
	});

	dialog.addEventListener('close', function () {
		var closed = current;

		root.classList.remove('has-project-modal');
		teardownLines();
		dialog.replaceChildren();
		current = null;

		if (closingFromHistory) {
			closingFromHistory = false;
		} else if (templateFor(hashAnchor())) {
			if (openedWithPush) {
				// Drop our history entry; Back no longer reopens the window.
				// Focus the card once the browser has restored the scroll.
				focusAfterPop = closed;
				history.back();
			} else {
				history.replaceState(null, '', window.location.pathname + window.location.search);
			}
		}

		openedWithPush = false;

		if (!focusAfterPop) focusCard(closed);
	});

	// Back / Forward, and plain "#project-…" links elsewhere on the page.
	window.addEventListener('popstate', function () {
		var anchor = hashAnchor();

		if (templateFor(anchor)) {
			// Same project: e.g. Back just closed the photo viewer on top
			// (same URL), so our own history entry is still in place.
			if (anchor !== current) {
				show(anchor);
				openedWithPush = false;
			}
		} else if (dialog.open) {
			closingFromHistory = true;
			dialog.close();
		}

		if (focusAfterPop) {
			focusCard(focusAfterPop);
			focusAfterPop = null;
		}
	});

	// Arrived through a project URL (redirected here) or a shared link.
	show(hashAnchor());
})();
