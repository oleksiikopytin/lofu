/**
 * Home page: the order form window (`#order-modal`, order-modal.php).
 *
 * A product window's "contact us about an order" link
 * (`[data-order-product="{name}"]`) opens it instead of scrolling to the
 * contacts section, shows the product's name in the heading and puts it in
 * the form's `product` field. Without this window the link keeps its href.
 */
(function () {
	'use strict';

	var dialog = document.getElementById('order-modal');
	if (!dialog || typeof dialog.showModal !== 'function') return;

	var nameEl = dialog.querySelector('[data-order-product-name]');
	var form = dialog.querySelector('form');
	var product = '';
	var opener = null;

	function fillProduct() {
		dialog.querySelectorAll('[name="product"]').forEach(function (field) {
			field.value = product;
		});
	}

	function open(link) {
		product = link.getAttribute('data-order-product') || '';
		opener = link;

		// Start clean after a sent message (Contact Form 7 keeps showing it).
		if (form && form.getAttribute('data-status') === 'sent' && window.wpcf7 && typeof window.wpcf7.reset === 'function') {
			window.wpcf7.reset(form);
		}

		if (nameEl) nameEl.textContent = product;
		fillProduct();
		dialog.showModal();
		dialog.scrollTop = 0;

		var first = dialog.querySelector('.wpcf7-form-control:not([type="hidden"])');
		if (first) first.focus({ preventScroll: true });
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('[data-order-product]');
		if (!link) return;
		if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

		event.preventDefault();
		open(link);
	});

	dialog.addEventListener('click', function (event) {
		// The close button, or a click on the backdrop around the panel.
		if (event.target.closest('[data-order-close]') || event.target === dialog) dialog.close();
	});

	// Contact Form 7 clears the field when it resets the form after sending;
	// fill it again just before every submit (capture runs before its handler).
	dialog.addEventListener('submit', fillProduct, true);

	dialog.addEventListener('close', function () {
		if (opener && opener.isConnected) opener.focus({ preventScroll: true });
		opener = null;
	});

	// The product window went away under it (Back, a hash link).
	var productDialog = document.getElementById('product-modal');
	if (productDialog) {
		productDialog.addEventListener('close', function () {
			if (dialog.open) dialog.close();
		});
	}
})();
