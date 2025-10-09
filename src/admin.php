<?php

// Prevent direct file access
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Gets a setting value from the serialized settings array.
 *
 * @param string $group Setting group (e.g., 'plausible', 'podcast')
 * @param string $key Setting key
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed The setting value
 */
function zw_staart_get_setting($group, $key, $default = '')
{
    $settings = get_option('zw_staart_settings', []);
    return $settings[$group][$key] ?? $default;
}

/**
 * Updates a setting value in the serialized settings array.
 *
 * @param string $group Setting group (e.g., 'plausible', 'podcast')
 * @param string $key Setting key
 * @param mixed $value Setting value
 */
function zw_staart_update_setting($group, $key, $value)
{
    $settings = get_option('zw_staart_settings', []);
    if (!isset($settings[$group])) {
        $settings[$group] = [];
    }
    $settings[$group][$key] = $value;
    update_option('zw_staart_settings', $settings);
}

/**
 * Migrates old zwr_ prefixed data to new zw_staart_ structure.
 * Cleans up old cron hooks and database entries.
 */
function zw_staart_migrate_from_old_version()
{
    // 1. Migrate old individual settings to new serialized structure (if not already done)
    $new_settings = get_option('zw_staart_settings', []);

    if (empty($new_settings['plausible']) || empty($new_settings['podcast'])) {
        $migrated_settings = [
            'plausible' => [
                'api_key' => get_option('zwr_plausible_api_key', ''),
                'site_id' => get_option('zwr_plausible_site_id', ''),
                'endpoint' => get_option('zwr_plausible_api_endpoint', ''),
                'days' => get_option('zwr_plausible_days', 5)
            ],
            'podcast' => [
                'title' => get_option('zwr_podcast_title', ''),
                'description' => get_option('zwr_podcast_description', ''),
                'artwork_url' => get_option('zwr_podcast_artwork_url', ''),
                'spotify_url' => get_option('zwr_podcast_spotify_url', ''),
                'apple_url' => get_option('zwr_podcast_apple_url', '')
            ]
        ];

        // Only save if we found some data
        if (!empty(array_filter($migrated_settings['plausible'])) || !empty(array_filter($migrated_settings['podcast']))) {
            update_option('zw_staart_settings', $migrated_settings);
        }
    }

    // 2. Migrate old top posts data
    $old_top_posts = get_option('zwr_top_posts');
    if ($old_top_posts && !get_option('zw_staart_top_posts')) {
        update_option('zw_staart_top_posts', $old_top_posts);
    }

    // 3. Remove old cron hook
    $old_timestamp = wp_next_scheduled('zwr_event_hook');
    if ($old_timestamp) {
        wp_unschedule_event($old_timestamp, 'zwr_event_hook');
    }

    // 4. Delete old individual option keys
    delete_option('zwr_plausible_api_key');
    delete_option('zwr_plausible_site_id');
    delete_option('zwr_plausible_api_endpoint');
    delete_option('zwr_plausible_days');
    delete_option('zwr_podcast_title');
    delete_option('zwr_podcast_description');
    delete_option('zwr_podcast_artwork_url');
    delete_option('zwr_podcast_spotify_url');
    delete_option('zwr_podcast_apple_url');
    delete_option('zwr_top_posts');
}

/**
 * Activation hook function: Schedules the event to fetch top posts.
 */
function zw_staart_activate()
{
    // Run migration from old version
    zw_staart_migrate_from_old_version();

    // Clear any existing event to ensure clean scheduling
    $timestamp = wp_next_scheduled('zw_staart_event_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'zw_staart_event_hook');
    }

    // Schedule the new event
    wp_schedule_event(time(), 'hourly', 'zw_staart_event_hook');
}

/**
 * Deactivation hook function: Clears the scheduled event.
 */
function zw_staart_deactivate()
{
    $timestamp = wp_next_scheduled('zw_staart_event_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'zw_staart_event_hook');
    }
}

add_action('zw_staart_event_hook', 'zw_staart_get_top_posts');

/**
 * Adds admin menu for plugin settings.
 */
