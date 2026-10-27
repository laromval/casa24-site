/**
 * Переключатель светлая/тёмная тема сайта (обратная связь 19.09.2026).
 * По умолчанию — "light" (текущий дизайн: тёмная шапка + светлые секции).
 * "dark" — тот же дизайн, но секции категорий/товаров/футер тоже тёмные
 * (см. токены [data-ip-theme="dark"] в style.css).
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'casa24-ip-theme';

	function getStored() {
		try {
			return window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	}

	function setStored( value ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, value );
		} catch ( e ) {
			// Приватный режим браузера/запрещённое хранилище — молча игнорируем.
		}
	}

	function apply( theme ) {
		document.documentElement.setAttribute( 'data-ip-theme', theme );
	}

	var stored = getStored();
	if ( 'dark' === stored ) {
		apply( 'dark' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggles = document.querySelectorAll( '[data-ip-theme-toggle]' );

		toggles.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var current = document.documentElement.getAttribute( 'data-ip-theme' ) || 'light';
				var next = 'dark' === current ? 'light' : 'dark';
				apply( next );
				setStored( next );
			} );
		} );
	} );
} )();
