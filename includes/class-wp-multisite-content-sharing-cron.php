<?php

class WP_Multisite_Content_Sharing_Cron {

    /**
     * Initialize the cron setup.
     */
    public function __construct() {
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedule']);
        add_action('wp', [$this, 'schedule_event']);
        add_action('wp_multisite_content_sharing_cron_event', [$this, 'run_cron_task']);
        register_deactivation_hook(__FILE__, [$this, 'unschedule_event']);
    }

    /**
     * Add a custom cron schedule based on the WP_MULTISITE_CONTENT_SHARING_CRON_TIME constant.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function add_custom_cron_schedule(array $schedules): array {
        $interval = defined('WP_MULTISITE_CONTENT_SHARING_CRON_TIME') ? WP_MULTISITE_CONTENT_SHARING_CRON_TIME : 86400; // Default to 24 hours
        $schedules['custom_interval'] = [
            'interval' => $interval,
            'display'  => __('Custom Interval for Multisite Content Sharing'),
        ];
        return $schedules;
    }

    /**
     * Schedule the cron event if not already scheduled.
     */
    public function schedule_event(): void {
        if (!wp_next_scheduled('wp_multisite_content_sharing_cron_event')) {
            error_log('Scheduling wp_multisite_content_sharing_cron_event...');
            $gmt_offset = get_option('gmt_offset'); // Get offset in hours
            $midnight_pst = strtotime('tomorrow midnight') - ($gmt_offset - 8) * 3600;
    
            wp_schedule_event($midnight_pst, 'custom_interval', 'wp_multisite_content_sharing_cron_event');
        } else {
            error_log('wp_multisite_content_sharing_cron_event already scheduled.');
        }
    }

    /**
     * Run the cron task to import posts between sites.
     */
    public function run_cron_task(): void {
        $source_site = defined('WP_MULTISITE_CONTENT_SHARING_SOURCE') ? WP_MULTISITE_CONTENT_SHARING_SOURCE : null;
        $destination_site = defined('WP_MULTISITE_CONTENT_SHARING_DESTINATION') ? WP_MULTISITE_CONTENT_SHARING_DESTINATION : null;

        if (!$source_site || !$destination_site) {
            error_log('WP Multisite Content Sharing: Source and destination sites must be defined.');
            return;
        }

        $importer = new WP_Multisite_Content_Sharing_Importer();
        $imported_count = $importer->import_posts($source_site, $destination_site);

        error_log("WP Multisite Content Sharing: Import completed at " . current_time('mysql') . ". Imported $imported_count posts.");
        update_option('wp_multisite_content_sharing_last_import', current_time('mysql'));
    }

    /**
     * Unschedule the cron event when the plugin is deactivated.
     */
    public function unschedule_event(): void {
        $timestamp = wp_next_scheduled('wp_multisite_content_sharing_cron_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_multisite_content_sharing_cron_event');
        }
    }
}