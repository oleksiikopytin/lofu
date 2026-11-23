/**
 * Header: mobile burger menu.
 */
(function () {
	'use strict';

	var header = document.querySelector('.header');
	if (!header) return;

	var burger = header.querySelector('.header__burger');
	var nav = header.querySelector('.header__nav');
	if (!burger || !nav) return;

	var desktop = window.matchMedia('(min-width: 1024px)');

	function setOpen(open) {
		burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		header.classList.toggle('header--nav-open', open);
		document.documentElement.classList.toggle('header-nav-open', open);
	}

	function isOpen() {
		return burger.getAttribute('aria-expanded') === 'true';
	}

	burger.addEventListener('click', function () {
		setOpen(!isOpen());
	});

	// Close after following an anchor link so the target section is visible.
	nav.addEventListener('click', function (e) {
		if (e.target.closest('a')) setOpen(false);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && isOpen()) {
			setOpen(false);
			burger.focus();
		}
	});

	desktop.addEventListener('change', function (e) {
		if (e.matches) setOpen(false);
	});
})();
