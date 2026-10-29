<?php
/**
 * Settings and Plausible sync.
 *
 * @package ZuidWest_Staart
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Returns a grouped setting or its default.
 *
 * @param string $group Setting group.
 * @param string $key Setting key.
 * @param mixed  $default_value Fallback value.
 * @return mixed The setting value.
 */
function zw_staart_get_setting( $group, $key, $default_value = '' ) {
	$settings = get_option( 'zw_staart_settings', array() );
	return $settings[ $group ][ $key ] ?? $default_value;
}

/**
 * Returns next article modes and labels.
 *
 * @since 0.5.0
 *
 * @return array<string, string> Labels keyed by mode.
 */
function zw_staart_next_article_modes() {
	return array(
		'off'  => 'Uit: iedereen ziet het leestipsblok',
		'on'   => 'Aan: iedereen ziet onderin een balk met de volgende leestip',
		'test' => 'A/B-test: de helft ziet de balk (variant b), de andere helft het leestipsblok (variant a)',
	);
}

/**
 * Returns a supported next article bar mode, or 'off' for an invalid value.
 *
 * @since 0.5.0
 *
 * @param mixed $mode Submitted value.
 * @return string Mode.
 */
function zw_staart_sanitize_next_article_mode( $mode ) {
	return is_string( $mode ) && isset( zw_staart_next_article_modes()[ $mode ] ) ? $mode : 'off';
}

/**
 * Migrates pre-0.4.0 options and removes the legacy cron event.
 *
 * @since 0.4.0
 */
function zw_staart_migrate_from_old_version() {
	$new_settings = get_option( 'zw_staart_settings', array() );

	if ( empty( $new_settings['plausible'] ) ) {
		$migrated_settings = array(
			'plausible' => array(
				'api_key'  => get_option( 'zwr_plausible_api_key', '' ),
				'site_id'  => get_option( 'zwr_plausible_site_id', '' ),
				'endpoint' => get_option( 'zwr_plausible_api_endpoint', '' ),
				'days'     => get_option( 'zwr_plausible_days', '' ),
			),
		);

		if ( ! empty( array_filter( $migrated_settings['plausible'] ) ) ) {
			update_option( 'zw_staart_settings', $migrated_settings );
		}
	}

	$old_top_posts = get_option( 'zwr_top_posts' );
	if ( $old_top_posts && ! get_option( 'zw_staart_top_posts' ) ) {
		update_option( 'zw_staart_top_posts', $old_top_posts );
	}

	$old_timestamp = wp_next_scheduled( 'zwr_event_hook' );
	if ( $old_timestamp ) {
		wp_unschedule_event( $old_timestamp, 'zwr_event_hook' );
	}

	$old_options = array(
		'zwr_plausible_api_key',
		'zwr_plausible_site_id',
		'zwr_plausible_api_endpoint',
		'zwr_plausible_days',
		'zwr_podcast_title',
		'zwr_podcast_description',
		'zwr_podcast_artwork_url',
		'zwr_podcast_spotify_url',
		'zwr_podcast_apple_url',
		'zwr_top_posts',
	);
	array_map( delete_option( ... ), $old_options );
}

/**
 * Migrates options and schedules the hourly sync.
 *
 * @return void
 */
function zw_staart_activate() {
	zw_staart_migrate_from_old_version();

	$timestamp = wp_next_scheduled( 'zw_staart_event_hook' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'zw_staart_event_hook' );
	}

	wp_schedule_event( time(), 'hourly', 'zw_staart_event_hook' );
}

/**
 * Clears the scheduled event on plugin deactivation.
 *
 * @return void
 */
function zw_staart_deactivate() {
	$timestamp = wp_next_scheduled( 'zw_staart_event_hook' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'zw_staart_event_hook' );
	}
}

add_action(
	hook_name: 'zw_staart_event_hook',
	callback: zw_staart_get_top_posts( ... ),
	priority: 10,
	accepted_args: 0
);

add_action(
	hook_name: 'admin_menu',
	callback: zw_staart_add_admin_menu( ... ),
	priority: 10,
	accepted_args: 0
);

/**
 * Adds admin menu for plugin settings.
 *
 * @return void
 */
function zw_staart_add_admin_menu() {
	add_options_page(
		page_title: 'ZuidWest Staart Instellingen',
		menu_title: 'ZuidWest Staart',
		capability: 'manage_options',
		menu_slug: 'zw-staart-settings',
		callback: zw_staart_settings_page( ... ),
		position: null
	);
}

add_action(
	hook_name: 'admin_init',
	callback: zw_staart_register_settings( ... ),
	priority: 10,
	accepted_args: 0
);

/**
 * Registers settings for the plugin.
 *
 * @return void
 */
