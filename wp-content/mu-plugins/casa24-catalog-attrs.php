<?php
/**
 * Plugin Name: Casa24 — каталог: производитель и размеры из названия товара
 * Description: Из 1С характеристики не приходят (ни производителя, ни размеров), поэтому фильтру по параметрам не на что опереться. Модуль разбирает название товара и ставит атрибуты WooCommerce: Производитель, Объём, Вес, Мощность, Размер — для RU-товара и его RO-перевода. После каждой выгрузки товара из 1С разбор повторяется. Обратная связь владельца 29.10.2026.
 *
 * Значения записаны международными обозначениями (0,5 L, 25 kg, 60 W,
 * 250 mm) — одинаково читаются в RU и RO, поэтому таксономии атрибутов не
 * переводятся в WPML: RO-товар получает те же термины.
 *
 * Фильтр Glozin (виджет «Products Filter» в сайдбаре каталога) сам прячет
 * значения, которых нет в текущей категории, — в каждой конечной папке
 * видны только её производители и размеры.
 *
 * WP-CLI:
 *   wp casa24 attrs setup                          — создать атрибуты, отключить их перевод в WPML
 *   wp casa24 attrs apply [--dry-run] [--ids=1,2]  — разобрать товары и записать атрибуты
 *   wp casa24 attrs show <название>                 — что получится из названия
 *
 * Производителей и правила разбора править здесь, затем wp casa24 attrs apply.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Атрибуты: slug => подпись (RU, RO).
 *
 * @return array[]
 */
function casa24_attrs_list() {
	return array(
		'proizvoditel' => array( 'Производитель', 'Producător' ),
		'obem'         => array( 'Объём', 'Volum' ),
		'ves'          => array( 'Вес', 'Greutate' ),
		'moshchnost'   => array( 'Мощность', 'Putere' ),
		'razmer'       => array( 'Размер', 'Dimensiune' ),
	);
}

/**
 * Производители: как показывать => варианты написания в названиях.
 *
 * Только то, что в названиях действительно стоит как марка (проверено по
 * выгрузке названий 29.10.2026). Слова-описания (Turbo, Star, Mini, Pro…)
 * сюда не попадают.
 *
 * @return array
 */
