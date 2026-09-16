/**
 * Polls GET scc/v1/orders/{id}/status for the "Payment Pending" box
 * scc_the_checkout_notices() renders, so it flips to "Paid" (or
 * whatever PayPal's IPN eventually sets it to) on its own — no manual
 * refresh needed. Pure progressive enhancement: the box already shows
 * the real status as of page load either way, this just keeps it
 * current without one.
 *
 * Expects the same global `sccCommerce` object (restUrl) basket.js
 * uses, localized by Frontend\Assets.
 */
(function () {
	'use strict';

	if (typeof window.sccCommerce === 'undefined') {
		return;
	}

	var POLL_INTERVAL_MS = 3000;
	var MAX_ATTEMPTS = 40; // ~2 minutes — long enough for a normal IPN round trip, not forever if one never arrives

	document.addEventListener('DOMContentLoaded', function () {
		var box = document.querySelector('[data-scc-order-status][data-poll="1"]');

		if (!box) {
			return;
		}

		var orderId = box.getAttribute('data-order-id');
		var label = box.querySelector('[data-scc-status-label]');
		var notice = box.querySelector('[data-scc-pending-notice]');
		var attempts = 0;

		var timer = setInterval(function () {
			attempts += 1;

			if (attempts > MAX_ATTEMPTS) {
				clearInterval(timer);

				return;
			}

			fetch(window.sccCommerce.restUrl + 'orders/' + orderId + '/status')
				.then(function (response) {
					return response.ok ? response.json() : null;
				})
				.then(function (data) {
					if (!data || data.status === box.getAttribute('data-status')) {
						return;
					}

					box.setAttribute('data-status', data.status);

					if (label) {
						label.textContent = data.status_label;
					}

					if (data.status !== 'payment_pending') {
						if (notice) {
							notice.remove();
						}

						clearInterval(timer);
					}
				})
				.catch(function () {
					// A single failed poll isn't fatal — just try again next interval, up to MAX_ATTEMPTS.
				});
		}, POLL_INTERVAL_MS);
	});
})();
