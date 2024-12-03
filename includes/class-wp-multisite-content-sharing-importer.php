<?php
class WP_Multisite_Content_Sharing_Importer {
    public function import_posts($source_site, $destination_site) {
        // Switch to source site
        switch_to_blog($source_site);
        $source_posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1]);
        $source_site_title = get_bloginfo('name');
        restore_current_blog();

        // Switch to destination site
        switch_to_blog($destination_site);

        $category = $this->ensure_category_exists($source_site_title);
        $imported_count = 0;

        // If user doesn't exist on destination site with the username source site title, create one
        $user = get_user_by('login', $source_site_title);
        if (!$user) {
            $destination_site_url = get_blog_option($destination_site, 'siteurl');
            $user = wp_create_user($source_site_title, wp_generate_password(), $source_site_title . '@' . parse_url($destination_site_url, PHP_URL_HOST));
        }

        foreach ($source_posts as $post) {
            $source_post_id = $post->ID;

            // Check if a post with the same title exists on the destination site
            $existing_posts = get_posts([
                'title' => $post->post_title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'numberposts' => 1
            ]);
            
            $existing_post = !empty($existing_posts) ? $existing_posts[0] : null;

            if ($existing_post) {
                error_log("Post with title '{$post->post_title}' already exists on destination site. Skipping import.");
                continue;
            }

            // Insert new post
            $new_post_id = wp_insert_post([
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => 'publish',
                'post_date'    => $post->post_date,
                'post_author'  => $user->ID,
            ]);

            if ($new_post_id) {
                // Assign category
                wp_set_post_categories($new_post_id, [$category]);

                switch_to_blog($source_site);
                $has_thumbnail = has_post_thumbnail($source_post_id);
                restore_current_blog();

                // Import featured image
                if ($has_thumbnail) {
                    error_log("Importing featured image for post ID: $source_post_id.");
                    $this->import_featured_image($source_post_id, $new_post_id, $source_site);
                } else {
                    error_log("No featured image found for post ID: $source_post_id.");
                }

                // Track source post ID
                update_post_meta($new_post_id, '_source_post_id', $source_post_id);
                $imported_count++;
            }
        }

        restore_current_blog();
        return $imported_count;
    }

    private function ensure_category_exists($category_name) {
        $category = get_term_by('name', $category_name, 'category');
        if (!$category) {
            $category = wp_insert_term($category_name, 'category');
        }
        return is_array($category) ? $category['term_id'] : $category->term_id;
    }

    private function import_featured_image($source_post_id, $destination_post_id, $source_site_id) {
        // Switch to the source site to fetch the featured image
        switch_to_blog($source_site_id);

        $thumbnail_id = get_post_thumbnail_id($source_post_id);
        if (!$thumbnail_id) {
            error_log("No thumbnail found for post ID: $source_post_id on source site $source_site_id.");
            restore_current_blog();
            return;
        }

        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
        if (!$thumbnail_url) {
            error_log("No valid thumbnail URL found for attachment ID: $thumbnail_id on source site $source_site_id.");
            restore_current_blog();
            return;
        }
        restore_current_blog();

        // Check if the attachment already exists on the destination site
        $existing_attachment = get_posts([
            'post_type'   => 'attachment',
            'meta_query'  => [['key' => '_source_thumbnail_id', 'value' => $thumbnail_id, 'compare' => '=']],
            'fields'      => 'ids',
            'numberposts' => 1,
        ]);

        if (!empty($existing_attachment)) {
            error_log("Attachment already exists for thumbnail ID: $thumbnail_id.");
            // Set the existing attachment as the featured image
            set_post_thumbnail($destination_post_id, $existing_attachment[0]);
            return;
        }

        // Fetch the image data
        $image_data = @file_get_contents($thumbnail_url); // Suppress warnings
        if (!$image_data) {
            error_log("Failed to fetch image data from URL: $thumbnail_url.");
            return;
        }

        // Upload the image to the destination site
        $filename = basename($thumbnail_url);
        $upload = wp_upload_bits($filename, null, $image_data);

        if ($upload['error']) {
            error_log("Error uploading image $filename: " . $upload['error']);
            return;
        }

        // Check the file type and insert as an attachment
        $wp_filetype = wp_check_filetype($filename, null);
        if (!$wp_filetype['type']) {
            error_log("Invalid file type for image $filename.");
            return;
        }

        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $destination_post_id);

        if (!$attachment_id) {
            error_log("Failed to insert attachment for image $filename.");
            return;
        }

        // Generate attachment metadata and associate with the post
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Set as the featured image
        set_post_thumbnail($destination_post_id, $attachment_id);
        error_log("Featured image successfully imported for destination post ID: $destination_post_id.");

        // Track the source thumbnail ID
        update_post_meta($attachment_id, '_source_thumbnail_id', $thumbnail_id);
    }
}