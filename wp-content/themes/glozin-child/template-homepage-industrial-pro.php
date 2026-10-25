<?php
/**
 * Template Name: Industrial Pro — Главная
 *
 * Статический каркас главной страницы в направлении "Industrial Pro"
 * (docs/tz-redesign-phase1.md, раздел 4.4). Реализован обычными
 * WordPress/WooCommerce-вызовами (WP_Query, get_terms, wp_nav_menu) —
 * без Elementor. Нужно назначить этот шаблон странице "Главная" в
 * wp-admin после появления доступа к сайту (см. README.md).
 *
 * @package Glozin_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Возвращает alt-текст для карточки товара: по умолчанию название товара,
 * с фолбэком на общую понятную строку, если название по какой-то причине
 * пустое. Закрывает пункт 7 раздела 5 ТЗ (изображения без alt на главной).
 *
 * @param int $product_id ID товара (поста product).
 * @return string
 */
function glozin_child_product_image_alt( $product_id ) {
	$title = get_the_title( $product_id );

	if ( '' !== trim( (string) $title ) ) {
		return $title;
	}

	return __( 'Фото товара Casa24.md — Meșterul Casei', 'glozin-child' );
}

/**
 * Рендер одной карточки товара для сеток "Топ продаж" / "Популярные товары".
 *
 * @param WC_Product|false $product Объект товара WooCommerce.
 */
function glozin_child_render_product_card( $product ) {
	if ( ! $product ) {
		return;
	}

	$product_id  = $product->get_id();
	$permalink   = get_permalink( $product_id );
	$thumb_id    = $product->get_image_id();
	$alt         = glozin_child_product_image_alt( $product_id );
	$image_html  = '';

	if ( $thumb_id ) {
		$image_html = wp_get_attachment_image(
			$thumb_id,
			'woocommerce_thumbnail',
			false,
			array(
				'alt'     => esc_attr( $alt ),
				'loading' => 'lazy',
			)
		);
	} else {
		// Плейсхолдер, если у товара вовсе нет изображения (см. TODO.md, пункт 5 ТЗ — Clasica KN SS 70 G).
		$image_html = '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/logo-mesterul-casei.svg' ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
	}
	?>
	<li class="ip-product-card">
		<a class="ip-product-card__photo" href="<?php echo esc_url( $permalink ); ?>">
			<?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image() уже экранирует. ?>
		</a>
		<div class="ip-product-card__body">
			<h3 class="ip-product-card__name">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $product_id ) ); ?></a>
			</h3>
			<div class="ip-product-card__footer">
				<span class="ip-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				<button
					type="button"
					class="ip-product-card__cart-btn"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: имя товара */ __( 'Добавить «%s» в корзину', 'glozin-child' ), get_the_title( $product_id ) ) ); ?>"
					data-product-id="<?php echo esc_attr( $product_id ); ?>"
				>
					<?php /* Заглушка иконки корзины — реальное добавление в корзину подключить через woocommerce_add_to_cart / AJAX add-to-cart хуки WooCommerce. */ ?>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
				</button>
			</div>
		</div>
	</li>
	<?php
}

get_header();
?>

