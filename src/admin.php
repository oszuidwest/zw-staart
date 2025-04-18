<?php

// Prevent direct file access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Activation hook function: Schedules the event to fetch top posts.
 */
function zwr_activate()
{
    // Clear any existing event to ensure clean scheduling
    $timestamp = wp_next_scheduled('zwr_event_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'zwr_event_hook');
    }

    // Schedule the new event
    wp_schedule_event(time(), 'hourly', 'zwr_event_hook');
}

/**
 * Deactivation hook function: Clears the scheduled event.
 */
function zwr_deactivate()
{
    $timestamp = wp_next_scheduled('zwr_event_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'zwr_event_hook');
    }
}

add_action('zwr_event_hook', 'zwr_get_top_posts');

/**
 * Fetches the top posts data from Plausible Analytics API.
 */
function zwr_get_top_posts()
{
    // Configuration
    $apiKey = 'your_token_here';
    $siteId = 'zuidwestupdate.nl';
    $apiEndpoint = 'https://stats.zuidwesttv.nl/api/v2/query';

    // Calculate date range (last 5 days)
    $endDate = date('Y-m-d'); // Today
    $startDate = date('Y-m-d', strtotime('-4 days')); // 4 days ago (for a total of 5 days including today)

    // Create the request data
    $requestData = [
        'site_id' => $siteId,
        'metrics' => ['pageviews'],
        'date_range' => [$startDate, $endDate],
        'dimensions' => ['event:page'],
        'order_by' => [['pageviews', 'desc']],
        'pagination' => ['limit' => 100, 'offset' => 0]
    ];

    // Get the response via the WordPress HTTP API
    $response = wp_remote_post($apiEndpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($requestData),
        'timeout' => 30
    ]);

    // Check for WP_Error or a non-200 response code
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        error_log('Error fetching top posts: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response)));
        return []; // Handle error accordingly
    }

    // Decode the response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Structure matches API v2 response format
    $articles = [];
    if (!empty($data['results'])) {
        foreach ($data['results'] as $result) {
            $page = $result['dimensions'][0];
            $pageviews = $result['metrics'][0];
            
            $articles[] = [
                'page' => $page,
                'pageviews' => $pageviews
            ];
        }
    }

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
