<?php
/**
 * Shuriken Reviews Uninstall
 *
 * Fired when the plugin is deleted (uninstalled).
 *
 * @package Shuriken_Reviews
 * @since 1.15.x
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only proceed if the user opted to delete database data on uninstall
if (get_option('shuriken_delete_data_on_uninstall', '0') !== '1') {
    exit;
}

// Fire action for add-ons to clean up before core does
do_action('shuriken_uninstall');

global $wpdb;

// Drop custom database tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}shuriken_ratings");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}shuriken_votes");

// List of all options to delete
$options = array(
    'shuriken_reviews_db_version',
    'shuriken_allow_guest_voting',
    'shuriken_exclude_author_comments',
    'shuriken_exclude_reply_comments',
    'shuriken_rate_limiting_enabled',
    'shuriken_rate_limit_warning_dismissed',
    'shuriken_vote_cooldown',
    'shuriken_hourly_vote_limit',
    'shuriken_daily_vote_limit',
    'shuriken_guest_hourly_limit',
    'shuriken_guest_daily_limit',
    'shuriken_archive_sort_enabled',
    'shuriken_archive_sort_rating',
    'shuriken_archive_sort_orderby',
    'shuriken_archive_sort_order',
    'shuriken_comments_system_enabled',
    'shuriken_deleted_ratings_backup',
    'shuriken_trending_ratings',
    'shuriken_delete_data_on_uninstall'
);

foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option); // Cover network settings if any
}
