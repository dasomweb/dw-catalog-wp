/**
 * DW Catalog WP — [dw_catalog] in-place navigation.
 *
 * The catalog works as plain links and GET forms. This upgrades it: filter changes,
 * sorting, paging and opening an item fetch the same page and swap only the catalog
 * block, keeping the URL in sync (history.pushState) so back/forward and sharing work.
 * Any failure falls back to a normal page load.
 */
(function () {
	'use strict';

	var ROOT = '[data-dwcat-catalog]';
	var controller = null;
	var searchTimer = null;

	function getRoot() {
		return document.querySelector(ROOT);
	}

	function formUrl(form) {
		var params = new URLSearchParams();
		new FormData(form).forEach(function (value, key) {
			if (String(value).trim() !== '') {
				params.append(key, value);
			}
		});
		var sort = form.ownerDocument.querySelector('[data-dwcat-sort][form="' + form.id + '"]');
		if (sort && params.get(sort.name) === sort.getAttribute('data-default')) {
			params.delete(sort.name);
		}
		var qs = params.toString();
		return form.action.split('?')[0] + (qs ? '?' + qs : '');
	}

	function load(url, push) {
		var root = getRoot();
		if (!root || !window.fetch || !window.DOMParser) {
			window.location.href = url;
			return;
		}

		// Keep typing focus in the search box across the swap
		var active = document.activeElement;
		var keepSearch = active && active.id === 'dwcat-search';

		if (controller) {
			controller.abort();
		}
		controller = window.AbortController ? new AbortController() : null;
		root.classList.add('is-loading');

		fetch(url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined })
			.then(function (res) {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}
				return res.text();
			})
			.then(function (html) {
				var next = new DOMParser().parseFromString(html, 'text/html').querySelector(ROOT);
				if (!next) {
					window.location.href = url;
					return;
				}
				root.innerHTML = next.innerHTML;
				root.classList.remove('is-loading');
				if (push) {
					window.history.pushState({ dwcat: true }, '', url);
				}
				if (keepSearch) {
					var input = document.getElementById('dwcat-search');
					if (input) {
						input.focus();
						input.setSelectionRange(input.value.length, input.value.length);
					}
				} else {
					var top = root.getBoundingClientRect().top;
					if (top < 0) {
						window.scrollBy({ top: top - 24, behavior: 'smooth' });
					}
				}
			})
			.catch(function (err) {
				if (err && err.name === 'AbortError') {
					return;
				}
				window.location.href = url;
			});
	}

	function inCatalog(el) {
		return el && el.closest && el.closest(ROOT);
	}

	// Filter checkboxes + sort select
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!inCatalog(t)) {
			return;
		}
		var form = t.form || (t.closest && t.closest('[data-dwcat-form]'));
		if (form && form.hasAttribute('data-dwcat-form') && (t.type === 'checkbox' || t.hasAttribute('data-dwcat-sort'))) {
			load(formUrl(form), true);
		}
	});

	// Search: submit, or live after a short pause
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (form.hasAttribute && form.hasAttribute('data-dwcat-form')) {
			e.preventDefault();
			clearTimeout(searchTimer);
			load(formUrl(form), true);
		}
	});
	document.addEventListener('input', function (e) {
		var t = e.target;
		if (t.id !== 'dwcat-search' || !inCatalog(t)) {
			return;
		}
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () {
			load(formUrl(t.form), true);
		}, 450);
	});

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!inCatalog(t)) {
			return;
		}

		// Mobile filter panel
		var toggle = t.closest('[data-dwcat-toggle]');
		if (toggle) {
			var sidebar = toggle.closest('.dwcat-sidebar');
			var open = sidebar.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			return;
		}

		// Detail gallery thumbnails
		var thumb = t.closest('[data-dwcat-thumb]');
		if (thumb) {
			var main = thumb.closest('.dwcat-detail-media').querySelector('[data-dwcat-main] img');
			if (main) {
				main.removeAttribute('srcset');
				main.src = thumb.getAttribute('data-dwcat-thumb');
			}
			var siblings = thumb.parentNode.querySelectorAll('[data-dwcat-thumb]');
			for (var i = 0; i < siblings.length; i++) {
				siblings[i].classList.toggle('is-active', siblings[i] === thumb);
			}
			return;
		}

		// Pagination, reset, back, and item links on this page
		var link = t.closest('a[href]');
		if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}
		var sameCatalog = link.hasAttribute('data-dwcat-link') ||
			link.closest('.dwcat-pagination') ||
			(link.classList.contains('dwcat-card-link') && link.href.indexOf('dw_item=') !== -1);
		if (sameCatalog && link.origin === window.location.origin) {
			e.preventDefault();
			load(link.href, true);
		}
	});

	window.addEventListener('popstate', function () {
		if (getRoot()) {
			load(window.location.href, false);
		}
	});
})();
