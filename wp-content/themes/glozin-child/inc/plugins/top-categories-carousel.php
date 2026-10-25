<?php

/**
 * Top Categories carousel override + AJAX sync.
 *
 * Overrides the parent theme's Top Categories block on /shop and category pages.
 * Fixes: scroll by 1, shows pagination dots, unlimited items, shows subcategories on category pages.
 * Syncs carousel and h1 title after glozin AJAX filter.
 */

add_action('wp', 'glozin_child_override_top_categories', 20);

function glozin_child_override_top_categories() {
    if (!is_shop() && !is_product_category()) {
        return;
    }
    if (!class_exists('Glozin\WooCommerce\Catalog\Top_Categories')) {
        return;
    }

    $instance = \Glozin\WooCommerce\Catalog\Top_Categories::instance();

    // Remove original output
    remove_action('glozin_after_site_content_open', array($instance, 'top_categories'), 10);

    // Add custom output (priority 15 = after container-xxl opens at 10)
    add_action('glozin_after_site_content_open', 'glozin_child_custom_top_categories', 15);
}

function glozin_child_custom_top_categories() {
    if (is_search())
        return;

    $queried = get_queried_object();
    if (empty($queried)) {
        return;
    }

    $current_term = !empty($queried->term_id) ? $queried->term_id : '';
    $taxonomy = 'product_cat';

    $uncategorized = get_term_by('slug', 'uncategorized', 'product_cat');
    $exclude_ids = array();
    if ($uncategorized && !empty($uncategorized->term_id)) {
        $exclude_ids[] = $uncategorized->term_id;
    }

    if (glozin_child_is_shop_top_level()) {
        $args = array(
            'taxonomy' => $taxonomy,
            'parent' => 0,
            'hide_empty' => true,
            'exclude' => $exclude_ids,
        );
    } else {
        $args = array(
            'taxonomy' => $taxonomy,
            'parent' => $current_term,
            'hide_empty' => true,
            'exclude' => $exclude_ids,
        );
    }

    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        $terms = array();
    }

    $output = array();
    if (!empty($terms)) {
        foreach ($terms as $term) {
            $term_link = get_term_link($term->term_id);

            if (is_wp_error($term_link)) {
                continue;
            }

            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            $image = '';
            if (!empty($thumb_id)) {
                $image = wp_get_attachment_image($thumb_id, 'medium');
            }
            if (empty($image)) {
                $image = wc_placeholder_img('medium');
            }

            $is_active = (!empty($current_term) && $current_term == $term->term_id) ? 'active' : '';
            $aria_label = esc_html__('Link for', 'glozin') . ' ' . $term->name;

            $output[] = sprintf(
                '<div class="catalog-top-categories__item overflow-hidden swiper-slide %s">
                    <a class="catalog-top-categories__inner gz-arrows-middle__image gz-ratio gz-hover-zoom gz-hover-effect gz-image-rounded overflow-hidden position-relative" href="%s" aria-label="%s">
                        %s
                    </a>
                    <a class="catalog-top-categories__button d-block mt-15 position-relative" href="%s">
                        <h2 class="catalog-top-categories__text d-block fs-14 fw-semibold text-dark">
                            %s
                            <span class="glozin-categories-carousel__cat-count position-absolute top-0 right-0 translate-middle badge rounded-pill bg-success" style="font-size: 0.75rem; padding: 0.25em 0.5em;">
                                %d
                            </span>
                        </h2>
                    </a>
                </div>',
                esc_attr($is_active),
                esc_url($term_link),
                esc_attr($aria_label),
                $image,
                esc_url($term_link),
                esc_html($term->name),
                esc_html($term->count)
            );
        }
    }

    $hidden = empty($output) ? ' style="display:none"' : '';
    $slides_html = implode('', $output);

    $data_swiper = array(
        'slidesPerView' => array(
            'desktop' => '6',
            'tablet' => '4',
            'mobile' => '3',
        ),
        'slidesPerGroup' => array(
            'desktop' => '1',
            'tablet' => '1',
            'mobile' => '1',
        ),
        'spaceBetween' => array(
            'desktop' => 30,
            'tablet' => 30,
            'mobile' => 15,
        ),
    );

    echo sprintf(
        '<div class="catalog-top-categories position-relative w-100 mb-40 mb-md-60 glozin-swiper swiper glozin-carousel--elementor navigation-class-both navigation-class--tabletboth navigation-class--mobileboth gz-arrows-middle"%s data-swiper="%s" data-desktop="%s" data-tablet="%s" data-mobile="%s">
            <div class="catalog-top-categories__wrapper swiper-wrapper">%s</div>
            <div class="swiper-pagination swiper-pagination-bullets swiper-pagination-horizontal"></div>
            %s %s
        </div>',
        $hidden,
        esc_attr(json_encode($data_swiper)),
        '6',
        '4',
        '3',
        $slides_html,
        \Glozin\Icon::get_svg('left-mini', 'ui', 'class=gz-button-light glozin-swiper-button swiper-button swiper-button-prev'),
        \Glozin\Icon::get_svg('right-mini', 'ui', 'class=gz-button-light glozin-swiper-button swiper-button swiper-button-next')
    );
}

