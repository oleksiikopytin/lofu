/**
 * Full-screen photo viewer (template-parts/components/photo-viewer.php).
 *
 * A click on a `[data-photo-src]` button opens its full, uncropped photo;
 * prev/next (buttons, ← →, swipe) step through the buttons in the same
 * `[data-photo-group]`, wrapping around. Each button carries
 * data-photo-src / -srcset / -alt and an optional -caption. Delegated from the document, so it
 * works for content cloned in later (the project window).
 *
 * The URL doesn't change, but opening adds a history entry so Back closes
 * the viewer before anything else (e.g. the project window under it).
 */
(function () {
	'use strict';

	var viewer = document.getElementById('photo-viewer');
	if (!viewer || typeof viewer.showModal !== 'function') return;

	var root = document.documentElement;
	var stage = viewer.querySelector('.photo-viewer__stage');
	var image = viewer.querySelector('.photo-viewer__image');
	var caption = viewer.querySelector('.photo-viewer__caption-text');
	var counter = viewer.querySelector('.photo-viewer__counter');
	var arrows = viewer.querySelectorAll('.photo-viewer__arrow');

	var photos = [];
	var index = 0;
	var pushedHistory = false;
	var closingFromHistory = false;
	var swipe = null;
	var suppressClickUntil = 0;

	function setSource(img, button) {
		// srcset before src, so the browser picks from the set straight away.
		img.sizes = '100vw';
		if (button.dataset.photoSrcset) img.srcset = button.dataset.photoSrcset;
		else img.removeAttribute('srcset');
		img.src = button.dataset.photoSrc;
	}

	function show(i) {
		index = (i + photos.length) % photos.length;
		var button = photos[index];

		viewer.classList.add('is-loading');
		image.alt = button.dataset.photoAlt || '';
		setSource(image, button);
		if (image.complete && image.naturalWidth) viewer.classList.remove('is-loading');

		// The media library caption, if any (alt text is for screen readers).
		caption.textContent = button.dataset.photoCaption || '';
		counter.textContent = photos.length > 1 ? (index + 1) + ' / ' + photos.length : '';

		// Warm up the next photo so stepping forward feels instant.
		if (photos.length > 1) setSource(new Image(), photos[(index + 1) % photos.length]);
	}

	function step(delta) {
		if (photos.length > 1) show(index + delta);
	}

	image.addEventListener('load', function () {
		viewer.classList.remove('is-loading');
	});
	image.addEventListener('error', function () {
		viewer.classList.remove('is-loading');
	});

	function open(button) {
		var group = button.closest('[data-photo-group]');
		photos = group ? Array.prototype.slice.call(group.querySelectorAll('[data-photo-src]')) : [button];

		arrows.forEach(function (arrow) {
			arrow.hidden = photos.length < 2;
		});

		show(photos.indexOf(button));
		viewer.showModal();
		root.classList.add('has-photo-viewer');

		history.pushState({ lofuPhotoViewer: true }, '', window.location.href);
		pushedHistory = true;
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-photo-src]');
		if (!button || !button.dataset.photoSrc) return;
		event.preventDefault();
		open(button);
	});

	viewer.addEventListener('click', function (event) {
		if (event.target.closest('.photo-viewer__close')) {
			viewer.close();
		} else if (event.target.closest('.photo-viewer__arrow--prev')) {
			step(-1);
		} else if (event.target.closest('.photo-viewer__arrow--next')) {
			step(1);
		} else if ((event.target === stage || event.target === viewer) && event.timeStamp > suppressClickUntil) {
			// A click on the dark area around the photo.
			viewer.close();
		}
	});

	viewer.addEventListener('keydown', function (event) {
		if (event.key === 'ArrowLeft') step(-1);
		else if (event.key === 'ArrowRight') step(1);
		else return;
		event.preventDefault();
	});

	// Swipe (touch / pen): a mostly horizontal move of 50px+ steps.
	stage.addEventListener('pointerdown', function (event) {
		if (event.pointerType === 'mouse') return;
		swipe = { x: event.clientX, y: event.clientY };
	});

	stage.addEventListener('pointerup', function (event) {
		if (!swipe) return;
		var dx = event.clientX - swipe.x;
		var dy = event.clientY - swipe.y;
		swipe = null;

		if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) return;
		step(dx < 0 ? 1 : -1);
		// Don't let the swipe's trailing click close the viewer.
		suppressClickUntil = event.timeStamp + 400;
	});

	stage.addEventListener('pointercancel', function () {
		swipe = null;
	});

	var focusAfterPop = null;

	// Back to the photo that was last shown (scrolls it into view).
	function focusPhoto(button) {
		if (button && button.isConnected) button.focus();
	}

	viewer.addEventListener('close', function () {
		root.classList.remove('has-photo-viewer');
		var button = photos[index];

		if (pushedHistory && !closingFromHistory) {
			// Returning to the previous entry makes the browser jump to its
			// #hash, which moves focus away; focus once that has happened.
			focusAfterPop = button;
			history.back();
		} else {
			focusPhoto(button);
		}

		pushedHistory = false;
		closingFromHistory = false;
	});

	window.addEventListener('popstate', function () {
		if (viewer.open) {
			closingFromHistory = true;
			viewer.close();
			return;
		}

		if (focusAfterPop) {
			var button = focusAfterPop;
			focusAfterPop = null;
			setTimeout(function () {
				focusPhoto(button);
			}, 0);
		}
	});
})();