function zw_staart_register_settings() {
	register_setting(
		option_group: 'zw_staart_settings_group',
		option_name: 'zw_staart_settings',
		args: array(
			'type'              => 'array',
			'sanitize_callback' => zw_staart_sanitize_settings( ... ),
			'default'           => array(),
			'show_in_rest'      => false,
			'description'       => 'ZuidWest Staart plugin settings for Plausible Analytics and reading tips',
		)
	);
}

/**
 * Sanitizes and validates plugin settings.
 *
 * @since 0.5.0
 *
 * @param array $input Settings grouped by plausible, top_posts and next_article.
 * @return array Sanitized settings with defaults for missing or invalid values.
 */
function zw_staart_sanitize_settings( $input ) {
	// Keep the stored shape: WordPress sanitizes twice on the first save.
	$plausible    = (array) ( $input['plausible'] ?? array() );
	$top_posts    = (array) ( $input['top_posts'] ?? array() );
	$next_article = (array) ( $input['next_article'] ?? array() );

	$days = intval( $plausible['days'] ?? ZW_STAART_DEFAULT_DAYS );
	if ( $days < 1 || $days > 30 ) {
		$days = ZW_STAART_DEFAULT_DAYS;
	}

	return array(
		'plausible'    => array(
			'api_key'  => sanitize_text_field( $plausible['api_key'] ?? '' ),
			'site_id'  => sanitize_text_field( $plausible['site_id'] ?? '' ),
			'endpoint' => esc_url_raw( $plausible['endpoint'] ?? '' ),
			'days'     => $days,
		),
		'top_posts'    => array(
			'heading' => sanitize_text_field( $top_posts['heading'] ?? ZW_STAART_DEFAULT_HEADING ),
		),
		'next_article' => array(
			'mode' => zw_staart_sanitize_next_article_mode( $next_article['mode'] ?? '' ),
		),
	);
}

/**
 * Displays the plugin settings page.
 *
 * @return void
 */
function zw_staart_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'zw_staart_messages' );

	$plausible_api_key  = zw_staart_get_setting( 'plausible', 'api_key' );
	$plausible_site_id  = zw_staart_get_setting( 'plausible', 'site_id' );
	$plausible_endpoint = zw_staart_get_setting( 'plausible', 'endpoint' );
	$plausible_days     = zw_staart_get_setting( 'plausible', 'days' );
	$plausible_days     = $plausible_days ? $plausible_days : ZW_STAART_DEFAULT_DAYS;

	$top_posts_heading = zw_staart_get_setting( 'top_posts', 'heading', ZW_STAART_DEFAULT_HEADING );
	$next_article_mode = zw_staart_get_setting( 'next_article', 'mode', 'off' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p style="max-width: 800px; margin-bottom: 30px;">
			Deze plugin toont leestips onder artikelen (in de 'staart van het artikel') op basis van populaire artikelen uit Plausible Analytics.
			Configureer hieronder de weergave en de verbinding met Plausible.
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'zw_staart_settings_group' ); ?>

			<h2>Weergave</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Koptekst leestips</th>
					<td>
						<input type="text" name="zw_staart_settings[top_posts][heading]" value="<?php echo esc_attr( $top_posts_heading ); ?>" class="regular-text" placeholder="<?php echo esc_attr( ZW_STAART_DEFAULT_HEADING ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row">Doorscrollen</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">Doorscrollen</legend>
							<?php foreach ( zw_staart_next_article_modes() as $mode => $label ) : ?>
								<label>
									<input type="radio" name="zw_staart_settings[next_article][mode]"
										value="<?php echo esc_attr( $mode ); ?>" <?php checked( $next_article_mode, $mode ); ?> />
									<?php echo esc_html( $label ); ?>
								</label><br />
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							Het wiel in de balk loopt vol tijdens het lezen; wie doorscrolt tot het einde van de tekst, gaat naar het volgende artikel.
							Staat de balk aan, dan wordt het leestipsblok onder het artikel verborgen;
							na sluiten komt het terug als er genoeg ongelezen leestips zijn.
						</p>
						<p class="description">
							In de A/B-test wordt elke bezoeker per dag ingedeeld; ingelogde gebruikers zien de balk en tellen niet mee.
							Plausible krijgt bij elke artikelweergave het event Recirculatie Weergave
							en bij elk recirculatie-event de eigenschap variant.
						</p>
					</td>
				</tr>
			</table>

			<hr style="margin: 40px 0;">

			<h2>Leestips</h2>
			<p>Vul onderstaande velden in om leestips te tonen op basis van Plausible Analytics-data.</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">API-sleutel</th>
					<td>
						<input type="password" name="zw_staart_settings[plausible][api_key]" value="<?php echo esc_attr( $plausible_api_key ); ?>" class="regular-text" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<th scope="row">Site-ID</th>
					<td>
						<input type="text" name="zw_staart_settings[plausible][site_id]" value="<?php echo esc_attr( $plausible_site_id ); ?>" class="regular-text" placeholder="zuidwestupdate.nl" />
					</td>
				</tr>
				<tr>
					<th scope="row">API-endpoint</th>
					<td>
						<input type="url" name="zw_staart_settings[plausible][endpoint]" value="<?php echo esc_attr( $plausible_endpoint ); ?>" class="large-text" placeholder="https://stats.zuidwesttv.nl/api/v2/query" />
					</td>
				</tr>
				<tr>
					<th scope="row">Aantal dagen</th>
					<td>
						<input type="number" name="zw_staart_settings[plausible][days]" value="<?php echo esc_attr( $plausible_days ); ?>" min="1" max="30" class="small-text" />
						<p class="description">Leestips zijn maximaal zoveel dagen oud en worden gerangschikt op weergaven in die periode (standaard: 5)</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Opslaan' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Fetches and caches popular articles from Plausible.
 */
