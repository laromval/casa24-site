<?php
/**
 * Plugin Name: Casa24 — товары поставщиков
 * Description: Товар, взятый с сайта поставщика (сейчас instrumentmarket.md), помечается в карточке WooCommerce: поставщик, ссылка на его страницу, код у поставщика. Поставщик виден в списке товаров (колонка и фильтр), в админке заказа под каждой позицией и в письме «Новый заказ» администратору. Цена и наличие обновляются с сайта поставщика автоматически (WP-Cron и `wp casa24-supplier sync`).
 *
 * Решение владельца 31.10.2026: цены на сайте поставщика уже с нашей надбавкой — берём как есть;
 * нет в наличии у поставщика — «Нет в наличии» у нас; покупатель этих полей не видит.
 *
 * Метки товара (у RU-оригинала и у RO-перевода): _casa24_supplier (ключ из casa24_suppliers()),
 * _casa24_supplier_url, _casa24_supplier_id, _casa24_supplier_synced (время последней сверки).
 * В позицию заказа поставщик копируется при оформлении (_casa24_supplier*), поэтому старые заказы
 * не меняются, если карточку потом поправят.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Справочник поставщиков: ключ => название, сайт, как читать страницу товара.
 */
function casa24_suppliers() {
	return array(
		'instrumentmarket' => array(
			'name' => 'instrumentmarket.md',
			'site' => 'https://instrumentmarket.md',
		),
	);
}

/** RU-оригинал и все переводы товара (WPML). */
function casa24_supplier_product_ids( $product_id ) {
	$ids = array( (int) $product_id );
	foreach ( array( 'ru', 'ro' ) as $lang ) {
		$tr = (int) apply_filters( 'wpml_object_id', $product_id, 'product', false, $lang );
		if ( $tr ) {
			$ids[] = $tr;
		}
	}
	return array_values( array_unique( $ids ) );
}

/** Поставщик товара (для вариации — по родителю): array( key, name, url, id ) или null. */
function casa24_product_supplier( $product_id ) {
	$product_id = (int) $product_id;
	$parent     = (int) wp_get_post_parent_id( $product_id );
	foreach ( array_filter( array( $product_id, $parent ) ) as $id ) {
		$key = get_post_meta( $id, '_casa24_supplier', true );
		if ( $key ) {
			$list = casa24_suppliers();
			return array(
				'key'  => $key,
				'name' => isset( $list[ $key ] ) ? $list[ $key ]['name'] : $key,
				'url'  => (string) get_post_meta( $id, '_casa24_supplier_url', true ),
				'id'   => (string) get_post_meta( $id, '_casa24_supplier_id', true ),
			);
		}
	}
	return null;
}

/* ---------- карточка товара: вкладка «Основные» ---------- */

add_action(
	'woocommerce_product_options_general_product_data',
	function () {
		echo '<div class="options_group">';
		$options = array( '' => 'Свой склад / 1С' );
		foreach ( casa24_suppliers() as $key => $s ) {
			$options[ $key ] = $s['name'];
		}
		woocommerce_wp_select(
			array(
				'id'          => '_casa24_supplier',
				'label'       => 'Поставщик',
				'options'     => $options,
				'description' => 'Только для администратора: у кого заказывать товар. Цена и наличие обновляются с сайта поставщика сами.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_casa24_supplier_url',
				'label'       => 'Ссылка у поставщика',
				'type'        => 'url',
				'description' => 'Страница этого товара на сайте поставщика.',
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => '_casa24_supplier_id',
				'label' => 'Код у поставщика',
			)
		);
		global $post;
		$synced = $post ? (int) get_post_meta( $post->ID, '_casa24_supplier_synced', true ) : 0;
		if ( $synced ) {
			echo '<p class="form-field"><label>Сверено с поставщиком</label><span>' . esc_html( wp_date( 'd.m.Y H:i', $synced ) ) . '</span></p>';
		}
		echo '</div>';
	}
);