function glozin_child_is_shop_top_level() {
    if (function_exists('is_product_category') && is_product_category()) {
        return false;
    }
    if (function_exists('is_shop') && is_shop()) {
        if (!empty($_GET) && isset($_GET['product_cat'])) {
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Sync carousel + h1 title with AJAX filter on shop/category pages.
 */
add_action('wp_footer', 'glozin_child_ajax_carousel_sync');
function glozin_child_ajax_carousel_sync() {
    if (!is_shop() && !is_product_category()) {
        return;
    }
    ?>
    <script>
    (function($) {
        $(document.body).on('glozin_products_filter_request_success', function(e, response) {
            var $html = $(response);

            // Update browser tab title
            var newTitle = $html.filter('title').text();
            if (newTitle) {
                document.title = newTitle;
            }

            // Fix h1 title — theme's product-catalog.js caches #page-header once,
            // so on 2nd+ AJAX the stale reference fails. We re-query and sync.
            var $currentHeader = $('#page-header');
            var $newHeader = $html.find('#page-header');
            if ($newHeader.length) {
                var currentH1 = $currentHeader.find('.page-header__title').text().trim();
                var newH1 = $newHeader.find('.page-header__title').text().trim();
                if (currentH1 !== newH1) {
                    $currentHeader.replaceWith($newHeader);
                }
            }

            // Sync carousel — always exists in DOM, just update inner content
            var $currentCarousel = $('.catalog-top-categories');
            if (!$currentCarousel.length) return;

            var $newSlides = $html.find('.catalog-top-categories .catalog-top-categories__wrapper').children();
            var $wrapper = $currentCarousel.find('.catalog-top-categories__wrapper');

            // Destroy old Swiper before touching DOM
            if ($currentCarousel[0].swiper) {
                $currentCarousel[0].swiper.destroy(true, true);
                $currentCarousel[0].swiper = null;
            }

            if ($newSlides.length) {
                $newSlides.addClass('swiper-slide');
                $wrapper.html($newSlides);
                $currentCarousel.show();
            } else {
                $wrapper.html('');
                $currentCarousel.hide();
                return;
            }

            // Re-init Swiper only on this carousel
            var data = $currentCarousel.data('swiper');
            if (typeof Swiper !== 'undefined' && data) {
                new Swiper($currentCarousel[0], {
                    watchOverflow: true,
                    navigation: {
                        nextEl: $currentCarousel.find('.swiper-button-next').get(0),
                        prevEl: $currentCarousel.find('.swiper-button-prev').get(0),
                    },
                    pagination: {
                        el: $currentCarousel.find('.swiper-pagination').get(0),
                        type: 'bullets',
                        clickable: true,
                    },
                    slidesPerView: parseInt(data.slidesPerView.desktop) || 6,
                    slidesPerGroup: parseInt(data.slidesPerGroup.desktop) || 1,
                    spaceBetween: parseInt(data.spaceBetween.desktop) || 30,
                    breakpoints: {
                        0: {
                            slidesPerView: parseInt(data.slidesPerView.mobile) || 3,
                            slidesPerGroup: parseInt(data.slidesPerGroup.mobile) || 1,
                            spaceBetween: parseInt(data.spaceBetween.mobile) || 15,
                        },
                        768: {
                            slidesPerView: parseInt(data.slidesPerView.tablet) || 4,
                            slidesPerGroup: parseInt(data.slidesPerGroup.tablet) || 1,
                            spaceBetween: parseInt(data.spaceBetween.tablet) || 30,
                        },
                        1025: {
                            slidesPerView: parseInt(data.slidesPerView.desktop) || 6,
                            slidesPerGroup: parseInt(data.slidesPerGroup.desktop) || 1,
                            spaceBetween: parseInt(data.spaceBetween.desktop) || 30,
                        },
                    },
                });
            }
        });
    })(jQuery);
    </script>
    <?php
}

/**
 * Show only top-level categories on /all-categories/ page.
 */
add_filter('get_terms_args', function($args, $taxonomies) {
    if (is_page('all-categories') && in_array('product_cat', (array) $taxonomies)) {
        $args['parent'] = 0;
    }
    return $args;
}, 10, 2);
