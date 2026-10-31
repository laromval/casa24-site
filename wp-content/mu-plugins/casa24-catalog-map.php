<?php
/**
 * Plugin Name: Casa24 — каталог: новое дерево категорий поверх групп 1С
 * Description: Группы 1С остаются как есть (их приносит обмен), а покупатель видит новое дерево «раздел → группа → подгруппа». После каждой выгрузки товара из 1С товар ставится в новые категории по таблице соответствия (casa24-catalog/map.php). Решение владельца 28.10.2026: перестраивать группы в самой 1С некому.
 *
 * Как это устроено:
 * - Новые категории — обычные product_cat с метаполем _casa24_catalog_key
 *   (RU и RO-перевод WPML). У них нет _id_1c, поэтому обмен их не трогает:
 *   CategoriesProduct::setCategoryListToProduct() сохраняет у товара все
 *   категории «не из 1С».
 * - Старые категории 1С остаются у товара; страницы старых категорий
 *   переадресуются (301) на новые.
 * - Товар, который не удалось разложить, попадает в «Не разобрано»
 *   (покупатель её не видит) — её разбирают вручную.
 *
 * Дерево и соответствие правятся в tools/catalog/catalog_tree.py, затем
 * python tools/catalog/build_map.py. Подробности — docs/catalog-structure-proposal.md.
 *
 * WP-CLI:
 *   wp casa24 catalog tree [--dry-run]                   — создать/обновить новое дерево (RU + RO)
 *   wp casa24 catalog assign [--dry-run] [--ids=1,2]     — разложить товары
 *   wp casa24 catalog thumbs [--dry-run] [--force]       — картинки-значки групп и подгрупп
 *   wp casa24 catalog stats                              — сколько товаров в каждой новой категории
 *
 * Журнал — WooCommerce → Статус → Журналы, источник casa24-catalog.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CASA24_CATALOG_META = '_casa24_catalog_key';

/**
 * Таблица соответствия (сгенерирована из tools/catalog/catalog_tree.py).
 *
 * @return array
 */
function casa24_catalog_map() {
	static $map = null;

	if ( null === $map ) {
		$map = require __DIR__ . '/casa24-catalog/map.php';
	}

	return $map;
}

/**
 * Новые категории на языке: ключ => term_id.
 *
 * Прямой запрос, а не get_terms(): get_terms() WPML фильтрует по текущему
 * языку, а здесь нужен конкретный.
 *
 * @param string $lang  Код языка WPML (ru, ro).
 * @param bool   $fresh Перечитать из базы.
 * @return int[]
 */
function casa24_catalog_term_ids( $lang, $fresh = false ) {
	static $cache = array();
	global $wpdb;

	if ( $fresh || ! isset( $cache[ $lang ] ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_value AS catalog_key, t.term_id
				 FROM {$wpdb->termmeta} m
				 JOIN {$wpdb->term_taxonomy} t ON t.term_id = m.term_id AND t.taxonomy = 'product_cat'
				 JOIN {$wpdb->prefix}icl_translations i ON i.element_id = t.term_taxonomy_id AND i.element_type = 'tax_product_cat'
				 WHERE m.meta_key = %s AND i.language_code = %s",
				CASA24_CATALOG_META,
				$lang
			)
		);

		$cache[ $lang ] = array();
		foreach ( $rows as $row ) {
			$cache[ $lang ][ $row->catalog_key ] = (int) $row->term_id;
		}
	}

	return $cache[ $lang ];
}

/**
 * Новое дерево уже создано (есть хотя бы один раздел на основном языке).
 *
 * @return bool
 */
function casa24_catalog_is_ready() {
	return ! empty( casa24_catalog_term_ids( casa24_catalog_default_lang() ) );
}

/**
 * Основной язык WPML (на сайте — ru).
 *
 * @return string
 */
function casa24_catalog_default_lang() {
	return (string) apply_filters( 'wpml_default_language', 'ru' );
}

/**
 * Записать в журнал WooCommerce.
 *
 * @param string $message Сообщение.
 * @param string $level   info|warning|error.
 */
function casa24_catalog_log( $message, $level = 'info' ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->log( $level, $message, array( 'source' => 'casa24-catalog' ) );
	}
}

/* -------------------------------------------------------------------------
 * 1. Дерево категорий
 * ---------------------------------------------------------------------- */

/**
 * Создать или обновить новое дерево (RU + RO-перевод).
 *
 * Ничего не удаляет. Существующие категории ищутся по метаполю, поэтому
 * повторный запуск только обновляет названия, родителя и порядок.
 *
 * @param bool $dry_run Только показать, что будет сделано.
 * @return string[] Журнал действий.
 */
