<?php
// Register a custom cron schedule for midnight PST
add_filter('cron_schedules', function ($schedules) {
    $schedules['midnight_pst'] = [
        'interval' => 86400, // 24 hours in seconds
        'display'  => __('Once Daily at Midnight PST'),
    ];
    return $schedules;
});

// Schedule the event if not already scheduled
add_action('wp', function () {
    if (!wp_next_scheduled('wp_multisite_content_sharing_midnight_event')) {
        // Calculate midnight PST in server time
        $gmt_offset = get_option('gmt_offset'); // Get offset in hours
        $midnight_pst = strtotime('tomorrow midnight') - ($gmt_offset - 8) * 3600;

        wp_schedule_event($midnight_pst, 'midnight_pst', 'wp_multisite_content_sharing_midnight_event');
    }
});

// Hook the event to trigger the import process
add_action('wp_multisite_content_sharing_midnight_event', function () {
    $source_site = 2; // Set the source site ID
    $destination_site = 1; // Set the destination site ID

    $importer = new WP_Multisite_Content_Sharing_Importer();
    $importer->import_posts($source_site, $destination_site);

    error_log('WP Multisite Content Sharing: Import completed at ' . current_time('mysql'));
});

// Unschedule the event on plugin deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('wp_multisite_content_sharing_midnight_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wp_multisite_content_sharing_midnight_event');
    }
});