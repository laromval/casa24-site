<?php
/**
 * Футер Industrial Pro + кнопка «наверх». Выводится на wp_footer
 * (inc/industrial-pro.php) там же, где шапка Industrial Pro.
 *
 * @package Glozin Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Ссылки футера — по ID страниц (RU-оригиналы), а не по слагам:
 * слаги вида /o-nas/, /contacte/, /obmen-i-vozvrat/ на сайте не существуют
 * (404 на проде 27.10.2026, отчёт qa), а RO-версии у страниц свои адреса.
 * wpml_object_id подставляет перевод на текущем языке. ID одинаковые на
 * проде и staging (проверено 27.10.2026).
 */
$ip_footer_page = static function ( $page_id ) {
	$url = get_permalink( apply_filters( 'wpml_object_id', $page_id, 'page', true ) );

	return $url ? $url : home_url( '/' );
};

/*
 * «Сертификаты» (28.10.2026) — страница создана скриптом
 * tools/certificates/certificates-page.php, ID на проде и staging разные —
 * ищем RU-оригинал по слагу. Нет страницы — пункт не выводится.
 */
$ip_cert_page           = get_page_by_path( 'sertifikaty' );
$ip_footer_certificates = $ip_cert_page ? get_permalink( apply_filters( 'wpml_object_id', $ip_cert_page->ID, 'page', true ) ) : '';

/* Соцсети и карта: пустая строка — пункт не выводится (адресов пока нет, ссылки "#" вели в никуда). */
$ip_footer_social = array(
	'Facebook'  => '',
	'Instagram' => '',
	'Telegram'  => '',
);
$ip_footer_map_url = '';
?>
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
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/logo-casa24.svg' ); ?>" alt="Casa24" width="160" height="38">
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
				/* TODO (marketing-agent): реальные адреса соцсетей — в $ip_footer_social вверху файла. */
				$ip_social_icons = array(
					'Facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>',
					'Instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1"></circle>',
					'Telegram'  => '<path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path>',
				);
				$ip_social_links = array_filter( $ip_footer_social );
				?>
				<?php if ( $ip_social_links ) : ?>
					<div class="ip-footer__social-links">
						<?php foreach ( $ip_social_links as $ip_social_name => $ip_social_url ) : ?>
							<a href="<?php echo esc_url( $ip_social_url ); ?>" aria-label="<?php echo esc_attr( $ip_social_name ); ?>" target="_blank" rel="noopener"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><?php echo $ip_social_icons[ $ip_social_name ]; // phpcs:ignore WordPress.Security.EscapeOutput -- статичный SVG. ?></svg></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

		</div>
	</div>

	<div class="ip-footer__main">
		<div class="container">

			<div class="ip-footer__cols" id="ip-footer-cols" data-ip-footer-cols>

				<div class="ip-footer__col">
					<h3><?php esc_html_e( 'Интернет-магазин', 'glozin-child' ); ?></h3>
					<ul>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1000696 ) ); ?>"><?php esc_html_e( 'Доставка и оплата', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1085732 ) ); ?>"><?php esc_html_e( 'Публичная оферта', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1085726 ) ); ?>"><?php esc_html_e( 'Обмен и возврат товара', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1080727 ) ); ?>"><?php esc_html_e( 'Политика конфиденциальности', 'glozin-child' ); ?></a></li>
						<li><a href="#" data-c24-consent-open><?php esc_html_e( 'Настройки cookie', 'glozin-child' ); ?></a></li>
					</ul>
				</div>

				<div class="ip-footer__col">
					<h3><?php esc_html_e( 'О компании', 'glozin-child' ); ?></h3>
					<ul>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1000810 ) ); ?>"><?php esc_html_e( 'О нас', 'glozin-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1000799 ) ); ?>"><?php esc_html_e( 'Статьи', 'glozin-child' ); ?></a></li>
						<?php if ( $ip_footer_certificates ) : ?>
							<li><a href="<?php echo esc_url( $ip_footer_certificates ); ?>"><?php esc_html_e( 'Сертификаты', 'glozin-child' ); ?></a></li>
						<?php endif; ?>
						<li><a href="<?php echo esc_url( $ip_footer_page( 1000804 ) ); ?>"><?php esc_html_e( 'Контакты', 'glozin-child' ); ?></a></li>
					</ul>
				</div>

			</div>

		</div>
	</div>

	<div class="ip-footer__bottom">
		<div class="container ip-footer__bottom-row">
			<?php if ( $ip_footer_map_url ) : ?>
				<a class="ip-footer__bottom-link" href="<?php echo esc_url( $ip_footer_map_url ); ?>" target="_blank" rel="noopener">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					<?php esc_html_e( 'Как нас найти', 'glozin-child' ); ?>
				</a>
			<?php endif; ?>
			<a class="ip-footer__bottom-link" href="<?php echo esc_url( $ip_footer_page( 1000696 ) ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="7" width="15" height="13" rx="1"></rect><path d="M16 10h4l3 3v4h-7z"></path><circle cx="6" cy="20" r="2"></circle><circle cx="18" cy="20" r="2"></circle></svg>
				<?php esc_html_e( 'Доставка и оплата', 'glozin-child' ); ?>
			</a>
			<span class="ip-footer__copy">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Casa24. <?php esc_html_e( 'Все права защищены.', 'glozin-child' ); ?>
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