add_action('admin_menu', 'zw_staart_add_admin_menu');
function zw_staart_add_admin_menu()
{
    add_options_page(
        'ZuidWest Staart',
        'ZuidWest Staart',
        'manage_options',
        'zw-staart-settings',
        'zw_staart_settings_page'
    );
}

/**
 * Registers settings for the plugin.
 */
add_action('admin_init', 'zw_staart_register_settings');
function zw_staart_register_settings()
{
    register_setting('zw_staart_settings_group', 'zw_staart_settings');
}

/**
 * Sanitizes and saves the settings from POST data.
 */
add_action('admin_init', 'zw_staart_save_settings');
function zw_staart_save_settings()
{
    if (!isset($_POST['zw_staart_settings_nonce']) ||
        !wp_verify_nonce($_POST['zw_staart_settings_nonce'], 'zw_staart_settings_action')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = [
        'plausible' => [
            'api_key' => sanitize_text_field($_POST['zw_staart_plausible_api_key'] ?? ''),
            'site_id' => sanitize_text_field($_POST['zw_staart_plausible_site_id'] ?? ''),
            'endpoint' => esc_url_raw($_POST['zw_staart_plausible_endpoint'] ?? ''),
            'days' => intval($_POST['zw_staart_plausible_days'] ?? 5)
        ],
        'podcast' => [
            'description' => sanitize_textarea_field($_POST['zw_staart_podcast_description'] ?? ''),
            'artwork_url' => esc_url_raw($_POST['zw_staart_podcast_artwork_url'] ?? ''),
            'spotify_url' => esc_url_raw($_POST['zw_staart_podcast_spotify_url'] ?? ''),
            'apple_url' => esc_url_raw($_POST['zw_staart_podcast_apple_url'] ?? ''),
            'heading' => sanitize_text_field($_POST['zw_staart_podcast_heading'] ?? 'Luister ook naar onze podcast')
        ],
        'top_posts' => [
            'heading' => sanitize_text_field($_POST['zw_staart_top_posts_heading'] ?? 'Leestips voor jou')
        ]
    ];

    update_option('zw_staart_settings', $settings);

    add_settings_error(
        'zw_staart_messages',
        'zw_staart_message',
        'Instellingen opgeslagen',
        'updated'
    );
}

/**
 * Renders the settings page.
 */
function zw_staart_settings_page()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Show error/update messages
    settings_errors('zw_staart_messages');

    // Get current settings
    $plausible_api_key = zw_staart_get_setting('plausible', 'api_key');
    $plausible_site_id = zw_staart_get_setting('plausible', 'site_id');
    $plausible_endpoint = zw_staart_get_setting('plausible', 'endpoint');
    $plausible_days = zw_staart_get_setting('plausible', 'days') ?: 5;

    $podcast_description = zw_staart_get_setting('podcast', 'description');
    $podcast_artwork_url = zw_staart_get_setting('podcast', 'artwork_url');
    $podcast_spotify_url = zw_staart_get_setting('podcast', 'spotify_url');
    $podcast_apple_url = zw_staart_get_setting('podcast', 'apple_url');
    $podcast_heading = zw_staart_get_setting('podcast', 'heading', 'Luister ook naar onze podcast');
    $top_posts_heading = zw_staart_get_setting('top_posts', 'heading', 'Leestips voor jou');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('zw_staart_settings_action', 'zw_staart_settings_nonce'); ?>

            <h2>Plausible Analytics-instellingen (optioneel)</h2>
            <p>Configureer de verbinding met Plausible Analytics voor het ophalen van populaire artikelen. <strong>Laat leeg als je alleen het podcast-promoblok wilt gebruiken.</strong></p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="zw_staart_plausible_api_key" value="<?php echo esc_attr($plausible_api_key); ?>" class="regular-text" />
                        <p class="description">De API-sleutel voor Plausible Analytics</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Site ID</th>
                    <td>
                        <input type="text" name="zw_staart_plausible_site_id" value="<?php echo esc_attr($plausible_site_id); ?>" class="regular-text" />
                        <p class="description">Het site-ID in Plausible (bijv. zuidwestupdate.nl)</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">API Endpoint</th>
                    <td>
                        <input type="url" name="zw_staart_plausible_endpoint" value="<?php echo esc_attr($plausible_endpoint); ?>" class="large-text" />
                        <p class="description">De volledige URL naar de Plausible API v2 endpoint (bijv. https://stats.zuidwesttv.nl/api/v2/query)</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Aantal dagen terug</th>
                    <td>
                        <input type="number" name="zw_staart_plausible_days" value="<?php echo esc_attr($plausible_days); ?>" min="1" max="30" class="small-text" />
                        <p class="description">Hoeveel dagen terug moet de data worden opgehaald (standaard: 5)</p>
                    </td>
                </tr>
            </table>

            <h2>Podcast-promoinstellingen (optioneel)</h2>
            <p>Vul onderstaande velden in om het podcast-promoblok te configureren. <strong>Laat leeg als je alleen de meest gelezen artikelen wilt tonen.</strong> Als beide blokken zijn geconfigureerd, worden ze willekeurig afgewisseld (50/50).</p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Koptekst</th>
                    <td>
                        <input type="text" name="zw_staart_podcast_heading" value="<?php echo esc_attr($podcast_heading); ?>" class="regular-text" />
                        <p class="description">De koptekst boven het podcast-promoblok (standaard: "Luister ook naar onze podcast")</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Beschrijving</th>
                    <td>
                        <textarea name="zw_staart_podcast_description" rows="4" class="large-text"><?php echo esc_textarea($podcast_description); ?></textarea>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Artwork URL (1:1)</th>
                    <td>
                        <input type="url" name="zw_staart_podcast_artwork_url" value="<?php echo esc_attr($podcast_artwork_url); ?>" class="regular-text" />
                        <p class="description">Voer de volledige URL in naar de podcast-coverafbeelding (bijv. https://example.com/artwork.jpg)</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Spotify-link</th>
                    <td>
                        <input type="url" name="zw_staart_podcast_spotify_url" value="<?php echo esc_attr($podcast_spotify_url); ?>" class="regular-text" />
                        <p class="description">Voer de volledige Spotify-URL in (bijv. https://open.spotify.com/show/...)</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Apple Podcasts-link</th>
                    <td>
                        <input type="url" name="zw_staart_podcast_apple_url" value="<?php echo esc_attr($podcast_apple_url); ?>" class="regular-text" />
                        <p class="description">Voer de volledige Apple Podcasts-URL in (bijv. https://podcasts.apple.com/...)</p>
                    </td>
                </tr>
            </table>

            <h2>Leestips-instellingen</h2>
            <p>Configureer de weergave van de meest gelezen artikelen.</p>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Koptekst</th>
                    <td>
                        <input type="text" name="zw_staart_top_posts_heading" value="<?php echo esc_attr($top_posts_heading); ?>" class="regular-text" />
                        <p class="description">De koptekst boven de lijst met meest gelezen artikelen (standaard: "Leestips voor jou")</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Instellingen opslaan'); ?>
        </form>
    </div>
    <?php
}

/**
 * Fetches the top posts data from Plausible Analytics API.
 */
function zw_staart_get_top_posts()
{
    // Get configuration from serialized settings
    $apiKey = zw_staart_get_setting('plausible', 'api_key');
    $siteId = zw_staart_get_setting('plausible', 'site_id');
    $apiEndpoint = zw_staart_get_setting('plausible', 'endpoint');
    $days = intval(zw_staart_get_setting('plausible', 'days') ?: 5);

    // Validate required settings
    if (empty($apiKey) || empty($siteId) || empty($apiEndpoint)) {
        error_log('ZuidWest Staart: Plausible Analytics settings are not configured. Please configure them in Settings → ZuidWest Staart.');
        return [];
    }

    // Calculate date range based on configured number of days
    $endDate = date('Y-m-d'); // Today
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days')); // X days ago (for a total of X days including today)

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
    zw_staart_save_top_posts($topArticles);
}

/**
 * Saves the top posts data in the WordPress options table.
 *
 * @param array $topPostsDetails Array of top post details to be saved.
 */
function zw_staart_save_top_posts($topPostsDetails)
{
    update_option('zw_staart_top_posts', $topPostsDetails);
}
