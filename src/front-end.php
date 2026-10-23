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
 * Generates the HTML markup for displaying the top posts list.
 *
 * @return string The HTML output for the top posts list.
 */
function zw_staart_top_posts_list() {
	// Get heading from settings.
	$heading = zw_staart_get_setting( 'top_posts', 'heading', 'Leestips voor jou' );

	// Fetch the top posts.
	$posts = zw_staart_fetch_top_posts();

	// Check if there are no posts.
	if ( empty( $posts ) ) {
		return '';
	}

	global $post;

	// Post IDs are pre-resolved during API fetch to avoid N+1 queries.
	$output_posts = array_filter(
		$posts,
		fn( $p ) => ( $p['post_id'] ?? 0 ) !== 0 && ( $p['post_id'] ?? 0 ) !== $post->ID
	);

	// Generate a mapping of post IDs to URLs (used in JavaScript).
	$post_id_to_url_mapping = array();
	foreach ( $output_posts as $p ) {
		$post_id = $p['post_id'] ?? 0;
		if ( $post_id ) {
			$post_id_to_url_mapping[ $post_id ] = get_permalink( $post_id );
		}
	}

	// Start output buffering to capture HTML output.
	ob_start();
	?>
	<aside id="zw-staart-top-posts-list" style="margin-top: 20px">
		<h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;"><?php echo esc_html( $heading ); ?></h3>
		<ol style="margin: 0; padding-left: 20px;">
			<?php foreach ( $output_posts as $p ) : ?>
				<?php
					$post_id = $p['post_id'] ?? 0;
				if ( ! $post_id ) {
					continue; // Skip if post ID wasn't resolved.
				}

					$post_permalink = get_permalink( $post_id );
					$post_title     = get_the_title( $post_id );
				?>
				<li class="zw-staart-top-post-item" data-post-id="<?php echo esc_attr( $post_id ); ?>" style="margin-bottom: 10px;">
					<a href="<?php echo esc_url( $post_permalink . '?utm_source=recirculatie' ); ?>" class="plausible-event-name=Recirculatie+Artikel" style="text-decoration: none;">
						<?php echo esc_html( $post_title ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ol>
	</aside>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var postIdToUrlMapping = <?php echo wp_json_encode( $post_id_to_url_mapping, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
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
			var displayedCount = 0;
			topPostItems.forEach(function (item) {
				var postId = parseInt(item.getAttribute('data-post-id'));
				if (!visitedPostIds.includes(postId) && displayedCount < minPostsDisplay) {
					var url = new URL(item.querySelector('a').getAttribute('href'));
					var baseUrl = url.origin + url.pathname;
					displayedTopPostUrls.push(baseUrl);
					displayedCount++;
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
		global $post;

		// Skip REST API requests to avoid polluting API responses with promotional HTML.
		if ( is_single() && get_post_type() === 'post' &&
		( ! has_term( '', 'dossier', $post->ID ) ) &&
		! wp_is_json_request() ) {
			$content .= zw_staart_top_posts_list();
		}
		return $content;
	},
	priority: 99, // Run late to ensure other plugins have added their content first.
	accepted_args: 1
);
