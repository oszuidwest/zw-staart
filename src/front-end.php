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

    // Filter out the current post
    $filtered_posts = array_filter($posts, function ($p) use ($post) {
        $post_id = url_to_postid(home_url($p['page']));
        return $post_id != 0 && $post_id != $post->ID;
    });

    // Check if there are at least 5 posts after filtering
    if (count($filtered_posts) < 5) {
        error_log('Not enough top posts to display after excluding the current post.');
        return '';
    }

    // Start output buffering to capture HTML output
    ob_start();
    ?>
    <aside style="margin-top: 20px; font-family: Arial, sans-serif;">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Meest gelezen</h3>
        <ol style="margin: 0; padding-left: 20px;">
            <?php foreach (array_slice($filtered_posts, 0, 5) as $p): ?>
                <?php
                    $post_id = url_to_postid(home_url($p['page']));
                    $post_permalink = get_permalink($post_id);
                    $post_title = get_the_title($post_id);
                ?>
                <li style="margin-bottom: 10px;" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <a href="<?php echo esc_url($post_permalink . '?utm_source=recirculatie'); ?>" style="text-decoration: none;">
                        <?php echo esc_html($post_title); ?>
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
