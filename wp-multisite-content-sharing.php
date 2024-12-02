<?php
/**
 * Plugin Name:         WP Multisite Content Sharing
 * Plugin URI:          https://github.com/9ete/wp-multisite-content-sharing
 * GitHub Plugin URI:   USER/wp-multisite-content-sharing
 * Description:         Add custom post types on the fly via WP post type functionality
 * Author:              USER
 * Author URI:          9ete.com
 * Text Domain:         wp-custom-multisite-content-sharing
 * Domain Path:         /languages
 * Version:             0.0.1
 *
 * @package         WP_Multisite_Content_Sharing
 */

/**
 * Define the plugin version
 */
define( 'WP_MULTISITE_CONTENT_SHARING_VERSION', '0.0.1' );

/**
 * Imports posts from a source site to a destination site in a WordPress multisite network.
 * 
 * If a post with the same title already exists on the destination site, it will be deleted and recreated.
 * A category based on the source site's title is assigned to all imported posts.
 * Post meta is used to ensure the import runs only once per source post.
 * 
 * @param int $source_site      The blog ID of the source site from which posts will be imported.
 * @param int $destination_id   The blog ID of the destination site where posts will be imported.
 * @return void
 */
function import_posts_from_site($source_site = 2, $destination_id = 1) {

    // Ensure this runs only for logged-in users if needed
    if (!is_user_logged_in()) {
        return; // Exit if the user is not logged in
    }

    // Switch to the source site
    switch_to_blog($source_site);

    // Get all posts from the source site
    $args = [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Get all posts
    ];
    $source_posts = get_posts($args);

    // Get the source site title to use as a category
    $source_site_title = get_bloginfo('name');

    // Switch back to the destination site
    restore_current_blog();
    switch_to_blog($destination_id);

    // Ensure the category exists on the destination site
    $category = get_term_by('name', $source_site_title, 'category');
    if (!$category) {
        $category = wp_insert_term($source_site_title, 'category');
    }

    // Extract the category ID
    $category_id = is_array($category) ? $category['term_id'] : $category->term_id;

    // if user doesn't exist on destination site with the username source site title, create one
    $user = get_user_by('login', $source_site_title);
    if (!$user) {
        $destination_site_url = get_blog_option($destination_id, 'siteurl');
        $user = wp_create_user($source_site_title, wp_generate_password(), $source_site_title . '@' . parse_url($destination_site_url, PHP_URL_HOST));
    }

    // Import posts to the destination site
    foreach ($source_posts as $post) {
        // Check if this post has already been imported using its unique source ID
        $source_post_meta_key = '_source_post_id'; // Meta key to track the source post ID
        $source_post_id = $post->ID; // ID of the post on the source site

        $existing_posts = new WP_Query([
            'post_type'      => 'post',
            'meta_query'     => [
                [
                    'key'   => $source_post_meta_key,
                    'value' => $source_post_id,
                    'compare' => '=',
                ],
            ],
        ]);

        // If the post already exists, skip re-importing
        if ($existing_posts->have_posts()) {
            continue;
        }

        // Prepare post data for insertion
        $new_post = [
            'post_title'    => $post->post_title,
            'post_content'  => $post->post_content,
            'post_status'   => 'publish',
            'post_author'   => $user->ID,
            'post_date'     => $post->post_date,
            'post_type'     => 'post',
        ];

        // Insert the new post
        $new_post_id = wp_insert_post($new_post);

        if ($new_post_id) {
            // Assign the source site's category to the post
            wp_set_post_categories($new_post_id, [(int)$category_id]); // Ensure category ID is cast to an integer

            // Save the source post ID as meta to track the post
            update_post_meta($new_post_id, $source_post_meta_key, $source_post_id);
        }
    }

    // Restore to the current site
    restore_current_blog();
}

/**
 * Restrict editing of imported posts.
 *
 * @param array $allcaps The capabilities for the user.
 * @param array $caps    Required primitive capabilities.
 * @param array $args    Arguments for capability checks.
 * @return array Modified capabilities.
 */
function restrict_editing_of_imported_posts($allcaps, $caps, $args) {
    // Check if this is for editing a post
    if (!isset($args[2]) || $args[0] !== 'edit_post') {
        return $allcaps;
    }

    $post_id = $args[2];

    // Check if the post is imported by looking for the _source_post_id meta key
    if (get_post_meta($post_id, '_source_post_id', true)) {
        // Remove edit capability for all users
        $allcaps['edit_post'] = false;
    }

    return $allcaps;
}
add_filter('user_has_cap', 'restrict_editing_of_imported_posts', 10, 3);