<div class="industrial-pro">

	<!-- =================================================================
	     1. Верхняя строка: логотип, телефон/часы, избранное/корзина,
	     язык, переключатель светлая/тёмная тема.

	     ВАЖНО для деплоя (см. TODO.md): в реальной теме сайта эта
	     разметка должна заменить существующий header.php/inc/header,
	     а не просто вывестись НИЖЕ него — иначе на сайте будут два
	     заголовка друг под другом. get_header() ниже по коду вызывает
	     заголовок ТЕКУЩЕЙ активной темы, который на сайте уже содержит
	     свою шапку — при переносе на прод это надо свести в одно место.
	     ================================================================= -->
	<div class="ip-topbar">
		<div class="container">
			<?php
			/*
			 * Логотип и кнопки — во вложенном .ip-topbar__row, ЖЁСТКО одной строкой
			 * (flex-wrap: nowrap), телефон/часы — отдельным блоком под ней (не в
			 * общем flex-ряду с ними). Раньше все три блока были прямыми children
			 * одного flex-контейнера с wrap — при сужении окна порядок переноса не
			 * контролировался явно, и было видно, как кнопки "прыгают" между
			 * строкой логотипа и отдельной строкой на разных промежуточных ширинах
			 * (обратная связь 22.09.2026).
			 */
			?>
			<div class="ip-topbar__row">
				<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Casa24.md — на главную', 'glozin-child' ); ?>">
				<?php
				/*
				 * Логотип-заглушка (не финальный дизайн, см. README.md). Заменяет прежний
				 * логотип "Larom Val" — пункт 1 раздела 5 ТЗ. Здесь, в отличие от футера,
				 * SVG встроен прямо в разметку (не через <img>), чтобы строка "MEȘTERUL
				 * CASEI" могла перекрашиваться через CSS-токен --topbar-text в зависимости
				 * от темы (обратная связь 19.09.2026: белая шапка -> тёмный текст цвета
				 * второй строки, тёмная шапка -> светлый текст).
				 */
				?>
				<svg width="220" height="47" viewBox="0 0 340 72" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
					<rect x="0" y="4" width="64" height="64" rx="12" fill="oklch(0.27 0.02 255)"/>
					<g transform="translate(12,16)" fill="none" stroke="oklch(0.70 0.19 45)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
						<path d="M2 20 L20 4 L38 20"/>
						<path d="M7 17 V38 H33 V17"/>
						<rect x="17" y="25" width="6" height="13" fill="oklch(0.27 0.02 255)" stroke="none"/>
					</g>
					<text class="ip-logo__line1" x="80" y="34" font-family="'Barlow Condensed', Arial, sans-serif" font-weight="700" font-size="24" letter-spacing="1.2">MEȘTERUL CASEI</text>
					<text x="80" y="56" font-family="'Barlow Condensed', Arial, sans-serif" font-weight="600" font-size="16" letter-spacing="2.8" fill="oklch(0.70 0.19 45)">CASA24.MD</text>
				</svg>
			</a>

			<?php /* Телефон — в одной строке с логотипом; часы работы убраны, чтобы шапка была на строку ниже (обратная связь 25.10.2026). */ ?>
			<a class="ip-topbar__phone" href="tel:+37368338813" aria-label="<?php esc_attr_e( 'Позвонить: +373 68 338 813', 'glozin-child' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
				<span class="ip-topbar__phone-number"><?php esc_html_e( '+373 68 338 813', 'glozin-child' ); ?></span>
			</a>

			<div class="ip-topbar__actions">
				<?php
				/*
				 * Порядок в этом блоке — по обратной связи 19.09.2026:
				 * Зарегистрироваться → Избранное → переключатель темы → Корзина → RU/RO.
				 */
				?>
				<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Зарегистрироваться / Личный кабинет', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
						<circle cx="12" cy="7" r="4"></circle>
					</svg>
				</a>

				<?php /* Избранное — разметка-заглушка. Подключить реальный счётчик через хуки плагина wishlist (например, YITH/TI WooCommerce Wishlist), когда плагин будет определён на живом сайте. */ ?>
				<a href="#" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Избранное', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
					</svg>
					<span class="ip-icon-btn__badge"><?php echo esc_html( '0' ); /* TODO: подключить реальное количество избранного. */ ?></span>
				</a>

				<button type="button" class="ip-theme-toggle" data-ip-theme-toggle aria-label="<?php esc_attr_e( 'Переключить светлую/тёмную тему сайта', 'glozin-child' ); ?>">
					<svg class="ip-theme-toggle__icon ip-theme-toggle__icon--sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
					<svg class="ip-theme-toggle__icon ip-theme-toggle__icon--moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
				</button>

				<?php /* Корзина — заглушка счётчика. Подключить WC()->cart->get_cart_contents_count() и фрагменты WooCommerce (woocommerce_add_to_cart_fragments) для живого обновления без перезагрузки. */ ?>
				<a href="<?php echo function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : '#'; ?>" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Корзина', 'glozin-child' ); ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
					<span class="ip-icon-btn__badge">
						<?php
						echo ( function_exists( 'WC' ) && WC()->cart ) ? esc_html( WC()->cart->get_cart_contents_count() ) : '0';
						?>
					</span>
				</a>

				<?php glozin_child_language_switcher(); ?>
			</div>
			</div>
		</div>
	</div>

	<!-- =================================================================
	     2. Вторая строка: «Каталог» (подкатегории — выпадающий список)
	     слева, поиск по товарам по центру.
	     ================================================================= -->
	<div class="ip-navrow">
		<div class="container">
			<div class="ip-catalog">
				<button type="button" class="ip-catalog__toggle" aria-expanded="false" aria-haspopup="true">
					<?php esc_html_e( 'Каталог', 'glozin-child' ); ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
				<nav class="ip-catalog__menu" aria-label="<?php esc_attr_e( 'Категории каталога', 'glozin-child' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
							'fallback_cb'    => 'glozin_child_primary_nav_fallback',
						)
					);
					?>
				</nav>
			</div>

			<form class="ip-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" class="ip-search__input" placeholder="<?php esc_attr_e( 'Искать товары…', 'glozin-child' ); ?>">
				<input type="hidden" name="post_type" value="product">
				<button type="submit" class="ip-search__submit" aria-label="<?php esc_attr_e( 'Найти', 'glozin-child' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				</button>
			</form>
		</div>
	</div>

	<!-- =================================================================
	     3. Рекламные баннеры — полноширинная карусель из 3 слайдов.

	     21.09.2026 (первая версия): владелец попросил просто готовые
	     картинки без наложения текста поверх, взамен собранного ранее в тот
	     же день виджета с иконками/градиентами.

	     22.09.2026 (уточнение): слайдам с текстовым оверлеем нужны реальные
	     кликабельные ссылки на разные категории — несколько ссылок физически
	     нельзя "запечь" в один файл картинки, поэтому у таких слайдов (см.
	     ключ 'overlay' в массиве ниже) текст и ссылки — обычный HTML поверх
	     фото, а не часть изображения. Плюс это чинит и предыдущую проблему:
	     HTML-текст переводится через WPML как любой другой текст темы,
	     отдельная картинка с румынским текстом для RO не нужна.

	     22.09.2026 (позже в тот же день, важное уточнение владельца): слайд
	     "уборка участка" — это НОВЫЙ третий слайд, а не замена слайда
	     "консервация" ("Сохрани вкус лета" — банки/вёдра/бочки). Оба остаются
	     в карусели: консервация — 2-я позиция (как было исходно), уборка —
	     новая 3-я. Слайд с дисконтной картой (был 2-м) сдвинулся на 1-е
	     место — текст в нём по-прежнему часть картинки, ссылка одна,
	     `overlay` для него не задан.

	     Секция намеренно НЕ обёрнута в .container — баннер должен доходить
	     до края экрана. Карусель использует тот же генеричный JS, что и
	     раньше (см. assets/ui-interactions.js — [data-ip-carousel]), он не
	     завязан на конкретное количество слайдов.
	     ================================================================= -->
	<?php
	$ip_promo_banners = array(
		array(
			'file'   => 'promo-season-cleanup.webp',
			'alt'    => '',
			'w'      => 1942,
			'h'      => 809,
			'leaves' => true,
			// Новый слайд (обратная связь 22.09.2026) — фото владельца с
			// граблями/тачкой/мешками для листвы. Ведущий слайд карусели —
			// самая актуальная сейчас сезонная тема ("конец сезона"), поэтому
			// первый; слайд "консервация" — второй (как попросил владелец),
			// дисконтная карта (вечнозелёный оффер, без сезонной срочности) —
			// последняя, как и было исходно.
			'overlay' => array(
				'title'    => __( 'Завершение дачного сезона', 'glozin-child' ),
				'subtitle' => __( 'Всё для уборки', 'glozin-child' ),
				'links'    => array(
					array(
						// TODO (content-agent): подтвердить реальный слаг категории
						// "Инвентарь" (грабли/секаторы/лопаты) — сейчас ведёт на "#".
						'label' => __( 'Инвентарь', 'glozin-child' ),
						'href'  => '#',
					),
					array(
						// TODO (content-agent): подтвердить реальный слаг категории "Тачки".
						'label' => __( 'Тачки', 'glozin-child' ),
						'href'  => '#',
					),
					array(
						// TODO (content-agent): подтвердить реальный слаг категории "Мешки"
						// (для листвы/мусора).
						'label' => __( 'Мешки', 'glozin-child' ),
						'href'  => '#',
					),
					array(
						// Родительская категория "Вёдра" — подкатегория "Пищевые вёдра"
						// подтверждена на слайде "консервация" ниже, эта ссылка на
						// уровень выше по тому же дереву категорий.
						'label' => __( 'Вёдра', 'glozin-child' ),
						'href'  => 'https://casa24.md/product-category/%d1%85%d0%be%d0%b7%d1%8f%d0%b9%d1%81%d1%82%d0%b2%d0%b5%d0%bd%d0%bd%d0%be-%d0%b1%d1%8b%d1%82%d0%be%d0%b2%d1%8b%d0%b5/%d0%b2%d0%b5%d0%b4%d1%80%d0%b0/',
					),
				),
			),
		),
		array(
			'file'   => 'promo-season-canning.webp',
			// Декоративное фото: реальный смысл несёт текстовый оверлей ниже,
			// поэтому alt пустой — иначе скринридер дважды озвучит то же самое.
			'alt'    => '',
			'w'      => 1942,
			'h'      => 809,
			// Анимация падающих кленовых листьев (обратная связь 21.09.2026,
			// показана и одобрена в превью) — тематически подходит слайдам
			// "дачный сезон", у карты скидок её нет.
			'leaves' => true,
			'overlay' => array(
				'title'    => __( 'Завершение дачного сезона', 'glozin-child' ),
				'subtitle' => __( 'Сохрани вкус лета', 'glozin-child' ),
				'links'    => array(
					array(
						'label' => __( 'Банки', 'glozin-child' ),
						'href'  => 'https://casa24.md/product-category/%d1%85%d0%be%d0%b7%d1%8f%d0%b9%d1%81%d1%82%d0%b2%d0%b5%d0%bd%d0%bd%d0%be-%d0%b1%d1%8b%d1%82%d0%be%d0%b2%d1%8b%d0%b5/%d1%82%d0%be%d0%b2%d0%b0%d1%80%d1%8b-%d0%b4%d0%bb%d1%8f-%d0%ba%d1%83%d1%85%d0%bd%d0%b8/%d0%b1%d0%b0%d0%bd%d0%ba%d0%b8-%d1%81%d1%82%d0%b5%d0%ba%d0%bb%d1%8f%d0%bd%d0%bd%d1%8b%d0%b5/',
					),
					array(
						'label' => __( 'Пищевые вёдра', 'glozin-child' ),
						'href'  => 'https://casa24.md/product-category/%d1%85%d0%be%d0%b7%d1%8f%d0%b9%d1%81%d1%82%d0%b2%d0%b5%d0%bd%d0%bd%d0%be-%d0%b1%d1%8b%d1%82%d0%be%d0%b2%d1%8b%d0%b5/%d0%b2%d0%b5%d0%b4%d1%80%d0%b0/%d0%b2%d0%b5%d0%b4%d1%80%d0%b0-%d0%bf%d0%b8%d1%89%d0%b5%d0%b2%d1%8b%d0%b5/',
					),
					array(
						'label' => __( 'Бочки пищевые', 'glozin-child' ),
						'href'  => 'https://casa24.md/product-category/bidoane-si-butoaie/',
					),
				),
			),
		),
		array(
			'file'   => 'promo-discount-card.webp',
			// TODO(content-agent/владелец): подтвердить точный текст и ссылку
			// страницы с условиями дисконтной карты — сейчас ведёт на "#".
			'alt'    => __( 'Дисконтная карта «Meșterul Casei» — максимальная скидка по карте на кассе', 'glozin-child' ),
			'href'   => '#',
			// 22.09.2026: заменена на версию 1942×809 (та же картинка, но без
			// обрезки по высоте) — старая 1920×500 была заметно шире слота
			// карусели (aspect-ratio 2.4 в style.css) и показывалась с пустыми
			// полями сверху/снизу; новая почти точно совпадает с рамкой.
			'w'      => 1942,
			'h'      => 809,
			'leaves' => false,
		),
	);
	?>
	<section class="ip-season-promo" aria-roledescription="карусель" aria-label="<?php esc_attr_e( 'Акции и предложения', 'glozin-child' ); ?>" data-ip-carousel>
		<div class="ip-season-promo__viewport">
			<div class="ip-season-promo__track" data-ip-carousel-track>
				<?php foreach ( $ip_promo_banners as $ip_banner ) : ?>
					<?php if ( ! empty( $ip_banner['overlay'] ) ) : ?>
						<div class="ip-season-promo__slide ip-season-promo__slide--overlay">
							<img
								class="ip-season-promo__banner"
								src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/' . $ip_banner['file'] ); ?>"
								alt=""
								width="<?php echo esc_attr( $ip_banner['w'] ); ?>"
								height="<?php echo esc_attr( $ip_banner['h'] ); ?>"
								loading="lazy"
							>
							<?php
							/*
							 * Затемнение — отдельный слой на всю картинку (не боксик под
							 * текстом, не градиент, ограниченный серединой кадра). И "плашка
							 * под текстом" (обратная связь 22.09.2026 — "текст как на
							 * затеняющей плашке"), и более ранний узкий градиент до 75%
							 * ширины (давал видимую полосу на границе — обратная связь
							 * 22.09.2026 раньше в тот же день) не подошли. Решение: растянуть
							 * затемнение на все 100% слайда БЕЗ края внутри кадра — граница
							 * гашения оказывается за пределами видимой области, смотреть
							 * не на чем, полосе неоткуда взяться. Стоит ДО листьев в разметке,
							 * чтобы листья рисовались поверх затемнения и оставались яркими.
							 */
							?>
							<div class="ip-season-promo__scrim" aria-hidden="true"></div>
							<?php if ( ! empty( $ip_banner['leaves'] ) ) : ?>
								<div class="ip-season-promo__leaves" data-ip-falling-leaves aria-hidden="true"></div>
							<?php endif; ?>
							<div class="ip-season-promo__overlay">
								<h2 class="ip-season-promo__overlay-title"><?php echo esc_html( $ip_banner['overlay']['title'] ); ?></h2>
								<p class="ip-season-promo__overlay-subtitle"><?php echo esc_html( $ip_banner['overlay']['subtitle'] ); ?></p>
								<div class="ip-season-promo__overlay-links">
									<?php foreach ( $ip_banner['overlay']['links'] as $ip_overlay_link ) : ?>
										<a class="ip-season-promo__overlay-link" href="<?php echo esc_url( $ip_overlay_link['href'] ); ?>"><?php echo esc_html( $ip_overlay_link['label'] ); ?></a>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php else : ?>
						<a class="ip-season-promo__slide" href="<?php echo esc_url( $ip_banner['href'] ); ?>">
							<img
								class="ip-season-promo__banner"
								src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/' . $ip_banner['file'] ); ?>"
								alt="<?php echo esc_attr( $ip_banner['alt'] ); ?>"
								width="<?php echo esc_attr( $ip_banner['w'] ); ?>"
								height="<?php echo esc_attr( $ip_banner['h'] ); ?>"
								loading="lazy"
							>
							<?php if ( ! empty( $ip_banner['leaves'] ) ) : ?>
								<div class="ip-season-promo__leaves" data-ip-falling-leaves aria-hidden="true"></div>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( count( $ip_promo_banners ) > 1 ) : ?>
			<div class="ip-season-promo__dots" data-ip-carousel-dots role="tablist" aria-label="<?php esc_attr_e( 'Выбор слайда', 'glozin-child' ); ?>">
				<?php foreach ( $ip_promo_banners as $ip_dot_index => $ip_dot_banner ) : ?>
					<button
						type="button"
						class="ip-season-promo__dot<?php echo 0 === $ip_dot_index ? ' is-active' : ''; ?>"
						data-ip-carousel-dot="<?php echo esc_attr( $ip_dot_index ); ?>"
						role="tab"
						aria-selected="<?php echo 0 === $ip_dot_index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: номер слайда */ __( 'Слайд %d', 'glozin-child' ), $ip_dot_index + 1 ) ); ?>"
					></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<!-- =================================================================
	     3b. Полоса преимуществ — 4 пункта с иконками (доставка/гарантия/
	     оплата/поддержка), сразу под баннерами карусели (обратная связь
	     21.09.2026, по референсу). Статичный контент — не зависит от
	     WooCommerce, поэтому без условий вывода.
	     ================================================================= -->
	<?php
	$ip_usp_items = array(
		array(
			'icon'  => '<path d="M21 8 12 3 3 8l9 5 9-5Z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path>',
			'title' => __( 'Бесплатная доставка', 'glozin-child' ),
			'text'  => __( 'При заказе от 1000 лей.', 'glozin-child' ),
		),
		array(
			'icon'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
			'title' => __( 'Гарантия качества', 'glozin-child' ),
			'text'  => __( 'Высокое качество всех товаров.', 'glozin-child' ),
		),
		array(
			'icon'  => '<rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
			'title' => __( 'Удобная оплата', 'glozin-child' ),
			'text'  => __( 'Любым удобным способом.', 'glozin-child' ),
		),
		array(
			'icon'  => '<path d="M21 11.5a8.38 8.38 0 0 1-4.7 7.6 8.38 8.38 0 0 1-3.8.9H12a8.5 8.5 0 0 1-4-1L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"></path>',
			'title' => __( 'Мы на связи', 'glozin-child' ),
			'text'  => __( 'Поддержка в один клик.', 'glozin-child' ),
		),
	);
	?>
	<div class="ip-usp-bar">
		<div class="container">
			<?php
			/*
			 * Сетка — на ВЛОЖЕННОМ div, не на самом .container: у общего
			 * `.industrial-pro .container` (0-2-0) выше по специфичности
			 * правило `padding: 0 20px`, и если повесить .ip-usp-bar__grid
			 * на тот же элемент, что и .container, он перебивает вертикальные
			 * отступы сетки (обнаружено на скриншоте 21.09.2026 — полоса
			 * сжалась до высоты иконок без отступов).
			 */
			?>
			<div class="ip-usp-bar__grid">
				<?php foreach ( $ip_usp_items as $ip_usp ) : ?>
					<div class="ip-usp-item">
						<span class="ip-usp-item__icon" aria-hidden="true">
							<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $ip_usp['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- статичный набор SVG, без пользовательских данных. ?></svg>
						</span>
						<span class="ip-usp-item__body">
							<span class="ip-usp-item__title"><?php echo esc_html( $ip_usp['title'] ); ?></span>
							<span class="ip-usp-item__text"><?php echo esc_html( $ip_usp['text'] ); ?></span>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- =================================================================
	     5. Категории
	     ================================================================= -->
	<section class="ip-section">
		<div class="container">
			<div class="ip-section__head">
				<h2><?php esc_html_e( 'Категории', 'glozin-child' ); ?></h2>
				<a class="ip-section__link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">
					<?php esc_html_e( 'Все категории', 'glozin-child' ); ?>
				</a>
			</div>

			<?php
			$ip_categories = array();
			if ( taxonomy_exists( 'product_cat' ) ) {
				$ip_categories = get_terms(
					array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => true,
						'number'     => 4,
						'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
					)
				);
			}

			if ( ! is_wp_error( $ip_categories ) && ! empty( $ip_categories ) ) :
				?>
				<ul class="ip-categories-grid">
					<?php foreach ( $ip_categories as $ip_cat ) : ?>
						<li class="ip-category-card">
							<a href="<?php echo esc_url( get_term_link( $ip_cat ) ); ?>">
								<span class="ip-category-card__badge" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<rect x="3" y="3" width="18" height="18" rx="2"></rect>
									</svg>
								</span>
								<h3 class="ip-category-card__name"><?php echo esc_html( $ip_cat->name ); ?></h3>
								<p class="ip-category-card__desc">
									<?php
									echo esc_html(
										$ip_cat->description
											? wp_trim_words( $ip_cat->description, 8 )
											: __( 'Смотреть товары в категории', 'glozin-child' )
									);
									?>
								</p>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php
			else :
				?>
				<p><?php esc_html_e( 'Категории товаров появятся здесь после подключения каталога WooCommerce.', 'glozin-child' ); ?></p>
				<?php
			endif;
			?>
		</div>
	</section>

	<?php if ( class_exists( 'WooCommerce' ) ) : ?>

		<!-- =============================================================
		     6a. Топ продаж — сортировка по total_sales (фактические продажи)
		     ============================================================= -->
		<section class="ip-section ip-section--muted">
			<div class="container">
				<div class="ip-section__head">
					<h2><?php esc_html_e( 'Топ продаж', 'glozin-child' ); ?></h2>
					<a class="ip-section__link" href="<?php echo esc_url( home_url( '/shop/?orderby=popularity' ) ); ?>">
						<?php esc_html_e( 'Смотреть всё', 'glozin-child' ); ?>
					</a>
				</div>

				<?php
				/*
				 * Пункт 3 раздела 5 ТЗ: "Топ продаж" и "Популярные товары" на живом
				 * сайте сейчас показывают ОДИНАКОВЫЙ список. Здесь — два разных
				 * WP_Query с разными критериями, чтобы структурно исключить повтор:
				 * этот блок — реальные продажи (total_sales), блок ниже (6b) —
				 * товары со скидкой (переименован в "Товары на скидках" по
				 * обратной связи 19.09.2026, см. комментарий там).
				 */
				$ip_top_sales_query = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 4,
						'meta_key'       => 'total_sales', // Реальные продажи товара (нативное поле WooCommerce).
						'orderby'        => 'meta_value_num',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				);

				if ( $ip_top_sales_query->have_posts() ) :
					?>
					<ul class="ip-products-grid">
						<?php
						while ( $ip_top_sales_query->have_posts() ) :
							$ip_top_sales_query->the_post();
							glozin_child_render_product_card( wc_get_product( get_the_ID() ) );
						endwhile;
						?>
					</ul>
					<?php
					wp_reset_postdata();
				else :
					?>
					<p><?php esc_html_e( 'Товары появятся здесь после подключения каталога WooCommerce.', 'glozin-child' ); ?></p>
					<?php
				endif;
				?>
			</div>
		</section>

		<!-- =============================================================
		     6b. Товары на скидках — по обратной связи 19.09.2026 заменяет
		     "Популярные товары": владелец счёл рейтинг/просмотры недостаточно
		     отличимыми от "Топ продаж" на глаз. Скидка — однозначно другой
		     критерий (реальные товары со скидкой, а не просто другая сортировка).
		     ============================================================= -->
		<section class="ip-section">
			<div class="container">
				<div class="ip-section__head">
					<h2><?php esc_html_e( 'Товары на скидках', 'glozin-child' ); ?></h2>
					<a class="ip-section__link" href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">
						<?php esc_html_e( 'Смотреть всё', 'glozin-child' ); ?>
					</a>
				</div>

				<?php
				$ip_sale_ids   = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
				$ip_sale_query = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 4,
						// array( 0 ) — намеренно: пустой post__in WP_Query интерпретирует
						// как "фильтр не задан" и вернёт ВСЕ товары; так возвращает ноль.
						'post__in'       => ! empty( $ip_sale_ids ) ? $ip_sale_ids : array( 0 ),
						'orderby'        => 'post__in',
						'no_found_rows'  => true,
					)
				);

				if ( $ip_sale_query->have_posts() ) :
					?>
					<ul class="ip-products-grid">
						<?php
						while ( $ip_sale_query->have_posts() ) :
							$ip_sale_query->the_post();
							glozin_child_render_product_card( wc_get_product( get_the_ID() ) );
						endwhile;
						?>
					</ul>
					<?php
					wp_reset_postdata();
				else :
					?>
					<p><?php esc_html_e( 'Сейчас нет товаров со скидкой — блок появится, как только в WooCommerce будет хотя бы один товар с указанной акционной ценой.', 'glozin-child' ); ?></p>
					<?php
				endif;
				?>
			</div>
		</section>

	<?php else : ?>

		<section class="ip-section">
			<div class="container">
				<p><?php esc_html_e( 'WooCommerce не активен — блоки "Топ продаж" и "Товары на скидках" появятся после активации плагина.', 'glozin-child' ); ?></p>
			</div>
		</section>

	<?php endif; ?>