function casa24_attrs_brands() {
	$list = array(
		'Total', 'Wokin', 'Geko', 'Topex', 'EGA', 'Vorel', 'Zaruba', 'Strend Pro' => array( 'Strend Pro', 'Strend' ), 'Yato', 'Elefant',
		'Vector+' => array( 'Vector+', 'Vector' ), 'Coloriks', 'Coloris', 'Falon Tech' => array( 'Falon Tech', 'Falon' ), 'SGS',
		'Richmann' => array( 'Richmann', 'Richman' ), 'Stern', 'Profmet', 'Mixxus', 'Intex', 'Horoz',
		'Kraft&Dele' => array( 'Kraft&Dele', 'Kraft & Dele' ), 'Bradas', 'Brimix', 'Biodur', 'Primaflex', 'Tytan', 'Akfix', 'Farbis',
		'Zambak', 'Maxter', 'Samurai', 'Santino', 'DeWalt', 'Elif', 'Blueshark', 'Zegor', 'Kseibi', 'TDM', 'Procraft', 'DWT', 'Sigma',
		'Moltopren', 'Torfland', 'Corona', 'Fleko' => array( 'Fleko', 'Fleco' ), 'Flexitaly', 'Proline', 'Smartfix', 'Hardy', 'Mustang',
		'Prosperplast', 'Soma Fix' => array( 'Soma Fix', 'Somafix', 'Soma' ), 'Hardstar', 'Polax', 'Verto', 'Bauwelt' => array( 'Bauwelt', 'Bau Welt' ),
		'Tuffex', 'Elitacolor', 'Rotaru', 'Diolsem', 'Vatan', 'Faster Tools' => array( 'Faster Tools', 'Faster' ), 'Hardex', 'Kristalon',
		'Plastart', 'Zimmer', 'Mutlusan', 'Tesa', 'Tolsen', 'Goalmac', 'Goalmag', 'Forse', 'Milwaukee', 'Dedra', 'Hammer', 'Prescott',
		'Gosia', 'Monoflex', 'Berossi', 'Gepard', 'Stavtool', 'Neomax', 'Fertilux', 'Crasdan', 'Fairy', 'Maxell', 'Rodex', 'Emos',
		'Bison', 'Bostik', 'Cif', 'Supraton', 'Mastiff', 'Kuchcik', 'Beorol', 'Cellfast', 'Trigor', 'Madeco', 'Wismark', 'HighGenic',
		'Novax', 'Wermop', 'Gallus', 'Motive', 'Asude', 'Fudi', 'Deleex', 'Ezra', 'Sanliv', 'Tucai', 'Alya', 'Ouya', 'Medalyan', 'Suyap',
		'Singa', 'Palmiye', 'Defne', 'Mamut', 'Festa', 'Vargo', 'Detex', 'Amigo', 'Dunya', 'MegaWatt', 'Partner', 'Sintex', 'Bondit',
		'Zwalum', 'Gredre', 'Exen', 'Pratik', 'Dracon', 'Tiffany', 'Bingo', 'Antares', 'Aviora', 'Winso', 'Karasu', 'Unimas', 'Ipsan',
		'Tufflex', 'Kratos', 'Burak', 'Mesan', 'OZK', 'Perilla', 'Oregon', 'Nika', 'Keltin', 'Dolpfin', 'Gondol', 'Zerix', 'Zhwei', 'HPX',
		'Orient', 'Lider', 'Maan', 'Sebos', 'Soudal', 'Duracell', 'Kodak', 'Recyko', 'Klingspor', 'Tekasil',
		'Śnieżka' => array( 'Śnieżka', 'Sniezka', 'Sniezca' ), 'Speedymask', 'Unitape', 'Colombo', 'Trotec', 'Siroflex', 'XTLine',
		'Eurolumina', 'Agrecol', 'Sunflex', 'Draumet', 'Sthor', 'Gamma' => array( 'Gamma', 'Gama' ), 'Foton', 'Imperial', 'Zuber', 'Aksel',
		'Avers', 'Metsan', 'Mechanic', 'Kingjet', 'Penosil', 'Ceresit', 'Knauf', 'Bosch', 'Makita', 'Stanley', 'Gardena', 'Varta',
		'Energizer', 'Philips', 'Osram', 'Feron', 'Lemanso', 'Videx',
	);

	$brands = array();
	foreach ( $list as $key => $value ) {
		if ( is_int( $key ) ) {
			$brands[ $value ] = array( $value );
		} else {
			$brands[ $key ] = $value;
		}
	}

	return $brands;
}

/**
 * Производитель по названию: марка, которая стоит в названии раньше других.
 *
 * @param string $title Название товара.
 * @return string
 */
function casa24_attrs_brand( $title ) {
	static $patterns = null;

	if ( null === $patterns ) {
		$patterns = array();
		foreach ( casa24_attrs_brands() as $name => $variants ) {
			foreach ( $variants as $variant ) {
				$patterns[] = array( '/(?<![\p{L}\d])' . preg_quote( $variant, '/' ) . '(?![\p{L}])/iu', $name );
			}
		}
	}

	$best = array( PHP_INT_MAX, '' );
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern[0], $title, $m, PREG_OFFSET_CAPTURE ) && $m[0][1] < $best[0] ) {
			$best = array( $m[0][1], $pattern[1] );
		}
	}

	return $best[1];
}

/**
 * Число для показа: 0.5 → «0,5», 10.0 → «10».
 *
 * @param string|float $value Число.
 * @return string
 */
function casa24_attrs_number( $value ) {
	$value = (float) str_replace( ',', '.', (string) $value );

	return str_replace( '.', ',', rtrim( rtrim( sprintf( '%.3f', $value ), '0' ), '.' ) );
}

/**
 * Число с единицей; крупные граммы/миллилитры — в кг/литры.
 *
 * @param string $value Число из названия.
 * @param string $unit  Единица (g, kg, ml, L, W, kW).
 * @return string
 */
