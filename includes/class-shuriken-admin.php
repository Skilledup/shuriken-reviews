<?php
/**
 * Shuriken Reviews Admin Class
 *
 * Handles admin menu registration, page rendering, and admin-specific functionality.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Admin
 *
 * Manages the WordPress admin interface.
 *
 * @since 1.7.0
 */
class Shuriken_Admin {

    /**
     * @var Shuriken_Admin Singleton instance
     */
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_rating_forms'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_ratings_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_about_scripts'));
        add_action('admin_post_shuriken_export_ratings', array($this, 'export_ratings'));
        add_action('admin_post_shuriken_export_item_votes', array($this, 'export_item_votes'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize admin
     *
     * @return void
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Register admin menu pages
     *
     * @return void
     * @since 1.1.0
     */
    public function register_menu() {
        add_menu_page(
            __('Shuriken Reviews', 'shuriken-reviews'),
            __('Shuriken Reviews', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews',
            array($this, 'render_ratings_page'),
            'dashicons-star-filled',
            26
        );

        // Add Ratings submenu
        add_submenu_page(
            'shuriken-reviews',
            __('Ratings Management', 'shuriken-reviews'),
            __('Ratings', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews',
            array($this, 'render_ratings_page')
        );

        // Add Comments Settings submenu
        add_submenu_page(
            'shuriken-reviews',
            __('Comments Settings', 'shuriken-reviews'),
            __('Comments Settings', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-comments',
            array($this, 'render_comments_page')
        );

        // Add Analytics submenu
        add_submenu_page(
            'shuriken-reviews',
            __('Stats & Analytics', 'shuriken-reviews'),
            __('Analytics', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-analytics',
            array($this, 'render_analytics_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'shuriken-reviews',
            __('Settings', 'shuriken-reviews'),
            __('Settings', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-settings',
            array($this, 'render_settings_page')
        );

        // Add hidden Item Stats page (no menu item, accessed via link)
        add_submenu_page(
            null, // Hidden - no parent menu
            __('Item Statistics', 'shuriken-reviews'),
            __('Item Stats', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-item-stats',
            array($this, 'render_item_stats_page')
        );

        // Add About submenu
        add_submenu_page(
            'shuriken-reviews',
            __('About', 'shuriken-reviews'),
            __('About', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-about',
            array($this, 'render_about_page')
        );
    }

    /**
     * Handle rating form submissions before any output
     *
     * This runs on admin_init to allow proper redirects.
     *
     * @return void
     * @since 1.3.5
     */
    public function handle_rating_forms() {
        // Only process on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'shuriken-reviews') {
            return;
        }

        // Handle create rating
        if (isset($_POST['create_rating']) && isset($_POST['shuriken_rating_nonce'])) {
            if (!wp_verify_nonce($_POST['shuriken_rating_nonce'], 'shuriken_create_rating')) {
                return;
            }
            
            if (!current_user_can('manage_options')) {
                return;
            }

            $name = sanitize_text_field($_POST['rating_name']);
            $mirror_of = isset($_POST['mirror_of']) && !empty($_POST['mirror_of']) ? intval($_POST['mirror_of']) : null;
            // Clear parent_id if this is a mirror (mirrors cannot be sub-ratings)
            $parent_id = $mirror_of ? null : (isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null);
            $effect_type = isset($_POST['effect_type']) ? sanitize_text_field($_POST['effect_type']) : 'positive';
            $display_only = isset($_POST['display_only']) && $_POST['display_only'] === '1';

            if (!empty($name)) {
                $result = shuriken_db()->create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of);
                if ($result) {
                    wp_redirect(admin_url('admin.php?page=shuriken-reviews&message=created'));
                    exit;
                }
            }
        }
    }

    /**
     * Check if we're on a specific plugin admin page
     *
     * Uses $_GET['page'] instead of hook name because WordPress generates
     * hook names using sanitize_title() on the translated menu title,
     * which produces different results for different languages.
     *
     * @param string|array $page_slugs Page slug(s) to check for.
     * @return bool
     * @since 1.7.5
     */
    private function is_plugin_page($page_slugs) {
        if (!isset($_GET['page'])) {
            return false;
        }
        
        $current_page = sanitize_text_field(wp_unslash($_GET['page']));
        
        if (is_array($page_slugs)) {
            return in_array($current_page, $page_slugs, true);
        }
        
        return $current_page === $page_slugs;
    }

    /**
     * Enqueue scripts and styles for the ratings admin page
     *
     * @param string $hook The current admin page hook (unused, kept for hook signature).
     * @return void
     * @since 1.7.5
     */
    public function enqueue_ratings_scripts($hook) {
        // Check using page slug - works regardless of language/locale
        if (!$this->is_plugin_page('shuriken-reviews')) {
            return;
        }

        // Enqueue admin ratings CSS
        wp_enqueue_style(
            'shuriken-reviews-admin-ratings',
            plugins_url('assets/css/admin-ratings.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Enqueue admin ratings JS
        wp_enqueue_script(
            'shuriken-reviews-admin-ratings',
            plugins_url('assets/js/admin-ratings.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array('jquery'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );

        // Localize script for translations
        wp_localize_script('shuriken-reviews-admin-ratings', 'shurikenRatingsAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shuriken-ratings-admin-nonce'),
            'i18n' => array(
                'confirmDelete' => __('Are you sure you want to delete this rating? This action cannot be undone.', 'shuriken-reviews'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected ratings? This action cannot be undone.', 'shuriken-reviews'),
                'copied' => __('Shortcode copied to clipboard!', 'shuriken-reviews'),
            )
        ));
    }

    /**
     * Enqueue scripts and styles for the analytics admin page
     *
     * @param string $hook The current admin page hook (unused, kept for hook signature).
     * @return void
     * @since 1.3.0
     */
    public function enqueue_analytics_scripts($hook) {
        // Check using page slug - works regardless of language/locale
        $allowed_pages = array(
            'shuriken-reviews-analytics',
            'shuriken-reviews-item-stats'
        );
        
        if (!$this->is_plugin_page($allowed_pages)) {
            return;
        }

        // Enqueue Chart.js from CDN
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Enqueue analytics CSS
        wp_enqueue_style(
            'shuriken-admin-analytics',
            plugins_url('assets/css/admin-analytics.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Enqueue analytics JS
        wp_enqueue_script(
            'shuriken-admin-analytics',
            plugins_url('assets/js/admin-analytics.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array('jquery', 'chartjs'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );
    }

    /**
     * Enqueue styles for the About admin page
     *
     * @param string $hook The current admin page hook (unused, kept for hook signature).
     * @return void
     * @since 1.5.8
     */
    public function enqueue_about_scripts($hook) {
        // Check using page slug - works regardless of language/locale
        if (!$this->is_plugin_page('shuriken-reviews-about')) {
            return;
        }

        wp_enqueue_style(
            'shuriken-admin-about',
            plugins_url('assets/css/admin-about.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );
    }

    // =========================================================================
    // Page Render Methods
    // =========================================================================

    /**
     * Render the Ratings page
     *
     * @return void
     * @since 1.1.5
     */
    public function render_ratings_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/ratings.php';
    }

    /**
     * Render the Comments Settings page
     *
     * @return void
     * @since 1.1.5
     */
    public function render_comments_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/comments.php';
    }

    /**
     * Render the Settings page
     *
     * @return void
     * @since 1.2.0
     */
    public function render_settings_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Render the Analytics page
     *
     * @return void
     * @since 1.3.0
     */
    public function render_analytics_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/analytics.php';
    }

    /**
     * Render the Item Statistics page
     *
     * @return void
     * @since 1.3.0
     */
    public function render_item_stats_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/item-stats.php';
    }

    /**
     * Render the About page
     *
     * @return void
     * @since 1.5.8
     */
    public function render_about_page() {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/about.php';
    }

    // =========================================================================
    // Export Methods
    // =========================================================================

    /**
     * Handle CSV export of ratings data
     *
     * @return void
     * @since 1.3.0
     */
    public function export_ratings() {
        // Check nonce and permissions
        if (!isset($_POST['shuriken_export_nonce']) || 
            !wp_verify_nonce($_POST['shuriken_export_nonce'], 'shuriken_export_data')) {
            wp_die(__('Security check failed', 'shuriken-reviews'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data', 'shuriken-reviews'));
        }

        // Get all ratings with their stats
        $ratings = shuriken_db()->get_ratings_for_export();

        // Set headers for CSV download
        $filename = 'shuriken-ratings-export-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        fputcsv($output, array(
            __('ID', 'shuriken-reviews'),
            __('Name', 'shuriken-reviews'),
            __('Total Votes', 'shuriken-reviews'),
            __('Total Rating Points', 'shuriken-reviews'),
            __('Average Rating', 'shuriken-reviews'),
            __('Date Created', 'shuriken-reviews')
        ));

        // Write data rows
        foreach ($ratings as $rating) {
            fputcsv($output, array(
                $rating->id,
                $rating->name,
                $rating->total_votes,
                $rating->total_rating,
                $rating->average_rating ?: '0',
                $rating->date_created
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Handle CSV export of individual item votes
     *
     * @return void
     * @since 1.3.0
     */
    public function export_item_votes() {
        // Check nonce and permissions
        if (!isset($_POST['shuriken_export_item_nonce']) || 
            !wp_verify_nonce($_POST['shuriken_export_item_nonce'], 'shuriken_export_item_votes')) {
            wp_die(__('Security check failed', 'shuriken-reviews'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data', 'shuriken-reviews'));
        }

        $rating_id = isset($_POST['rating_id']) ? intval($_POST['rating_id']) : 0;
        if (!$rating_id) {
            wp_die(__('Invalid rating ID', 'shuriken-reviews'));
        }

        $db = shuriken_db();

        // Get rating info
        $rating = $db->get_rating($rating_id);

        if (!$rating) {
            wp_die(__('Rating not found', 'shuriken-reviews'));
        }

        // Get all votes for this rating
        $votes = $db->get_votes_for_export($rating_id);

        // Set headers for CSV download
        $filename = 'shuriken-votes-' . sanitize_title($rating->name) . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        fputcsv($output, array(
            __('Vote ID', 'shuriken-reviews'),
            __('Rating Value', 'shuriken-reviews'),
            __('Voter Type', 'shuriken-reviews'),
            __('Voter Name', 'shuriken-reviews'),
            __('Voter Email', 'shuriken-reviews'),
            __('IP Address', 'shuriken-reviews'),
            __('Date & Time', 'shuriken-reviews')
        ));

        // Write data rows
        foreach ($votes as $vote) {
            $voter_type = $vote->user_id > 0 ? __('Member', 'shuriken-reviews') : __('Guest', 'shuriken-reviews');
            $voter_name = $vote->user_id > 0 ? ($vote->display_name ?: __('Deleted User', 'shuriken-reviews')) : __('Guest', 'shuriken-reviews');
            $voter_email = $vote->user_id > 0 ? ($vote->user_email ?: '-') : '-';
            
            fputcsv($output, array(
                $vote->id,
                $vote->rating_value,
                $voter_type,
                $voter_name,
                $voter_email,
                $vote->user_ip,
                $vote->date_created
            ));
        }

        fclose($output);
        exit;
    }
}

/**
 * Helper function to get admin instance
 *
 * @return Shuriken_Admin
 */
function shuriken_admin() {
    return Shuriken_Admin::get_instance();
}

