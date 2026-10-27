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
				<?php glozin_child_card_cart_button( $product ); ?>
			</div>
		</div>
	</li>
	<?php
}

get_header();
?>

<div class="industrial-pro">

	<?php /* Шапка — template-parts/industrial-pro/header.php, выводится на wp_body_open (inc/industrial-pro.php). */ ?>

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
					// ID категорий — из каталога casa24.md (Store API, 25.10.2026).
					array(
						// Садово-огородные > Лопаты, грабли, вилы.
						'label' => __( 'Инвентарь', 'glozin-child' ),
						'href'  => glozin_child_category_url( 820 ),
					),
					array(
						// Садово-огородные > Тачки.
						'label' => __( 'Тачки', 'glozin-child' ),
						'href'  => glozin_child_category_url( 836 ),
					),
					array(
						// Садово-огородные > Для сбора мусора.
						'label' => __( 'Мешки', 'glozin-child' ),
						'href'  => glozin_child_category_url( 823 ),
					),
					array(
						// Товары для дома > Ведра.
						'label' => __( 'Вёдра', 'glozin-child' ),
						'href'  => glozin_child_category_url( 869 ),
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
						'href'  => glozin_child_category_url( 868 ), // Банки стеклянные,
					),
					array(
						'label' => __( 'Пищевые вёдра', 'glozin-child' ),
						'href'  => glozin_child_category_url( 870 ), // Ведра пищевые,
					),
					array(
						'label' => __( 'Бочки пищевые', 'glozin-child' ),
						'href'  => glozin_child_category_url( 961 ), // Бидоны, бочки,
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
				<a class="ip-section__link" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) ); ?>">
					<?php esc_html_e( 'Все категории', 'glozin-child' ); ?>
				</a>
			</div>

			<?php
			/*
			 * Основные разделы магазина (обратная связь 25.10.2026) — вместо
			 * первых попавшихся get_terms() (выходили бренды AIST/BINGO/DOLEPAK).
			 * ID — категории верхнего уровня casa24.md (Store API, 25.10.2026),
			 * отобраны по профилю магазина и числу товаров. Иконки — свои,
			 * контурные 24×24 в стиле остальных иконок шаблона.
			 */
			$ip_main_categories = array(
				841  => '<path d="M3 11l9-8 9 8"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',                                                                                         // Товары для дома.
				910  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>', // Ручной инструмент.
				886  => '<rect x="2" y="6" width="12" height="6" rx="1"/><path d="M14 8h4l3 1-3 1h-4"/><path d="M6 12v7a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-7"/>',                                     // Электроинструмент.
				815  => '<path d="M12 21V11"/><path d="M12 11C12 7 9 4 4 4c0 4 3 7 8 7z"/><path d="M12 14c0-3.5 2.5-6 7-6 0 3.5-2.5 6-7 6z"/><path d="M7 21h10"/>',                               // Садово-огородные.
				1351 => '<path d="M12 2.7l5.66 5.66a8 8 0 1 1-11.31 0z"/>',                                                                                                                        // Сантехника.
				1282 => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',                                                                                                                                  // Электрооборудование.
				813  => '<path d="M6 21V3h12v18"/><path d="M3 21h18"/><path d="M14.5 12h.01"/>',                                                                                                         // Двери (вместо «Строительного крепежа», обратная связь 25.10.2026).
				1172 => '<rect x="3" y="3" width="15" height="6" rx="1"/><path d="M18 6h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-8v3"/><rect x="10" y="14" width="4" height="7" rx="1"/>',               // Декоративно-отделочные материалы.
			);

			$ip_categories = array();
			if ( taxonomy_exists( 'product_cat' ) ) {
				foreach ( $ip_main_categories as $ip_cat_id => $ip_cat_icon ) {
					// На RO-версии WPML подставляет румынскую категорию.
					$ip_cat = get_term( (int) apply_filters( 'wpml_object_id', $ip_cat_id, 'product_cat', true ), 'product_cat' );
					if ( $ip_cat && ! is_wp_error( $ip_cat ) ) {
						// Число товаров с учётом подкатегорий: WordPress в ->count считает
						// только товары, лежащие прямо в категории (у разделов верхнего
						// уровня это почти 0), а WooCommerce хранит полный пересчёт в мете.
						$ip_count = get_term_meta( $ip_cat->term_id, 'product_count_product_cat', true );

						$ip_categories[] = array(
							'term'  => $ip_cat,
							'icon'  => $ip_cat_icon,
							'count' => '' !== $ip_count ? (int) $ip_count : (int) $ip_cat->count,
						);
					}
				}
			}

			if ( ! empty( $ip_categories ) ) :
				?>
				<ul class="ip-categories-grid">
					<?php foreach ( $ip_categories as $ip_item ) : ?>
						<li class="ip-category-card">
							<a class="ip-category-card__link" href="<?php echo esc_url( get_term_link( $ip_item['term'] ) ); ?>">
								<span class="ip-category-card__badge" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $ip_item['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- статичная разметка иконки из массива выше. ?></svg>
								</span>
								<span class="ip-category-card__text">
									<h3 class="ip-category-card__name"><?php echo esc_html( $ip_item['term']->name ); ?></h3>
									<?php if ( $ip_item['count'] > 0 ) : ?>
										<span class="ip-category-card__desc">
											<?php
											/* translators: %s: количество товаров в категории */
											echo esc_html( sprintf( __( 'Товаров: %s', 'glozin-child' ), number_format_i18n( $ip_item['count'] ) ) );
											?>
										</span>
									<?php endif; ?>
								</span>
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
						// Только в наличии (проверка qa-agent 27.10.2026, Н-3): первым
						// в блоке стоял товар «нет в наличии» с неактивной кнопкой.
						'meta_query'     => array(
							array(
								'key'   => '_stock_status',
								'value' => 'instock',
							),
						),
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

<?php /* Футер — template-parts/industrial-pro/footer.php, выводится на wp_footer. */ ?>

<?php
get_footer();
