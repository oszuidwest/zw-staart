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
    $heading = zw_staart_get_setting('podcast', 'heading', 'Luister ook naar onze podcast');
    $description = zw_staart_get_setting('podcast', 'description');
    $artwork_url = zw_staart_get_setting('podcast', 'artwork_url');
    $spotify_url = zw_staart_get_setting('podcast', 'spotify_url');
    $apple_url = zw_staart_get_setting('podcast', 'apple_url');

    // Return empty if required fields are missing
    if (empty($description)) {
        return '';
    }

    // Start output buffering to capture HTML output
    ob_start();
    ?>
    <aside id="zw-staart-podcast-promo" style="margin-top: 20px;">
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;"><?php echo esc_html($heading); ?></h3>
        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px;">
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <div style="flex: 1;">
                    <p style="margin: 0; padding: 0; line-height: 1.5; font-weight: normal; font-size: 0.95em;"><?php echo esc_html($description); ?></p>
                </div>
                <?php if (!empty($artwork_url)): ?>
                    <img src="<?php echo esc_url($artwork_url); ?>" alt="Podcast artwork" style="width: 100px; min-width: 100px; height: 100px; margin: 0; padding: 0; aspect-ratio: 1; border-radius: 6px; object-fit: cover; display: block;" />
                <?php endif; ?>
            </div>
            <div>
                <p style="margin: 0 0 8px 0; font-size: 0.7em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #666;">Luister nu via:</p>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php if (!empty($spotify_url)): ?>
                        <a href="<?php echo esc_url($spotify_url . '?utm_source=recirculatie'); ?>" class="plausible-event-name=Podcast+Spotify" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background-color: #1DB954; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 0.85em;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                            Spotify
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($apple_url)): ?>
                        <a href="<?php echo esc_url($apple_url . '?utm_source=recirculatie'); ?>" class="plausible-event-name=Podcast+Apple" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background-color: #A45EE5; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; font-size: 0.85em;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></svg>
                            Apple Podcasts
                        </a>
                    <?php endif; ?>
                </div>
                <a href="#" id="zw-staart-show-leestips" style="display: inline-block; margin-top: 12px; font-size: 0.85em; color: #666; text-decoration: underline; cursor: pointer;">Toon mijn vertrouwde leestips</a>
            </div>
        </div>
    </aside>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var showLeestipsLink = document.getElementById('zw-staart-show-leestips');
            if (showLeestipsLink) {
                showLeestipsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    var podcastPromo = document.getElementById('zw-staart-podcast-promo');
                    var topPostsList = document.getElementById('zw-staart-top-posts-list');

                    if (podcastPromo) {
                        podcastPromo.style.display = 'none';
                    }
                    if (topPostsList) {
                        topPostsList.style.display = 'block';
                    }
                });
            }
        });
    </script>
    <style>
        @media (min-width: 600px) {
            #zw-staart-podcast-promo > div {
                position: relative !important;
                flex-direction: column !important;
                padding-right: 160px !important;
            }
            #zw-staart-podcast-promo > div > div:first-child {
                flex-direction: column !important;
            }
            #zw-staart-podcast-promo > div > div:first-child > div {
                margin-bottom: 16px !important;
            }
            #zw-staart-podcast-promo > div > div:first-child img {
                position: absolute !important;
                right: 0 !important;
                top: 0 !important;
                width: 140px !important;
                height: 140px !important;
            }
            #zw-staart-podcast-promo p:first-of-type {
                font-size: 1em !important;
                line-height: 1.6 !important;
            }
            #zw-staart-podcast-promo .plausible-event-name\=Podcast\+Spotify,
            #zw-staart-podcast-promo .plausible-event-name\=Podcast\+Apple {
                font-size: 0.9em !important;
                padding: 10px 18px !important;
                gap: 8px !important;
            }
            #zw-staart-podcast-promo .plausible-event-name\=Podcast\+Spotify svg,
            #zw-staart-podcast-promo .plausible-event-name\=Podcast\+Apple svg {
                width: 16px !important;
                height: 16px !important;
            }
        }
    </style>
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
    // Get heading from settings
    $heading = zw_staart_get_setting('top_posts', 'heading', 'Leestips voor jou');

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
        <h3 style="border-bottom: 2px solid rgb(0, 222, 1); padding-bottom: 5px;"><?php echo esc_html($heading); ?></h3>
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

                // Remove 'lees ookje' if it's directly after our block
                function removeLeesOokjeAfter(element) {
                    if (!element || element.style.display === 'none') return;

                    var nextEl = element.nextElementSibling;
                    if (nextEl && nextEl.tagName === 'A' && nextEl.classList.contains('block')) {
                        var firstSpan = nextEl.querySelector('span');
                        if (firstSpan && firstSpan.textContent.includes('Lees ook:')) {
                            nextEl.remove();
                        }
                    }
                }

                // Check both blocks
                removeLeesOokjeAfter(topPostsList);
                removeLeesOokjeAfter(podcastPromo);
            })();
        </script>
        ";
    }
    return $content;
});
