/**
 * Мелкие интерактивные элементы каркаса, не связанные с темой оформления.
 * Сейчас: кнопка "наверх страницы" в футере (обратная связь 19.09.2026,
 * по референсу конкурента) и аккордеон "Полезная информация" в мобильном
 * футере (обратная связь 21.09.2026).
 */
( function () {
	'use strict';

	/*
	 * Ширина полосы прокрутки → CSS-переменная --ip-scrollbar. Нужна для
	 * растягивания страницы на всю ширину окна (industrial-pro.css,
	 * body.ip-page .industrial-pro): 100vw включает полосу прокрутки.
	 */
	function updateScrollbarWidth() {
		var width = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty( '--ip-scrollbar', Math.max( width, 0 ) + 'px' );
	}

	updateScrollbarWidth();
	window.addEventListener( 'resize', updateScrollbarWidth );
	document.addEventListener( 'DOMContentLoaded', updateScrollbarWidth );

	/*
	 * «Каталог» в шапке — открывается и закрывается по нажатию (обратная связь
	 * 26.10.2026: при наведении список исчезал, пока курсор шёл к пункту).
	 * Закрывается также кликом мимо списка и клавишей Esc.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.querySelector( '[data-ip-catalog-toggle]' );
		if ( ! toggle ) {
			return;
		}

		var catalog = toggle.closest( '.ip-catalog' );

		function setOpen( open ) {
			catalog.classList.toggle( 'is-open', open );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		toggle.addEventListener( 'click', function () {
			setOpen( ! catalog.classList.contains( 'is-open' ) );
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( catalog.classList.contains( 'is-open' ) && ! catalog.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && catalog.classList.contains( 'is-open' ) ) {
				setOpen( false );
				toggle.focus();
			}
		} );
	} );

	/*
	 * Подсказка «Быстрый просмотр» — как у «Добавить в список желаний»
	 * (обратная связь 26.10.2026). У глазика Glozin ставит класс
	 * gz-tooltip-inside: подсказка рисуется внутри фото и обрезается им.
	 * С классом gz-tooltip Glozin показывает её отдельным элементом поверх
	 * страницы (обработчик повешен на body, поэтому работает и для карточек,
	 * подгруженных прокруткой). Меняем класс при первом наведении — до того,
	 * как сработает обработчик Glozin (фаза перехвата).
	 */
	document.addEventListener( 'mouseover', function ( event ) {
		var el = event.target.closest ? event.target.closest( '.gz-tooltip-inside[data-tooltip]' ) : null;
		if ( el ) {
			el.classList.remove( 'gz-tooltip-inside' );
			el.classList.add( 'gz-tooltip' );
		}
	}, true );

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.querySelector( '[data-ip-scroll-top]' );
		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	} );

	/**
	 * Кнопка "наверх" — фиксирована относительно окна, но по умолчанию
	 * упиралась в нижнюю полосу футера, когда страница долистана до конца
	 * (обратная связь 21.09.2026: "залезла на нижнюю полосу, не туда и не
	 * сюда"). Поднимаем её настолько, чтобы не перекрывать футер — не весь
	 * футер целиком, иначе на мобильных с открытым аккордеоном "Полезная
	 * информация" (футер выше экрана) кнопку уносило за пределы вьюпорта.
	 * Отдельно подстрахованы от этого через maxRaise — кнопка никогда не
	 * поднимается выше верхней четверти экрана.
	 *
	 * dockTarget зависит от состояния аккордеона: пока он свёрнут (по
	 * умолчанию на мобильных), футер короткий — верхняя полоса соцсетей
	 * (.ip-footer__social-bar) стоит вплотную над нижней полосой копирайта,
	 * и если целиться только в неё, кнопка перекрывает иконки соцсетей
	 * (обратная связь 22.09.2026, скриншот). Поэтому пока аккордеон закрыт,
	 * целимся в верхушку футера целиком — он и так короткий, "уносить за
	 * пределы вьюпорта" там нечему. Как только аккордеон открыт (колонки
	 * ссылок делают футер высоким), возвращаемся к прежней логике — целимся
	 * только в последнюю полосу, чтобы не задирать кнопку выше середины
	 * экрана на всё время прокрутки колонок.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var scrollBtn = document.querySelector( '[data-ip-scroll-top]' );
		var socialBar = document.querySelector( '.ip-footer__social-bar' );
		// Именно .ip-footer__bottom, а не __payments — на мобильных строки
		// "Как нас найти/Доставка и оплата/копирайт" складываются в
		// колонку и растягивают эту полосу, кнопка должна уходить выше
		// её целиком, а не только выше полосы с платёжными иконками.
		var bottomBar = document.querySelector( '.ip-footer__bottom' ) || document.querySelector( '.ip-footer__payments' );
		var cols = document.querySelector( '[data-ip-footer-cols]' );
		if ( ! scrollBtn || ! bottomBar ) {
			return;
		}

		var GAP = 16;
		var BTN_SIZE = 46;
		var TOP_MARGIN = 20;
		var ticking = false;

		function getDockTarget() {
			var accordionOpen = cols && cols.classList.contains( 'is-open' );
			if ( ! accordionOpen && socialBar ) {
				return socialBar;
			}
			return bottomBar;
		}

		function updateScrollBtnPosition() {
			ticking = false;
			var rect = getDockTarget().getBoundingClientRect();
			var overlap = window.innerHeight - rect.top;
			var maxRaise = window.innerHeight - GAP - BTN_SIZE - TOP_MARGIN;
			overlap = Math.min( Math.max( overlap, 0 ), Math.max( maxRaise, 0 ) );
			scrollBtn.style.bottom = overlap > 0 ? ( overlap + GAP ) + 'px' : '';
		}

		function requestUpdate() {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( updateScrollBtnPosition );
		}

		window.addEventListener( 'scroll', requestUpdate, { passive: true } );
		window.addEventListener( 'resize', requestUpdate );
		document.addEventListener( 'ip-footer-toggle', requestUpdate );
		updateScrollBtnPosition();
	} );

	/**
	 * Аккордеон "Полезная информация" — на мобильных ссылки футера
	 * (Интернет-магазин/Каталог/О компании) скрыты по умолчанию, чтобы не
	 * растягивать футер, и появляются по клику (обратная связь 21.09.2026).
	 * На десктопе переключатель скрыт через CSS, колонки видны всегда.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.querySelector( '[data-ip-footer-toggle]' );
		var cols = document.querySelector( '[data-ip-footer-cols]' );
		if ( ! toggle || ! cols ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = cols.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
			// Раскрытие/закрытие аккордеона двигает нижнюю полосу футера —
			// пересчитываем положение кнопки "наверх" (см. выше).
			document.dispatchEvent( new Event( 'ip-footer-toggle' ) );
		} );
	} );

	/**
	 * Сезонный рекламный виджет (секция 3 главной, .ip-season-promo) —
	 * карусель с автопрокруткой и точками-навигацией, по референсу
	 * конкурента (обратная связь 19.09.2026). Пауза при наведении/фокусе,
	 * свайп на тач-устройствах.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '[data-ip-carousel]' );
		if ( ! root ) {
			return;
		}

		var track = root.querySelector( '[data-ip-carousel-track]' );
		var slides = track ? Array.prototype.slice.call( track.children ) : [];
		var dots = Array.prototype.slice.call( root.querySelectorAll( '[data-ip-carousel-dot]' ) );

		if ( slides.length < 2 ) {
			return;
		}

		var AUTOPLAY_MS = 6000;
		var current = 0;
		var timer = null;

		function goTo( index ) {
			current = ( index + slides.length ) % slides.length;
			track.style.transform = 'translateX(-' + ( current * 100 ) + '%)';

			dots.forEach( function ( dot, i ) {
				var isActive = i === current;
				dot.classList.toggle( 'is-active', isActive );
				dot.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
		}

		function stopAutoplay() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		function startAutoplay() {
			stopAutoplay();
			timer = window.setInterval( function () {
				goTo( current + 1 );
			}, AUTOPLAY_MS );
		}

		dots.forEach( function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				goTo( i );
				startAutoplay();
			} );
		} );

		root.addEventListener( 'mouseenter', stopAutoplay );
		root.addEventListener( 'mouseleave', startAutoplay );
		root.addEventListener( 'focusin', stopAutoplay );
		root.addEventListener( 'focusout', startAutoplay );

		// Свайп влево/вправо на тач-устройствах.
		var touchStartX = null;
		track.addEventListener( 'touchstart', function ( e ) {
			touchStartX = e.touches[0].clientX;
			stopAutoplay();
		}, { passive: true } );

		track.addEventListener( 'touchend', function ( e ) {
			if ( null === touchStartX ) {
				return;
			}
			var deltaX = e.changedTouches[0].clientX - touchStartX;
			if ( Math.abs( deltaX ) > 40 ) {
				goTo( current + ( deltaX < 0 ? 1 : -1 ) );
			}
			touchStartX = null;
			startAutoplay();
		} );

		goTo( 0 );
		startAutoplay();
	} );

	/**
	 * Падающие кленовые листья поверх сезонного баннера (обратная связь
	 * 21.09.2026, показано и одобрено в превью-демо) — декоративный слой
	 * поверх статичной картинки, саму картинку не трогает. Уважает
	 * prefers-reduced-motion через CSS (см. style.css .ip-leaf), в JS
	 * дополнительно не создаём листья вовсе, если пользователь просил
	 * меньше анимации — чтобы не тратить даже на создание DOM-узлов.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			return;
		}

		var layers = document.querySelectorAll( '[data-ip-falling-leaves]' );
		if ( ! layers.length ) {
			return;
		}

		var LEAF_COLORS = [ 'oklch(0.68 0.16 55)', 'oklch(0.75 0.15 85)', 'oklch(0.55 0.14 35)', 'oklch(0.62 0.17 45)' ];
		// Кленовый лист: 3 лопасти + черешок (тот же силуэт, что и значок
		// после слова "Завершение" на самой картинке баннера).
		var LEAF_PATH = 'M12 2 C13 5 15 6 18 5 C16.5 8 17.5 10.5 21 11 C18 12.5 17 13.5 18.5 17 C15.5 15.5 14 15 12.5 17 L12.5 22 L11.5 22 L11.5 17 C10 15 8.5 15.5 5.5 17 C7 13.5 6 12.5 3 11 C6.5 10.5 7.5 8 6 5 C9 6 11 5 12 2 Z';
		var LEAF_COUNT = 16;

		function buildLeaf( colorIndex ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'ip-leaf';

			// Размер x2 по обратной связи 23.09.2026 (было 14–28px).
			var size = 28 + Math.random() * 28;
			var startLeft = Math.random() * 100;
			var fallDuration = 7 + Math.random() * 6;
			var swayDuration = 2 + Math.random() * 2;
			var fallDelay = Math.random() * -14;
			var swayDelay = Math.random() * -3;

			wrap.style.left = startLeft + '%';
			wrap.style.width = size + 'px';
			wrap.style.height = size + 'px';
			wrap.style.animationDuration = fallDuration + 's, ' + swayDuration + 's';
			wrap.style.animationDelay = fallDelay + 's, ' + swayDelay + 's';

			wrap.innerHTML = '<svg viewBox="0 0 24 24" fill="' + LEAF_COLORS[ colorIndex % LEAF_COLORS.length ] + '"><path d="' + LEAF_PATH + '"></path></svg>';
			return wrap;
		}

		layers.forEach( function ( layer ) {
			for ( var i = 0; i < LEAF_COUNT; i++ ) {
				layer.appendChild( buildLeaf( i ) );
			}
		} );
	} );
} )();