function casa24_attrs_amount( $value, $unit ) {
	$number = (float) str_replace( ',', '.', $value );
	$bigger = array(
		'g'  => 'kg',
		'ml' => 'L',
	);

	if ( isset( $bigger[ $unit ] ) && $number >= 1000 ) {
		$number /= 1000;
		$unit    = $bigger[ $unit ];
	}
	// «1000kw» в названиях светильников и т.п. — это ватты.
	if ( 'kW' === $unit && $number >= 50 ) {
		$unit = 'W';
	}

	return casa24_attrs_number( $number ) . ' ' . $unit;
}

/**
 * Разобрать название: значения атрибутов (slug => значение).
 *
 * @param string $title Название товара.
 * @return string[]
 */
function casa24_attrs_parse( $title ) {
	// Число не должно быть хвостом артикула («SPS-02 W»), но может стоять
	// сразу после буквы («D16mm» — диаметр).
	$num    = '(?<![\d\-.,\/])(\d+(?:[.,]\d+)?)';
	$end    = '(?![\p{L}])';
	$values = array();

	$brand = casa24_attrs_brand( $title );
	if ( '' !== $brand ) {
		$values['proizvoditel'] = $brand;
	}

	if ( preg_match( '/' . $num . '\s*(мл|ml|л|l|литр\p{L}*)' . $end . '/iu', $title, $m ) ) {
		$values['obem'] = casa24_attrs_amount( $m[1], in_array( mb_strtolower( $m[2] ), array( 'мл', 'ml' ), true ) ? 'ml' : 'L' );
	}

	// «40 гр/м2» — плотность, а не вес.
	if ( preg_match( '/' . $num . '\s*(кг|kg|гр|gr|г|g)(?![\p{L}\/])/iu', $title, $m ) ) {
		$values['ves'] = casa24_attrs_amount( $m[1], in_array( mb_strtolower( $m[2] ), array( 'кг', 'kg' ), true ) ? 'kg' : 'g' );
	}

	if ( preg_match( '/' . $num . '\s*(квт|kw|вт|w)' . $end . '/iu', $title, $m ) ) {
		$values['moshchnost'] = casa24_attrs_amount( $m[1], in_array( mb_strtolower( $m[2] ), array( 'квт', 'kw' ), true ) ? 'kW' : 'W' );
	}

	$units = array(
		'мм' => 'mm',
		'mm' => 'mm',
		'см' => 'cm',
		'cm' => 'cm',
		'м'  => 'm',
		'm'  => 'm',
	);
	$x     = '\s*[xх×*]\s*';
	if ( preg_match( '/' . $num . $x . '(\d+(?:[.,]\d+)?)(?:' . $x . '(\d+(?:[.,]\d+)?))?\s*(мм|mm|см|cm|м|m)' . $end . '/iu', $title, $m ) ) {
		$parts            = array_map( 'casa24_attrs_number', array_filter( array( $m[1], $m[2], $m[3] ), 'strlen' ) );
		$values['razmer'] = implode( '×', $parts ) . ' ' . $units[ mb_strtolower( $m[4] ) ];
	} elseif ( preg_match( '/' . $num . '\s*(мм|mm|см|cm|м|m)' . $end . '/iu', $title, $m ) ) {
		$values['razmer'] = casa24_attrs_number( $m[1] ) . ' ' . $units[ mb_strtolower( $m[2] ) ];
	}

	return $values;
}

/**
 * Slug значения: «0,5 L» → «0-5-l», «2×3 m» → «2x3-m».
 *
 * @param string $value Значение.
 * @return string
 */
function casa24_attrs_slug( $value ) {
	return sanitize_title( str_replace( array( ',', '×', '+', '&' ), array( '-', 'x', '-plus', '-and-' ), $value ) );
}

/**
 * Термин значения (создать, если нет).
 *
 * @param string $taxonomy Таксономия pa_*.
 * @param string $value    Значение.
 * @return int ID термина или 0.
 */
