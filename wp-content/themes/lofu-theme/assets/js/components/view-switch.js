/**
 * Gallery view switcher (template-parts/components/view-switch.php).
 *
 * A click on a `.view-switch__button` sets its `data-view` on the element
 * named by its `aria-controls`. Delegated from the document, so it also
 * works for switchers added later (the project window's content is cloned
 * in on demand).
 */
(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.view-switch__button');
		if (!button) return;

		var target = document.getElementById(button.getAttribute('aria-controls'));
		if (!target) return;

		target.setAttribute('data-view', button.getAttribute('data-view'));
		target.scrollLeft = 0;

		var group = button.closest('.view-switch');
		group.querySelectorAll('.view-switch__button').forEach(function (other) {
			other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
		});

		target.dispatchEvent(new CustomEvent('lofu:viewchange', { bubbles: true }));
	});

	/*
	 * Slider view: drag with the mouse to scroll sideways. Touch and
	 * trackpads already scroll natively, so only mouse pointers are handled.
	 * Snapping is paused while dragging; on release the slider glides to the
	 * nearest card, carried a little by the drag's speed. A drag doesn't
	 * count as a click on the card under the pointer.
	 */
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var drag = null;

	function sliderFrom(el) {
		var slider = el.closest('[data-view="slider"]');
		// Only lists driven by a switcher (their id is some button's aria-controls).
		return slider && slider.id && document.querySelector('.view-switch__button[aria-controls="' + slider.id + '"]') ? slider : null;
	}

	function nearestStart(slider, left) {
		var base = slider.getBoundingClientRect().left - slider.scrollLeft;
		var best = 0;
		Array.prototype.forEach.call(slider.children, function (item) {
			var start = item.getBoundingClientRect().left - base;
			if (Math.abs(start - left) < Math.abs(best - left)) best = start;
		});
		return Math.min(best, slider.scrollWidth - slider.clientWidth);
	}

	document.addEventListener('pointerdown', function (event) {
		if (event.pointerType !== 'mouse' || event.button !== 0) return;
		var slider = sliderFrom(event.target);
		if (!slider || slider.scrollWidth <= slider.clientWidth) return;

		drag = {
			slider: slider,
			startX: event.clientX,
			startLeft: slider.scrollLeft,
			lastX: event.clientX,
			lastTime: event.timeStamp,
			velocity: 0,
			moved: false
		};
	});

	document.addEventListener('pointermove', function (event) {
		if (!drag) return;
		var dx = event.clientX - drag.startX;

		if (!drag.moved) {
			if (Math.abs(dx) < 5) return;
			drag.moved = true;
			drag.slider.classList.add('is-dragging');
		}

		var dt = event.timeStamp - drag.lastTime;
		if (dt > 0) drag.velocity = (event.clientX - drag.lastX) / dt;
		drag.lastX = event.clientX;
		drag.lastTime = event.timeStamp;

		drag.slider.scrollLeft = drag.startLeft - dx;
	});

	function swallowClick(event) {
		event.preventDefault();
		event.stopPropagation();
	}

	function endDrag(event) {
		if (!drag) return;
		var current = drag;
		drag = null;
		if (!current.moved) return;

		var slider = current.slider;
		// Only a release while still moving flings; a pause before letting go doesn't.
		var fling = event.timeStamp - current.lastTime < 100 ? current.velocity * 200 : 0;
		var target = nearestStart(slider, slider.scrollLeft - fling);

		slider.scrollTo({ left: target, behavior: reduceMotion ? 'auto' : 'smooth' });
		// Re-enable snapping once the glide has (about) finished.
		window.setTimeout(function () {
			slider.classList.remove('is-dragging');
		}, reduceMotion ? 0 : 450);

		// Swallow the click that follows this mouseup (it fires in the same
		// task), so a drag never opens the card it ended on.
		document.addEventListener('click', swallowClick, true);
		window.setTimeout(function () {
			document.removeEventListener('click', swallowClick, true);
		}, 0);
	}

	document.addEventListener('pointerup', endDrag);
	document.addEventListener('pointercancel', endDrag);

	// Stop the browser's own image/link drag from hijacking the gesture.
	document.addEventListener('dragstart', function (event) {
		if (sliderFrom(event.target)) event.preventDefault();
	});
})();