function casa24_catalog_build_tree( $dry_run = false ) {
	$map     = casa24_catalog_map();
	$default = casa24_catalog_default_lang();
	$log     = array();
	$nodes   = $map['tree'];

	$nodes[ $map['unsorted'] ] = $nodes[ $map['unsorted'] ] ?? array(
		'parent'  => '',
		'ru'      => 'Не разобрано',
		'ro'      => 'Nesortat',
		'slug_ro' => 'nesortat',
		'order'   => 99,
	);

	$ru_ids = casa24_catalog_term_ids( $default, true );
	$ro_ids = casa24_catalog_term_ids( 'ro', true );

	foreach ( $nodes as $key => $node ) {
		// Родитель всегда идёт в map.php раньше детей.
		$ru_parent = '' === $node['parent'] ? 0 : ( $ru_ids[ $node['parent'] ] ?? 0 );
		$ro_parent = '' === $node['parent'] ? 0 : ( $ro_ids[ $node['parent'] ] ?? 0 );

		$ru_ids[ $key ] = casa24_catalog_upsert_term( $key, $default, $node['ru'], $key, $ru_parent, (int) $node['order'], $ru_ids[ $key ] ?? 0, $dry_run, $log );

		if ( $dry_run && ! $ru_ids[ $key ] ) {
			$ro_ids[ $key ] = 0;
			continue;
		}

		$ro_ids[ $key ] = casa24_catalog_upsert_term( $key, 'ro', $node['ro'], $node['slug_ro'], $ro_parent, (int) $node['order'], $ro_ids[ $key ] ?? 0, $dry_run, $log, $ru_ids[ $key ] );
	}

	// Категории нового дерева, которых больше нет в map.php (раздел переименован
	// или разделён), — удалить. Это только наши категории: группы 1С не трогаем.
	foreach ( array( $default, 'ro' ) as $lang ) {
		foreach ( casa24_catalog_term_ids( $lang, true ) as $key => $term_id ) {
			if ( isset( $nodes[ $key ] ) ) {
				continue;
			}
			$log[] = "удалить [$lang] $key (term_id $term_id) — нет в дереве";
			if ( ! $dry_run ) {
				// На языке категории — иначе WPML подменит ID на перевод.
				do_action( 'wpml_switch_language', $lang );
				wp_delete_term( $term_id, 'product_cat' );
				do_action( 'wpml_switch_language', null );
			}
		}
	}

	if ( ! $dry_run ) {
		delete_option( 'product_cat_children' );
		clean_term_cache( array_merge( array_values( $ru_ids ), array_values( $ro_ids ) ), 'product_cat' );
		casa24_catalog_term_ids( $default, true );
		casa24_catalog_term_ids( 'ro', true );
	}

	return $log;
}

/**
 * Создать или обновить одну категорию на одном языке.
 *
 * @param string   $key       Ключ категории.
 * @param string   $lang      Язык.
 * @param string   $name      Название.
 * @param string   $slug      Слаг.
 * @param int      $parent    Родитель (term_id на этом же языке).
 * @param int      $order     Порядок среди соседей.
 * @param int      $term_id   Существующая категория или 0.
 * @param bool     $dry_run   Ничего не менять.
 * @param string[] $log       Журнал (дополняется).
 * @param int      $source_id RU-категория, переводом которой будет эта (только для RO).
 * @return int term_id (0 в режиме проверки для новой категории).
 */
function casa24_catalog_upsert_term( $key, $lang, $name, $slug, $parent, $order, $term_id, $dry_run, &$log, $source_id = 0 ) {
	// WPML подменяет ID категории в get_term() на перевод текущего языка —
	// работаем на языке категории.
	do_action( 'wpml_switch_language', $lang );
	$result = casa24_catalog_upsert_term_in_lang( $key, $lang, $name, $slug, $parent, $order, $term_id, $dry_run, $log, $source_id );
	do_action( 'wpml_switch_language', null );

	return $result;
}

/**
 * Тело casa24_catalog_upsert_term() — вызывается при включённом языке $lang.
 *
 * Параметры — как у casa24_catalog_upsert_term().
 *
 * @return int
 */
function casa24_catalog_upsert_term_in_lang( $key, $lang, $name, $slug, $parent, $order, $term_id, $dry_run, &$log, $source_id ) {
	if ( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );

		if ( $term && ( $term->name !== $name || (int) $term->parent !== $parent ) ) {
			$log[] = "обновить [$lang] $key: «{$term->name}» → «{$name}», родитель {$term->parent} → {$parent}";
			if ( ! $dry_run ) {
				wp_update_term( $term_id, 'product_cat', array( 'name' => $name, 'parent' => $parent ) );
			}
		}

		if ( ! $dry_run ) {
			update_term_meta( $term_id, 'order', $order );
		}

		return $term_id;
	}

	$log[] = "создать [$lang] $key: «{$name}» (родитель $parent)";

	if ( $dry_run ) {
		return 0;
	}

	$result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug, 'parent' => $parent ) );

	if ( is_wp_error( $result ) ) {
		$log[] = "  ОШИБКА: " . $result->get_error_message();
		return 0;
	}

	$term_id = (int) $result['term_id'];
	$tt_id   = (int) $result['term_taxonomy_id'];

	update_term_meta( $term_id, CASA24_CATALOG_META, $key );
	update_term_meta( $term_id, 'order', $order );

	if ( $source_id ) {
		global $wpdb;
		$source_tt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'product_cat'", $source_id ) );
		$trid      = apply_filters( 'wpml_element_trid', null, $source_tt, 'tax_product_cat' );

		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $tt_id,
				'element_type'         => 'tax_product_cat',
				'trid'                 => $trid,
				'language_code'        => $lang,
				'source_language_code' => casa24_catalog_default_lang(),
			)
		);
	} else {
		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'    => $tt_id,
				'element_type'  => 'tax_product_cat',
				'trid'          => false,
				'language_code' => $lang,
			)
		);
	}

	return $term_id;
}

/* -------------------------------------------------------------------------
 * 2. Раскладка товаров
 * ---------------------------------------------------------------------- */

/**
 * Подгруппа по названию товара — для товаров из групп-брендов (краски,
 * бытовая химия), где по группе 1С вид товара не понять.
 *
 * Те же правила, что при подготовке таблицы для 1С (docs/1c-novye-gruppy-2026-10-28.xlsx).
 *
 * @param string $name  Название товара.
 * @param string $rules paint | paint_wall | paint_lac | clean | power.
 * @return string|null RU-название подгруппы или null.
 */