/**
 * Remove the edit post link for imported posts.
 *
 * @param string $link The edit post link.
 * @param int    $post_id The post ID.
 * @return string|null Modified edit link or null.
 */
function remove_edit_links_for_imported_posts($link, $post_id) {
    // Check if the post is imported by looking for the _source_post_id meta key
    if (get_post_meta($post_id, '_source_post_id', true)) {
        return null; // Remove the edit link
    }

    return $link;
}
add_filter('get_edit_post_link', 'remove_edit_links_for_imported_posts', 10, 2);

/**
 * Display an admin notice when attempting to edit imported posts.
 */
function admin_notice_for_imported_posts() {
    if (!is_admin() || !isset($_GET['post'])) {
        return;
    }

    $post_id = (int) $_GET['post'];

    // Check if the post is imported
    if (get_post_meta($post_id, '_source_post_id', true)) {
        echo '<div class="notice notice-error"><p>';
        echo 'This post cannot be edited because it was imported from another site.';
        echo '</p></div>';
    }
}
add_action('admin_notices', 'admin_notice_for_imported_posts');

/**
 * Remove the "Edit" link for imported posts in the admin post list.
 *
 * @param array $actions Array of row action links.
 * @param WP_Post $post The post object.
 * @return array Modified row action links.
 */
function remove_edit_link_from_admin_list($actions, $post) {
    // Check if the post is imported by looking for the _source_post_id meta key
    if (get_post_meta($post->ID, '_source_post_id', true)) {
        $actions = [];
    }

    return $actions;
}
add_filter('post_row_actions', 'remove_edit_link_from_admin_list', 10, 2);

// create admin page, multiste content sharing, that allows user to select source site and destination site and then click a button to import posts, also show the last time the import was run
add_action('admin_menu', 'wp_multisite_content_sharing_menu');

function wp_multisite_content_sharing_menu() {
    add_submenu_page('tools.php', 'Multisite Content Sharing', 'Multisite Content Sharing', 'manage_options', 'wp_multisite_content_sharing', 'wp_multisite_content_sharing_page');
}

function wp_multisite_content_sharing_page() {
    ?>
    <style>
        .flex {
            display: flex;
            justify-content: space-between;
        }
        .input-wrap {
            width: 350px;
            margin-bottom: 0.75rem;
        }
    </style>
    <div class="wrap">
        <h2>Multisite Content Sharing</h2>
        <form method="post" action="">
            <div class="flex input-wrap">
                <label for="source_site">Source Site:</label>
                <select name="source_site" id="source_site">
                    <?php
                    $sites = get_sites();
                    $sites = array_reverse($sites);
                    foreach ($sites as $site) {
                        $site_id = $site->blog_id;
                        $site_name = get_blog_option($site_id, 'blogname');
                        echo "<option value='$site_id'>$site_name</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="flex input-wrap">
                <label for="destination_site">Destination Site:</label>
                <select name="destination_site" id="destination_site">
                    <?php
                    $sites = get_sites();
                    foreach ($sites as $site) {
                        $site_id = $site->blog_id;
                        $site_name = get_blog_option($site_id, 'blogname');
                        echo "<option value='$site_id'>$site_name</option>";
                    }
                    ?>
                </select>
            </div>
            <input class="button" type="submit" name="import_posts" value="Import Posts">
            <?php
            if (isset($_POST['import_posts'])) {
                $source_site = intval($_POST['source_site']);
                $destination_site = intval($_POST['destination_site']);
                $imported_count = import_posts_from_site($source_site, $destination_site);
                echo '<div class="notice notice-success"><p>' . $imported_count . ' posts imported successfully.</p></div>';
                update_option('wp_multisite_content_sharing_last_import', current_time('mysql'));
            }

            $last_import = get_option('wp_multisite_content_sharing_last_import');
            if ($last_import) {
                $last_import_time = strtotime($last_import);
                $formatted_time = date_i18n('F j, Y, g:i a', $last_import_time);
                echo '<p style="margin-top:2rem;">Last import was run on: <b>' . $formatted_time . '</b></p>';
            }
            ?>
        </form>
    </div>
    <?php
}

