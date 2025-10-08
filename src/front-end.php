<?php
// Prevent direct file access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Retrieves top posts from the WordPress options table.
 *
 * @return array An array of top posts or an empty array if not set.
 */
function zw_staart_fetch_top_posts()
{
    // Get the top posts stored in the wp_options table
    $topPosts = get_option('zw_staart_top_posts');
    // Ensure the returned value is an array
    return is_array($topPosts) ? $topPosts : [];
}

/**
 * Generates the HTML markup for displaying the podcast promo block.
 *
 * @return string The HTML output for the podcast promo block.
 */
function zw_staart_podcast_promo_block()
{
    // Get podcast settings from serialized options
    $title = zw_staart_get_setting('podcast', 'title');
    $description = zw_staart_get_setting('podcast', 'description');
    $artwork_url = zw_staart_get_setting('podcast', 'artwork_url');
    $spotify_url = zw_staart_get_setting('podcast', 'spotify_url');
    $apple_url = zw_staart_get_setting('podcast', 'apple_url');

    // Return empty if required fields are missing
    if (empty($title) || empty($description)) {
        return '';
    }

    // Start output buffering to capture HTML output
    ob_start();
    ?>
    <aside id="zw-staart-podcast-promo" style="margin-top: 20px">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Luister ook naar onze podcast</h3>
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 15px;">
            <?php if (!empty($artwork_url)): ?>
                <img src="<?php echo esc_url($artwork_url); ?>" alt="<?php echo esc_attr($title); ?>" style="width: 120px; height: 120px; border-radius: 8px; object-fit: cover;" />
            <?php endif; ?>
            <div style="flex: 1;">
                <h4 style="margin: 0 0 10px 0; font-size: 1.2em;"><?php echo esc_html($title); ?></h4>
                <p style="margin: 0 0 15px 0; line-height: 1.5;"><?php echo esc_html($description); ?></p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if (!empty($spotify_url)): ?>
                        <a href="<?php echo esc_url($spotify_url . '?utm_source=recirculatie'); ?>" class="plausible-event-name=Podcast+Spotify" style="display: inline-block; padding: 8px 16px; background-color: #1DB954; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">
                            Luister op Spotify
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($apple_url)): ?>
                        <a href="<?php echo esc_url($apple_url . '?utm_source=recirculatie'); ?>" class="plausible-event-name=Podcast+Apple" style="display: inline-block; padding: 8px 16px; background-color: #A45EE5; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">
                            Luister op Apple Podcasts
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>
    <?php
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Generates the HTML markup for displaying the top posts list.
 *
 * @return string The HTML output for the top posts list.
 */
function zw_staart_top_posts_list()
{
    // Fetch the top posts
    $posts = zw_staart_fetch_top_posts();

    // Check if there are no posts and log an error
    if (empty($posts)) {
        error_log('No top post data available in zw_staart_top_posts option.');
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
    <aside id="zw-staart-top-posts-list" style="margin-top: 20px">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Leestips voor jou</h3>
        <ol style="margin: 0; padding-left: 20px;">
            <?php foreach ($output_posts as $p): ?>
                <?php
                    $post_id = url_to_postid(home_url($p['page']));
                    $post_permalink = get_permalink($post_id);
                    $post_title = get_the_title($post_id);
                ?>
                <li class="zw-staart-top-post-item" data-post-id="<?php echo esc_attr($post_id); ?>" style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url($post_permalink . '?utm_source=recirculatie'); ?>" class="plausible-event-name=Recirculatie" style="text-decoration: none;">
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
	        var topPostItems = document.querySelectorAll('#zw-staart-top-posts-list .zw-staart-top-post-item');
	        var displayedTopPostUrls = [];

	        // Function to parse the 'zw_staart_visited_posts' cookie
	        function getVisitedPostIds() {
	            var cookieValue = document.cookie.split('; ').find(row => row.startsWith('zw_staart_visited_posts='));
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
	            document.getElementById('zw-staart-top-posts-list').remove();
	        }

        // Remove "Read this too" blocks if they match any of the displayed top posts
        document.querySelectorAll('a.block').forEach(function (block) {
            if (block.querySelector('span') && block.querySelector('span').textContent.includes('Lees ook:')) {
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
 * Appends both the top posts list and podcast promo block to the end of the post content.
 * JavaScript will randomly hide one of the two blocks (50/50) for cache compatibility.
 *
 * @param string $content The content of the post.
 * @return string The modified content with both blocks appended.
 */
add_filter('the_content', function ($content) {
    global $post;

    // Only append content for single post pages of type 'post'
    // and not having terms in the 'dossier' taxonomy
    if (is_single() && get_post_type() === 'post' &&
        (!has_term('', 'dossier', $post->ID)) &&
        (!defined('REST_REQUEST') || !REST_REQUEST)) {

        // Output both blocks - JavaScript will hide one randomly
        $content .= zw_staart_top_posts_list();
        $content .= zw_staart_podcast_promo_block();

        // Add JavaScript for 50/50 random hiding with smart fallback
        $content .= "
        <script>
            (function() {
                var topPostsList = document.getElementById('zw-staart-top-posts-list');
                var podcastPromo = document.getElementById('zw-staart-podcast-promo');

                // Check which blocks actually exist in the DOM
                var hasTopPosts = topPostsList !== null;
                var hasPodcast = podcastPromo !== null;

                // Smart selection logic
                if (hasTopPosts && hasPodcast) {
                    // Both blocks exist: 50/50 random selection
                    var showPodcast = Math.random() < 0.5;
                    if (showPodcast) {
                        topPostsList.style.display = 'none';
                    } else {
                        podcastPromo.style.display = 'none';
                    }
                } else if (hasTopPosts && !hasPodcast) {
                    // Only top posts exists: show it (nothing to hide)
                } else if (!hasTopPosts && hasPodcast) {
                    // Only podcast exists: show it (nothing to hide)
                }
                // If neither exists, nothing to do
            })();
        </script>
        ";
    }
    return $content;
});