function casa24_catalog_classify( $name, $rules ) {
	$n   = mb_strtolower( $name );

	// Наборы правил из catalog_tree.py (NAME_RULES): первое совпадение побеждает,
	// «^» в начале подстроки — название с неё начинается.
	$map = casa24_catalog_map();
	if ( isset( $map['rules'][ $rules ] ) ) {
		$trimmed = trim( $n );
		foreach ( $map['rules'][ $rules ] as $rule ) {
			foreach ( $rule[1] as $needle ) {
				$found = '^' === $needle[0] ? 0 === mb_strpos( $trimmed, mb_substr( $needle, 1 ) ) : false !== mb_strpos( $n, $needle );
				if ( $found ) {
					return $rule[0];
				}
			}
		}
		return null;
	}

	$has = static function ( ...$needles ) use ( $n ) {
		foreach ( $needles as $needle ) {
			if ( false !== mb_strpos( $n, $needle ) ) {
				return true;
			}
		}
		return false;
	};

	// Товары прямо в группе 1С «Электроинструмент» (28.10.2026).
	if ( 'power' === $rules ) {
		$power = array(
			'Аппараты для сварки пластиковых труб'          => array( 'сварки труб', 'ппр' ),
			'Цепи для пил'                                  => array( 'lant', 'цепь' ),
			'Полотна для ножовок и лобзиков'                => array( 'пилоч', 'пилки' ),
			'Насосы для накачки, пневмопистолеты, шланги' => array( 'пневмо', 'компрессор', 'шланг пневм' ),
			'Секаторы и инструмент для обрезки'             => array( 'секатор', 'set de gradina' ),
			'Аккумуляторы и зарядные устройства'            => array( 'baterie', 'аккумулятор li', 'зарядн' ),
			'Пилы электрические и бензопилы'                => array( 'циркулярк', 'пила', 'fereste', 'ferestr', 'углорезк' ),
			'Электрорубанки'                                => array( 'рубанок' ),
			'Воздуходувы'                                   => array( 'воздуходув' ),
			'Шлифмашины'                                    => array( 'наждак', 'шлифстанок', 'полиров', 'точил' ),
			'Строительные фены'                             => array( 'фен ' ),
			'Степлеры и заклёпочники'                       => array( 'степлер' ),
			'Фрезеры и штроборезы'                          => array( 'freza', 'фрез', 'штроборез' ),
			'Дрели и шуруповёрты'                           => array( 'carota', 'дрел', 'шуруповерт' ),
			'Реноваторы (многофункциональные)'              => array( 'renovator', 'реноватор', 'функцион' ),
			'Скотч и клейкие ленты'                         => array( 'скотч' ),
		);
		foreach ( $power as $target => $needles ) {
			if ( $has( ...$needles ) ) {
				return $target;
			}
		}
		return null;
	}

	if ( 'clean' === $rules ) {
		if ( $has( 'rufe', 'spalat', 'стир', 'порошок', 'detergent', 'deterjent', 'ополаск', 'balsam', 'капсул', 'capsule', 'смягчит', 'lenor', 'persil', 'ariel', 'omo ', 'praf', 'антинакип', 'синька', 'белизна', 'antistatic' ) ) {
			return 'Средства для стирки';
		}
		if ( $has( 'мыло', 'sapun' ) ) {
			return 'Мыло';
		}
		if ( $has( 'vesela', 'посуд', 'wc', 'podea', 'для пол', 'плесен', 'antimucegai', 'domestos', 'pronto', 'sano', 'kalk', 'curatare', 'чист', 'gril' ) ) {
			return 'Средства для уборки';
		}
		return null;
	}

	if ( $has( '400ml', '400мл', '400 мл', '400 ml', 'аэрозол', 'spray', 'спрей' ) ) {
		return 'Аэрозольные краски';
	}
	if ( $has( 'колер', 'pigment', 'пигмент' ) ) {
		return 'Колеры и пигменты';
	}
	if ( 'paint_lac' === $rules ) {
		return $has( 'лак', 'lac' ) ? 'Лаки' : null;
	}
	if ( 'paint_wall' === $rules ) {
		if ( $has( 'fasad', 'фасад', 'exterior', 'relief' ) ) {
			return 'Фасадные краски';
		}
		if ( $has( 'antisept', 'антисепт', 'sansiro', 'влагост' ) ) {
			return 'Антисептические и влагостойкие краски';
		}
		return null;
	}
	if ( $has( 'lubricant', 'смазк' ) ) {
		return 'Смазки и технические жидкости';
	}
	if ( $has( 'graffite', 'metalic', 'металлик', 'пудра декорат', 'декоративн' ) ) {
		return 'Декоративные краски';
	}
	if ( $has( 'термост' ) ) {
		return 'Термостойкие краски';
	}
	if ( $has( '3 в 1', '3в1', '3in1', '3 in 1', 'грунт-эмал', 'grund-email', 'грунт эмал', '2 в 1', 'молотк', 'хаммер' ) ) {
		return 'Грунт-эмали 3 в 1 и молотковые';
	}
	if ( $has( 'радиатор', 'calorifer', 'батаре' ) ) {
		return 'Эмали для радиаторов';
	}
	if ( $has( 'кровел', 'кровл', 'elastic', 'резин' ) ) {
		return 'Краски для кровли';
	}
	if ( $has( 'лак', ' lac' ) ) {
		return 'Лаки';
	}
	if ( $has( 'acryl', 'акрил', 'acrilic' ) ) {
		return 'Эмали акриловые';
	}
	if ( $has( 'пф', 'pf-', 'pf ', 'super email', 'superemail', 'супер эмал', 'super farb', '115', 'алкид', 'эмал', 'email' ) ) {
		return 'Эмали алкидные (ПФ-115)';
	}

	return null;
}

/**
 * Ключи новых категорий для товара основного языка.
 *
 * @param int $product_id ID товара (основной язык).
 * @return string[]
 */
