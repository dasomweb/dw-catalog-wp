/**
 * DW Catalog — admin UI behaviour (no dependencies).
 *
 * data-dwc-add="x"        clone <template data-dwc-template="x"> into table[data-dwc-rows="x"]
 * data-dwc-remove         remove the row
 * data-dwc-up / -down     move the row (or image) up / down — form order = saved order
 * data-dwc-checkall       toggle all row checkboxes
 * data-dwc-confirm="msg"  confirm before submitting
 * data-dwc-preset="g.k"   business-type preset: switch field + variation column set
 */
(function () {
	'use strict';

	var seq = 0;

	function rowsBody(name) {
		var table = document.querySelector('[data-dwc-rows="' + name + '"]');
		return table ? table.tBodies[0] : null;
	}

	function newRow(name) {
		var tpl = document.querySelector('template[data-dwc-template="' + name + '"]');
		if (!tpl) {
			return null;
		}
		var html = tpl.innerHTML.replace(/__i__/g, 'n' + Date.now().toString(36) + (seq++));
		var holder = document.createElement('tbody');
		holder.innerHTML = html.trim();
		return holder.firstElementChild;
	}

	document.addEventListener('click', function (e) {
		var t = e.target.closest('[data-dwc-add],[data-dwc-remove],[data-dwc-up],[data-dwc-down],[data-dwc-preset]');
		if (!t) {
			return;
		}
		if (t.hasAttribute('data-dwc-add')) {
			var name = t.getAttribute('data-dwc-add');
			var row = newRow(name);
			var body = rowsBody(name);
			if (row && body) {
				body.appendChild(row);
				var first = row.querySelector('input[type=text],input:not([type]),select');
				if (first) {
					first.focus();
				}
			}
		} else if (t.hasAttribute('data-dwc-remove')) {
			t.closest('tr').remove();
		} else if (t.hasAttribute('data-dwc-up') || t.hasAttribute('data-dwc-down')) {
			var item = t.closest('tr, li');
			if (t.hasAttribute('data-dwc-up') && item.previousElementSibling) {
				item.parentNode.insertBefore(item, item.previousElementSibling);
			} else if (t.hasAttribute('data-dwc-down') && item.nextElementSibling) {
				item.parentNode.insertBefore(item.nextElementSibling, item);
			}
		} else if (t.hasAttribute('data-dwc-preset')) {
			applyPreset(t);
		}
	});

	document.addEventListener('change', function (e) {
		if (e.target.matches('[data-dwc-checkall]')) {
			var form = e.target.closest('form');
			form.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
				cb.checked = e.target.checked;
			});
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target.matches('.dwc-color')) {
			var sw = e.target.nextElementSibling;
			if (sw && sw.classList.contains('dwc-swatch')) {
				sw.style.background = e.target.value;
			}
		}
	});

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-dwc-confirm]');
		if (btn && !window.confirm(btn.getAttribute('data-dwc-confirm'))) {
			e.preventDefault();
		}
	}, true);

	// ── Presets ────────────────────────────────────────────────

	var colsSnapshot = null;

	function keysIn(name, input) {
		var body = rowsBody(name);
		if (!body) {
			return [];
		}
		return Array.prototype.map.call(body.querySelectorAll('input[name$="[' + input + ']"]'), function (i) {
			return i.value.trim();
		}).filter(Boolean);
	}

	function fill(row, values) {
		Object.keys(values).forEach(function (k) {
			var el = row.querySelector('[name$="[' + k + ']"]:not([type=hidden])');
			if (!el) {
				return;
			}
			if (el.type === 'checkbox') {
				el.checked = !!values[k];
			} else {
				el.value = values[k];
			}
		});
		row.classList.add('dwc-added');
	}

	function applyPreset(btn) {
		var box = btn.closest('[data-dwc-presets]');
		var presets = JSON.parse(box.getAttribute('data-dwc-presets'));
		var defaults = JSON.parse(box.getAttribute('data-dwc-default-cols'));
		var parts = btn.getAttribute('data-dwc-preset').split('.');
		var preset = presets[parts[0]].presets[parts[1]];
		var fieldsBody = rowsBody('fields');
		var colsBody = rowsBody('cols');
		var result = box.querySelector('[data-dwc-preset-result]');
		var wasActive = btn.classList.contains('dwc-btn-primary');

		if (colsSnapshot === null) {
			colsSnapshot = colsBody.innerHTML;
		}
		// Undo the previous pick (unsaved rows only)
		fieldsBody.querySelectorAll('tr.dwc-added').forEach(function (tr) { tr.remove(); });
		colsBody.innerHTML = colsSnapshot;
		box.querySelectorAll('[data-dwc-preset]').forEach(function (b) { b.classList.remove('dwc-btn-primary'); });
		if (wasActive) {
			result.textContent = result.getAttribute('data-removed') || 'Preset removed. Saved fields are unchanged.';
			result.hidden = false;
			return;
		}
		btn.classList.add('dwc-btn-primary');

		var keys = keysIn('fields', 'meta_key');
		var added = 0;
		var skipped = 0;
		preset.fields.forEach(function (f) {
			if (keys.indexOf(f.meta_key) !== -1) {
				skipped++;
				return;
			}
			var row = newRow('fields');
			fill(row, {
				label: f.label, meta_key: f.meta_key, type: f.type, options: f.options,
				show_in_list: f.show_in_list, show_in_export: f.show_in_export, show_in_detail: f.show_in_detail
			});
			fieldsBody.appendChild(row);
			keys.push(f.meta_key);
			added++;
		});

		// Variation columns: an untouched default set (or none) is replaced, a customised set extended
		var cur = keysIn('cols', 'key');
		var colsAdded = 0;
		if (preset.variation_columns.length) {
			if (!cur.length || cur.join(',') === defaults.join(',')) {
				colsBody.innerHTML = '';
				cur = [];
			}
			preset.variation_columns.forEach(function (c) {
				if (cur.indexOf(c.key) !== -1) {
					return;
				}
				var row = newRow('cols');
				fill(row, { label: c.label, key: c.key, type: c.type, options: c.options, public: true });
				colsBody.appendChild(row);
				cur.push(c.key);
				colsAdded++;
			});
		}
		result.textContent = preset.label + ': ' + added + ' fields added, ' + skipped + ' already existed, ' + colsAdded +
			' variation columns added. Pick another type to switch, or click it again to undo. Click "Save fields" to keep.';
		result.hidden = false;
	}
})();