function casa24_attrs_term( $taxonomy, $value ) {
	static $cache = array();

	$slug = casa24_attrs_slug( $value );
	if ( isset( $cache[ $taxonomy ][ $slug ] ) ) {
		return $cache[ $taxonomy ][ $slug ];
	}

	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term ) {
		$created = wp_insert_term( $value, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			$id = (int) $created->get_error_data( 'term_exists' );
			$cache[ $taxonomy ][ $slug ] = $id;
			return $id;
		}
		$term = get_term( $created['term_id'], $taxonomy );
	}

	$cache[ $taxonomy ][ $slug ] = (int) $term->term_id;

	return $cache[ $taxonomy ][ $slug ];
}

/**
 * Записать атрибуты товару (RU) и его RO-переводу.
 *
 * Другие атрибуты товара (если есть) не трогаются. Значения разбираются из
 * RU-названия: RO-название — перевод, в нём бывают другие сокращения.
 *
 * @param int  $product_id ID товара основного языка.
 * @param bool $dry_run    Только вычислить.
 * @return string[] Найденные значения.
 */
function casa24_attrs_apply( $product_id, $dry_run = false ) {
	$product_id = (int) $product_id;
	$values     = casa24_attrs_parse( get_post_field( 'post_title', $product_id ) );

	if ( $dry_run ) {
		return $values;
	}

	$targets = array( $product_id );
	$ro_id   = (int) apply_filters( 'wpml_object_id', $product_id, 'product', false, 'ro' );
	if ( $ro_id && $ro_id !== $product_id ) {
		$targets[] = $ro_id;
	}

	foreach ( $targets as $post_id ) {
		$attributes = get_post_meta( $post_id, '_product_attributes', true );
		$attributes = is_array( $attributes ) ? $attributes : array();
		$changed    = false;
		$position   = count( $attributes );

		foreach ( array_keys( casa24_attrs_list() ) as $slug ) {
			$taxonomy = 'pa_' . $slug;
			$want     = isset( $values[ $slug ] ) ? array( casa24_attrs_term( $taxonomy, $values[ $slug ] ) ) : array();
			$want     = array_filter( $want );
			$have     = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			$have     = is_wp_error( $have ) ? array() : array_map( 'intval', $have );

			sort( $want );
			sort( $have );
			if ( $want !== $have ) {
				wp_set_object_terms( $post_id, $want, $taxonomy );
				$changed = true;
			}

			if ( $want && ! isset( $attributes[ $taxonomy ] ) ) {
				$attributes[ $taxonomy ] = array(
					'name'         => $taxonomy,
					'value'        => '',
					'position'     => $position++,
					'is_visible'   => 1,
					'is_variation' => 0,
					'is_taxonomy'  => 1,
				);
				$changed                 = true;
			} elseif ( ! $want && isset( $attributes[ $taxonomy ] ) ) {
				unset( $attributes[ $taxonomy ] );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			continue;
		}

		update_post_meta( $post_id, '_product_attributes', $attributes );
		wc_delete_product_transients( $post_id );

		// Таблица атрибутов, по которой WooCommerce фильтрует товары.
		if ( 'yes' === get_option( 'woocommerce_attribute_lookup_enabled' ) && function_exists( 'wc_get_container' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				wc_get_container()->get( \Automattic\WooCommerce\Internal\ProductAttributesLookup\LookupDataStore::class )->on_product_changed( $product );
			}
		}
	}

	return $values;
}

/**
 * После обмена с 1С: название могло измениться — разобрать заново.
 * Обмен не должен падать из-за нас, поэтому ошибки только в журнал.
 *
 * @param int $product_id ID товара.
 */
function casa24_attrs_after_1c( $product_id ) {
	try {
		$details = apply_filters( 'wpml_post_language_details', null, $product_id );
		if ( is_array( $details ) && (string) apply_filters( 'wpml_default_language', 'ru' ) !== $details['language_code'] ) {
			return;
		}
		if ( ! taxonomy_exists( 'pa_proizvoditel' ) ) {
			return;
		}

		casa24_attrs_apply( $product_id );
	} catch ( \Throwable $e ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( "товар $product_id: " . $e->getMessage(), array( 'source' => 'casa24-catalog-attrs' ) );
		}
	}
}
add_action( 'itglx_wc1c_after_product_info_resolve', 'casa24_attrs_after_1c', 40 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Производитель и размеры из названий товаров.
	 */
	class Casa24_Attrs_CLI {

		/**
		 * Создать атрибуты и отключить их перевод в WPML (значения одинаковы для RU и RO).
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function setup( $args, $assoc_args ) {
			$existing = array();
			foreach ( wc_get_attribute_taxonomies() as $attribute ) {
				$existing[ $attribute->attribute_name ] = (int) $attribute->attribute_id;
			}

			foreach ( casa24_attrs_list() as $slug => $labels ) {
				$data = array(
					'name'         => $labels[0],
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'name_num',
					'has_archives' => false,
				);
				$id   = isset( $existing[ $slug ] ) ? wc_update_attribute( $existing[ $slug ], $data ) : wc_create_attribute( $data );
				if ( is_wp_error( $id ) ) {
					WP_CLI::error( $slug . ': ' . $id->get_error_message() );
				}
				WP_CLI::log( "pa_$slug — {$labels[0]}" );

				// Подпись атрибута на RO (карточка товара, «Характеристики»).
				if ( function_exists( 'icl_register_string' ) && function_exists( 'icl_add_string_translation' ) ) {
					$string_id = icl_register_string( 'WordPress', 'taxonomy singular name: ' . $labels[0], $labels[0] );
					if ( $string_id ) {
						icl_add_string_translation( $string_id, 'ro', $labels[1], ICL_TM_COMPLETE );
					}
				}
			}

			// Значения не переводятся: RO-товар получает те же термины.
			$settings = get_option( 'icl_sitepress_settings' );
			if ( is_array( $settings ) ) {
				foreach ( array_keys( casa24_attrs_list() ) as $slug ) {
					$settings['taxonomies_sync_option'][ 'pa_' . $slug ] = 0;
				}
				update_option( 'icl_sitepress_settings', $settings );
			}

			delete_transient( 'wc_attribute_taxonomies' );
			WP_CLI::success( 'Атрибуты готовы.' );
		}

		/**
		 * Разобрать названия и записать атрибуты.
		 *
		 * [--dry-run]
		 * : Только посчитать.
		 *
		 * [--ids=<ids>]
		 * : ID товаров (основной язык) через запятую.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function apply( $args, $assoc_args ) {
			global $wpdb;

			if ( ! taxonomy_exists( 'pa_proizvoditel' ) ) {
				WP_CLI::error( 'Сначала: wp casa24 attrs setup' );
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
							(string) apply_filters( 'wpml_default_language', 'ru' )
						)
					)
				);
			}

			$found = array();
			wp_defer_term_counting( true );
			foreach ( $ids as $i => $id ) {
				foreach ( casa24_attrs_apply( $id, $dry ) as $slug => $value ) {
					$found[ $slug ] = ( $found[ $slug ] ?? 0 ) + 1;
				}
				if ( 0 === $i % 500 ) {
					WP_CLI::log( sprintf( '%d / %d', $i, count( $ids ) ) );
				}
			}
			wp_defer_term_counting( false );

			if ( ! $dry ) {
				foreach ( array_keys( casa24_attrs_list() ) as $slug ) {
					delete_transient( 'wc_layered_nav_counts_pa_' . $slug );
				}
			}

			$summary = array();
			foreach ( casa24_attrs_list() as $slug => $labels ) {
				$summary[] = $labels[0] . ': ' . ( $found[ $slug ] ?? 0 );
			}
			WP_CLI::success( sprintf( '%s товаров %d. %s', $dry ? 'Проверка:' : 'Готово:', count( $ids ), implode( ', ', $summary ) ) );
		}

		/**
		 * Фильтр каталога: убрать список категорий (в конечной папке он не
		 * нужен — по разделам ходят плитками), добавить производителя и
		 * размеры после цены. Подписи секций — строки WPML «Widgets /
		 * products filter - section N» (по номеру секции), их переводы
		 * переставляются вместе с секциями.
		 *
		 * [--dry-run]
		 * : Только показать новый порядок.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function filter( $args, $assoc_args ) {
			global $wpdb;

			$option   = get_option( 'widget_glozin-products-filter' );
			$instance = is_array( $option ) && isset( $option[1] ) ? $option[1] : null;
			if ( ! $instance || empty( $instance['filter'] ) ) {
				WP_CLI::error( 'Виджет glozin-products-filter-1 не найден.' );
			}

			// Переводы подписей сейчас: номер секции => [ язык => текст ].
			$strings = $wpdb->prefix . 'icl_strings';
			$labels  = array();
			foreach ( array_values( $instance['filter'] ) as $index => $section ) {
				$name                  = 'products filter - section ' . ( $index + 1 );
				$labels[ $index ]      = array( 'value' => $section['name'] );
				$rows                  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT t.language, t.value FROM {$wpdb->prefix}icl_string_translations t
						 JOIN $strings s ON s.id = t.string_id
						 WHERE s.context = 'Widgets' AND s.name = %s",
						$name
					)
				);
				foreach ( $rows as $row ) {
					$labels[ $index ][ $row->language ] = $row->value;
				}
			}

			$sections = array();
			$ours     = array();
			foreach ( casa24_attrs_list() as $slug => $names ) {
				$ours[] = array(
					'section' => array(
						'name'                  => $names[0],
						'source'                => 'attribute',
						'attribute'             => $slug,
						'display'               => 'checkboxes',
						'ranges'                => '',
						'multiple'              => 1,
						'include_categories'    => '',
						'exclude_categories'    => '',
						'orderby_attribute'     => 'count',
						'order_attribute'       => 'desc',
						'number_attribute'      => 0,
						'query_type'            => 'or',
						'show_counts'           => 1,
						'searchable'            => 'proizvoditel' === $slug ? 1 : 0,
						'scrollable'            => 1,
						'show_empty_attributes' => 0,
						'cats_numbers'          => 0,
					),
					'labels'  => array(
						'value' => $names[0],
						'ru'    => $names[0],
						'ro'    => $names[1],
					),
				);
			}

			foreach ( array_values( $instance['filter'] ) as $index => $section ) {
				if ( 'product_cat' === $section['source'] ) {
					continue;
				}
				if ( 'attribute' === $section['source'] && isset( casa24_attrs_list()[ $section['attribute'] ] ) ) {
					continue; // Наши секции — заново, в своём порядке.
				}
				$sections[] = array(
					'section' => $section,
					'labels'  => $labels[ $index ],
				);
				if ( 'price' === $section['source'] ) {
					$sections = array_merge( $sections, $ours );
					$ours     = array();
				}
			}
			$sections = array_merge( $sections, $ours );

			foreach ( $sections as $index => $item ) {
				WP_CLI::log( sprintf( '%d. %s (%s) — ru: %s, ro: %s', $index + 1, $item['labels']['value'], $item['section']['source'], $item['labels']['ru'] ?? '—', $item['labels']['ro'] ?? '—' ) );
			}
			if ( ! empty( $assoc_args['dry-run'] ) ) {
				return;
			}

			$instance['filter'] = wp_list_pluck( $sections, 'section' );
			$option[1]          = $instance;
			update_option( 'widget_glozin-products-filter', $option );

			if ( function_exists( 'icl_register_string' ) && function_exists( 'icl_add_string_translation' ) ) {
				foreach ( $sections as $index => $item ) {
					$string_id = icl_register_string( 'Widgets', 'products filter - section ' . ( $index + 1 ), $item['labels']['value'] );
					foreach ( array( 'ru', 'ro' ) as $lang ) {
						if ( $string_id && ! empty( $item['labels'][ $lang ] ) ) {
							icl_add_string_translation( $string_id, $lang, $item['labels'][ $lang ], ICL_TM_COMPLETE );
						}
					}
				}
			}

			WP_CLI::success( 'Фильтр обновлён.' );
		}

		/**
		 * Показать, что получится из названия.
		 *
		 * <title>
		 * : Название товара.
		 *
		 * @param array $args       Аргументы.
		 * @param array $assoc_args Параметры.
		 */
		public function show( $args, $assoc_args ) {
			foreach ( casa24_attrs_parse( $args[0] ) as $slug => $value ) {
				WP_CLI::log( casa24_attrs_list()[ $slug ][0] . ': ' . $value );
			}
		}
	}

	WP_CLI::add_command( 'casa24 attrs', 'Casa24_Attrs_CLI' );
}
