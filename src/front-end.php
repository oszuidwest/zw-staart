<?php
/**
 * Reading tips rendering and eligibility.
 *
 * @package ZuidWest_Staart
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Returns cached popular articles.
 *
 * @return array Articles, or an empty array if unset.
 */
function zw_staart_fetch_top_posts() {
	return (array) get_option( 'zw_staart_top_posts', array() );
}

/**
 * Returns cached, published post IDs in popularity order.
 *
 * @since 0.5.0
 *
 * @param int $exclude_id Post ID to omit.
 * @return int[] Post IDs.
 */
function zw_staart_top_post_ids( $exclude_id ) {
	$post_ids = array_map( static fn( $p ) => (int) ( $p['post_id'] ?? 0 ), zw_staart_fetch_top_posts() );
	$post_ids = array_filter( $post_ids, static fn( $post_id ) => 0 !== $post_id && $post_id !== $exclude_id );

	// Prime posts, terms and meta to avoid per-post queries.
	_prime_post_caches( $post_ids );

	return array_values( array_filter( $post_ids, static fn( $post_id ) => 'publish' === get_post_status( $post_id ) ) );
}

/**
 * Checks whether reading tips belong on a single post outside a dossier.
 *
 * @since 0.5.0
 *
 * @param int $post_id ID of the post being displayed.
 * @return bool True if reading tips should be shown.
 */
function zw_staart_is_eligible_post( $post_id ) {
	return is_single() && 'post' === get_post_type( $post_id ) && ! has_term( '', 'dossier', $post_id ) && ! wp_is_json_request();
}

/**
 * Renders reading tips for browser filtering.
 *
 * @return string Markup, or an empty string if no candidates remain.
 */
function zw_staart_top_posts_list() {
	$heading = zw_staart_get_setting( 'top_posts', 'heading', ZW_STAART_DEFAULT_HEADING );

	global $post;

	$post_ids = zw_staart_top_post_ids( $post->ID );

	if ( empty( $post_ids ) ) {
		return '';
	}

	ob_start();
	?>
	<aside id="zw-staart-top-posts-list" style="margin-top: 20px">
		<h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;"><?php echo esc_html( $heading ); ?></h3>
		<ol style="margin: 0; padding-left: 20px;">
			<?php foreach ( $post_ids as $post_id ) : ?>
				<?php
					$post_permalink = get_permalink( $post_id );
					$post_title     = get_the_title( $post_id );
				?>
				<li class="zw-staart-top-post-item" data-post-id="<?php echo absint( $post_id ); ?>" style="margin-bottom: 10px;">
					<a href="<?php echo esc_url( $post_permalink . '?utm_source=recirculatie' ); ?>" class="plausible-event-name=Recirculatie+Artikel" style="text-decoration: none;">
						<?php echo esc_html( $post_title ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ol>
	</aside>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var minPostsDisplay = <?php echo intval( ZW_STAART_MIN_POSTS_DISPLAY ); ?>;
			var visitedPostIds = getVisitedPostIds();
			var topPostItems = document.querySelectorAll('#zw-staart-top-posts-list .zw-staart-top-post-item');
			var displayedTopPostUrls = [];

			function getVisitedPostIds() {
				var cookieName = 'zw_staart_visited_posts=';
				var cookieValue = document.cookie.split('; ').find(row => row.startsWith(cookieName));
				return cookieValue ? cookieValue.substring(cookieName.length).split(',').map(function(id) {
					return parseInt(id, 10);
				}).filter(function(id) {
					return id > 0;
				}) : [];
			}

			topPostItems.forEach(function (item) {
				var postId = parseInt(item.getAttribute('data-post-id'));
				if (!visitedPostIds.includes(postId) && displayedTopPostUrls.length < minPostsDisplay) {
					var url = new URL(item.querySelector('a').getAttribute('href'));
					var baseUrl = url.origin + url.pathname;
					displayedTopPostUrls.push(baseUrl);
				} else {
					item.style.display = 'none';
				}
			});

			if (displayedTopPostUrls.length < minPostsDisplay) {
				document.getElementById('zw-staart-top-posts-list').remove();
			}

		// Remove duplicate "Lees ook" links.
		document.querySelectorAll('a.block').forEach(function (block) {
			var span = block.querySelector('span');
			if (span && span.textContent.includes('Lees ook:')) {
				var blockUrl = new URL(block.getAttribute('href'));
				var blockBaseUrl = blockUrl.origin + blockUrl.pathname;

				if (displayedTopPostUrls.includes(blockBaseUrl)) {
					block.remove();
				}
			}
		});
	});
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Appends cacheable reading tips; the browser filters visited posts.
 *
 * @param string $content The post content.
 * @return string Content with reading tips on eligible posts.
 */
add_filter(
	hook_name: 'the_content',
	callback: function ( string $content ): string {
		if ( zw_staart_is_eligible_post( (int) get_the_ID() ) ) {
			$content .= zw_staart_top_posts_list();
		}
		return $content;
	},
	priority: 99,
	accepted_args: 1
);
