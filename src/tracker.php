<?php

add_action('wp_enqueue_scripts', 'enqueue_postid_tracker_script');
function enqueue_postid_tracker_script() {
    global $post;
    wp_enqueue_script('postid-tracker-js', plugin_dir_url(__FILE__) . 'postid-tracker.js', array('jquery'), '1.0', true);

    if (is_single() && isset($post->ID)) {
        wp_localize_script('postid-tracker-js', 'wpData', array('postId' => $post->ID));
    }

    add_inline_script();
}

function add_inline_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            let postId = null;

            if (typeof wpData !== 'undefined' && wpData.postId) {
                postId = wpData.postId;
                updatePostIdCookie(postId);
            }

            function updatePostIdCookie(postId) {
                let postIds = getCookie('visitedPostIds');
                postIds = postIds ? postIds.split(',') : [];
                if (postIds.indexOf(postId.toString()) === -1) {
                    postIds.push(postId);
                }
                setCookie('visitedPostIds', postIds.join(','), 7);
            }

            function setCookie(name, value, days) {
                let expires = "";
                if (days) {
                    let date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "")  + expires + "; path=/";
            }

            function getCookie(name) {
                let nameEQ = name + "=";
                let ca = document.cookie.split(';');
                for(let i=0;i < ca.length;i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1,c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
                }
                return null;
            }
        });
    </script>
    <?php
}
