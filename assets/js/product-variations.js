(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var typeSelect = document.getElementById('scc_type');
		var simpleFields = document.getElementById('scc-simple-fields');
		var variableFields = document.getElementById('scc-variable-fields');
		var table = document.getElementById('scc-variations-table');
		var addButton = document.getElementById('scc-add-variation');

		if (!typeSelect || !table || !addButton) {
			return;
		}

		function toggleFields() {
			var isVariable = typeSelect.value === 'variable';
			simpleFields.style.display = isVariable ? 'none' : '';
			variableFields.style.display = isVariable ? '' : 'none';
		}

		typeSelect.addEventListener('change', toggleFields);
		toggleFields();

		addButton.addEventListener('click', function () {
			var tbody = table.querySelector('tbody');
			var index = tbody.querySelectorAll('.scc-variation-row').length;
			var row = document.createElement('tr');
			row.className = 'scc-variation-row';
			row.innerHTML =
				'<td><div class="scc-variation-image">' +
					'<img class="scc-variation-image-preview" style="display:none;" alt="">' +
					'<input type="hidden" class="scc-variation-image-id" name="scc_variations[' + index + '][image_id]" value="">' +
					'<button type="button" class="button scc-variation-choose-image">Choose image</button> ' +
					'<button type="button" class="button scc-variation-remove-image" style="display:none;">Remove image</button>' +
				'</div></td>' +
				'<td><input type="text" name="scc_variations[' + index + '][label]" placeholder="e.g. Large / Blue"></td>' +
				'<td><input type="number" step="0.01" min="0" name="scc_variations[' + index + '][price]"></td>' +
				'<td><input type="text" name="scc_variations[' + index + '][sku]"></td>' +
				'<td><textarea rows="2" name="scc_variations[' + index + '][description]" placeholder="Shown on the product page when this variant is selected"></textarea></td>' +
				'<td><button type="button" class="button scc-remove-variation">Remove</button></td>';
			tbody.appendChild(row);
		});

		table.addEventListener('click', function (event) {
			if (event.target.classList.contains('scc-remove-variation')) {
				event.target.closest('tr').remove();
				return;
			}

			if (event.target.classList.contains('scc-variation-choose-image')) {
				event.preventDefault();
				openImagePicker(event.target.closest('.scc-variation-image'));
				return;
			}

			if (event.target.classList.contains('scc-variation-remove-image')) {
				event.preventDefault();
				setVariationImage(event.target.closest('.scc-variation-image'), null);
			}
		});

		// wp.media is core's own image picker modal — this plugin doesn't
		// build a custom uploader, just wires the result into the row
		// that opened it.
		function openImagePicker(container) {
			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var frame = wp.media({
				title: 'Select variation image',
				multiple: false,
				library: { type: 'image' },
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				setVariationImage(container, attachment);
			});

			frame.open();
		}

		function setVariationImage(container, attachment) {
			var img = container.querySelector('.scc-variation-image-preview');
			var input = container.querySelector('.scc-variation-image-id');
			var chooseButton = container.querySelector('.scc-variation-choose-image');
			var removeButton = container.querySelector('.scc-variation-remove-image');

			if (!attachment) {
				img.src = '';
				img.style.display = 'none';
				input.value = '';
				chooseButton.textContent = 'Choose image';
				removeButton.style.display = 'none';

				return;
			}

			var preview = (attachment.sizes && (attachment.sizes.medium || attachment.sizes.thumbnail || attachment.sizes.full)) || attachment;

			img.src = preview.url;
			img.style.display = '';
			input.value = attachment.id;
			chooseButton.textContent = 'Change image';
			removeButton.style.display = '';
		}
	});
})();
