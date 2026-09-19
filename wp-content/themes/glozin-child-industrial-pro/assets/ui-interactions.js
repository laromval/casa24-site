/**
 * Мелкие интерактивные элементы каркаса, не связанные с темой оформления.
 * Сейчас: кнопка "наверх страницы" в футере (обратная связь 19.09.2026,
 * по референсу конкурента).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.querySelector( '[data-ip-scroll-top]' );
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	} );
} )();