function casa24_catalog_target_keys( $product_id ) {
	$map = casa24_catalog_map();

	if ( isset( $map['products'][ $product_id ] ) ) {
		return array( $map['products'][ $product_id ] );
	}

	// Только категории 1С; если товар стоит и в группе, и в её подгруппе — берём подгруппу.
	$old = array();
	foreach ( casa24_catalog_raw_term_ids( $product_id ) as $term_id ) {
		if ( '' === (string) get_term_meta( $term_id, CASA24_CATALOG_META, true ) ) {
			$old[ $term_id ] = true;
		}
	}
	foreach ( array_keys( $old ) as $term_id ) {
		foreach ( get_ancestors( $term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
			unset( $old[ $ancestor ] );
		}
	}

	$keys = array();
	$name = get_post_field( 'post_title', $product_id );

	foreach ( array_keys( $old ) as $term_id ) {
		if ( isset( $map['groups'][ $term_id ] ) ) {
			$keys[] = $map['groups'][ $term_id ];
		} elseif ( isset( $map['sort'][ $term_id ] ) ) {
			list( $rules, $default ) = $map['sort'][ $term_id ];
			$target                  = casa24_catalog_classify( $name, $rules );
			$keys[]                  = $target ? $map['names'][ $target ] : ( $default ? $default : $map['unsorted'] );
		}
	}

	$keys = array_values( array_unique( $keys ) );

	return $keys ? $keys : array( $map['unsorted'] );
}

/**
 * Категории товара как они записаны в базе — без подмены языка WPML.
 *
 * @param int $post_id ID товара.
 * @return int[]
 */
function casa24_catalog_raw_term_ids( $post_id ) {
	global $wpdb;

	return array_map(
		'intval',
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id FROM {$wpdb->term_relationships} r
				 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id
				 WHERE r.object_id = %d AND tt.taxonomy = 'product_cat'",
				$post_id
			)
		)
	);
}

/**
 * Поставить товар (и его RO-перевод) в новые категории.
 *
 * Категории 1С и любые другие категории товара не трогаются — меняется
 * только набор категорий нового дерева.
 *
 * @param int  $product_id ID товара основного языка.
 * @param bool $dry_run    Только вычислить.
 * @return array{keys: string[], changed: bool}
 */
function casa24_catalog_assign( $product_id, $dry_run = false ) {
	$product_id = (int) $product_id;
	$default    = casa24_catalog_default_lang();
	$keys       = casa24_catalog_target_keys( $product_id );
	$changed    = false;

	$targets = array( $default => $product_id );
	$ro_id   = (int) apply_filters( 'wpml_object_id', $product_id, 'product', false, 'ro' );
	if ( $ro_id && $ro_id !== $product_id ) {
		$targets['ro'] = $ro_id;
	}

	// Категории нового дерева по языкам: term_id => язык. У товара не должно
	// остаться категорий нового дерева «чужого» языка.
	$ours = array();
	foreach ( array( $default, 'ro' ) as $lang ) {
		foreach ( casa24_catalog_term_ids( $lang ) as $term_id ) {
			$ours[ $term_id ] = $lang;
		}
	}

	foreach ( $targets as $lang => $post_id ) {
		$ids = casa24_catalog_term_ids( $lang );
		$new = array();
		foreach ( $keys as $key ) {
			if ( isset( $ids[ $key ] ) ) {
				$new[] = $ids[ $key ];
			}
		}

		$had    = array_values( array_intersect( casa24_catalog_raw_term_ids( $post_id ), array_keys( $ours ) ) );
		$add    = array_values( array_diff( $new, $had ) );
		$remove = array_values( array_diff( $had, $new ) );

		if ( ! $add && ! $remove ) {
			continue;
		}

		$changed = true;
		if ( $dry_run ) {
			continue;
		}

		// WPML в wp_*_object_terms() подменяет ID категории на перевод текущего
		// языка, поэтому каждую категорию добавляем/убираем на её языке.
		// Остальные категории товара (группы 1С) не трогаем.
		foreach ( $remove as $term_id ) {
			do_action( 'wpml_switch_language', $ours[ $term_id ] );
			wp_remove_object_terms( $post_id, $term_id, 'product_cat' );
		}
		if ( $add ) {
			do_action( 'wpml_switch_language', $lang );
			wp_add_object_terms( $post_id, $add, 'product_cat' );
		}
		do_action( 'wpml_switch_language', null );

		// Основная категория для хлебных крошек Rank Math.
		if ( $new ) {
			update_post_meta( $post_id, 'rank_math_primary_product_cat', $new[0] );
		}
	}

	return array( 'keys' => $keys, 'changed' => $changed );
}

/**
 * После обмена с 1С: товар уже получил группы 1С — ставим его в новое дерево.
 *
 * Обмен не должен падать из-за нас, поэтому все ошибки только в журнал.
 *
 * @param int $product_id ID товара.
 */
function casa24_catalog_after_1c( $product_id ) {
	if ( ! casa24_catalog_is_ready() ) {
		return;
	}

	try {
		$details = apply_filters( 'wpml_post_language_details', null, $product_id );
		if ( is_array( $details ) && casa24_catalog_default_lang() !== $details['language_code'] ) {
			return;
		}

		$result = casa24_catalog_assign( $product_id );
		if ( $result['changed'] ) {
			casa24_catalog_log( "товар $product_id → " . implode( ', ', $result['keys'] ) );
		}
	} catch ( \Throwable $e ) {
		casa24_catalog_log( "товар $product_id: " . $e->getMessage(), 'error' );
	}
}
// Группы 1С записываются внутри Product::mainProductData(), до этого события.
add_action( 'itglx_wc1c_after_product_info_resolve', 'casa24_catalog_after_1c', 30 );

/* -------------------------------------------------------------------------
 * 3. Витрина: старые категории 1С → новые
 * ---------------------------------------------------------------------- */

