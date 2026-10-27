<?php
/**
 * Template part for displaying posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Glozin
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php \Glozin\Blog\Post::category('gz-blog-badge-outline', true); ?>
		<?php \Glozin\Blog\Post::title('h1', true); ?>
		<?php // Entry meta (author, date, comments) removed per request ?>
	</header>
	<div class="entry-content entry-single-content mt-40 clearfix">
		<?php \Glozin\Blog\Post::content(); ?>
	</div>
	<footer class="entry-footer mt-40 mb-40 d-flex flex-wrap align-items-center justify-content-between gap-15">
		<?php // Tags removed per request ?>
		<?php \Glozin\Blog\Post::share(); ?>
	</footer>
</article>
