<?php
/**
 * Visitor tracking functionality for ZuidWest Staart plugin.
 *
 * @package ZuidWest_Staart
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds visitor tracking script to footer.
 *
 * Tracks visited post IDs in a cookie for personalized content filtering.
 *
 * @return void
 */
function zw_staart_add_postid_tracker_script(): void {
	global $post;

	if ( is_single() && isset( $post->ID ) ) {
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var postId = <?php echo intval( $post->ID ); ?>;
				var cookieExpiryDays = <?php echo intval( ZW_STAART_COOKIE_EXPIRY_DAYS ); ?>;
				var maxVisitedPosts = <?php echo intval( ZW_STAART_MAX_VISITED_POSTS ); ?>;
				updatePostIdCookie(postId);

				function updatePostIdCookie(postId) {
					var postIds = getCookie('zw_staart_visited_posts');
					postIds = postIds ? parsePostIds(postIds) : [];
					// Move re-visited IDs to the tail so slice(-maxVisitedPosts) keeps the most recent visits.
					postIds = postIds.filter(function(id) {
						return id !== postId;
					});
					postIds.push(postId);
					postIds = postIds.slice(-maxVisitedPosts);
					setCookie('zw_staart_visited_posts', postIds.join(','), cookieExpiryDays);
				}

				function parsePostIds(value) {
					return value.split(',').map(function(id) {
						return parseInt(id, 10);
					}).filter(function(id, index, postIds) {
						return id > 0 && postIds.lastIndexOf(id) === index;
					});
				}

				function setCookie(name, value, days) {
					var expires = "";
					if (days) {
						var date = new Date();
						date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
						expires = ";expires=" + date.toUTCString();
					}
					// Add Secure flag if on HTTPS, and SameSite for CSRF protection
					var secure = window.location.protocol === 'https:' ? ';Secure' : '';
					document.cookie = name + "=" + (value || "") + expires + ";path=/;SameSite=Lax" + secure;
				}

				function getCookie(name) {
					var nameEQ = name + "=";
					var ca = document.cookie.split(';');
					for (var i = 0; i < ca.length; i++) {
						var c = ca[i];
						while (c.charAt(0) === ' ') c = c.substring(1, c.length);
						if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
					}
					return null;
				}
			});
		</script>
		<?php
	}
}

add_action(
	hook_name: 'wp_footer',
	callback: zw_staart_add_postid_tracker_script( ... ),
	priority: 20,
	accepted_args: 0
);
