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

	/**
	 * Wraps each rendered line of a plain-text element in a mask + inner span.
	 * Lines are found by grouping words on their offsetTop, so this has to be
	 * redone whenever the element's width changes.
	 */
	function splitLines(el, text) {
		var words = text.trim().split(/\s+/);

		el.textContent = '';
		var spans = words.map(function (word, i) {
			var span = document.createElement('span');
			span.textContent = word;
			el.appendChild(span);
			if (i < words.length - 1) el.appendChild(document.createTextNode(' '));
			return span;
		});

		var lines = [];
		var lastTop = null;
		spans.forEach(function (span, i) {
			var top = span.offsetTop;
			if (top !== lastTop) {
				lines.push([]);
				lastTop = top;
			}
			lines[lines.length - 1].push(words[i]);
		});

		el.textContent = '';
		lines.forEach(function (lineWords, i) {
			var line = document.createElement('span');
			var inner = document.createElement('span');
			line.className = 'about__line';
			inner.className = 'about__line-inner';
			inner.style.setProperty('--line-index', i);
			inner.textContent = lineWords.join(' ');
			line.appendChild(inner);
			el.appendChild(line);
		});
	}

	function setupLines(el) {
		var text = el.textContent;
		var width = 0;
		var timer;

		function update() {
			if (el.clientWidth === width) return;
			width = el.clientWidth;
			splitLines(el, text);
		}

		update();
		// Line breaks change with the fallback → Montserrat swap and on resize.
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(function () {
				width = 0;
				update();
			});
		}
		window.addEventListener('resize', function () {
			clearTimeout(timer);
			timer = setTimeout(update, 150);
		});
	}

	section.querySelectorAll('.about__lines').forEach(setupLines);

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
