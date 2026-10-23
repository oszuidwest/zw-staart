<?php
/**
 * Front-end functionality for ZuidWest Staart plugin.
 *
 * @package ZuidWest_Staart
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Retrieves top posts from the WordPress options table.
 *
 * @return array An array of top posts or an empty array if not set.
 */
function zw_staart_fetch_top_posts() {
	return (array) get_option( 'zw_staart_top_posts', array() );
}

/**
 * Returns the IDs of the cached top posts, most read first.
 *
 * Skips posts whose ID could not be resolved during the Plausible sync, and posts that were deleted or
 * unpublished since.
 *
 * @param int $exclude_id Post ID to leave out, usually the post being displayed.
 * @return int[] Post IDs.
 */
function zw_staart_top_post_ids( $exclude_id ) {
	$post_ids = array_map( static fn( $p ) => (int) ( $p['post_id'] ?? 0 ), zw_staart_fetch_top_posts() );
	$post_ids = array_filter( $post_ids, static fn( $post_id ) => 0 !== $post_id && $post_id !== $exclude_id );

	// Load the posts with their terms and meta in a few queries instead of a few per post.
	_prime_post_caches( $post_ids );

	return array_values( array_filter( $post_ids, static fn( $post_id ) => 'publish' === get_post_status( $post_id ) ) );
}

/**
 * Checks whether reading tips belong on this request: a single news post outside a dossier.
 *
 * REST API requests are skipped to avoid polluting API responses with promotional HTML.
 *
 * @param int $post_id ID of the post being displayed.
 * @return bool True if reading tips should be shown.
 */
function zw_staart_is_eligible_post( $post_id ) {
	return is_single() && 'post' === get_post_type( $post_id ) && ! has_term( '', 'dossier', $post_id ) && ! wp_is_json_request();
}

/**
 * Generates the HTML markup for displaying the top posts list.
 *
 * @return string The HTML output for the top posts list.
 */
function zw_staart_top_posts_list() {
	// Get heading from settings.
	$heading = zw_staart_get_setting( 'top_posts', 'heading', ZW_STAART_DEFAULT_HEADING );

	global $post;

	// Post IDs are pre-resolved during API fetch to avoid N+1 queries.
	$post_ids = zw_staart_top_post_ids( $post->ID );

	if ( empty( $post_ids ) ) {
		return '';
	}

	// Start output buffering to capture HTML output.
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

			// Display only the top posts not visited, up to the minimum required.
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

			// Remove the entire aside element if less than minimum posts are displayed.
			if (displayedTopPostUrls.length < minPostsDisplay) {
				document.getElementById('zw-staart-top-posts-list').remove();
			}

		// Remove "Read this too" blocks if they match any of the displayed top posts.
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
 * Appends the reading tips block to post content.
 *
 * The block is rendered server-side for caching efficiency; client-side JavaScript
 * hides articles the visitor has already read.
 *
 * @param string $content The post content.
 * @return string Modified content with the reading tips block appended.
 */
add_filter(
	hook_name: 'the_content',
	callback: function ( string $content ): string {
		if ( zw_staart_is_eligible_post( (int) get_the_ID() ) ) {
			$content .= zw_staart_top_posts_list();
		}
		return $content;
	},
	priority: 99, // Run late to ensure other plugins have added their content first.
	accepted_args: 1
);
