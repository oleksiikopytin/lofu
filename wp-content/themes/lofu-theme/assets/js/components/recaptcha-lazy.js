/**
 * Lazy-loads Google reCAPTCHA v3 for Contact Form 7.
 *
 * Replaces CF7's own reCAPTCHA scripts (dequeued in lib/scripts.php): api.js is
 * loaded on the first interaction with a CF7 form, and every submit fetches a
 * fresh token before handing off to CF7's submit.
 */
(function (config) {
	if (!config || !config.sitekey || !config.src) return;

	var loader = null;

	function load() {
		if (loader) return loader;

		loader = new Promise(function (resolve, reject) {
			var script = document.createElement('script');

			script.src = config.src;
			script.async = true;
			script.onload = function () {
				window.grecaptcha.ready(resolve);
			};
			script.onerror = function () {
				script.remove();
				loader = null;
				reject(new Error('reCAPTCHA failed to load'));
			};
			document.head.appendChild(script);
		});

		return loader;
	}

	function refreshToken() {
		return load()
			.then(function () {
				return window.grecaptcha.execute(config.sitekey, { action: config.action });
			})
			.then(function (token) {
				document.querySelectorAll('form.wpcf7-form input[name="_wpcf7_recaptcha_response"]').forEach(function (input) {
					input.value = token;
				});
			});
	}

	function warm(event) {
		if (event.target && event.target.closest && event.target.closest('form.wpcf7-form')) {
			load().catch(function () {});
		}
	}

	['focusin', 'pointerdown', 'touchstart'].forEach(function (type) {
		document.addEventListener(type, warm, { passive: true, capture: true });
	});

	// This script loads after CF7's, so CF7's DOMContentLoaded handler (which defines wpcf7.submit) runs first.
	document.addEventListener('DOMContentLoaded', function () {
		if (typeof window.wpcf7 === 'undefined' || typeof window.wpcf7.submit !== 'function') return;

		var submit = window.wpcf7.submit;

		window.wpcf7.submit = function (form, options) {
			refreshToken()
				.catch(function (error) {
					console.error(error);
				})
				.then(function () {
					submit(form, options || {});
				});
		};
	});
})(window.lofuRecaptcha);
