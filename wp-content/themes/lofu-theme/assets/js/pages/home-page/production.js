/**
 * Home page: production — scroll reveal of the tiles, and the video player.
 *
 * Photo tiles are handled by the shared photo viewer (photo-viewer.js).
 * A click on a `[data-video-src]` tile opens the `#production-video` dialog
 * and starts the video there. Without JS (or <dialog>) the tile is a plain
 * link to the video file. Opening adds a history entry so Back closes the
 * player, like the photo viewer.
 */
(function () {
	'use strict';

	var section = document.querySelector('.production');
	if (!section) return;

	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Scroll reveal: the title slides up from its mask, tiles rise in.
	if (!reduceMotion && 'IntersectionObserver' in window) {
		var items = section.querySelectorAll('.production__item');
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				entry.target.classList.add('is-visible');
				observer.unobserve(entry.target);
			});
		}, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

		section.classList.add('production--reveal');
		section.querySelectorAll('.production__head, .production__item').forEach(function (el) {
			observer.observe(el);
		});

		// Tiles off to the side in the slider view would never intersect,
		// so after any view change just show them all.
		section.addEventListener('lofu:viewchange', function () {
			items.forEach(function (item) {
				item.classList.add('is-visible');
			});
			observer.disconnect();
		});
	}

	var dialog = document.getElementById('production-video');
	if (!dialog || typeof dialog.showModal !== 'function') return;

	var root = document.documentElement;
	var stage = dialog.querySelector('.production-video__stage');
	var player = dialog.querySelector('.production-video__player');
	var caption = dialog.querySelector('.production-video__caption');
	var opener = null;
	var pushedHistory = false;
	var closingFromHistory = false;
	var focusAfterPop = null;

	// The main file (usually WebM), or the MP4 fallback where it can't play.
	function videoSrc(tile) {
		var type = tile.dataset.videoType;
		var fallback = tile.dataset.videoFallback;
		if (fallback && type && !player.canPlayType(type)) return fallback;
		return tile.dataset.videoSrc;
	}

	function open(tile) {
		opener = tile;
		caption.textContent = tile.dataset.videoCaption || '';
		player.setAttribute('aria-label', tile.getAttribute('aria-label') || '');
		if (tile.dataset.videoPoster) player.poster = tile.dataset.videoPoster;
		else player.removeAttribute('poster');
		player.src = videoSrc(tile);

		dialog.showModal();
		root.classList.add('has-production-video');

		// Started by the click, so playback with sound is allowed.
		var playing = player.play();
		if (playing && typeof playing.catch === 'function') playing.catch(function () {});

		history.pushState({ lofuProductionVideo: true }, '', window.location.href);
		pushedHistory = true;
	}

	section.addEventListener('click', function (event) {
		var tile = event.target.closest('[data-video-src]');
		if (!tile || !tile.dataset.videoSrc) return;
		event.preventDefault();
		open(tile);
	});

	dialog.addEventListener('click', function (event) {
		if (event.target.closest('.production-video__close') || event.target === stage || event.target === dialog) {
			dialog.close();
		}
	});

	function focusOpener(tile) {
		if (tile && tile.isConnected) tile.focus();
	}

	dialog.addEventListener('close', function () {
		root.classList.remove('has-production-video');

		// Stop the download as well as playback.
		player.pause();
		player.removeAttribute('src');
		player.load();

		if (pushedHistory && !closingFromHistory) {
			// Going back can move focus (hash jump); refocus once it has.
			focusAfterPop = opener;
			history.back();
		} else {
			focusOpener(opener);
		}

		pushedHistory = false;
		closingFromHistory = false;
	});

	window.addEventListener('popstate', function () {
		if (dialog.open) {
			closingFromHistory = true;
			dialog.close();
			return;
		}

		if (focusAfterPop) {
			var tile = focusAfterPop;
			focusAfterPop = null;
			setTimeout(function () {
				focusOpener(tile);
			}, 0);
		}
	});
})();
