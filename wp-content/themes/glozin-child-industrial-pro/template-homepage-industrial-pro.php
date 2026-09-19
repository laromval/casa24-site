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
			<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Casa24.md — на главную', 'glozin-child' ); ?>">
				<?php
				/*
				 * Логотип-заглушка (не финальный дизайн, см. комментарий в самом файле SVG
				 * и README.md). Заменяет прежний логотип "Larom Val" — пункт 1 раздела 5 ТЗ.
				 */
				?>
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-mesterul-casei.svg' ); ?>" alt="<?php esc_attr_e( 'Meșterul Casei — Casa24.md', 'glozin-child' ); ?>" width="220" height="47">
			</a>

			<div class="ip-topbar__info">
				<a class="ip-topbar__phone" href="tel:+37368338813">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
					<?php esc_html_e( '+373 68 338 813', 'glozin-child' ); ?>
				</a>
				<span class="ip-topbar__hours">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path></svg>
					<?php esc_html_e( 'Пн–Пт 9:00–19:00, Сб–Вс 9:00–17:00', 'glozin-child' ); ?>
				</span>
			</div>

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
	     3. Рекламная карусель-виджет — плейсхолдер разметки. Реальная
	     логика слайдера (автопрокрутка/точки/свайп) и содержимое —
	     отдельная задача, здесь только структура и стили.
	     ================================================================= -->
	<div class="ip-promo-carousel">
		<div class="container">
			<div class="ip-promo-carousel__track">
				<div class="ip-promo-carousel__slide"><?php esc_html_e( 'Место для рекламного блока №1', 'glozin-child' ); ?></div>
				<div class="ip-promo-carousel__slide"><?php esc_html_e( 'Место для рекламного блока №2', 'glozin-child' ); ?></div>
				<div class="ip-promo-carousel__slide"><?php esc_html_e( 'Место для рекламного блока №3', 'glozin-child' ); ?></div>
			</div>
		</div>
	</div>

	<!-- =================================================================
	     4. Хиро
	     ================================================================= -->
	<section class="ip-hero">
		<div class="container">
			<div class="ip-hero__content">
				<span class="ip-hero__badge"><?php esc_html_e( 'Строительные материалы и хозтовары', 'glozin-child' ); ?></span>

				<h1 class="ip-hero__title">
					<?php
					printf(
						/* translators: %s: акцентная фраза, выделяется оранжевым */
						esc_html__( 'Всё для ремонта и стройки — %s', 'glozin-child' ),
						'<em>' . esc_html__( 'в одном месте', 'glozin-child' ) . '</em>'
					);
					?>
				</h1>

				<p class="ip-hero__subtitle">
					<?php esc_html_e( 'Двери, электроинструмент, товары для сада и огорода — с доставкой по Кишинёву и Молдове. Заглушка текста: финальный копирайт согласовать с владельцем сайта.', 'glozin-child' ); ?>
				</p>

				<div class="ip-hero__actions">
					<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="btn-accent">
						<?php esc_html_e( 'Смотреть каталог', 'glozin-child' ); ?>
					</a>
					<a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>" class="btn-outline">
						<?php esc_html_e( 'О компании', 'glozin-child' ); ?>
					</a>
				</div>

				<ul class="ip-hero__usp">
					<li><?php esc_html_e( 'Бесплатная доставка от 1000 MDL', 'glozin-child' ); ?></li>
					<li><?php esc_html_e( 'Гарантия качества', 'glozin-child' ); ?></li>
				</ul>
			</div>

			<?php
			/*
			 * Сетка 2x2 категорий-плашек. Ссылки временно "#" — заменить на
			 * реальные get_term_link() после того, как будут определены
			 * 4 приоритетные категории для хиро (решение владельца/content-agent,
			 * не техническая задача).
			 */
			$ip_hero_categories = array(
				array(
					'label' => __( 'Двери', 'glozin-child' ),
					'alt'   => false,
				),
				array(
					'label' => __( 'Электроинструмент', 'glozin-child' ),
					'alt'   => true,
				),
				array(
					'label' => __( 'Сад и огород', 'glozin-child' ),
					'alt'   => true,
				),
				array(
					'label' => __( 'Хозтовары', 'glozin-child' ),
					'alt'   => false,
				),
			);
			?>
			<div class="ip-hero__categories">
				<?php foreach ( $ip_hero_categories as $ip_hero_cat ) : ?>
					<a class="ip-hero-cat<?php echo $ip_hero_cat['alt'] ? ' ip-hero-cat--alt' : ''; ?>" href="#">
						<svg class="ip-hero-cat__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<rect x="3" y="3" width="18" height="18" rx="2"></rect>
						</svg>
						<span class="ip-hero-cat__label"><?php echo esc_html( $ip_hero_cat['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

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

	<div class="ip-footer__social-bar">
		<div class="container">
			<span><?php esc_html_e( 'Будьте на связи', 'glozin-child' ); ?></span>
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

	<div class="ip-footer__main">
		<div class="container ip-footer__grid">

			<div class="ip-footer__brand">
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-mesterul-casei.svg' ); ?>" alt="<?php esc_attr_e( 'Meșterul Casei — Casa24.md', 'glozin-child' ); ?>" width="170" height="38">
				<a class="ip-footer__phone" href="tel:+37368338813">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
					<?php esc_html_e( '+373 68 338 813', 'glozin-child' ); ?>
				</a>
				<a class="ip-footer__address" href="#" target="_blank" rel="noopener">
					<?php /* TODO: подставить реальную ссылку на карту (Google Maps) после подтверждения адреса магазина/пункта выдачи владельцем. */ ?>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					<?php esc_html_e( 'Кишинёв, Молдова', 'glozin-child' ); ?>
				</a>
			</div>

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

</footer>

<button type="button" class="ip-scroll-top" data-ip-scroll-top aria-label="<?php esc_attr_e( 'Наверх страницы', 'glozin-child' ); ?>">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
</button>

<?php
get_footer();
