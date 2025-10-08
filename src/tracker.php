<?php

add_action('wp_footer', 'zw_staart_add_postid_tracker_script');
function zw_staart_add_postid_tracker_script() {
    global $post;

    if (is_single() && isset($post->ID)) {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var postId = '<?php echo $post->ID; ?>';
                updatePostIdCookie(postId);

                function updatePostIdCookie(postId) {
                    var postIds = getCookie('zw_staart_visited_posts');
                    postIds = postIds ? postIds.split(',') : [];
                    if (postIds.indexOf(postId) === -1) {
                        postIds.push(postId);
                    }
                    setCookie('zw_staart_visited_posts', postIds.join(','), 7);
                }

                function setCookie(name, value, days) {
                    var expires = "";
                    if (days) {
                        var date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = ";expires=" + date.toUTCString();
                    }
                    document.cookie = name + "=" + (value || "") + expires + ";path=/";
                }

                function getCookie(name) {
                    var nameEQ = name + "=";
                    var ca = document.cookie.split(';');
                    for (var i = 0; i < ca.length; i++) {
                        var c = ca[i];
                        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                    }
                    return null;
                }
            });
        </script>
        <?php
    }
}
