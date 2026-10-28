/**
 * Cookie-баннер Casa24 (№38, №41) — разметка и настройки в inc/privacy-consent.php.
 *
 * Выбор посетителя — cookie casa24_consent: v=<версия>&id=<uuid>&a=0|1&m=0|1.
 * Пока аналитика не разрешена, тег Google не грузится, а WooCommerce
 * не пишет cookie «источник заказа» (sbjs_*). При отзыве согласия
 * cookie аналитики удаляются.
 */
(function () {
	'use strict';

	var cfg = window.casa24Consent;
	var root = document.getElementById('c24-consent');
	if (!cfg || !root) return;

	var COOKIE = 'casa24_consent';
	var prefs = root.querySelector('[data-c24-prefs]');
	var btnSave = root.querySelector('[data-c24-consent="save"]');
	var btnCustomize = root.querySelector('[data-c24-consent="customize"]');
	var gaLoaded = false;

	function readChoice() {
		var m = document.cookie.match(/(?:^|;\s*)casa24_consent=([^;]*)/);
		if (!m) return null;
		var p = new URLSearchParams(decodeURIComponent(m[1]));
		if (p.get('v') !== cfg.version || !p.get('id')) return null;
		return { id: p.get('id'), analytics: p.get('a') === '1', marketing: p.get('m') === '1' };
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			return (c === 'x' ? r : (r & 3 | 8)).toString(16);
		});
	}

	function writeChoice(choice) {
		var value = 'v=' + cfg.version + '&id=' + choice.id + '&a=' + (choice.analytics ? 1 : 0) + '&m=' + (choice.marketing ? 1 : 0);
		document.cookie = COOKIE + '=' + encodeURIComponent(value) + '; max-age=' + (cfg.days * 86400) +
			'; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	function logChoice(choice) {
		var body = new URLSearchParams({ id: choice.id, v: cfg.version, lang: cfg.lang || '', a: choice.analytics ? '1' : '', m: choice.marketing ? '1' : '' });
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(cfg.logUrl, body)) return;
		} catch (e) { /* ниже — обычный запрос */ }
		fetch(cfg.logUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
	}

	/* --- что разрешено --- */

	function loadGoogleTag(choice) {
		if (!cfg.ga4 || gaLoaded) return;
		gaLoaded = true;
		window.dataLayer = window.dataLayer || [];
		window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
		gtag('consent', 'default', {
			analytics_storage: 'granted',
			ad_storage: choice.marketing ? 'granted' : 'denied',
			ad_user_data: choice.marketing ? 'granted' : 'denied',
			ad_personalization: choice.marketing ? 'granted' : 'denied'
		});
		gtag('js', new Date());
		gtag('config', cfg.ga4.measurement);
		var s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4.tag);
		document.head.appendChild(s);
	}

	function setOrderAttribution(allow) {
		function apply() {
			if (window.wc_order_attribution && wc_order_attribution.setOrderTracking) {
				wc_order_attribution.setOrderTracking(allow);
			}
		}
		if (document.readyState === 'complete') apply();
		else window.addEventListener('load', apply);
	}

	function deleteCookies(re) {
		var host = location.hostname;
		var domains = ['', host, '.' + host, '.' + host.split('.').slice(-2).join('.')];
		document.cookie.split(';').forEach(function (c) {
			var name = c.split('=')[0].trim();
			if (!re.test(name)) return;
			domains.forEach(function (d) {
				document.cookie = name + '=; max-age=0; path=/' + (d ? '; domain=' + d : '');
			});
		});
	}

	function apply(choice, changed) {
		if (choice.analytics) {
			loadGoogleTag(choice);
			setOrderAttribution(true);
		} else {
			setOrderAttribution(false);
			if (changed) deleteCookies(/^(_ga|_gid|_gat)/);
		}
		if (changed && gaLoaded && window.gtag) {
			gtag('consent', 'update', {
				analytics_storage: choice.analytics ? 'granted' : 'denied',
				ad_storage: choice.marketing ? 'granted' : 'denied',
				ad_user_data: choice.marketing ? 'granted' : 'denied',
				ad_personalization: choice.marketing ? 'granted' : 'denied'
			});
		}
	}

	/* --- баннер --- */

	// На телефоне баннер стоит над нижней панелью Glozin, а не поверх неё (М-6 отчёта qa).
	function placeAboveMobileBar() {
		// Панель fixed — offsetParent у неё всегда null, поэтому смотрим на её положение.
		var bar = document.querySelector('.glozin-mobile-navigation-bar');
		var h = 0;
		if (bar && getComputedStyle(bar).display !== 'none' && getComputedStyle(bar).visibility !== 'hidden') {
			var r = bar.getBoundingClientRect();
			if (r.height > 0 && r.top < window.innerHeight) h = Math.max(0, window.innerHeight - r.top);
		}
		root.style.setProperty('--c24-bottom', h + 'px');
	}

	function show(withPrefs) {
		var current = readChoice();
		prefs.analytics.checked = !!(current && current.analytics);
		prefs.marketing.checked = !!(current && current.marketing);
		togglePrefs(withPrefs);
		placeAboveMobileBar();
		root.hidden = false;
	}

	function togglePrefs(open) {
		prefs.hidden = !open;
		btnSave.hidden = !open;
		btnCustomize.hidden = open;
	}

	function save(analytics, marketing) {
		var prev = readChoice();
		var choice = { id: prev ? prev.id : uuid(), analytics: analytics, marketing: marketing };
		writeChoice(choice);
		logChoice(choice);
		root.hidden = true;
		apply(choice, true);
	}

	root.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-c24-consent]');
		if (!btn) return;
		switch (btn.getAttribute('data-c24-consent')) {
			case 'all': save(true, true); break;
			case 'necessary': save(false, false); break;
			case 'customize': togglePrefs(true); prefs.querySelector('input[name="analytics"]').focus(); break;
			case 'save': save(prefs.analytics.checked, prefs.marketing.checked); break;
		}
	});

	// Ссылка «Настройки cookie» в футере (и любая с data-c24-consent-open).
	document.addEventListener('click', function (e) {
		var link = e.target.closest('[data-c24-consent-open]');
		if (!link) return;
		e.preventDefault();
		show(true);
	});

	window.addEventListener('resize', function () {
		if (!root.hidden) placeAboveMobileBar();
	});

	var choice = readChoice();
	if (choice) {
		apply(choice, false);
	} else {
		show(false);
	}
})();
