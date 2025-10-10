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
 * Migrates data from legacy plugin prefix (zwr_) to current prefix (zw_staart_).
 *
 * Handles backward compatibility for sites upgrading from version < 0.4.0.
 * Converts individual option entries to serialized settings structure and
 * cleans up legacy cron jobs and database entries.
 *
 * Safe to run multiple times - only migrates if new structure is not populated.
 *
 * @since 0.4.0
 */
function zw_staart_migrate_from_old_version()
{
    // Migrate individual option entries to serialized settings array
    // Legacy: Multiple get_option('zwr_*') calls -> Current: Single get_option('zw_staart_settings')
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

        // Prevent overwriting existing settings with empty migration data
        if (!empty(array_filter($migrated_settings['plausible'])) || !empty(array_filter($migrated_settings['podcast']))) {
            update_option('zw_staart_settings', $migrated_settings);
        }
    }

    // Migrate cached top posts data to new option name
    $old_top_posts = get_option('zwr_top_posts');
    if ($old_top_posts && !get_option('zw_staart_top_posts')) {
        update_option('zw_staart_top_posts', $old_top_posts);
    }

    // Clean up legacy cron job
    $old_timestamp = wp_next_scheduled('zwr_event_hook');
    if ($old_timestamp) {
        wp_unschedule_event($old_timestamp, 'zwr_event_hook');
    }

    // Delete legacy individual option entries
    $old_options = [
        'zwr_plausible_api_key',
        'zwr_plausible_site_id',
        'zwr_plausible_api_endpoint',
        'zwr_plausible_days',
        'zwr_podcast_title',
        'zwr_podcast_description',
        'zwr_podcast_artwork_url',
        'zwr_podcast_spotify_url',
        'zwr_podcast_apple_url',
        'zwr_top_posts'
    ];
    array_map(delete_option(...), $old_options);
}

/**
 * Activation hook function: Schedules the event to fetch top posts.
 */
