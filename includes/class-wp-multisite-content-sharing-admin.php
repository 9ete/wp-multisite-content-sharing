<?php

class WP_Multisite_Content_Sharing_Admin {

    private bool $mandatory_source_site = false;

    private bool $mandatory_destination_site = false;

    private bool $both_sites_mandatory = false;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        $this->mandatory_source_site = defined('WP_MULTISITE_CONTENT_SHARING_SOURCE') ? WP_MULTISITE_CONTENT_SHARING_SOURCE : false;
        $this->mandatory_destination_site = defined('WP_MULTISITE_CONTENT_SHARING_DESTINATION') ? WP_MULTISITE_CONTENT_SHARING_DESTINATION : false;
        $this->both_sites_mandatory = $this->mandatory_source_site && $this->mandatory_destination_site;
    }

    public function add_menu() {
        add_submenu_page(
            'tools.php',
            'Multisite Content Sharing',
            'Multisite Content Sharing',
            'manage_options',
            'wp_multisite_content_sharing',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        if (isset($_POST['import_posts'])) {
            $source_site = intval($_POST['source_site']);
            $destination_site = intval($_POST['destination_site']);

            $importer = new WP_Multisite_Content_Sharing_Importer();
            $imported_count = $importer->import_posts($source_site, $destination_site);

            echo '<div class="notice notice-success"><p>' . $imported_count . ' posts imported successfully.</p></div>';
            update_option('wp_multisite_content_sharing_last_import', current_time('mysql'));
        }

        $last_import = get_option('wp_multisite_content_sharing_last_import');
        ?>
        <style>
            .flex {
                display: flex;
                justify-content: space-between;
            }
            .input-wrap {
                width: 325px;
                margin-bottom: 0.75rem;
            }
        </style>
        <div class="wrap">
            <h2>Multisite Content Sharing</h2>
            <form method="post" action="">
                <div class="flex input-wrap">
                    <label for="source_site">Source Site:</label>
                    <select name="source_site" id="source_site">
                        <?php $this->render_sites_dropdown(true); ?>
                    </select>
                </div>
                <div class="flex input-wrap">
                    <label for="destination_site">Destination Site:</label>
                    <select name="destination_site" id="destination_site">
                        <?php $this->render_sites_dropdown(); ?>
                    </select>
                </div>
                <br>
                <input class="button button-primary" type="submit" name="import_posts" value="Import Posts">
            </form>
            <?php if ($last_import) : ?>
                <p>Last import was run on: <b><?php echo date_i18n('F j, Y, g:i a', strtotime($last_import)); ?></b></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_sites_dropdown($reverse = false) {
        $sites = $this->get_available_sites();

        if ($reverse) {
            $sites = array_reverse($sites);
        }

        foreach ($sites as $index => $site) {
            if ( $this->both_sites_mandatory && ! $index == 0 ) {
                continue;
            }

            $site_id = $site->blog_id;
            $site_name = get_blog_option($site_id, 'blogname');
            echo "<option value='$site_id'>$site_name</option>";
        }
    }

    private function get_available_sites() {
        if ( $this->both_sites_mandatory ) {
            $source_site = WP_MULTISITE_CONTENT_SHARING_SOURCE;
            $destination_site = WP_MULTISITE_CONTENT_SHARING_DESTINATION;
            return [
                (object) ['blog_id' => $destination_site],
                (object) ['blog_id' => $source_site],
            ];

        } else {
            return get_sites();
        }
    }
}