/**
 * Новое дерево для меню «Каталог» на текущем языке: разделы → группы → подгруппы.
 *
 * Пустые категории (нет товаров в наличии) и «Не разобрано» пропускаются.
 * Число товаров — пересчёт WooCommerce (видимые товары, с подкатегориями).
 *
 * @return array[] [ [ 'key', 'name', 'url', 'count', 'children' => [ ... ] ], ... ]
 */
function casa24_catalog_menu_tree() {
	static $result = array();

	$lang = (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() );
	if ( isset( $result[ $lang ] ) ) {
		return $result[ $lang ];
	}

	$map = casa24_catalog_map();
	$ids = casa24_catalog_term_ids( $lang );
	if ( ! $ids ) {
		$ids  = casa24_catalog_term_ids( casa24_catalog_default_lang() );
	}

	// Один запрос на все категории (заодно прогревает кеш метаполей).
	$terms = get_terms(
		array(
			'taxonomy'         => 'product_cat',
			'include'          => array_values( $ids ),
			'hide_empty'       => false,
			'suppress_filters' => true,
		)
	);
	$by_id = array();
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$by_id[ $term->term_id ] = $term;
		}
	}

	$nodes = array();
	foreach ( $map['tree'] as $key => $node ) {
		if ( $key === $map['unsorted'] || empty( $ids[ $key ] ) || empty( $by_id[ $ids[ $key ] ] ) ) {
			continue;
		}

		$term  = $by_id[ $ids[ $key ] ];
		$count = (int) get_term_meta( $term->term_id, 'product_count_product_cat', true );
		if ( $count <= 0 ) {
			continue;
		}

		$link = get_term_link( $term );
		$nodes[ $key ] = array(
			'key'      => $key,
			'parent'   => $node['parent'],
			'name'     => $term->name,
			'url'      => is_wp_error( $link ) ? '' : $link,
			'count'    => $count,
			'children' => array(),
		);
	}

	// Сборка дерева снизу вверх: map.php перечисляет родителя раньше детей.
	foreach ( array_reverse( array_keys( $nodes ) ) as $key ) {
		$parent = $nodes[ $key ]['parent'];
		if ( '' !== $parent && isset( $nodes[ $parent ] ) ) {
			array_unshift( $nodes[ $parent ]['children'], $nodes[ $key ] );
			unset( $nodes[ $key ] );
		} elseif ( '' !== $parent ) {
			unset( $nodes[ $key ] );
		}
	}

	$result[ $lang ] = array_values( $nodes );

	return $result[ $lang ];
}

/**
 * Сейчас выводится витрина для покупателя (а не админка, обмен с 1С, WP-CLI)?
 *
 * Обмен с 1С работает до template_redirect и завершает запрос сам, поэтому
 * «после template_redirect» — надёжный признак вывода страницы.
 *
 * @return bool
 */
function casa24_catalog_is_storefront() {
	return ! is_admin() && did_action( 'template_redirect' ) && casa24_catalog_is_ready();
}

/**
 * Витрина: списки категорий (фильтры, виджеты, карта сайта) — только новое
 * дерево, без групп 1С и без «Не разобрано».
 *
 * Явные выборки по ID (include) не трогаем. Служебные выборки тоже: WP
 * пересобирает опцию product_cat_children через get_terms( get=all,
 * fields=id=>parent ) — урезанная иерархия осталась бы в базе.
 *
 * @param array    $args       Аргументы get_terms().
 * @param string[] $taxonomies Таксономии.
 * @return array
 */
function casa24_catalog_storefront_terms_args( $args, $taxonomies ) {
	if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || ! empty( $args['include'] ) || ! empty( $args['object_ids'] ) || ! casa24_catalog_is_storefront() ) {
		return $args;
	}
	if ( ( isset( $args['get'] ) && 'all' === $args['get'] ) || ( isset( $args['fields'] ) && in_array( $args['fields'], array( 'id=>parent', 'ids', 'tt_ids', 'count' ), true ) ) ) {
		return $args;
	}

	$ours = array(
		'key'     => CASA24_CATALOG_META,
		'value'   => casa24_catalog_map()['unsorted'],
		'compare' => '!=',
	);

	$args['meta_query'] = empty( $args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		? array( $ours )
		: array( 'relation' => 'AND', $args['meta_query'], $ours );

	return $args;
}
add_filter( 'get_terms_args', 'casa24_catalog_storefront_terms_args', 20, 2 );

/**
 * Карта сайта Rank Math: в product_cat-sitemap — только новое дерево (без
 * групп 1С, которые отдают 301, и без «Не разобрано»). 28.10.2026.
 *
 * Обе выборки Rank Math для карты (индекс и страницы) идут через get_terms()
 * с условием по rank_math_robots — по нему их и узнаём.
 *
 * @param array    $args       Аргументы get_terms().
 * @param string[] $taxonomies Таксономии.
 * @return array
 */
function casa24_catalog_sitemap_terms_args( $args, $taxonomies ) {
	if ( ! in_array( 'product_cat', (array) $taxonomies, true ) || '' === (string) get_query_var( 'sitemap' ) || empty( $args['meta_query'] ) || ! casa24_catalog_is_ready() ) {
		return $args;
	}
	if ( false === strpos( wp_json_encode( $args['meta_query'] ), 'rank_math_robots' ) ) {
		return $args;
	}

	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'relation' => 'AND',
		$args['meta_query'],
		array(
			'key'     => CASA24_CATALOG_META,
			'value'   => casa24_catalog_map()['unsorted'],
			'compare' => '!=',
		),
	);

	return $args;
}
add_filter( 'get_terms_args', 'casa24_catalog_sitemap_terms_args', 20, 2 );

/**
 * Витрина: категории товара («Категория:» в карточке, хлебные крошки) —
 * только новые, если товар уже разложен.
 *
 * @param WP_Term[]|mixed $terms      Категории.
 * @param int[]           $object_ids ID записей.
 * @param string[]        $taxonomies Таксономии.
 * @return WP_Term[]|mixed
 */
