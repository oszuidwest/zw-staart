<?php

// Prevent direct file access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Activation hook function: Schedules the event to fetch top posts.
 */
function zwr_activate()
{
    if (!wp_next_scheduled('zwr_event_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'zwr_event_hook');
    }
}

/**
 * Deactivation hook function: Clears the scheduled event.
 */
function zwr_deactivate()
{
    $timestamp = wp_next_scheduled('zwr_event_hook');
    wp_unschedule_event($timestamp, 'zwr_event_hook');
}

register_activation_hook(__FILE__, 'zwr_activate');
register_deactivation_hook(__FILE__, 'zwr_deactivate');

add_action('zwr_event_hook', 'zwr_get_top_posts');

/**
 * Fetches the top posts data from an external API.
 */
function zwr_get_top_posts()
{
    $baseUrl = 'https://stats.zuidwesttv.nl';
    $siteId = 'zuidwestupdate.nl';
    $token = 'your_token_here'; // Replace with your actual token

    $url = "{$baseUrl}/api/v1/stats/breakdown?site_id={$siteId}&period=7d&metrics=pageviews&property=event:page";
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token
        )
    ));

    // Check for WP_Error or a non-200 response code
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        error_log('Error fetching top posts: ' . wp_remote_retrieve_response_message($response));
        return []; // Handle error accordingly
    }

    // Decode the response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $articles = $data['results'] ?? [];

    // Filter and sort the articles
    $filteredArticles = array_filter($articles, static function ($article) {
        $page = $article['page'] ?? '';
        return (str_starts_with($page, '/nieuws/') || str_starts_with($page, '/achtergrond/')) && strlen($page) > strlen('/nieuws/');
    });

    usort($filteredArticles, static function ($item1, $item2) {
        return $item2['pageviews'] <=> $item1['pageviews'];
    });

    $topArticles = array_slice($filteredArticles, 0, 25);
    zwr_save_top_posts($topArticles);
}

/**
 * Saves the top posts data in the WordPress options table.
 *
 * @param array $topPostsDetails Array of top post details to be saved.
 */
function zwr_save_top_posts($topPostsDetails)
{
    update_option('zwr_top_posts', $topPostsDetails);
}
