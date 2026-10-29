<?php
/**
 * Next article bar.
 *
 * @package ZuidWest_Staart
 * @since 0.5.0
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Returns a 160x90 imgproxy thumbnail, falling back to WordPress 'medium'.
 *
 * @since 0.5.0
 *
 * @param int $post_id Post ID.
 * @return string Image URL, or an empty string when no image is available.
 */
function zw_staart_next_article_image( $post_id ) {
	$src = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( ! $src ) {
		return '';
	}

	return function_exists( 'zw_imgproxy' ) ? zw_imgproxy( $src, 160, 90 ) : (string) get_the_post_thumbnail_url( $post_id, 'medium' );
}

/**
 * Builds candidates for the browser to filter by reading history.
 *
 * @since 0.5.0
 *
 * @param int $current_id Current post ID to omit.
 * @return array<int, array{id: int, url: string, title: string, image: string}>
 *     Candidates in ranking order.
 */
function zw_staart_next_article_candidates( $current_id ) {
	$candidates = array();
	$post_ids   = zw_staart_top_post_ids( $current_id );

	// Prime attachments to avoid per-image queries.
	_prime_post_caches( array_filter( array_map( 'get_post_thumbnail_id', $post_ids ) ), false, true );

	foreach ( $post_ids as $post_id ) {
		$candidates[] = array(
			'id'    => $post_id,
			'url'   => add_query_arg(
				array(
					'utm_source'  => 'recirculatie',
					'utm_content' => 'scroll',
				),
				get_permalink( $post_id )
			),
			'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ),
			'image' => zw_staart_next_article_image( $post_id ),
		);
	}

	return $candidates;
}

/**
 * Enqueues the next article bar on eligible posts in 'on' or 'test' mode.
 *
 * @since 0.5.0
 *
 * @return void
 */
function zw_staart_enqueue_next_article() {
	$current_id = get_queried_object_id();
	$mode       = zw_staart_get_setting( 'next_article', 'mode', 'off' );

	if ( 'off' === $mode || ! zw_staart_is_eligible_post( $current_id ) ) {
		return;
	}

	$candidates = zw_staart_next_article_candidates( $current_id );
	if ( empty( $candidates ) ) {
		return;
	}

	wp_enqueue_style(
		handle: 'zw-staart-next-article',
		src: plugins_url( 'assets/next-article.css', ZW_STAART_PLUGIN_FILE ),
		deps: array(),
		ver: ZW_STAART_VERSION
	);

	wp_enqueue_script(
		'zw-staart-next-article',
		plugins_url( 'assets/next-article.js', ZW_STAART_PLUGIN_FILE ),
		array(),
		ZW_STAART_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	// Exclude logged-in users from the test; their pages must bypass the page cache.
	$in_test = 'test' === $mode && ! is_user_logged_in();

	wp_add_inline_script(
		handle: 'zw-staart-next-article',
		data: 'window.zwStaartNextArticle = ' . wp_json_encode(
			array(
				'candidates' => $candidates,
				'test'       => $in_test,
			),
			JSON_HEX_TAG | JSON_HEX_AMP
		) . ';',
		position: 'before'
	);
}

add_action(
	hook_name: 'wp_enqueue_scripts',
	callback: zw_staart_enqueue_next_article( ... ),
	priority: 10,
	accepted_args: 0
);
