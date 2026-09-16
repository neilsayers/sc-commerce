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
				'<td><input type="text" name="scc_variations[' + index + '][label]" placeholder="e.g. Large / Blue"></td>' +
				'<td><input type="number" step="0.01" min="0" name="scc_variations[' + index + '][price]"></td>' +
				'<td><input type="text" name="scc_variations[' + index + '][sku]"></td>' +
				'<td><button type="button" class="button scc-remove-variation">Remove</button></td>';
			tbody.appendChild(row);
		});

		table.addEventListener('click', function (event) {
			if (event.target.classList.contains('scc-remove-variation')) {
				event.target.closest('tr').remove();
			}
		});
	});
})();
