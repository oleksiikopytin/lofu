/**
 * Splits a plain-text element into its rendered lines, each wrapped in a
 * mask + inner span, so CSS can slide the lines in one after another
 * (used by the about section and the project window).
 *
 *   var split = window.lofu.splitLines(el, {
 *     lineClass: 'about__line',
 *     innerClass: 'about__line-inner'
 *   });
 *   split.destroy(); // stop re-splitting (e.g. before el is removed)
 *
 * Each inner span gets `--line-index` (0, 1, 2…) for staggered delays.
 * Lines are found by grouping words on their offsetTop, so el must be laid
 * out (visible) when this is called. It re-splits whenever el's width
 * changes and once the web font has loaded, since both move line breaks.
 */
(function () {
	'use strict';

	function split(el, text, options) {
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
			line.className = options.lineClass;
			inner.className = options.innerClass;
			inner.style.setProperty('--line-index', i);
			inner.textContent = lineWords.join(' ');
			line.appendChild(inner);
			el.appendChild(line);
		});
	}

	function splitLines(el, options) {
		var text = el.textContent;
		var width = 0;
		var destroyed = false;
		var timer;

		function update() {
			if (destroyed || !el.clientWidth || el.clientWidth === width) return;
			width = el.clientWidth;
			split(el, text, options);
		}

		function onResize() {
			clearTimeout(timer);
			timer = setTimeout(update, 150);
		}

		update();

		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(function () {
				width = 0;
				update();
			});
		}

		var resizeObserver = null;
		if ('ResizeObserver' in window) {
			resizeObserver = new ResizeObserver(onResize);
			resizeObserver.observe(el);
		} else {
			window.addEventListener('resize', onResize);
		}

		return {
			destroy: function () {
				destroyed = true;
				clearTimeout(timer);
				if (resizeObserver) resizeObserver.disconnect();
				else window.removeEventListener('resize', onResize);
			}
		};
	}

	window.lofu = window.lofu || {};
	window.lofu.splitLines = splitLines;
})();
