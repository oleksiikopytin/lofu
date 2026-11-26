/**
 * Home page: concepts — an infinite one-image-at-a-time slider driven by the
 * section's own arrows and dots, plus swipe/drag and the arrow keys. The
 * counter, title and active dot follow the current slide (the counter and
 * title roll to their new values), and the section reveals itself on scroll
 * with a wipe across the stage.
 */
(function () {
	'use strict';

	var section = document.querySelector('.concepts');
	if (!section) return;

	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/**
	 * The slides' images are rendered with data-src/srcset/sizes (see
	 * lofu_defer_image_attributes()); set the real attributes so they load.
	 * Runs once the section is within half a screen of the viewport (so the
	 * first image is in by the time it shows), or on the first slide change
	 * or drag if that comes sooner. The wrap-around clones are made before
	 * this can run, so they are covered too.
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

	// Scroll reveal: head text slides up, the stage wipes in and the foot
	// (title + dots) follows it via CSS sibling selectors.
	function setupReveal() {
		if (reduceMotion || !('IntersectionObserver' in window)) return;

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

	var track = section.querySelector('.concepts__track');
	var rail = section.querySelector('.concepts__rail');
	var slides = rail ? Array.prototype.slice.call(rail.children) : [];
	var count = slides.length;

	// Clone the edge slides before the image observer can fire.
	if (count > 1) {
		var cloneLast = makeClone(slides[count - 1]);
		var cloneFirst = makeClone(slides[0]);
		rail.insertBefore(cloneLast, slides[0]);
		rail.appendChild(cloneFirst);
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

	setupReveal();

	if (count < 2) return;

	var SPEED = reduceMotion ? 0 : 800;
	var EASE = 'cubic-bezier(0.65, 0, 0.35, 1)';

	var dots = section.querySelectorAll('.concepts__dot');
	var counter = section.querySelector('.concepts__counter-current');
	var name = section.querySelector('.concepts__name-text');
	var titles = slides.map(function (slide) {
		return slide.getAttribute('data-title');
	});

	var current = 0; // Real slide index.
	var position = 1; // Index in the rail, which starts with the last slide's clone.
	var busy = false;
	var finishTimer = null;

	/**
	 * A copy of an edge slide placed at the other end of the rail, so moving
	 * past either end slides on instead of rewinding.
	 */
	function makeClone(slide) {
		var clone = slide.cloneNode(true);
		clone.setAttribute('aria-hidden', 'true');
		clone.removeAttribute('role');
		clone.removeAttribute('aria-roledescription');
		clone.removeAttribute('aria-label');
		clone.querySelectorAll('noscript').forEach(function (el) {
			el.remove();
		});
		return clone;
	}

	function setPosition(pos, animate, offset) {
		rail.style.transition = animate && SPEED ? 'transform ' + SPEED + 'ms ' + EASE : 'none';
		rail.style.transform = 'translateX(calc(' + (-pos * 100) + '% + ' + (offset || 0) + 'px))';
	}

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
		slides.forEach(function (slide, i) {
			if (i === index) slide.removeAttribute('aria-hidden');
			else slide.setAttribute('aria-hidden', 'true');
		});
		roll(counter, pad(index + 1));
		roll(name, titles[index]);
	}

	// After landing on a clone, jump to the real slide it copies.
	function finish() {
		if (!busy) return;
		window.clearTimeout(finishTimer);

		if (position === 0 || position === count + 1) {
			position = current + 1;
			setPosition(position, false);
			void rail.offsetWidth; // Commit the jump before the next transition.
		}
		busy = false;
	}

	/**
	 * Moves to slide `index`; -1 and `count` mean the clones past either end.
	 * Input is ignored while a move runs.
	 */
	function goTo(index) {
		if (busy) return;
		busy = true;

		current = (index + count) % count;
		position = index + 1;

		// Update the labels as the slide starts moving, not when it lands.
		loadDeferredImages();
		sync(current);
		setPosition(position, true);

		// Fallback in case transitionend doesn't fire (e.g. a hidden tab).
		finishTimer = window.setTimeout(finish, SPEED + 100);
	}

	function prev() {
		goTo(current - 1);
	}

	function next() {
		goTo(current + 1);
	}

	rail.addEventListener('transitionend', function (event) {
		if (event.target === rail && event.propertyName === 'transform') finish();
	});

	setPosition(position, false);
	sync(current);

	var prevArrow = section.querySelector('.concepts__arrow--prev');
	var nextArrow = section.querySelector('.concepts__arrow--next');

	if (prevArrow) prevArrow.addEventListener('click', prev);
	if (nextArrow) nextArrow.addEventListener('click', next);

	dots.forEach(function (dot, i) {
		dot.addEventListener('click', function () {
			if (i !== current) goTo(i);
		});
	});

	section.addEventListener('keydown', function (event) {
		if (event.key === 'ArrowLeft') prev();
		else if (event.key === 'ArrowRight') next();
		else return;
		event.preventDefault();
	});

	/**
	 * Swipe (touch) and drag (mouse/pen). `touch-action: pan-y` on the track
	 * leaves vertical scrolling to the browser; a mostly vertical gesture is
	 * dropped. A fifth of the width moves to the neighbour, less snaps back.
	 */
	var drag = null;

	track.addEventListener('dragstart', function (event) {
		event.preventDefault();
	});

	track.addEventListener('pointerdown', function (event) {
		if (busy || !event.isPrimary || event.button !== 0) return;
		drag = { id: event.pointerId, x: event.clientX, y: event.clientY, dx: 0, active: false };
	});

	track.addEventListener('pointermove', function (event) {
		if (!drag || event.pointerId !== drag.id) return;

		var dx = event.clientX - drag.x;

		if (!drag.active) {
			if (Math.abs(dx) < 6) return;
			if (Math.abs(event.clientY - drag.y) > Math.abs(dx)) {
				drag = null;
				return;
			}
			drag.active = true;
			track.setPointerCapture(drag.id);
			track.classList.add('is-dragging');
			loadDeferredImages();
		}

		drag.dx = dx;
		setPosition(position, false, dx);
	});

	function endDrag(event) {
		if (!drag || event.pointerId !== drag.id) return;

		var ended = drag;
		drag = null;
		if (!ended.active) return;

		track.classList.remove('is-dragging');

		var threshold = track.offsetWidth / 5;
		var dx = event.type === 'pointercancel' ? 0 : ended.dx;

		if (dx <= -threshold) next();
		else if (dx >= threshold) prev();
		else {
			// Snap back to the current slide.
			busy = true;
			setPosition(position, true);
			finishTimer = window.setTimeout(finish, SPEED + 100);
		}
	}

	track.addEventListener('pointerup', endDrag);
	track.addEventListener('pointercancel', endDrag);
})();