add_action(
	'woocommerce_process_product_meta',
	function ( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- nonce проверяет WooCommerce до этого хука
		$key = isset( $_POST['_casa24_supplier'] ) ? sanitize_key( wp_unslash( $_POST['_casa24_supplier'] ) ) : '';
		$url = isset( $_POST['_casa24_supplier_url'] ) ? esc_url_raw( wp_unslash( $_POST['_casa24_supplier_url'] ) ) : '';
		$sid = isset( $_POST['_casa24_supplier_id'] ) ? sanitize_text_field( wp_unslash( $_POST['_casa24_supplier_id'] ) ) : '';
		if ( $key && ! isset( casa24_suppliers()[ $key ] ) ) {
			$key = '';
		}
		foreach ( casa24_supplier_product_ids( $post_id ) as $id ) {
			if ( $key ) {
				update_post_meta( $id, '_casa24_supplier', $key );
				update_post_meta( $id, '_casa24_supplier_url', $url );
				update_post_meta( $id, '_casa24_supplier_id', $sid );
			} else {
				delete_post_meta( $id, '_casa24_supplier' );
				delete_post_meta( $id, '_casa24_supplier_url' );
				delete_post_meta( $id, '_casa24_supplier_id' );
			}
		}
	}
);

/* ---------- список товаров: колонка и фильтр ---------- */

add_filter(
	'manage_edit-product_columns',
	function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'name' === $k ) {
				$out['casa24_supplier'] = 'Поставщик';
			}
		}
		return $out;
	},
	20
);

