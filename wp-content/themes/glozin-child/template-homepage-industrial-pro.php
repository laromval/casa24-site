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
	     1. Утилити-полоса
	     ================================================================= -->
	<div class="ip-utility-bar">
		<div class="container">
			<div class="ip-utility-bar__contacts">
				<a href="tel:+37300000000">
					<?php esc_html_e( '+373 00 000 000', 'glozin-child' ); ?>
				</a>
				<a href="mailto:info@casa24.md">info@casa24.md</a>
			</div>
			<nav class="ip-utility-bar__links" aria-label="<?php esc_attr_e( 'Дополнительная навигация', 'glozin-child' ); ?>">
				<a href="<?php echo esc_url( home_url( '/dostavka-i-oplata/' ) ); ?>">
					<?php esc_html_e( 'Доставка и оплата', 'glozin-child' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/o-nas/' ) ); ?>">
					<?php esc_html_e( 'О нас', 'glozin-child' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/contacte/' ) ); ?>">
					<?php esc_html_e( 'Контакты', 'glozin-child' ); ?>
				</a>
			</nav>
		</div>
	</div>

	<!-- =================================================================
	     2. Шапка
	     ================================================================= -->
	<header class="ip-header">
		<div class="container">
			<a class="ip-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Casa24.md — на главную', 'glozin-child' ); ?>">
				<?php
				/*
				 * Логотип-заглушка (не финальный дизайн, см. комментарий в самом файле SVG
				 * и README.md). Заменяет прежний логотип "Larom Val" — пункт 1 раздела 5 ТЗ.
				 */
				?>
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-mesterul-casei.svg' ); ?>" alt="<?php esc_attr_e( 'Meșterul Casei — Casa24.md', 'glozin-child' ); ?>" width="160" height="36">
			</a>

			<nav class="ip-nav" aria-label="<?php esc_attr_e( 'Главное меню', 'glozin-child' ); ?>">
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

			<div class="ip-header__icons">
				<button type="button" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Поиск по сайту', 'glozin-child' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="11" cy="11" r="8"></circle>
						<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
					</svg>
				</button>

				<?php /* Избранное — разметка-заглушка. Подключить реальный счётчик через хуки плагина wishlist (например, YITH/TI WooCommerce Wishlist), когда плагин будет определён на живом сайте. */ ?>
				<a href="#" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Избранное', 'glozin-child' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
					</svg>
					<span class="ip-icon-btn__badge"><?php echo esc_html( '0' ); /* TODO: подключить реальное количество избранного. */ ?></span>
				</a>

				<?php /* Корзина — заглушка счётчика. Подключить WC()->cart->get_cart_contents_count() и фрагменты WooCommerce (woocommerce_add_to_cart_fragments) для живого обновления без перезагрузки. */ ?>
				<a href="<?php echo function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : '#'; ?>" class="ip-icon-btn" aria-label="<?php esc_attr_e( 'Корзина', 'glozin-child' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
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
			</div>
		</div>
	</header>

	<!-- =================================================================
	     3. Хиро
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
	     4. Категории
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
		     5a. Топ продаж — сортировка по total_sales (фактические продажи)
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
				 * WP_Query с разными критериями сортировки, чтобы структурно
				 * исключить повтор.
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
		     5b. Популярные товары — ДРУГОЙ критерий сортировки, чем "Топ продаж"
		     ============================================================= -->
		<section class="ip-section">
			<div class="container">
				<div class="ip-section__head">
					<h2><?php esc_html_e( 'Популярные товары', 'glozin-child' ); ?></h2>
					<a class="ip-section__link" href="<?php echo esc_url( home_url( '/shop/?orderby=rating' ) ); ?>">
						<?php esc_html_e( 'Смотреть всё', 'glozin-child' ); ?>
					</a>
				</div>

				<?php
				/*
				 * Критерий — средний рейтинг товара (_wc_average_rating, нативное
				 * поле WooCommerce). Если на сайте окажется отдельный механизм
				 * подсчёта просмотров товара (например, плагин "post views
				 * counter" или собственное поле, добавленное подрядчиком CLICK
				 * WEB DESIGN), логичнее заменить meta_key на него — тогда
				 * "популярное" будет означать "часто просматриваемое", что ещё
				 * заметнее отличается от "Топ продаж". Проверить на реальном
				 * сайте при первом деплое.
				 */
				$ip_popular_query = new WP_Query(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 4,
						'meta_key'       => '_wc_average_rating',
						'orderby'        => 'meta_value_num',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				);

				if ( $ip_popular_query->have_posts() ) :
					?>
					<ul class="ip-products-grid">
						<?php
						while ( $ip_popular_query->have_posts() ) :
							$ip_popular_query->the_post();
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

	<?php else : ?>

		<section class="ip-section">
			<div class="container">
				<p><?php esc_html_e( 'WooCommerce не активен — блоки "Топ продаж" и "Популярные товары" появятся после активации плагина.', 'glozin-child' ); ?></p>
			</div>
		</section>

	<?php endif; ?>

</div><!-- .industrial-pro -->

<!-- =====================================================================
     6. Футер-полоса (акцентный нижний слой — не заменяет основной футер темы)
     ===================================================================== -->
<div class="ip-footer-bar">
	<div class="container">
		<span class="ip-footer-bar__wordmark">
			MEȘTERUL CASEI <span>CASA24.MD</span>
		</span>
		<span class="ip-footer-bar__copy">
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Casa24.md — LAROMVAL SRL. <?php esc_html_e( 'Все права защищены.', 'glozin-child' ); ?>
		</span>
	</div>
</div>

<?php
get_footer();