function casa24_catalog_storefront_object_terms( $terms, $object_ids, $taxonomies ) {
	if ( ! is_array( $terms ) || ! $terms || ! in_array( 'product_cat', (array) $taxonomies, true ) || ! casa24_catalog_is_storefront() ) {
		return $terms;
	}
	// Корзина и оформление считают купоны/доставку по категориям — там группы 1С нужны целиком.
	if ( wp_doing_ajax() || ! empty( $_GET['wc-ajax'] ) || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $terms;
	}

	$unsorted = casa24_catalog_map()['unsorted'];
	$ours     = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			return $terms;
		}
		if ( 'product_cat' !== $term->taxonomy ) {
			$ours[] = $term;
			continue;
		}
		$key = (string) get_term_meta( $term->term_id, CASA24_CATALOG_META, true );
		if ( '' !== $key && $unsorted !== $key ) {
			$ours[] = $term;
		}
	}

	// Есть хоть одна новая категория — показываем только их.
	foreach ( $ours as $term ) {
		if ( 'product_cat' === $term->taxonomy ) {
			return $ours;
		}
	}

	return $terms;
}
add_filter( 'get_object_terms', 'casa24_catalog_storefront_object_terms', 20, 3 );

/**
 * То же для get_the_terms(): категории товара часто уже лежат в кеше (их
 * подгружает основной запрос до template_redirect), и тогда get_object_terms
 * не вызывается — в «Категория:» на карточке всплывала группа 1С (28.10.2026).
 *
 * @param WP_Term[]|false|WP_Error $terms    Категории.
 * @param int                      $post_id  ID записи.
 * @param string                   $taxonomy Таксономия.
 * @return WP_Term[]|false|WP_Error
 */
function casa24_catalog_storefront_the_terms( $terms, $post_id, $taxonomy ) {
	return casa24_catalog_storefront_object_terms( $terms, array( $post_id ), array( $taxonomy ) );
}
add_filter( 'get_the_terms', 'casa24_catalog_storefront_the_terms', 20, 3 );

/**
 * Куда вести со страницы старой категории 1С: ключ новой категории.
 *
 * @param WP_Term $term Старая категория (любой язык).
 * @return string|null
 */
function casa24_catalog_redirect_key( $term ) {
	$map = casa24_catalog_map();

	$ru_id = (int) apply_filters( 'wpml_object_id', $term->term_id, 'product_cat', true, casa24_catalog_default_lang() );

	// «Папки» — в раздел, а не туда, куда ушли их собственные товары.
	if ( isset( $map['redirects'][ $ru_id ] ) ) {
		return $map['redirects'][ $ru_id ];
	}
	if ( isset( $map['groups'][ $ru_id ] ) ) {
		return $map['groups'][ $ru_id ];
	}
	if ( isset( $map['sort'][ $ru_id ] ) ) {
		if ( $map['sort'][ $ru_id ][1] ) {
			return $map['sort'][ $ru_id ][1];
		}
		return 'clean' === $map['sort'][ $ru_id ][0] ? $map['names']['Уборка и бытовая химия'] : $map['names']['Краски и лаки'];
	}

	return null;
}

/**
 * 301 со страницы старой категории на новую (на том же языке).
 */
function casa24_catalog_redirect_old_categories() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() || ! casa24_catalog_is_ready() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || '' !== (string) get_term_meta( $term->term_id, CASA24_CATALOG_META, true ) ) {
		return;
	}

	$key = casa24_catalog_redirect_key( $term );
	if ( ! $key ) {
		return;
	}

	$lang = (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() );
	$ids  = casa24_catalog_term_ids( $lang );
	if ( empty( $ids[ $key ] ) ) {
		return;
	}

	$link = get_term_link( $ids[ $key ], 'product_cat' );
	if ( ! is_wp_error( $link ) && ! casa24_catalog_is_current_url( $link ) ) {
		wp_safe_redirect( $link, 301, 'Casa24 catalog' );
		exit;
	}
}
add_action( 'template_redirect', 'casa24_catalog_redirect_old_categories', 5 );

/**
 * Адрес совпадает с текущим запросом (без учёта регистра %-кодов) — защита
 * от переадресации страницы самой на себя.
 *
 * @param string $link Адрес.
 * @return bool
 */