function zw_staart_get_top_posts() {
	$api_key      = zw_staart_get_setting( 'plausible', 'api_key' );
	$site_id      = zw_staart_get_setting( 'plausible', 'site_id' );
	$api_endpoint = zw_staart_get_setting( 'plausible', 'endpoint' );
	$days         = zw_staart_get_setting( 'plausible', 'days' );
	$days         = intval( $days ? $days : ZW_STAART_DEFAULT_DAYS );

	if ( empty( $api_key ) || empty( $site_id ) || empty( $api_endpoint ) ) {
		return;
	}

	// Include today in the lookback.
	$end_date   = wp_date( 'Y-m-d' );
	$start_date = wp_date( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );

	$request_data = array(
		'site_id'    => $site_id,
		'metrics'    => array( 'pageviews' ),
		'date_range' => array( $start_date, $end_date ),
		'dimensions' => array( 'event:page' ),
		'order_by'   => array( array( 'pageviews', 'desc' ) ),
		'pagination' => array(
			'limit'  => ZW_STAART_API_PAGINATION_LIMIT,
			'offset' => 0,
		),
	);

	$response = wp_remote_post(
		$api_endpoint,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_data ),
			'timeout' => ZW_STAART_API_TIMEOUT,
		)
	);

	if ( is_wp_error( $response ) ) {
		return;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $response_code ) {
		return;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	$articles = array();
	if ( ! empty( $data['results'] ) ) {
		foreach ( $data['results'] as $result ) {
			$page      = $result['dimensions'][0];
			$pageviews = $result['metrics'][0];

			$articles[] = array(
				'page'      => $page,
				'pageviews' => $pageviews,
			);
		}
	}

	$filtered_articles = array_filter(
		$articles,
		static fn( $article ) => ( str_starts_with( $article['page'] ?? '', '/nieuws/' )
								|| str_starts_with( $article['page'] ?? '', '/achtergrond/' ) )
								&& strlen( $article['page'] ?? '' ) > strlen( '/nieuws/' )
	);

	usort( $filtered_articles, static fn( $a, $b ) => $b['pageviews'] <=> $a['pageviews'] );

	// Resolve before slicing, so articles that are too old leave room for the next ones.
	$top_articles = zw_staart_resolve_post_ids( $filtered_articles, $days );
	$top_articles = array_filter( $top_articles, static fn( $article ) => 0 !== $article['post_id'] );
	$top_articles = array_slice( $top_articles, 0, ZW_STAART_MAX_TOP_ARTICLES );

	zw_staart_save_top_posts( $top_articles );
}

/**
 * Resolves article paths to post IDs.
 *
 * Skips articles older than the lookback, which the next article bar would
 * otherwise keep popular by sending readers to them.
 *
 * @param array $articles Articles with URL paths in 'page'.
 * @param int   $days     Maximum article age in days.
 * @return array Articles with 'post_id' added; 0 if unresolved or too old.
 */
function zw_staart_resolve_post_ids( $articles, $days ) {
	if ( empty( $articles ) ) {
		return array();
	}

	$slugs = array();
	foreach ( $articles as $article ) {
		$path  = trim( $article['page'], '/' );
		$parts = explode( '/', $path );

		$slugs[] = end( $parts );
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'post_name__in'          => $slugs,
			'posts_per_page'         => count( $slugs ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
			'orderby'                => 'post_name__in',
			'date_query'             => array(
				array(
					'after'     => wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					'inclusive' => true,
				),
			),
		)
	);

	$slug_to_id = array();
	if ( $query->have_posts() ) {
		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$slug_to_id[ $post->post_name ] = $post_id;
			}
		}
	}

	foreach ( $articles as &$article ) {
		$path  = trim( $article['page'], '/' );
		$parts = explode( '/', $path );
		$slug  = end( $parts );

		$article['post_id'] = $slug_to_id[ $slug ] ?? 0;
	}
	unset( $article );

	return $articles;
}

/**
 * Caches popular articles.
 *
 * @param array $top_posts_details Articles to cache.
 */
function zw_staart_save_top_posts( $top_posts_details ) {
	update_option( 'zw_staart_top_posts', $top_posts_details );
}