add_action(
	'manage_product_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'casa24_supplier' !== $col ) {
			return;
		}
		$s = casa24_product_supplier( $post_id );
		if ( ! $s ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}
		echo $s['url'] ? '<a href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $s['name'] ) . '</a>' : esc_html( $s['name'] );
	},
	10,
	2
);

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'product' !== $post_type ) {
			return;
		}
		$cur = isset( $_GET['casa24_supplier'] ) ? sanitize_key( wp_unslash( $_GET['casa24_supplier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="casa24_supplier"><option value="">Все поставщики</option>';
		echo '<option value="_any"' . selected( $cur, '_any', false ) . '>Любой поставщик</option>';
		echo '<option value="_none"' . selected( $cur, '_none', false ) . '>Свой склад / 1С</option>';
		foreach ( casa24_suppliers() as $key => $s ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $cur, $key, false ) . '>' . esc_html( $s['name'] ) . '</option>';
		}
		echo '</select>';
	}
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'product' !== $q->get( 'post_type' ) || empty( $_GET['casa24_supplier'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$v    = sanitize_key( wp_unslash( $_GET['casa24_supplier'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$meta = (array) $q->get( 'meta_query' );
		if ( '_none' === $v ) {
			$meta[] = array( 'key' => '_casa24_supplier', 'compare' => 'NOT EXISTS' );
		} elseif ( '_any' === $v ) {
			$meta[] = array( 'key' => '_casa24_supplier', 'compare' => 'EXISTS' );
		} else {
			$meta[] = array( 'key' => '_casa24_supplier', 'value' => $v );
		}
		$q->set( 'meta_query', $meta );
	}
);

/* ---------- заказ: поставщик в позиции, в админке и в письме админу ---------- */

add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_item_key, $values ) {
		$pid = ! empty( $values['variation_id'] ) ? $values['variation_id'] : $values['product_id'];
		$s   = casa24_product_supplier( $pid );
		if ( $s ) {
			$item->add_meta_data( '_casa24_supplier', $s['name'], true );
			$item->add_meta_data( '_casa24_supplier_url', $s['url'], true );
			$item->add_meta_data( '_casa24_supplier_id', $s['id'], true );
		}
	},
	10,
	3
);

/** Поставщик позиции заказа: сохранённый при оформлении, иначе — по текущей карточке (заказы до включения плагина). */
function casa24_order_item_supplier( $item ) {
	$name = $item->get_meta( '_casa24_supplier' );
	if ( $name ) {
		return array(
			'name' => $name,
			'url'  => (string) $item->get_meta( '_casa24_supplier_url' ),
			'id'   => (string) $item->get_meta( '_casa24_supplier_id' ),
		);
	}
	if ( $item instanceof WC_Order_Item_Product ) {
		return casa24_product_supplier( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
	}
	return null;
}

add_action(
	'woocommerce_after_order_itemmeta',
	function ( $item_id, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$s = casa24_order_item_supplier( $item );
		if ( ! $s ) {
			return;
		}
		echo '<div style="margin-top:4px;padding:4px 8px;background:#fff7e0;border-left:3px solid #dba617"><strong>Заказать у поставщика:</strong> ';
		echo $s['url'] ? '<a href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $s['name'] ) . '</a>' : esc_html( $s['name'] );
		if ( $s['id'] ) {
			echo ' · код ' . esc_html( $s['id'] );
		}
		echo '</div>';
	},
	10,
	2
);

add_action(
	'woocommerce_email_after_order_table',
	function ( $order, $sent_to_admin, $plain_text ) {
		if ( ! $sent_to_admin ) {
			return;
		}
		$rows = array();
		foreach ( $order->get_items() as $item ) {
			$s = casa24_order_item_supplier( $item );
			if ( $s ) {
				$rows[] = array( $item->get_name() . ' × ' . $item->get_quantity(), $s );
			}
		}
		if ( ! $rows ) {
			return;
		}
		if ( $plain_text ) {
			echo "\nЗАКАЗАТЬ У ПОСТАВЩИКА:\n";
			foreach ( $rows as $r ) {
				echo '- ' . $r[0] . ' — ' . $r[1]['name'] . ' ' . $r[1]['url'] . "\n";
			}
			return;
		}
		echo '<h3>Заказать у поставщика</h3><ul>';
		foreach ( $rows as $r ) {
			echo '<li>' . esc_html( $r[0] ) . ' — ' . ( $r[1]['url'] ? '<a href="' . esc_url( $r[1]['url'] ) . '">' . esc_html( $r[1]['name'] ) . '</a>' : esc_html( $r[1]['name'] ) ) . '</li>';
		}
		echo '</ul>';
	},
	10,
	3
);

/* ---------- автообновление цены и наличия ---------- */

/**
 * Читает страницу товара на сайте поставщика.
 * Возвращает array( 'status' => 'ok'|'gone'|'error', 'price' => float|null, 'in_stock' => bool|null ).
 * Любая неожиданность разметки — 'error' (ничего не меняем, чтобы сбой на их стороне не обнулил наличие).
 */
function casa24_supplier_fetch( $url ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => 25,
			'user-agent' => 'Mozilla/5.0 (Casa24 catalog sync)',
		)
	);
	if ( is_wp_error( $res ) ) {
		return array( 'status' => 'error' );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 404 === $code || 410 === $code ) {
		return array( 'status' => 'gone' );
	}
	$html = wp_remote_retrieve_body( $res );
	if ( 200 !== $code || false === strpos( $html, 'name="product_id"' ) ) {
		return array( 'status' => 'error' );
	}
	$price = preg_match( '/itemprop="price" content="([\d.]+)"/', $html, $m ) ? (float) $m[1] : null;
	if ( false !== strpos( $html, 'journal-stock instock' ) ) {
		$in_stock = true;
	} elseif ( false !== strpos( $html, 'journal-stock' ) ) {
		$in_stock = false;
	} else {
		return array( 'status' => 'error' );
	}
	return array(
		'status'   => 'ok',
		'price'    => $price,
		'in_stock' => $in_stock,
	);
}

/**
 * Сверяет все товары поставщика. $dry — только посчитать, не писать.
 * Цена, которая упала ниже 5 MDL или изменилась больше чем на 50 %, не применяется — попадает в 'suspicious'
 * (ошибка цены у поставщика не должна доехать до покупателей, как «дверь за 1 MDL»).
 */
function casa24_supplier_sync( $dry = false ) {
	$ids = get_posts(
		array(
			'post_type'        => 'product',
			'post_status'      => array( 'publish', 'draft', 'private' ),
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'meta_key'         => '_casa24_supplier', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => 'instrumentmarket', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$log = array(
		'checked'    => 0,
		'price'      => array(),
		'stock'      => array(),
		'suspicious' => array(),
		'errors'     => array(),
	);
	foreach ( $ids as $id ) {
		$url = get_post_meta( $id, '_casa24_supplier_url', true );
		// метки есть и у RO-переводов — сверяем по RU-оригиналу, переводы обновим вместе с ним
		if ( ! $url || (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ru' ) !== (int) $id ) {
			continue;
		}
		++$log['checked'];
		$r = casa24_supplier_fetch( $url );
		usleep( 600000 );
		if ( 'error' === $r['status'] ) {
			$log['errors'][] = $id;
			continue;
		}
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}
		$in_stock = ( 'ok' === $r['status'] ) && $r['in_stock'];
		$new_stat = $in_stock ? 'instock' : 'outofstock';
		$new_price = ( 'ok' === $r['status'] ) ? $r['price'] : null;
		$old_price = (float) $product->get_regular_price();
		$apply_price = null !== $new_price;
		if ( $apply_price ) {
			$bad = $new_price < 5 || ( $old_price > 0 && abs( $new_price - $old_price ) / $old_price > 0.5 );
			if ( $bad ) {
				$log['suspicious'][ $id ] = array( $old_price, $new_price );
				$apply_price             = false;
			}
		}
		$change_price = $apply_price && abs( $new_price - $old_price ) > 0.001;
		$change_stock = $product->get_stock_status() !== $new_stat;
		if ( $change_price ) {
			$log['price'][ $id ] = array( $old_price, $new_price );
		}
		if ( $change_stock ) {
			$log['stock'][ $id ] = $new_stat;
		}
		if ( $dry ) {
			continue;
		}
		foreach ( casa24_supplier_product_ids( $id ) as $tid ) {
			$p = wc_get_product( $tid );
			if ( ! $p ) {
				continue;
			}
			if ( $change_price ) {
				$p->set_regular_price( (string) $new_price );
				$p->set_sale_price( '' );
			}
			if ( $change_stock ) {
				$p->set_manage_stock( false );
				$p->set_stock_status( $new_stat );
			}
			if ( $change_price || $change_stock ) {
				$p->save();
			}
			update_post_meta( $tid, '_casa24_supplier_synced', time() );
		}
	}
	return $log;
}

/* ---------- автодобавление новых товаров поставщика ---------- */

/**
 * Какие разделы поставщика следим: path у поставщика => ID RU-раздела у нас.
 * Новый раздел добавляется тут (или option casa24_supplier_categories) после сопоставления с деревом каталога.
 */
function casa24_supplier_category_map() {
	return (array) get_option( 'casa24_supplier_categories', array( '486_487' => 1695, '507' => 1915 ) ); // Бензопилы => Пилы электрические и бензопилы; Тачки => Тачки
}

/** Коды товаров поставщика, которые не добавляем (дубль нашего товара из 1С и т. п.). */
function casa24_supplier_ignored_ids() {
	return array_map( 'strval', (array) get_option( 'casa24_supplier_ignore', array( '2761' ) ) ); // 2761 — Elefant CS5200, уже есть из 1С
}

function casa24_supplier_norm_url( $url ) {
	$url = html_entity_decode( $url );
	// часть товаров в списках даётся ссылкой index.php?route=product/product&product_id=N — query нужен, по ней товар и узнаём
	if ( preg_match( '/product_id=(\d+)/', $url, $m ) ) {
		return casa24_suppliers()['instrumentmarket']['site'] . '/index.php?route=product/product&product_id=' . $m[1];
	}
	return urldecode( strtok( $url, '?' ) );
}

function casa24_supplier_http( $url ) {
	$res = wp_remote_get( $url, array( 'timeout' => 25, 'user-agent' => 'Mozilla/5.0 (Casa24 catalog sync)' ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	return wp_remote_retrieve_body( $res );
}

/** Товары на скидке у поставщика (страница «Акции») — их пока не добавляем. Возвращает null, если страницу не прочитали. */
function casa24_supplier_special_urls() {
	$set  = array();
	$site = casa24_suppliers()['instrumentmarket']['site'];
	for ( $page = 1; $page <= 20; $page++ ) {
		$html = casa24_supplier_http( $site . '/index.php?route=product/special&limit=100&page=' . $page );
		if ( null === $html ) {
			return $page > 1 ? $set : null;
		}
		$n = preg_match_all( '/<h4 class="name"><a href="([^"]+)"/', $html, $m );
		foreach ( $m[1] as $u ) {
			$set[ casa24_supplier_norm_url( $u ) ] = true;
		}
		if ( ! $n || false === strpos( $html, 'page=' . ( $page + 1 ) ) ) {
			break;
		}
	}
	return $set;
}

/** Товары раздела поставщика: URL => true, если «нет в наличии» (по списку раздела). */
function casa24_supplier_list_category( $path ) {
	$items = array();
	$site  = casa24_suppliers()['instrumentmarket']['site'];
	for ( $page = 1; $page <= 30; $page++ ) {
		$html = casa24_supplier_http( $site . '/index.php?route=product/category&path=' . $path . '&limit=100&page=' . $page );
		if ( null === $html ) {
			return null;
		}
		$found = 0;
		// боковой блок «популярные» стоит выше основного списка — берём только товары самого раздела
		$main = false !== strpos( $html, 'main-products' ) ? substr( $html, strpos( $html, 'main-products' ) ) : '';
		foreach ( array_slice( preg_split( '/<div class="product-grid-item/', $main ), 1 ) as $blk ) {
			if ( preg_match( '/<(?:h4|div) class="name"><a href="([^"]+)"/', $blk, $m ) ) {
				++$found;
				$items[ html_entity_decode( $m[1] ) ] = false !== strpos( $blk, 'label-outofstock' );
			}
		}
		if ( ! $found || false === strpos( $main, 'page=' . ( $page + 1 ) ) ) {
			break;
		}
	}
	return $items;
}

/** Название без КАПСА поставщика: русские слова заглавными — строчными, бренд — с заглавной; модели не трогаем. */
function casa24_supplier_tidy_title( $title, $brand ) {
	$words = preg_split( '/\s+/u', trim( $title ) );
	foreach ( $words as $i => $w ) {
		if ( $brand && 0 === mb_strlen( $brand ) - mb_strlen( $w ) && mb_strtolower( $w ) === mb_strtolower( $brand ) ) {
			$w = $brand;
		} elseif ( preg_match( '/\p{Cyrillic}/u', $w ) && ! preg_match( '/\d/', $w ) && mb_strtoupper( $w ) === $w && mb_strlen( $w ) > 1 ) {
			$w = mb_strtolower( $w );
		} elseif ( $brand && 0 === strcasecmp( $w, $brand ) ) {
			$w = $brand;
		} elseif ( preg_match( '/^[A-Z]{4,}$/', $w ) ) {
			$w = ucfirst( strtolower( $w ) );
		}
		$words[ $i ] = $w;
	}
	$out = implode( ' ', $words );
	return mb_strtoupper( mb_substr( $out, 0, 1 ) ) . mb_substr( $out, 1 );
}

/** Описание поставщика → чистый HTML: заголовок, список «параметр: значение». */
function casa24_supplier_clean_description( $html ) {
	$t     = preg_replace( '#</p>|<br ?/?>|</li>|</div>#i', "\n", $html );
	$lines = array();
	foreach ( explode( "\n", $t ) as $l ) {
		$l = trim( preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', html_entity_decode( wp_strip_all_tags( $l ) ) ) ) );
		if ( '' !== $l ) {
			$lines[] = $l;
		}
	}
	$out = '';
	$ul  = '';
	foreach ( $lines as $l ) {
		if ( ':' === substr( $l, -1 ) && mb_strlen( $l ) < 40 ) {
			$out .= $ul ? "<ul>$ul</ul>" : '';
			$ul   = '';
			$out .= '<p><strong>' . esc_html( $l ) . '</strong></p>';
		} elseif ( preg_match( '/^([^:]{2,40}?)\s:\s*(.+)$/u', $l, $m ) ) {
			$ul .= '<li><strong>' . esc_html( trim( $m[1] ) ) . ':</strong> ' . esc_html( trim( $m[2] ) ) . '</li>';
		} else {
			$ul .= '<li>' . esc_html( $l ) . '</li>';
		}
	}
	return $out . ( $ul ? "<ul>$ul</ul>" : '' );
}

/** Данные карточки поставщика для создания товара или null, если страница не разобралась. */
function casa24_supplier_parse_product( $html, $url ) {
	if ( false === strpos( $html, 'name="product_id"' ) || ! preg_match( '/name="product_id" value="(\d+)"/', $html, $m ) ) {
		return null;
	}
	$d       = array( 'supplier_id' => $m[1], 'supplier_url' => $url );
	$d['title'] = preg_match( '#<h1[^>]*>(.*?)</h1>#s', $html, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ) ) ) : '';
	$d['price'] = preg_match( '/itemprop="price" content="([\d.]+)"/', $html, $m ) ? (float) $m[1] : 0;
	$d['brand'] = preg_match( '#class="p-brand">[^<]*<a[^>]*>(.*?)</a>#s', $html, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : '';
	$d['model'] = preg_match( '#itemprop="model">(.*?)</span>#s', $html, $m ) ? trim( wp_strip_all_tags( $m[1] ) ) : '';
	$d['in_stock'] = false !== strpos( $html, 'journal-stock instock' );
	$d['desc']  = preg_match( '#<div[^>]+id="tab-description"[^>]*>(.*?)</div>\s*(?:<div[^>]+id="tab-|</div>)#s', $html, $m ) ? $m[1] : '';
	preg_match_all( '#(?:data-largeimg|href)="(https://instrumentmarket\.md/image/(?:cache/)?[^"]+\.(?:jpg|jpeg|png|webp))"#', $html, $im );
	$imgs = array();
	foreach ( array_unique( $im[1] ) as $u ) {
		if ( false === stripos( $u, 'Logo' ) ) {
			$imgs[] = $u;
		}
	}
	// фото из папки другого товара (номер папки ≠ код модели) отбрасываем, если у этого товара есть фото из «своей» папки
	$own = array_filter( $imgs, function ( $u ) use ( $d ) { return $d['model'] && false !== strpos( $u, '/' . $d['model'] . '/' ); } );
	if ( $own ) {
		$imgs = array_values( array_filter( $imgs, function ( $u ) use ( $d ) {
			return ! preg_match( '#/(\d{4,6})/#', $u, $f ) || $f[1] === $d['model'];
		} ) );
	}
	$d['images'] = array_slice( $imgs, 0, 6 );
	return $d;
}

/** Создаёт RU-товар по разобранной карточке. Возвращает ID или null. */
function casa24_supplier_create_product( $d, $cat_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$title = casa24_supplier_tidy_title( $d['title'], $d['brand'] );
	$sku   = 'IM-' . $d['model'];
	if ( ! $d['model'] || wc_get_product_id_by_sku( $sku ) ) {
		return null;
	}
	$p = new WC_Product_Simple();
	$p->set_name( $title );
	$p->set_status( 'publish' );
	$p->set_sku( $sku );
	$p->set_regular_price( (string) $d['price'] );
	$p->set_description( casa24_supplier_clean_description( $d['desc'] ) );
	$p->set_manage_stock( false );
	$p->set_stock_status( 'instock' );
	$p->set_category_ids( array( (int) $cat_id ) );
	if ( $d['brand'] ) {
		$tax  = 'pa_proizvoditel';
		$term = term_exists( $d['brand'], $tax ) ? term_exists( $d['brand'], $tax ) : wp_insert_term( $d['brand'], $tax );
		if ( ! is_wp_error( $term ) ) {
			$attr = new WC_Product_Attribute();
			$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
			$attr->set_name( $tax );
			$attr->set_options( array( (int) $term['term_id'] ) );
			$attr->set_visible( true );
			$p->set_attributes( array( $attr ) );
		}
	}
	$id  = $p->save();
	$ids = array();
	foreach ( $d['images'] as $u ) {
		$aid = media_sideload_image( str_replace( ' ', '%20', $u ), $id, $title, 'id' );
		if ( ! is_wp_error( $aid ) ) {
			update_post_meta( $aid, '_wp_attachment_image_alt', $title );
			$ids[] = $aid;
		}
	}
	if ( $ids ) {
		$p->set_image_id( array_shift( $ids ) );
		$p->set_gallery_image_ids( $ids );
		$p->save();
	}
	update_post_meta( $id, '_casa24_supplier', 'instrumentmarket' );
	update_post_meta( $id, '_casa24_supplier_url', $d['supplier_url'] );
	update_post_meta( $id, '_casa24_supplier_id', $d['supplier_id'] );
	update_post_meta( $id, '_casa24_supplier_synced', time() );
	return $id;
}

/**
 * Ищет в отслеживаемых разделах товары, которых у нас ещё нет, и добавляет те, что в наличии и не на скидке.
 * Нет фото у товара поставщика — не добавляем (товары без фото всё равно скрыты витриной).
 * Похожий товар уже есть у нас (та же модель в названии) — не добавляем, а пишем в 'conflicts'.
 * RO-версию новых товаров делает навык perevod-ro (wp eval-file ro-missing.php покажет очередь).
 */
function casa24_supplier_discover( $dry = false, $max_new = 10 ) {
	global $wpdb;
	$log     = array( 'added' => array(), 'conflicts' => array(), 'skipped' => 0, 'errors' => array() );
	$known   = array();
	foreach ( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_casa24_supplier_url'" ) as $u ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$known[ casa24_supplier_norm_url( $u ) ] = true;
	}
	$known_ids = array_flip( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_casa24_supplier_id'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ignored   = casa24_supplier_ignored_ids();
	$special = casa24_supplier_special_urls();
	if ( null === $special ) {
		$log['errors'][] = 'special page unreadable';
		return $log; // не уверены, что отсеем скидки — ничего не добавляем
	}
	foreach ( casa24_supplier_category_map() as $path => $cat_id ) {
		$list = casa24_supplier_list_category( $path );
		if ( null === $list ) {
			$log['errors'][] = "category $path unreadable";
			continue;
		}
		foreach ( $list as $url => $oos ) {
			$norm = casa24_supplier_norm_url( $url );
			if ( isset( $known[ $norm ] ) || $oos || isset( $special[ $norm ] ) ) {
				++$log['skipped'];
				continue;
			}
			if ( count( $log['added'] ) >= $max_new ) {
				break 2;
			}
			$html = casa24_supplier_http( $url );
			usleep( 600000 );
			$d = $html ? casa24_supplier_parse_product( $html, $norm ) : null;
			if ( $d && isset( $known_ids[ $d['supplier_id'] ] ) ) {
				++$log['skipped'];
				continue;
			}
			if ( ! $d || ! $d['in_stock'] || $d['price'] < 5 || ! $d['images'] || in_array( $d['supplier_id'], $ignored, true ) ) {
				if ( $d ) {
					++$log['skipped'];
				} else {
					$log['errors'][] = $url;
				}
				continue;
			}
			$title = casa24_supplier_tidy_title( $d['title'], $d['brand'] );
			// «Та же модель» = в названии нашего товара есть код модели поставщика (латиница+цифры, напр. WB6419, CS5200)
			$dup = 0;
			if ( preg_match_all( '/[A-Za-z0-9][A-Za-z0-9.\-]*/', $title, $mt ) ) {
				foreach ( $mt[0] as $token ) {
					if ( strlen( $token ) < 4 || ! preg_match( '/[A-Za-z]/', $token ) || ! preg_match( '/\d/', $token ) ) {
						continue;
					}
					// код модели у разных брендов бывает общим (WB6419 у Elefant, Rotor, Partner…) — совпадать должен и бренд
					$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private') AND post_title LIKE %s AND post_title LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $token ) . '%', '%' . $wpdb->esc_like( $d['brand'] ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					if ( $dup ) {
						break;
					}
				}
			}
			if ( $dup ) {
				$log['conflicts'][ $d['supplier_id'] ] = array( $title, $dup );
				continue;
			}
			if ( $dry ) {
				$log['added'][ $d['supplier_id'] ] = $title . ' — ' . $d['price'];
				continue;
			}
			$id = casa24_supplier_create_product( $d, $cat_id );
			if ( $id ) {
				$log['added'][ $id ] = $title . ' — ' . $d['price'];
				$known[ $norm ]      = true;
			} else {
				$log['errors'][] = $url;
			}
		}
	}
	return $log;
}

/** Полная сверка: цены и наличие у загруженных, затем поиск и добавление новых. Итог — в option casa24_supplier_last_sync. */
function casa24_supplier_run( $dry = false, $with_new = true ) {
	$log = casa24_supplier_sync( $dry );
	if ( $with_new ) {
		$log['new'] = casa24_supplier_discover( $dry );
	}
	if ( ! $dry ) {
		update_option( 'casa24_supplier_last_sync', array( 'time' => time() ) + $log, false );
	}
	return $log;
}

/** Часы сверки по времени сайта (Кишинёв): утро, обед, вечер. */
const CASA24_SUPPLIER_SYNC_HOURS = array( 7, 13, 19 );

/** Ближайший час сверки после «сейчас» (метка времени). */
function casa24_supplier_next_slot() {
	$tz  = new DateTimeZone( 'Europe/Chisinau' ); // время сайта в настройках WordPress — UTC, поэтому часы задаём явно
	$now = new DateTimeImmutable( 'now', $tz );
	foreach ( array( 0, 1 ) as $day ) {
		foreach ( CASA24_SUPPLIER_SYNC_HOURS as $h ) {
			$t = $now->setTime( $h, 0 )->modify( "+$day day" );
			if ( $t > $now ) {
				return $t->getTimestamp();
			}
		}
	}
	return time() + 6 * HOUR_IN_SECONDS;
}

// Каждый запуск сам назначает следующий. Если системный cron уже сверил товары меньше получаса назад — не дублируем.
add_action(
	'casa24_supplier_run_event',
	function () {
		wp_schedule_single_event( casa24_supplier_next_slot(), 'casa24_supplier_run_event' );
		$last = get_option( 'casa24_supplier_last_sync' );
		if ( ! $last || time() - (int) $last['time'] > 30 * MINUTE_IN_SECONDS ) {
			casa24_supplier_run();
		}
	}
);
add_action(
	'init',
	function () {
		wp_clear_scheduled_hook( 'casa24_supplier_sync_event' ); // прежнее расписание «раз в 12 часов»
		if ( wp_next_scheduled( 'casa24_supplier_run_event' ) ) {
			return;
		}
		wp_schedule_single_event( casa24_supplier_next_slot(), 'casa24_supplier_run_event' );
	}
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Сверка цен и наличия товаров поставщика: wp casa24-supplier sync [--dry-run]
	 */
	WP_CLI::add_command(
		'casa24-supplier sync',
		function ( $args, $assoc ) {
			$log = casa24_supplier_run( ! empty( $assoc['dry-run'] ), empty( $assoc['no-new'] ) );
			WP_CLI::log( wp_json_encode( $log, JSON_UNESCAPED_UNICODE ) );
			WP_CLI::success( 'проверено ' . $log['checked'] . ', цены ' . count( $log['price'] ) . ', наличие ' . count( $log['stock'] ) . ', подозрительных ' . count( $log['suspicious'] ) . ', ошибок чтения ' . count( $log['errors'] ) . ( isset( $log['new'] ) ? ', новых ' . count( $log['new']['added'] ) . ', совпадений с нашими ' . count( $log['new']['conflicts'] ) : '' ) );
		}
	);
}
