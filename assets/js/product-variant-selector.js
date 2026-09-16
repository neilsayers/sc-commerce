/**
 * Swaps a variable product's image/price/description as the visitor
 * changes the variant <select> (template-functions.php's
 * scc_the_variant_selector()), and keeps the add-to-basket form's
 * hidden "variation" input pointed at whichever one is selected. All
 * variations' data is already on the page (data-scc-variations) — this
 * never makes a request, it just re-renders from that.
 *
 * Progressive enhancement only in one direction: without this file (or
 * before it loads), the page still renders and Add to basket still
 * works, it just always adds the first variation — the same one the
 * server-rendered preview shows. There's no plain-HTML way to make a
 * <select> alone update a sibling preview and hidden field, so a
 * variant change genuinely needs JS to take effect.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-scc-variant-selector]').forEach(function (container) {
			var select = container.querySelector('[data-scc-variant-select]');
			var image = container.querySelector('[data-scc-variant-image]');
			var price = container.querySelector('[data-scc-variant-price]');
			var description = container.querySelector('[data-scc-variant-description]');
			var form = container.querySelector('.scc-add-to-basket-form');
			var variationInput = form ? form.querySelector('input[name="variation"]') : null;
			var variations;

			try {
				variations = JSON.parse(container.getAttribute('data-scc-variations'));
			} catch (e) {
				return;
			}

			if (!select || !Array.isArray(variations)) {
				return;
			}

			select.addEventListener('change', function () {
				var variation = variations[parseInt(select.value, 10)];

				if (!variation) {
					return;
				}

				if (image) {
					if (variation.image) {
						image.src = variation.image;
						image.style.display = '';
					} else {
						image.src = '';
						image.style.display = 'none';
					}
				}

				if (price) {
					price.textContent = variation.price_formatted;
				}

				if (description) {
					description.textContent = variation.description || '';
					description.style.display = variation.description ? '' : 'none';
				}

				if (variationInput) {
					variationInput.value = variation.index;
				}

				if (form) {
					form.setAttribute('data-scc-variation', variation.index);
				}
			});
		});
	});
})();
