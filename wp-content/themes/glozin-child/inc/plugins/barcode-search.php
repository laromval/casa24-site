<?php

add_filter('posts_clauses', 'glozin_child_barcode_search_clauses', 10, 2);
function glozin_child_barcode_search_clauses($clauses, $query) {
    if (! $query->is_search() || ! in_array('product', (array) $query->get('post_type'))) {
        return $clauses;
    }

    global $wpdb;
    $search = get_query_var('s');
    if (empty($search) || empty($wpdb->wc_product_meta_lookup)) {
        return $clauses;
    }

    if (str_contains($clauses['where'], 'global_unique_id')) {
        return $clauses;
    }

    $join  = $clauses['join'];
    $where = $clauses['where'];

    if (! str_contains($join, "LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup")) {
        $join .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id ";
    }

    $where = preg_replace(
        "/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*('[^']+')\s*\)/",
        "({$wpdb->posts}.post_title LIKE $1) OR (wc_product_meta_lookup.global_unique_id LIKE $1)",
        $where
    );

    if (str_contains($where, 'wc_product_meta_lookup.sku')) {
        $where = preg_replace(
            "/\(wc_product_meta_lookup\.sku LIKE\s*('[^']+')\)/",
            "(wc_product_meta_lookup.sku LIKE $1) OR (wc_product_meta_lookup.global_unique_id LIKE $1)",
            $where
        );
    }

    $groupby = $clauses['groupby'];
    $id_group = "{$wpdb->posts}.ID";
    if (! strlen(trim($groupby))) {
        $groupby = $id_group;
    } elseif (! preg_match("/$id_group/", $groupby)) {
        $groupby = $groupby . ', ' . $id_group;
    }

    $clauses['join']    = $join;
    $clauses['where']   = $where;
    $clauses['groupby'] = $groupby;

    return $clauses;
}

add_filter('query', 'glozin_child_barcode_ajax_query_filter');
function glozin_child_barcode_ajax_query_filter($sql) {
    if (! defined('DOING_AJAX') || ! DOING_AJAX) {
        return $sql;
    }

    if (! isset($_POST['term'])) {
        return $sql;
    }

    $wc_action = '';
    if (! empty($_GET['wc-ajax'])) {
        $wc_action = sanitize_text_field(wp_unslash($_GET['wc-ajax']));
    }
    if ($wc_action !== 'glozin_instance_search_form') {
        return $sql;
    }

    if (str_contains($sql, 'global_unique_id')) {
        return $sql;
    }

    if (! str_contains($sql, 'wc_product_meta_lookup')) {
        return $sql;
    }

    $has_sku_like = preg_match('/wc_product_meta_lookup\.sku\s+LIKE\s+(\'[^\']+\')/', $sql, $matches);
    if ($has_sku_like) {
        $sql = str_replace(
            "(wc_product_meta_lookup.sku LIKE {$matches[1]})",
            "(wc_product_meta_lookup.sku LIKE {$matches[1]}) OR (wc_product_meta_lookup.global_unique_id LIKE {$matches[1]})",
            $sql
        );
        return $sql;
    }

    $has_title_like = preg_match("/posts\.post_title\s+LIKE\s+('[^']+')/", $sql, $matches);
    if ($has_title_like && str_contains($sql, "posts.post_type = 'product'") && str_contains($sql, 'wc_product_meta_lookup')) {
        $or_clause = " OR (wc_product_meta_lookup.global_unique_id LIKE {$matches[1]})";

        if (str_contains($sql, 'ORDER BY')) {
            $sql = preg_replace('/ORDER BY/', $or_clause . ' ORDER BY', $sql, 1);
        } else {
            $sql .= $or_clause;
        }
    }

    return $sql;
}
