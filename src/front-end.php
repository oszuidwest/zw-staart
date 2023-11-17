<?php
// Prevent direct file access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Retrieves top posts from the WordPress options table.
 *
 * @return array An array of top posts or an empty array if not set.
 */
function fetch_top_posts()
{
    // Get the top posts stored in the wp_options table
    $topPosts = get_option('zwr_top_posts');
    // Ensure the returned value is an array
    return is_array($topPosts) ? $topPosts : [];
}

/**
 * Generates the HTML markup for displaying the top posts list.
 *
 * @return string The HTML output for the top posts list.
 */
function top_posts_list()
{
    // Fetch the top posts
    $posts = fetch_top_posts();

    // Check if there are no posts and log an error
    if (empty($posts)) {
        error_log('No top post data available in zwr_top_posts option.');
        return '';
    }

    global $post;

    // Output all posts, excluding the current post
    $output_posts = array_filter($posts, function ($p) use ($post) {
        $post_id = url_to_postid(home_url($p['page']));
        return $post_id != 0 && $post_id != $post->ID;
    });

    // Generate a mapping of post IDs to URLs
    $postIdToUrlMapping = [];
    foreach ($output_posts as $p) {
        $post_id = url_to_postid(home_url($p['page']));
        $postIdToUrlMapping[$post_id] = get_permalink($post_id);
    }

    // Start output buffering to capture HTML output
    ob_start();
    ?>
    <aside id="top-posts-list" style="margin-top: 20px; font-family: Arial, sans-serif;">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Leestips voor jou ⬇️</h3>
        <ol style="margin: 0; padding-left: 20px;">
            <?php foreach ($output_posts as $p): ?>
                <?php
                    $post_id = url_to_postid(home_url($p['page']));
                    $post_permalink = get_permalink($post_id);
                    $post_title = get_the_title($post_id);
                ?>
                <li class="top-post-item plausible-event-name=Recirculatie" data-post-id="<?php echo esc_attr($post_id); ?>" style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url($post_permalink . '?utm_source=recirculatie'); ?>" style="text-decoration: none;">
                        <?php echo esc_html($post_title); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </aside>
    <script>
	    document.addEventListener('DOMContentLoaded', function () {
	        var postIdToUrlMapping = <?php echo json_encode($postIdToUrlMapping); ?>;
	        var visitedPostIds = getVisitedPostIds();
	        var topPostItems = document.querySelectorAll('#top-posts-list .top-post-item');
	        var displayedTopPostUrls = [];

	        // Function to parse the 'visitedPostIds' cookie
	        function getVisitedPostIds() {
	            var cookieValue = document.cookie.split('; ').find(row => row.startsWith('visitedPostIds='));
	            return cookieValue ? cookieValue.split('=')[1].split(',').map(Number) : [];
	        }

	        // Display only the top posts not visited, up to 5
	        var displayedCount = 0;
	        topPostItems.forEach(function (item) {
	            var postId = parseInt(item.getAttribute('data-post-id'));
	            if (!visitedPostIds.includes(postId) && displayedCount < 5) {
	                var url = new URL(item.querySelector('a').getAttribute('href'));
	                var baseUrl = url.origin + url.pathname;
	                displayedTopPostUrls.push(baseUrl);
	                displayedCount++;
	            } else {
	                item.style.display = 'none';
	            }
	        });

	        // Remove the entire aside element if less than 5 posts are displayed
	        if (displayedTopPostUrls.length < 5) {
	            document.getElementById('top-posts-list').remove();
	        }

        // Remove "Read this too" blocks if they match any of the displayed top posts
        document.querySelectorAll('a.block').forEach(function (block) {
            if (block.querySelector('span').textContent.includes('Lees ook:')) {
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
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Appends the top posts list to the end of the post content.
 *
 * @param string $content The content of the post.
 * @return string The modified content with the top posts list appended.
 */
add_filter('the_content', function ($content) {
    global $post;

    // Append the top posts list only for single post pages of type 'post'
    // and not having terms in the 'dossier' taxonomy
    if (is_single() && get_post_type() === 'post' && 
        (!has_term('', 'dossier', $post->ID)) &&
        (!defined('REST_REQUEST') || !REST_REQUEST)) {
        $content .= top_posts_list();
    }
    return $content;
});