function casa24_catalog_is_current_url( $link ) {
	$current = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- только сравнение.
	$path    = static function ( $url ) {
		return untrailingslashit( strtolower( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
	};

	return $path( $link ) === $path( $current );
}

/**
 * Тот же 301 со старой категории, но раньше — из разбора запроса WPML.
 *
 * WPML в parse_query переадресует (302) категорию на её перевод, сравнивая
 * slug из адреса со slug перевода. У RO-групп 1С slug кириллические, в адресе
 * они %-кодированы иначе — WPML считает их разными и шлёт страницу саму на
 * себя (цикл, найден 31.10.2026). Поэтому старую категорию уводим на новую
 * до этой проверки. Новые категории текущего языка и чужие запросы не трогаем.
 *
 * @param WP_Query $q Запрос.
 * @return WP_Query
 */
function casa24_catalog_redirect_old_categories_early( $q ) {
	if ( ! $q instanceof WP_Query || ! $q->is_main_query() || is_admin() || wp_doing_ajax() || ! $q->is_tax || empty( $q->tax_query->queries ) || ! casa24_catalog_is_ready() ) {
		return $q;
	}

	$slug = '';
	foreach ( $q->tax_query->queries as $query ) {
		if ( isset( $query['taxonomy'], $query['field'], $query['terms'] ) && 'product_cat' === $query['taxonomy'] && 'slug' === $query['field'] ) {
			$slug = (string) end( $query['terms'] );
		}
	}
	if ( '' === $slug ) {
		return $q;
	}

	global $wpdb;
	$lang = (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.term_id, i.language_code AS lang, m.meta_value AS catalog_key
			 FROM {$wpdb->terms} t
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'product_cat'
			 LEFT JOIN {$wpdb->prefix}icl_translations i ON i.element_id = tt.term_taxonomy_id AND i.element_type = 'tax_product_cat'
			 LEFT JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key = %s
			 WHERE t.slug = %s",
			CASA24_CATALOG_META,
			sanitize_title( rawurldecode( $slug ) )
		)
	);

	// Новая категория этого языка — её страница, обычный путь. Новая другого
	// языка (RU-slug под /ro/) — сразу на её перевод, иначе WPML даст 302.
	$old = null;
	$key = null;
	foreach ( $rows as $row ) {
		if ( null !== $row->catalog_key && '' !== $row->catalog_key ) {
			if ( $lang === $row->lang ) {
				return $q;
			}
			$key = $row->catalog_key;
		} elseif ( ! $old || $lang === $row->lang ) {
			$old = $row;
		}
	}
	if ( ! $key && $old ) {
		$key = casa24_catalog_redirect_key( (object) array( 'term_id' => (int) $old->term_id ) );
	}

	$ids = casa24_catalog_term_ids( $lang );
	if ( ! $key || empty( $ids[ $key ] ) ) {
		return $q;
	}

	$link = get_term_link( $ids[ $key ], 'product_cat' );
	if ( ! is_wp_error( $link ) && ! casa24_catalog_is_current_url( $link ) ) {
		wp_safe_redirect( $link, 301, 'Casa24 catalog' );
		exit;
	}

	return $q;
}
add_filter( 'wpml_pre_parse_query', 'casa24_catalog_redirect_old_categories_early' );

/**
 * 301 с адресов категорий первой версии нового дерева, которых больше нет
 * (второй этап 28.10.2026: «Инструмент», «Товары для дома» и т. п.).
 * Такие адреса отдают 404 — по последнему сегменту ищем замену в map['legacy'].
 */
function casa24_catalog_redirect_legacy() {
	if ( ! is_404() || ! casa24_catalog_is_ready() ) {
		return;
	}

	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- только сравнение со словарём.
	if ( ! preg_match( '~/product-category/(?:.+/)?([a-z0-9-]+)/?$~', $path, $m ) ) {
		return;
	}

	$map = casa24_catalog_map();
	if ( empty( $map['legacy'][ $m[1] ] ) ) {
		return;
	}

	$ids = casa24_catalog_term_ids( (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() ) );
	$key = $map['legacy'][ $m[1] ];
	if ( empty( $ids[ $key ] ) ) {
		return;
	}

	$link = get_term_link( $ids[ $key ], 'product_cat' );
	if ( ! is_wp_error( $link ) && ! casa24_catalog_is_current_url( $link ) ) {
		wp_safe_redirect( $link, 301, 'Casa24 catalog' );
		exit;
	}
}
add_action( 'template_redirect', 'casa24_catalog_redirect_legacy', 6 );

/**
 * Картинки (thumbnail_id) для категорий нового дерева — их показывает Glozin
 * значком слева от названия в каталоге (обратная связь 28.10.2026: «куда
 * делись значки»). Разделы верхнего уровня получают коллажи отдельно
 * (tools/category-collages/category-collages.php apply <ID разделов>).
 *
 * Подгруппа — картинка её старой группы 1С, иначе фото товара из неё.
 * Группа — картинка первой подгруппы, у которой она есть. RO-перевод
 * получает ту же картинку.
 *
 * @param bool $dry_run Только показать.
 * @param bool $force   Заменить уже поставленные.
 * @return string[] Журнал.
 */
function casa24_catalog_build_thumbs( $dry_run = false, $force = false ) {
	$map     = casa24_catalog_map();
	$ru      = casa24_catalog_term_ids( casa24_catalog_default_lang(), true );
	$ro      = casa24_catalog_term_ids( 'ro', true );
	$log     = array();
	$thumbs  = array();
	$old_for = array();

	foreach ( $map['groups'] as $old_id => $key ) {
		$old_for[ $key ][] = (int) $old_id;
	}

	// Глубина: подгруппы раньше групп, чтобы группа могла взять картинку подгруппы.
	$depth = array();
	foreach ( $map['tree'] as $key => $node ) {
		$depth[ $key ] = 0;
		for ( $p = $node['parent']; '' !== $p; $p = $map['tree'][ $p ]['parent'] ) {
			++$depth[ $key ];
		}
	}
	arsort( $depth );

	foreach ( array_keys( $depth ) as $key ) {
		if ( empty( $ru[ $key ] ) || 0 === $depth[ $key ] || $key === $map['unsorted'] ) {
			continue;
		}

		$current = (int) get_term_meta( $ru[ $key ], 'thumbnail_id', true );
		if ( $current && ! $force ) {
			$thumbs[ $key ] = $current;
			continue;
		}

		$thumb  = 0;
		$source = '';

		foreach ( $old_for[ $key ] ?? array() as $old_id ) {
			$thumb = (int) get_term_meta( $old_id, 'thumbnail_id', true );
			if ( $thumb ) {
				$source = "группа 1С $old_id";
				break;
			}
		}

		if ( ! $thumb ) {
			foreach ( $map['tree'] as $child_key => $child ) {
				if ( $child['parent'] === $key && ! empty( $thumbs[ $child_key ] ) ) {
					$thumb  = $thumbs[ $child_key ];
					$source = "подгруппа $child_key";
					break;
				}
			}
		}

		if ( ! $thumb && function_exists( 'wc_get_products' ) ) {
			$term = get_term( $ru[ $key ], 'product_cat' );
			foreach ( array( true, false ) as $in_stock ) {
				$query = array(
					'status'   => 'publish',
					'limit'    => 20,
					'category' => array( $term->slug ),
					'return'   => 'objects',
				);
				if ( $in_stock ) {
					$query['stock_status'] = 'instock';
				}
				foreach ( wc_get_products( $query ) as $product ) {
					if ( $product->get_image_id() ) {
						$thumb  = (int) $product->get_image_id();
						$source = 'фото товара ' . $product->get_id();
						break 2;
					}
				}
			}
		}

		if ( ! $thumb ) {
			$log[] = "нет картинки: $key";
			continue;
		}

		$thumbs[ $key ] = $thumb;
		$log[]          = "$key ← $source";

		if ( ! $dry_run ) {
			update_term_meta( $ru[ $key ], 'thumbnail_id', $thumb );
			if ( ! empty( $ro[ $key ] ) ) {
				update_term_meta( $ro[ $key ], 'thumbnail_id', $thumb );
			}
		}
	}

	return $log;
}

/* -------------------------------------------------------------------------
 * 4. WP-CLI
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Новое дерево каталога поверх групп 1С.
	 */
	class Casa24_Catalog_CLI {

		/**
		 * Создать или обновить дерево категорий (RU + RO).
		 *
		 * [--dry-run]
		 * : Только показать изменения.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function tree( $args, $assoc_args ) {
			$dry = ! empty( $assoc_args['dry-run'] );
			$log = casa24_catalog_build_tree( $dry );

			foreach ( $log as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( ( $dry ? 'Проверка: ' : '' ) . count( $log ) . ' изменений.' );
		}

		/**
		 * Разложить товары основного языка по новому дереву.
		 *
		 * [--dry-run]
		 * : Только посчитать.
		 *
		 * [--ids=<ids>]
		 * : ID товаров через запятую.
		 *
		 * [--report=<file>]
		 * : Сохранить CSV: ID, название, категории.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function assign( $args, $assoc_args ) {
			global $wpdb;

			if ( ! casa24_catalog_is_ready() ) {
				WP_CLI::error( 'Сначала создайте дерево: wp casa24 catalog tree' );
			}

			$dry = ! empty( $assoc_args['dry-run'] );

			if ( ! empty( $assoc_args['ids'] ) ) {
				$ids = array_map( 'intval', explode( ',', $assoc_args['ids'] ) );
			} else {
				$ids = array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT p.ID FROM {$wpdb->posts} p
							 JOIN {$wpdb->prefix}icl_translations i ON i.element_id = p.ID AND i.element_type = 'post_product'
							 WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft') AND i.language_code = %s
							 ORDER BY p.ID",
							casa24_catalog_default_lang()
						)
					)
				);
			}

			$report  = array();
			$changed = 0;
			$per_key = array();

			wp_defer_term_counting( true );
			foreach ( $ids as $i => $id ) {
				$result = casa24_catalog_assign( $id, $dry );
				$changed += $result['changed'] ? 1 : 0;
				foreach ( $result['keys'] as $key ) {
					$per_key[ $key ] = ( $per_key[ $key ] ?? 0 ) + 1;
				}
				$report[] = array( $id, get_the_title( $id ), implode( ' | ', $result['keys'] ) );

				if ( 0 === $i % 200 ) {
					WP_CLI::log( sprintf( '%d / %d', $i, count( $ids ) ) );
				}
			}
			wp_defer_term_counting( false );

			if ( ! $dry && function_exists( 'wc_recount_all_terms' ) ) {
				wc_recount_all_terms();
			}

			if ( ! empty( $assoc_args['report'] ) ) {
				$fh = fopen( $assoc_args['report'], 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				fwrite( $fh, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				fputcsv( $fh, array( 'ID', 'Товар', 'Новые категории' ), ';' );
				foreach ( $report as $row ) {
					fputcsv( $fh, $row, ';' );
				}
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}

			$unsorted = $per_key[ casa24_catalog_map()['unsorted'] ] ?? 0;
			WP_CLI::success( sprintf( '%s: товаров %d, изменено %d, в «Не разобрано» %d.', $dry ? 'Проверка' : 'Готово', count( $ids ), $changed, $unsorted ) );
		}

		/**
		 * Картинки (значки) для групп и подгрупп нового дерева.
		 *
		 * [--dry-run]
		 * : Только показать.
		 *
		 * [--force]
		 * : Заменить уже поставленные картинки.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function thumbs( $args, $assoc_args ) {
			$log = casa24_catalog_build_thumbs( ! empty( $assoc_args['dry-run'] ), ! empty( $assoc_args['force'] ) );
			foreach ( $log as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( count( $log ) . ' строк.' );
		}

		/**
		 * Число товаров в новых категориях (RU), включая подкатегории.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function stats( $args, $assoc_args ) {
			$map  = casa24_catalog_map();
			$ids  = casa24_catalog_term_ids( casa24_catalog_default_lang(), true );
			$rows = array();

			foreach ( $map['tree'] as $key => $node ) {
				if ( empty( $ids[ $key ] ) ) {
					continue;
				}
				$depth = 0;
				for ( $p = $node['parent']; '' !== $p; $p = $map['tree'][ $p ]['parent'] ) {
					++$depth;
				}
				$rows[] = array(
					'категория' => str_repeat( '  ', $depth ) . $node['ru'],
					'товаров'   => (int) get_term_meta( $ids[ $key ], 'product_count_product_cat', true ),
					'напрямую'  => (int) get_term( $ids[ $key ], 'product_cat' )->count,
				);
			}

			WP_CLI\Utils\format_items( 'table', $rows, array( 'категория', 'товаров', 'напрямую' ) );
		}
	}

	WP_CLI::add_command( 'casa24 catalog', 'Casa24_Catalog_CLI' );
}