</div><!-- .industrial-pro -->

<!-- =====================================================================
     7. Полный футер — переделан по обратной связи 19.09.2026 (референс:
     конкурент Supraten.md, структура похожая, ссылки — только наши
     реальные страницы, без выдуманных категорий вроде "Магазины"/
     "Учебный центр", которых у нас нет).

     ВАЖНО для деплоя (см. TODO.md, тот же принцип, что и для шапки):
     это должно ЗАМЕНИТЬ вывод настоящего footer.php темы на сайте,
     а не вывестись НИЖЕ него — иначе будет два футера подряд.
     ================================================================= -->
<footer class="ip-footer">

	<?php
	/* Верхняя строка футера — переделана по обратной связи 22.09.2026:
	   логотип + кнопка "Полезная информация" слева, "Будьте на связи" +
	   телефон + соцсети справа, в одну строку (референс — скриншот
	   пользователя). Кнопка — аккордеон на любой ширине экрана: по
	   умолчанию .ip-footer__main пустой (0 высоты), колонки со ссылками
	   появляются широкой панелью только по клику. */
	?>
	<div class="ip-footer__social-bar">
		<div class="container ip-footer__social-bar-row">

			<div class="ip-footer__brand-row">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-mesterul-casei.svg' ); ?>" alt="<?php esc_attr_e( 'Meșterul Casei — Casa24.md', 'glozin-child' ); ?>" width="170" height="38">
				<button type="button" class="ip-footer__info-toggle" data-ip-footer-toggle aria-expanded="false" aria-controls="ip-footer-cols">
					<?php esc_html_e( 'Полезная информация', 'glozin-child' ); ?>
					<svg class="ip-footer__info-toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
				</button>
			</div>

			<div class="ip-footer__contact-row">
				<span><?php esc_html_e( 'Будьте на связи', 'glozin-child' ); ?></span>
				<a class="ip-footer__phone" href="tel:+37368338813">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
					<?php esc_html_e( '+373 68 338 813', 'glozin-child' ); ?>
				</a>
				<?php
				/* TODO (media-agent/marketing-agent): подставить реальные ссылки соцсетей — сейчас "#". */
				?>
				<div class="ip-footer__social-links">
					<a href="#" aria-label="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>
					<a href="#" aria-label="Instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle></svg></a>
					<a href="#" aria-label="Telegram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path></svg></a>
				</div>
			</div>

		</div>
	</div>

	<div class="ip-footer__main">
		<div class="container">

			<div class="ip-footer__cols" id="ip-footer-cols" data-ip-footer-cols>

				<div class="ip-footer__col">
					<h3><?php esc_html_e( 'Интернет-магазин', 'glozin-child' ); ?></h3>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/dostavka-i-oplata/' ) ); ?>"><?php esc_html_e( 'Доставка и оплата', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/oferta/' ) ); ?>"><?php esc_html_e( 'Публичная оферта', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/obmen-i-vozvrat/' ) ); ?>"><?php esc_html_e( 'Обмен и возврат товара', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Политика конфиденциальности', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/contacte/' ) ); ?>"><?php esc_html_e( 'Контакты', 'glozin-child' ); ?></a></li>
					</ul>
				</div>

				<div class="ip-footer__col">
					<h3><?php esc_html_e( 'Каталог', 'glozin-child' ); ?></h3>
					<ul>
						<li><a href="#"><?php esc_html_e( 'Двери', 'glozin-child' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Электроинструмент', 'glozin-child' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Сад и огород', 'glozin-child' ); ?></a></li>
						<li><a href="#"><?php esc_html_e( 'Хозтовары', 'glozin-child' ); ?></a></li>
					</ul>
				</div>

				<div class="ip-footer__col">
					<h3><?php esc_html_e( 'О компании', 'glozin-child' ); ?></h3>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>"><?php esc_html_e( 'О нас', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><?php esc_html_e( 'Статьи', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/contacte/' ) ); ?>"><?php esc_html_e( 'Контакты', 'glozin-child' ); ?></a></li>
					</ul>
				</div>

			</div>

		</div>
	</div>

	<div class="ip-footer__bottom">
		<div class="container ip-footer__bottom-row">
			<a class="ip-footer__bottom-link" href="#" target="_blank" rel="noopener">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
				<?php esc_html_e( 'Как нас найти', 'glozin-child' ); ?>
			</a>
			<a class="ip-footer__bottom-link" href="<?php echo esc_url( home_url( '/dostavka-i-oplata/' ) ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="7" width="15" height="13" rx="1"></rect><path d="M16 10h4l3 3v4h-7z"></path><circle cx="6" cy="20" r="2"></circle><circle cx="18" cy="20" r="2"></circle></svg>
				<?php esc_html_e( 'Доставка и оплата', 'glozin-child' ); ?>
			</a>
			<span class="ip-footer__copy">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Casa24.md — LAROMVAL SRL. <?php esc_html_e( 'Все права защищены.', 'glozin-child' ); ?>
			</span>
		</div>
	</div>

	<?php
	/* Полоса платёжных систем — обратная связь 21.09.2026, референс: скриншот
	   текущего сайта. Специально светлая (не в тёмной палитре футера) —
	   иконки платёжных систем и так низкоконтрастны, на тёмном фоне их почти
	   не видно. Плашку разработчика ("click WEB DESIGN") убрали по просьбе
	   владельца (обратная связь 22.09.2026). */
	?>
	<div class="ip-footer__payments">
		<div class="container ip-footer__payments-row">
			<div class="ip-footer__payment-icons">
				<span class="ip-footer__payment-icon ip-footer__payment-icon--maib" aria-label="maib">
					<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="9" cy="9" r="9" fill="#00A99D"></circle></svg>
					maib
				</span>
				<span class="ip-footer__payment-icon ip-footer__payment-icon--mastercard" aria-label="Mastercard">
					<svg width="32" height="20" viewBox="0 0 32 20" aria-hidden="true">
						<circle cx="12" cy="10" r="10" fill="#EB001B"></circle>
						<circle cx="20" cy="10" r="10" fill="#F79E1B"></circle>
						<path d="M16 2.2a10 10 0 0 1 0 15.6 10 10 0 0 1 0-15.6Z" fill="#FF5F00"></path>
					</svg>
				</span>
				<span class="ip-footer__payment-icon ip-footer__payment-icon--visa" aria-label="VISA">VISA</span>
			</div>
		</div>
	</div>

</footer>

<button type="button" class="ip-scroll-top" data-ip-scroll-top aria-label="<?php esc_attr_e( 'Наверх страницы', 'glozin-child' ); ?>">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
</button>

<?php
get_footer();
