/**
 * Progressive enhancement for the add-to-basket form
 * (template-functions.php's scc_add_to_basket_button): intercepts the
 * submit and posts to the REST basket endpoint instead, so adding an
 * item updates the on-page basket count without a full navigation.
 * Without this file (or with JS disabled) the form still works via
 * its plain admin-post.php action — see Frontend\BasketFormController.
 *
 * Expects a global `sccCommerce` object with `restUrl` and `nonce`,
 * localized onto this script by Frontend\Assets.
 */
(function () {
	'use strict';

	if (typeof window.sccCommerce === 'undefined') {
		return;
	}

	function updateCountDisplays(count) {
		document.querySelectorAll('[data-scc-basket-count]').forEach(function (el) {
			el.textContent = count;
		});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form.classList || !form.classList.contains('scc-add-to-basket-form')) {
			return;
		}

		event.preventDefault();

		var formData = new FormData(form);
		var body = {
			product_id: parseInt(formData.get('product_id'), 10),
			quantity: parseInt(formData.get('quantity'), 10) || 1,
		};

		if (formData.get('variation') !== null && formData.get('variation') !== '') {
			body.variation = parseInt(formData.get('variation'), 10);
		}

		fetch(window.sccCommerce.restUrl + 'basket/add', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.sccCommerce.nonce,
			},
			body: JSON.stringify(body),
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				updateCountDisplays(data.item_count);
			})
			.catch(function () {
				form.submit(); // fall back to the plain admin-post.php round trip
			});
	});
})();