function zw_staart_activate()
{
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

add_action(
    hook_name: 'zw_staart_event_hook',
    callback: zw_staart_get_top_posts(...),
    priority: 10,
    accepted_args: 0
);

/**
 * Adds admin menu for plugin settings.
 */
add_action(
    hook_name: 'admin_menu',
    callback: zw_staart_add_admin_menu(...),
    priority: 10,
    accepted_args: 0
);
function zw_staart_add_admin_menu()
{
    add_options_page(
        page_title: 'ZuidWest Staart Instellingen',
        menu_title: 'ZuidWest Staart',
        capability: 'manage_options',
        menu_slug: 'zw-staart-settings',
        callback: zw_staart_settings_page(...),
        position: null
    );
}

/**
 * Registers settings for the plugin.
 */
add_action(
    hook_name: 'admin_init',
    callback: zw_staart_register_settings(...),
    priority: 10,
    accepted_args: 0
);
function zw_staart_register_settings()
{
    register_setting(
        option_group: 'zw_staart_settings_group',
        option_name: 'zw_staart_settings',
        args: [
            'type'              => 'array',
            'sanitize_callback' => zw_staart_sanitize_settings(...),
            'default'           => [],
            'show_in_rest'      => false,
            'description'       => 'ZuidWest Staart plugin settings for Plausible Analytics and podcast promotion'
        ]
    );
}

/**
 * Sanitizes and validates plugin settings.
 *
 * @param array $input Raw form input data.
 * @return array Sanitized settings array.
 */
function zw_staart_sanitize_settings($input)
{
    $days = intval($input['zw_staart_plausible_days'] ?? ZW_STAART_DEFAULT_DAYS);
    if ($days < 1 || $days > 30) {
        $days = ZW_STAART_DEFAULT_DAYS;
    }

    $sanitized = [
        'plausible' => [
            'api_key' => sanitize_text_field($input['zw_staart_plausible_api_key'] ?? ''),
            'site_id' => sanitize_text_field($input['zw_staart_plausible_site_id'] ?? ''),
            'endpoint' => esc_url_raw($input['zw_staart_plausible_endpoint'] ?? ''),
            'days' => $days
        ],
        'podcast' => [
            'description' => sanitize_textarea_field($input['zw_staart_podcast_description'] ?? ''),
            'artwork_url' => esc_url_raw($input['zw_staart_podcast_artwork_url'] ?? ''),
            'spotify_url' => esc_url_raw($input['zw_staart_podcast_spotify_url'] ?? ''),
            'apple_url' => esc_url_raw($input['zw_staart_podcast_apple_url'] ?? ''),
            'heading' => sanitize_text_field($input['zw_staart_podcast_heading'] ?? 'Luister ook naar onze podcast')
        ],
        'top_posts' => [
            'heading' => sanitize_text_field($input['zw_staart_top_posts_heading'] ?? 'Leestips voor jou')
        ]
    ];

    return $sanitized;
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

    $plausible_api_key = zw_staart_get_setting('plausible', 'api_key');
    $plausible_site_id = zw_staart_get_setting('plausible', 'site_id');
    $plausible_endpoint = zw_staart_get_setting('plausible', 'endpoint');
    $plausible_days = zw_staart_get_setting('plausible', 'days') ?: ZW_STAART_DEFAULT_DAYS;

    $podcast_description = zw_staart_get_setting('podcast', 'description');
    $podcast_artwork_url = zw_staart_get_setting('podcast', 'artwork_url');
    $podcast_spotify_url = zw_staart_get_setting('podcast', 'spotify_url');
    $podcast_apple_url = zw_staart_get_setting('podcast', 'apple_url');
    $podcast_heading = zw_staart_get_setting('podcast', 'heading', 'Luister ook naar onze podcast');
    $top_posts_heading = zw_staart_get_setting('top_posts', 'heading', 'Leestips voor jou');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p style="max-width: 800px; margin-bottom: 30px;">
            Deze plugin toont promotionele blokken onder artikelen (in de 'staart van het artikel'). Leestips op basis van populaire artikelen, een podcast-promo, of beide willekeurig afgewisseld.
            Configureer hieronder welke blokken je wilt gebruiken.
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('zw_staart_settings_group'); ?>

            <!-- Weergave-instellingen -->
            <h2>Weergave</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Koptekst leestips</th>
                    <td>
                        <input type="text" name="zw_staart_settings[zw_staart_top_posts_heading]" value="<?php echo esc_attr($top_posts_heading); ?>" class="regular-text" placeholder="Leestips voor jou" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Koptekst podcast</th>
                    <td>
                        <input type="text" name="zw_staart_settings[zw_staart_podcast_heading]" value="<?php echo esc_attr($podcast_heading); ?>" class="regular-text" placeholder="Luister ook naar onze podcast" />
                    </td>
                </tr>
            </table>

            <hr style="margin: 40px 0;">

            <!-- Leestips configuratie -->
            <h2>Leestips</h2>
            <p>Vul onderstaande velden in om leestips te tonen op basis van Plausible Analytics-data.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">API-sleutel</th>
                    <td>
                        <input type="password" name="zw_staart_settings[zw_staart_plausible_api_key]" value="<?php echo esc_attr($plausible_api_key); ?>" class="regular-text" autocomplete="off" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Site-ID</th>
                    <td>
                        <input type="text" name="zw_staart_settings[zw_staart_plausible_site_id]" value="<?php echo esc_attr($plausible_site_id); ?>" class="regular-text" placeholder="zuidwestupdate.nl" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">API-endpoint</th>
                    <td>
                        <input type="url" name="zw_staart_settings[zw_staart_plausible_endpoint]" value="<?php echo esc_attr($plausible_endpoint); ?>" class="large-text" placeholder="https://stats.zuidwesttv.nl/api/v2/query" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Aantal dagen</th>
                    <td>
                        <input type="number" name="zw_staart_settings[zw_staart_plausible_days]" value="<?php echo esc_attr($plausible_days); ?>" min="1" max="30" class="small-text" />
                        <p class="description">Aantal dagen terug voor data-analyse (standaard: 5)</p>
                    </td>
                </tr>
            </table>

            <hr style="margin: 40px 0;">

            <!-- Podcast configuratie -->
            <h2>Podcast-promo</h2>
            <p>Vul onderstaande velden in om een podcast-promoblok te tonen.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Beschrijving</th>
                    <td>
                        <textarea name="zw_staart_settings[zw_staart_podcast_description]" rows="3" class="large-text" placeholder="Korte introductie van de podcast..."><?php echo esc_textarea($podcast_description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cover (1:1)</th>
                    <td>
                        <input type="url" name="zw_staart_settings[zw_staart_podcast_artwork_url]" value="<?php echo esc_attr($podcast_artwork_url); ?>" class="regular-text" placeholder="https://..." />
                        <p class="description">Vierkante afbeelding</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Spotify-link</th>
                    <td>
                        <input type="url" name="zw_staart_settings[zw_staart_podcast_spotify_url]" value="<?php echo esc_attr($podcast_spotify_url); ?>" class="regular-text" placeholder="https://open.spotify.com/show/..." />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Apple Podcasts-link</th>
                    <td>
                        <input type="url" name="zw_staart_settings[zw_staart_podcast_apple_url]" value="<?php echo esc_attr($podcast_apple_url); ?>" class="regular-text" placeholder="https://podcasts.apple.com/..." />
                    </td>
                </tr>
            </table>

            <p style="background: #f0f0f1; padding: 15px; border-left: 4px solid #72aee6; margin-top: 30px;">
                <strong>Tip:</strong> Als je beide blokken configureert, worden ze willekeurig afgewisseld (50/50).
                Configureer slechts één blok als je altijd hetzelfde wilt tonen.
            </p>

            <?php submit_button('Opslaan'); ?>
        </form>
    </div>
    <?php
}

/**
 * Fetches the top posts data from Plausible Analytics API.
 */
function zw_staart_get_top_posts()
{
    $apiKey = zw_staart_get_setting('plausible', 'api_key');
    $siteId = zw_staart_get_setting('plausible', 'site_id');
    $apiEndpoint = zw_staart_get_setting('plausible', 'endpoint');
    $days = intval(zw_staart_get_setting('plausible', 'days') ?: ZW_STAART_DEFAULT_DAYS);

    error_log('ZuidWest Staart DEBUG: API Key: ' . (!empty($apiKey) ? 'SET' : 'EMPTY'));
    error_log('ZuidWest Staart DEBUG: Site ID: ' . $siteId);
    error_log('ZuidWest Staart DEBUG: Endpoint: ' . $apiEndpoint);
    error_log('ZuidWest Staart DEBUG: Days: ' . $days);

    // Validate required settings
    if (empty($apiKey) || empty($siteId) || empty($apiEndpoint)) {
        error_log('ZuidWest Staart: Plausible Analytics settings are not configured. Please configure them in Settings → ZuidWest Staart.');
        return;
    }

    // Calculate date range using wp_date() to respect WordPress timezone settings
    $endDate = wp_date('Y-m-d'); // Today
    $startDate = wp_date('Y-m-d', strtotime('-' . ($days - 1) . ' days')); // X days ago (for a total of X days including today)

    // Create the request data
    $requestData = [
        'site_id' => $siteId,
        'metrics' => ['pageviews'],
        'date_range' => [$startDate, $endDate],
        'dimensions' => ['event:page'],
        'order_by' => [['pageviews', 'desc']],
        'pagination' => ['limit' => ZW_STAART_API_PAGINATION_LIMIT, 'offset' => 0]
    ];

    // Get the response via the WordPress HTTP API
    $response = wp_remote_post($apiEndpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($requestData),
        'timeout' => ZW_STAART_API_TIMEOUT
    ]);

    // Check for WP_Error or a non-200 response code
    if (is_wp_error($response)) {
        error_log('ZuidWest Staart ERROR: API call failed - ' . $response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        error_log('ZuidWest Staart ERROR: API returned non-200 status - ' . $response_code . ': ' . wp_remote_retrieve_response_message($response));
        error_log('ZuidWest Staart ERROR: Response body - ' . wp_remote_retrieve_body($response));
        return;
    }

    // Decode the response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    error_log('ZuidWest Staart DEBUG: API returned ' . count($data['results'] ?? []) . ' results');

    // Parse Plausible Analytics API response
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

    // Filter articles to include only news and background posts with valid paths
    $filteredArticles = array_filter(
        $articles,
        static fn($article) => (str_starts_with($article['page'] ?? '', '/nieuws/')
                                || str_starts_with($article['page'] ?? '', '/achtergrond/'))
                                && strlen($article['page'] ?? '') > strlen('/nieuws/')
    );

    error_log('ZuidWest Staart DEBUG: Filtered down to ' . count($filteredArticles) . ' news/background articles');

    usort($filteredArticles, static fn($a, $b) => $b['pageviews'] <=> $a['pageviews']);

    $topArticles = array_slice($filteredArticles, 0, ZW_STAART_MAX_TOP_ARTICLES);

    // Resolve post IDs in batch to avoid N+1 queries later
    $topArticles = zw_staart_resolve_post_ids($topArticles);

    $articles_with_ids = array_filter($topArticles, fn($a) => !empty($a['post_id']));
    error_log('ZuidWest Staart DEBUG: Resolved ' . count($articles_with_ids) . ' post IDs out of ' . count($topArticles) . ' articles');

    zw_staart_save_top_posts($topArticles);
    error_log('ZuidWest Staart DEBUG: Saved ' . count($topArticles) . ' articles to database');
}

/**
 * Resolves post IDs for article URLs in batch to avoid N+1 query problem.
 *
 * @param array $articles Array of articles with 'page' keys containing URL paths
 * @return array Articles with 'post_id' added to each item
 */
function zw_staart_resolve_post_ids($articles)
{
    if (empty($articles)) {
        return [];
    }

    // Extract slugs from URLs
    $slugs = [];
    foreach ($articles as $article) {
        $path = trim($article['page'], '/');
        $parts = explode('/', $path);
        // Last part is the slug (e.g., /nieuws/article-slug -> article-slug)
        if (!empty($parts)) {
            $slugs[] = end($parts);
        }
    }

    if (empty($slugs)) {
        return $articles;
    }

    // Query all posts in a single database call for optimal performance
    $query = new WP_Query([
        'post_type'              => 'post',
        'post_status'            => 'publish',
        'post_name__in'          => $slugs,
        'posts_per_page'         => count($slugs),
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'ignore_sticky_posts'    => true,
        'orderby'                => 'post_name__in' // Preserve order from post_name__in array
    ]);

    // Create a map of slug -> post_id
    $slug_to_id = [];
    if ($query->have_posts()) {
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $slug_to_id[$post->post_name] = $post_id;
            }
        }
    }

    // Add post IDs to articles
    foreach ($articles as &$article) {
        $path = trim($article['page'], '/');
        $parts = explode('/', $path);
        $slug = !empty($parts) ? end($parts) : '';

        $article['post_id'] = $slug_to_id[$slug] ?? 0;
    }
    unset($article);

    return $articles;
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
