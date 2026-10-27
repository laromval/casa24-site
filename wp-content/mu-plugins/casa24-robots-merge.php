<?php
/**
 * Plugin Name: Casa24 — robots.txt: одна группа на каждый User-agent
 * Description: robots.txt собирают Rank Math (основные правила) и Imunify Security (дописывает вторую группу «User-agent: * / Disallow: /imunify-bot-check»). Две группы для одного User-agent — замечание аудита (priorities №13). В самом конце фильтра robots_txt сводим группы с одинаковым User-agent в одну; правила не меняются, только объединяются.
 *
 * Поисковики и так объединяют такие группы, но не все валидаторы
 * (и не все боты) — поэтому приводим к одному виду. Строки вне групп
 * (Sitemap:, комментарии) остаются в конце.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Свести группы robots.txt с одинаковым набором User-agent.
 *
 * @param string $output Текст robots.txt.
 * @return string
 */
function casa24_robots_merge_groups( $output ) {
	$groups  = array(); // ключ — список User-agent в нижнем регистре.
	$order   = array();
	$global  = array(); // Sitemap и прочие строки вне групп.
	$agents  = array();
	$rules   = array();
	$in_rule = false;

	$flush = static function () use ( &$agents, &$rules, &$groups, &$order ) {
		if ( ! $agents ) {
			return;
		}
		$key = strtolower( implode( '|', $agents ) );
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array( 'agents' => $agents, 'rules' => array() );
			$order[]        = $key;
		}
		foreach ( $rules as $rule ) {
			if ( ! in_array( $rule, $groups[ $key ]['rules'], true ) ) {
				$groups[ $key ]['rules'][] = $rule;
			}
		}
		$agents = array();
		$rules  = array();
	};

	foreach ( preg_split( '/\r\n|\r|\n/', (string) $output ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/^user-agent\s*:/i', $line ) ) {
			if ( $in_rule ) {
				$flush();
				$in_rule = false;
			}
			$agents[] = $line;
		} elseif ( preg_match( '/^(allow|disallow|crawl-delay|clean-param|host)\s*:/i', $line ) && $agents ) {
			$rules[] = $line;
			$in_rule = true;
		} else {
			$global[] = $line;
		}
	}
	$flush();

	$out = array();
	foreach ( $order as $key ) {
		$out[] = implode( "\n", array_merge( $groups[ $key ]['agents'], $groups[ $key ]['rules'] ) );
	}
	if ( $global ) {
		$out[] = implode( "\n", array_unique( $global ) );
	}

	return implode( "\n\n", $out ) . "\n";
}
add_filter( 'robots_txt', 'casa24_robots_merge_groups', PHP_INT_MAX );
