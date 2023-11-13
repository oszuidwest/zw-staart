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
    global $post;
    // Filter out the current post to avoid self-reference in the list
    $filtered_posts = array_filter($posts, fn ($p) => $p['id'] != $post->ID);

    // Start output buffering to capture HTML output
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
 * Appends the top posts list to the end of the post content.
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
