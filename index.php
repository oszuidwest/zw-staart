<?php
/*
Plugin Name: Recirculatie experiment
Description: Top posts onder artikelen
Version: 1.0
Author: Raymon Mens
*/

/**
 * Fetches top posts from a specified URL.
 *
 * @return array The array of posts or an empty array on error.
 */
function fetch_top_posts()
{
    $response = wp_remote_get('https://www.zuidwestupdate.nl/top10.json');
    if (is_wp_error($response)) {
        // Log error message for troubleshooting
        error_log('Error fetching top posts: ' . $response->get_error_message());
        return [];
    }
    return json_decode(wp_remote_retrieve_body($response), true);
}

/**
 * Generates the HTML for the top posts list.
 *
 * @return string The HTML output for the top posts list.
 */
function top_posts_list()
{
    // Attempt to retrieve cached posts
    $cached_posts = get_transient('top_posts_list_cache');
    if ($cached_posts === false) {
        // Fetch new posts and cache them
        $posts = fetch_top_posts();
        set_transient('top_posts_list_cache', $posts, 12 * HOUR_IN_SECONDS);
    } else {
        $posts = $cached_posts;
    }

    global $post;
    // Filter out the current post to avoid self-reference
    $filtered_posts = array_filter($posts, fn ($p) => $p['id'] != $post->ID);

    // Start buffering the output
    ob_start();
    ?>
    <aside style="margin-top: 20px; font-family: Arial, sans-serif;">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Meest gelezen</h3>
        <ol style="margin: 0; padding-left: 20px;">
            <?php foreach (array_slice($filtered_posts, 0, 5) as $p): ?>
                <li style="margin-bottom: 10px;">
                    <a href="<?php echo esc_url($p['permalink'] . '?utm_source=recirculatie'); ?>" style="text-decoration: none;">
                        <?php echo esc_html($p['title']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </aside>
    <?php
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Appends the top posts list to the post content.
 *
 * @param string $content The content of the post.
 * @return string The modified content with the top posts list appended.
 */
add_filter('the_content', function ($content) {
    // Append the top posts list only for single post pages
    if (is_single() && get_post_type() === 'post' && (!defined('REST_REQUEST') || !REST_REQUEST)) {
        $content .= top_posts_list();
    }
    return $content;
});
