<?php
/*
Plugin Name: Recirculatie experiment
Description: Top posts onder artikelen
Version: 1.0
Author: Raymon Mens
*/

function top_posts_list() {
    // Check if the transient already exists
    $cached_posts = get_transient('top_posts_list_cache');

    if ($cached_posts === false) {
        // Fetch new data as the transient doesn't exist or has expired
        $response = wp_remote_get('https://www.zuidwestupdate.nl/top10.json');

        if (is_wp_error($response)) {
            return ''; // Or handle the error in a specific way
        }

        $body = wp_remote_retrieve_body($response);
        $posts = json_decode($body, true);

        // Set the transient for 12 hours
        set_transient('top_posts_list_cache', $posts, 12 * HOUR_IN_SECONDS);
    } else {
        $posts = $cached_posts;
    }

    global $post;

    // Filter out the current post's ID
    $filtered_posts = array_filter($posts, function($p) use ($post) {
        return $p['id'] != $post->ID;
    });

    $output = '<aside style="margin-top: 20px; font-family: Arial, sans-serif;">';
    $output .= '<h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;">Meest gelezen</h3>';
    $output .= '<ol style="margin: 0; padding-left: 20px;">';
    $count = 0;

    foreach ($filtered_posts as $p) {
        if ($count < 5) {
            $output .= '<li style="margin-bottom: 10px;"><a href="' . $p['permalink'] . '?utm_source=recirculatie" style="text-decoration: none;">' . $p['title'] . '</a></li>';
            $count++;
        }
    }

    $output .= '</ol></aside>';
    return $output;
}

add_filter('the_content', function($content) {
    if (is_single() && get_post_type() === 'post') {
        $content .= top_posts_list();
    }
    return $content;
});

?>
