<?php
/**
 * Next article bar for ZuidWest Staart plugin.
 *
 * Shows a floating bar with the next reading tip. Its wheel fills while the visitor reads
 * and opens the next article as a regular page load once the share buttons are reached.
 *
 * @package ZuidWest_Staart
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Returns a small 16:9 thumbnail URL for the next article bar.
 *
 * Uses the theme's imgproxy helper when it exists, as the theme's own tiles do.
 *
 * @param int $post_id Post ID.
 * @return string Image URL, or an empty string when the post has no featured image.
 */
function zw_staart_next_article_image( $post_id ) {
	$src = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( ! $src ) {
		return '';
	}

	// Twice the 80x45 slot in the bar, for sharp images on high density screens.
	return function_exists( 'zw_imgproxy' ) ? zw_imgproxy( $src, 160, 90 ) : (string) get_the_post_thumbnail_url( $post_id, 'medium' );
}

/**
 * Builds the candidates for the next article bar, most read first.
 *
 * The browser picks the first candidate the visitor has not read yet.
 *
 * @param int $current_id ID of the post being displayed.
 * @return array<int, array{id: int, url: string, title: string, image: string}> Candidates.
 */
function zw_staart_next_article_candidates( $current_id ) {
	$candidates = array();

	foreach ( zw_staart_top_post_ids( $current_id ) as $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			continue; // Post was removed after the last Plausible sync.
		}

		$candidates[] = array(
			'id'    => $post_id,
			'url'   => add_query_arg(
				array(
					'utm_source'  => 'recirculatie',
					'utm_content' => 'scroll',
				),
				$permalink
			),
			'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ),
			'image' => zw_staart_next_article_image( $post_id ),
		);
	}

	return $candidates;
}

/**
 * Enqueues the next article bar on eligible posts when it is on or in A/B test mode.
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

	// Logged-in users, the newsroom, see the bar during the test but do not take part in it. Cloudflare
	// does not cache their pages, so this check reaches them.
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
