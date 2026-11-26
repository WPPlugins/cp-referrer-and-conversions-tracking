/**
 * CP Referrer and Conversion Tracking — Modern Admin Interactions & Charts
 * Zero jQuery or external library dependencies.
 */
(function () {
	'use strict';

	var l10n = window.cprtAdminL10n || {
		confirmDeleteOne: 'Are you sure you want to delete this item?',
		confirmDeleteMarked: 'Are you sure you want to delete the selected items?',
		confirmDeleteAll: 'Are you sure you want to permanently delete ALL items?',
		noItemsSelected: 'Please select at least one item first.',
		copied: 'Copied!',
		copyLink: 'Copy Link',
		logsLabel: 'logs',
		noChartData: 'No data recorded for the selected range.',
		utmMissingRequired: 'Enter at least a UTM Source to build a campaign URL.'
	};

	function initSelectAll() {
		document.querySelectorAll('[data-cprt-select-all]').forEach(function (master) {
			master.addEventListener('change', function () {
				var table = master.closest('table');
				if (!table) {
					return;
				}
				table.querySelectorAll('.cprt-row-checkbox').forEach(function (cb) {
					cb.checked = master.checked;
				});
			});
		});
	}

	function initDeleteHandlers() {
		// Single row POST deletion.
		document.querySelectorAll('[data-cprt-delete-one]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!window.confirm(l10n.confirmDeleteOne)) {
					return;
				}
				var formId = btn.getAttribute('data-cprt-target-form');
				var form = formId ? document.getElementById(formId) : null;
				if (!form) {
					return;
				}
				var input = form.querySelector('input[name="item_id"]');
				if (input) {
					input.value = btn.getAttribute('data-cprt-delete-one') || '';
					form.submit();
				}
			});
		});

		// Bulk selected POST deletion.
		document.querySelectorAll('[data-cprt-bulk-delete]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var formId = btn.getAttribute('data-cprt-bulk-delete');
				var form = formId ? document.getElementById(formId) : null;
				if (!form) {
					return;
				}
				var checked = form.querySelectorAll('.cprt-row-checkbox:checked');
				if (!checked.length) {
					window.alert(l10n.noItemsSelected);
					return;
				}
				if (window.confirm(l10n.confirmDeleteMarked)) {
					form.submit();
				}
			});
		});

		// Delete-all form confirmation.
		document.querySelectorAll('form[data-cprt-confirm="deleteAll"]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				if (!window.confirm(l10n.confirmDeleteAll)) {
					e.preventDefault();
				}
			});
		});
	}

	function initAutoSubmitAndPrint() {
		document.querySelectorAll('[data-cprt-auto-submit]').forEach(function (selectEl) {
			selectEl.addEventListener('change', function () {
				if (selectEl.form) {
					selectEl.form.submit();
				}
			});
		});

		document.querySelectorAll('[data-cprt-print]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				window.print();
			});
		});
	}

	function initCampaignBuilder() {
		var form = document.querySelector('.cprt-builder-form');
		var preview = document.getElementById('cprt-live-preview');
		if (!form || !preview) {
			return;
		}
		var siteUrl = form.getAttribute('data-cprt-site-url') || '';
		var keyInput = document.getElementById('cprt-paramname');
		var valInput = document.getElementById('cprt-paramvalue');

		function updatePreview() {
			var k = (keyInput && keyInput.value.trim()) ? encodeURIComponent(keyInput.value.trim()) : 'source';
			var v = (valInput && valInput.value.trim()) ? encodeURIComponent(valInput.value.trim()) : 'fromfacebook';
			preview.textContent = '';
			preview.appendChild(document.createTextNode(siteUrl + '/?'));
			var spanK = document.createElement('span');
			spanK.className = 'cprt-hl-key';
			spanK.textContent = k;
			preview.appendChild(spanK);
			preview.appendChild(document.createTextNode('='));
			var spanV = document.createElement('span');
			spanV.className = 'cprt-hl-val';
			spanV.textContent = v;
			preview.appendChild(spanV);
		}

		if (keyInput) {
			keyInput.addEventListener('input', updatePreview);
		}
		if (valInput) {
			valInput.addEventListener('input', updatePreview);
		}
	}

	function initUtmBuilder() {
		var wrap = document.querySelector('.cprt-utm-builder');
		var preview = document.getElementById('cprt-utm-live-preview');
		var copyBtn = document.getElementById('cprt-utm-copy-btn');
		var resetBtn = document.getElementById('cprt-utm-reset');
		if (!wrap || !preview || !copyBtn) {
			return;
		}

		var defaultSiteUrl = wrap.getAttribute('data-cprt-site-url') || '';
		var urlInput = document.getElementById('cprt-utm-url');
		var fields = [
			{ key: 'utm_source', el: document.getElementById('cprt-utm-source') },
			{ key: 'utm_medium', el: document.getElementById('cprt-utm-medium') },
			{ key: 'utm_campaign', el: document.getElementById('cprt-utm-campaign') },
			{ key: 'utm_term', el: document.getElementById('cprt-utm-term') },
			{ key: 'utm_content', el: document.getElementById('cprt-utm-content') }
		];

		function buildBaseAndHash(rawUrl) {
			var trimmed = (rawUrl || '').trim() || defaultSiteUrl;
			var hashIndex = trimmed.indexOf('#');
			var hash = '';
			var withoutHash = trimmed;
			if (hashIndex !== -1) {
				hash = trimmed.slice(hashIndex);
				withoutHash = trimmed.slice(0, hashIndex);
			}
			return { base: withoutHash, hash: hash };
		}

		function updateUtmPreview() {
			var parts = buildBaseAndHash(urlInput ? urlInput.value : defaultSiteUrl);
			var activePairs = [];

			fields.forEach(function (item) {
				if (item.el) {
					var val = item.el.value.trim();
					if (val) {
						activePairs.push({ key: item.key, val: val });
					}
				}
			});

			preview.textContent = '';

			if (!activePairs.length) {
				var sepExample = parts.base.indexOf('?') === -1 ? '?' : '&';
				preview.appendChild(document.createTextNode(parts.base + sepExample));
				var exK1 = document.createElement('span');
				exK1.className = 'cprt-hl-key';
				exK1.textContent = 'utm_source';
				preview.appendChild(exK1);
				preview.appendChild(document.createTextNode('='));
				var exV1 = document.createElement('span');
				exV1.className = 'cprt-hl-val';
				exV1.textContent = 'google';
				preview.appendChild(exV1);
				preview.appendChild(document.createTextNode('&'));
				var exK2 = document.createElement('span');
				exK2.className = 'cprt-hl-key';
				exK2.textContent = 'utm_medium';
				preview.appendChild(exK2);
				preview.appendChild(document.createTextNode('='));
				var exV2 = document.createElement('span');
				exV2.className = 'cprt-hl-val';
				exV2.textContent = 'cpc';
				preview.appendChild(exV2);
				if (parts.hash) {
					preview.appendChild(document.createTextNode(parts.hash));
				}
				copyBtn.setAttribute('data-cprt-copy', '');
				copyBtn.disabled = true;
				return;
			}

			var sep = parts.base.indexOf('?') === -1 ? '?' : '&';
			var fullUrl = parts.base + sep;
			preview.appendChild(document.createTextNode(parts.base + sep));

			activePairs.forEach(function (pair, idx) {
				var encodedVal = encodeURIComponent(pair.val);
				if (idx > 0) {
					fullUrl += '&';
					preview.appendChild(document.createTextNode('&'));
				}
				fullUrl += pair.key + '=' + encodedVal;

				var spanK = document.createElement('span');
				spanK.className = 'cprt-hl-key';
				spanK.textContent = pair.key;
				preview.appendChild(spanK);
				preview.appendChild(document.createTextNode('='));

				var spanV = document.createElement('span');
				spanV.className = 'cprt-hl-val';
				spanV.textContent = encodedVal;
				preview.appendChild(spanV);
			});

			if (parts.hash) {
				fullUrl += parts.hash;
				preview.appendChild(document.createTextNode(parts.hash));
			}

			copyBtn.setAttribute('data-cprt-copy', fullUrl);
			copyBtn.disabled = false;
		}

		if (urlInput) {
			urlInput.addEventListener('input', updateUtmPreview);
		}
		fields.forEach(function (item) {
			if (item.el) {
				item.el.addEventListener('input', updateUtmPreview);
			}
		});

		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				if (urlInput) {
					urlInput.value = defaultSiteUrl;
				}
				fields.forEach(function (item) {
					if (item.el) {
						item.el.value = '';
					}
				});
				updateUtmPreview();
			});
		}

		updateUtmPreview();
	}

	function initCopyButtons() {
		document.querySelectorAll('[data-cprt-copy]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var text = btn.getAttribute('data-cprt-copy') || '';
				if (!text) {
					return;
				}
				var onSuccess = function () {
					var original = btn.textContent;
					btn.textContent = l10n.copied;
					btn.disabled = true;
					window.setTimeout(function () {
						btn.textContent = original;
						btn.disabled = false;
					}, 1600);
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
						fallbackCopy(text, onSuccess);
					});
				} else {
					fallbackCopy(text, onSuccess);
				}
			});
		});
	}

	function fallbackCopy(text, callback) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			callback();
		} catch (err) {
			// Ignore clipboard failure gracefully.
		}
		document.body.removeChild(ta);
	}

	function renderChart(canvas) {
		var raw = canvas.getAttribute('data-cprt-chart');
		if (!raw) {
			return;
		}
		var cfg;
		try {
			cfg = JSON.parse(raw);
		} catch (e) {
			return;
		}

		var labels = Array.isArray(cfg.labels) ? cfg.labels : [];
		var values = Array.isArray(cfg.values) ? cfg.values : [];
		var type = cfg.type === 'bar' ? 'bar' : 'line';
		var color = cfg.color || '#2563eb';

		var wrap = canvas.parentElement;
		var tooltip = wrap ? wrap.querySelector('.cprt-chart-tooltip') : null;
		var dpr = window.devicePixelRatio || 1;
		var cssW = Math.max(260, wrap ? wrap.clientWidth : 400);
		var cssH = 240;

		canvas.width = Math.round(cssW * dpr);
		canvas.height = Math.round(cssH * dpr);
		canvas.style.width = cssW + 'px';
		canvas.style.height = cssH + 'px';

		var ctx = canvas.getContext('2d');
		if (!ctx) {
			return;
		}
		ctx.scale(dpr, dpr);
		ctx.clearRect(0, 0, cssW, cssH);

		if (!values.length) {
			ctx.fillStyle = '#94a3b8';
			ctx.font = '13px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText(l10n.noChartData, cssW / 2, cssH / 2);
			return;
		}

		var pad = { top: 18, right: 16, bottom: 32, left: 42 };
		var plotW = cssW - pad.left - pad.right;
		var plotH = cssH - pad.top - pad.bottom;

		var maxVal = 0;
		values.forEach(function (v) {
			if (v > maxVal) {
				maxVal = v;
			}
		});
		var yMax = maxVal <= 4 ? Math.max(4, maxVal) : Math.ceil(maxVal * 1.15);
		var gridSteps = 4;

		// Grid & Y labels.
		ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
		ctx.textAlign = 'right';
		ctx.textBaseline = 'middle';

		for (var g = 0; g <= gridSteps; g++) {
			var ratio = g / gridSteps;
			var y = pad.top + plotH - ratio * plotH;
			var yVal = Math.round(ratio * yMax);

			ctx.strokeStyle = '#e2e8f0';
			ctx.lineWidth = 1;
			ctx.beginPath();
			ctx.moveTo(pad.left, y);
			ctx.lineTo(cssW - pad.right, y);
			ctx.stroke();

			ctx.fillStyle = '#64748b';
			ctx.fillText(String(yVal), pad.left - 8, y);
		}

		var points = [];
		var n = values.length;

		if (type === 'bar') {
			var slotW = plotW / n;
			var barW = Math.max(4, Math.min(24, slotW * 0.65));

			for (var i = 0; i < n; i++) {
				var bh = yMax > 0 ? (values[i] / yMax) * plotH : 0;
				var bx = pad.left + i * slotW + (slotW - barW) / 2;
				var by = pad.top + plotH - bh;

				ctx.fillStyle = color;
				ctx.beginPath();
				if (ctx.roundRect) {
					ctx.roundRect(bx, by, barW, bh, [4, 4, 0, 0]);
				} else {
					ctx.rect(bx, by, barW, bh);
				}
				ctx.fill();

				points.push({ x: bx + barW / 2, y: by, label: labels[i] || '', value: values[i] });
			}
		} else {
			var stepX = n > 1 ? plotW / (n - 1) : 0;
			for (var j = 0; j < n; j++) {
				var px = n > 1 ? pad.left + j * stepX : pad.left + plotW / 2;
				var py = pad.top + plotH - (yMax > 0 ? (values[j] / yMax) * plotH : 0);
				points.push({ x: px, y: py, label: labels[j] || '', value: values[j] });
			}

			// Area gradient under line.
			var grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
			grad.addColorStop(0, 'rgba(37, 99, 235, 0.22)');
			grad.addColorStop(1, 'rgba(37, 99, 235, 0.01)');

			ctx.beginPath();
			ctx.moveTo(points[0].x, pad.top + plotH);
			points.forEach(function (pt) {
				ctx.lineTo(pt.x, pt.y);
			});
			ctx.lineTo(points[points.length - 1].x, pad.top + plotH);
			ctx.closePath();
			ctx.fillStyle = grad;
			ctx.fill();

			// Line stroke.
			ctx.beginPath();
			points.forEach(function (pt, idx) {
				if (idx === 0) {
					ctx.moveTo(pt.x, pt.y);
				} else {
					ctx.lineTo(pt.x, pt.y);
				}
			});
			ctx.strokeStyle = color;
			ctx.lineWidth = 2.2;
			ctx.stroke();

			// Points.
			if (n <= 45) {
				points.forEach(function (pt) {
					ctx.beginPath();
					ctx.arc(pt.x, pt.y, 3, 0, Math.PI * 2);
					ctx.fillStyle = '#ffffff';
					ctx.fill();
					ctx.lineWidth = 2;
					ctx.strokeStyle = color;
					ctx.stroke();
				});
			}
		}

		// X axis labels (evenly spaced up to 6 ticks).
		ctx.fillStyle = '#64748b';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'top';
		var maxTicks = Math.min(6, n);
		for (var t = 0; t < maxTicks; t++) {
			var idxTick = maxTicks === 1 ? 0 : Math.round((t * (n - 1)) / (maxTicks - 1));
			var ptTick = points[idxTick];
			if (ptTick) {
				ctx.fillText(ptTick.label, ptTick.x, pad.top + plotH + 10);
			}
		}

		// Store points for interactive hover tooltip.
		canvas._cprtPoints = points;
		if (tooltip && !canvas._cprtBound) {
			canvas._cprtBound = true;
			canvas.addEventListener('mousemove', function (e) {
				var rect = canvas.getBoundingClientRect();
				var mx = e.clientX - rect.left;
				var pts = canvas._cprtPoints || [];
				if (!pts.length) {
					return;
				}
				var nearest = pts[0];
				var minDist = Math.abs(mx - nearest.x);
				for (var k = 1; k < pts.length; k++) {
					var d = Math.abs(mx - pts[k].x);
					if (d < minDist) {
						minDist = d;
						nearest = pts[k];
					}
				}
				if (minDist <= 40) {
					tooltip.textContent = nearest.label + ': ' + nearest.value + ' ' + l10n.logsLabel;
					tooltip.style.left = Math.max(48, Math.min(rect.width - 48, nearest.x)) + 'px';
					tooltip.style.top = Math.max(26, nearest.y) + 'px';
					tooltip.hidden = false;
				} else {
					tooltip.hidden = true;
				}
			});
			canvas.addEventListener('mouseleave', function () {
				tooltip.hidden = true;
			});
		}
	}

	function initCharts() {
		var canvases = document.querySelectorAll('[data-cprt-chart]');
		if (!canvases.length) {
			return;
		}
		var drawAll = function () {
			canvases.forEach(renderChart);
		};
		drawAll();

		var resizeTimer;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(drawAll, 150);
		});
	}

	function initHelpGuide() {
		var tocLinks = document.querySelectorAll('.cprt-toc-link');
		if (!tocLinks.length) {
			return;
		}

		var linkMap = {};
		var sections = [];

		tocLinks.forEach(function (link) {
			var href = link.getAttribute('href') || '';
			if (href.charAt(0) === '#') {
				var id = href.slice(1);
				var target = document.getElementById(id);
				if (target) {
					linkMap[id] = link;
					sections.push(target);
				}
			}
		});

		function setActiveLink(id) {
			tocLinks.forEach(function (l) {
				l.classList.remove('is-current');
			});
			if (linkMap[id]) {
				linkMap[id].classList.add('is-current');
			}
		}

		function highlightHashTarget() {
			var hash = window.location.hash ? window.location.hash.slice(1) : '';
			if (!hash) {
				return;
			}
			var target = document.getElementById(hash);
			if (!target) {
				return;
			}
			var card = target.classList.contains('cprt-help-section') ? target : target.closest('.cprt-help-section');
			if (card && card.id) {
				setActiveLink(card.id);
				card.classList.add('is-highlighted');
				window.setTimeout(function () {
					card.classList.remove('is-highlighted');
				}, 2200);
			}
		}

		if ('IntersectionObserver' in window && sections.length) {
			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting && entry.target.id) {
							setActiveLink(entry.target.id);
						}
					});
				},
				{ rootMargin: '-18% 0px -68% 0px', threshold: 0.01 }
			);
			sections.forEach(function (sec) {
				observer.observe(sec);
			});
		}

		window.addEventListener('hashchange', highlightHashTarget);
		highlightHashTarget();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initSelectAll();
		initDeleteHandlers();
		initAutoSubmitAndPrint();
		initCampaignBuilder();
		initUtmBuilder();
		initCopyButtons();
		initCharts();
		initHelpGuide();
	});
})();
