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
    private static ?self $instance = null;

    /**
     * @var Shuriken_Database_Interface Database instance
     */
    private Shuriken_Database_Interface $db;

    /**
     * @var Shuriken_Analytics_Interface Analytics instance
     */
    private Shuriken_Analytics_Interface $analytics;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null  $db        Database instance (optional, for dependency injection).
     * @param Shuriken_Analytics_Interface|null $analytics Analytics instance (optional, for dependency injection).
     */
    public function __construct(?Shuriken_Database_Interface $db = null, ?Shuriken_Analytics_Interface $analytics = null) {
        $this->db = $db ?: shuriken_db();
        $this->analytics = $analytics ?: shuriken_analytics();
        
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_rating_forms'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_ratings_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_scripts'));
        add_action('admin_post_shuriken_export_ratings', array($this, 'export_ratings'));
        add_action('admin_post_shuriken_export_item_votes', array($this, 'export_item_votes'));
        add_action('admin_post_shuriken_export_voter_votes', array($this, 'export_voter_votes'));
        add_action('wp_ajax_shuriken_dismiss_rate_limit_warning', array($this, 'dismiss_rate_limit_warning'));
        add_filter('set-screen-option', array($this, 'save_screen_options'), 10, 3);
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Admin
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self(shuriken_db(), shuriken_analytics());
        }
        return self::$instance;
    }

    /**
     * Get the database instance
     *
     * @return Shuriken_Database_Interface
     */
    public function get_db(): Shuriken_Database_Interface {
        return $this->db;
    }

    /**
     * Get the analytics instance
     *
     * @return Shuriken_Analytics_Interface
     */
    public function get_analytics(): Shuriken_Analytics_Interface {
        return $this->analytics;
    }

    /**
     * Initialize admin
     *
     * @return void
     */
    public static function init(): void {
        self::get_instance();
    }

    /**
     * Register admin menu pages
     *
     * @return void
     * @since 1.1.0
     */
    public function register_menu(): void {
        add_menu_page(
            __('Shuriken Reviews', 'shuriken-reviews'),
            __('Shuriken Reviews', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews',
            array($this, 'render_ratings_page'),
            'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'),
            26
        );

        // Add Ratings submenu
        $ratings_hook = add_submenu_page(
            'shuriken-reviews',
            __('Ratings Management', 'shuriken-reviews'),
            __('Ratings', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews',
            array($this, 'render_ratings_page')
        );

        // Register screen options for the ratings page
        add_action("load-{$ratings_hook}", array($this, 'ratings_screen_options'));

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

        // Add hidden Voter Activity page (no menu item, accessed via link)
        add_submenu_page(
            null, // Hidden - no parent menu
            __('Voter Activity', 'shuriken-reviews'),
            __('Voter Activity', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-voter-activity',
            array($this, 'render_voter_activity_page')
        );

        // Add hidden Context Stats page (no menu item, accessed via Per-Post view link)
        add_submenu_page(
            null, // Hidden - no parent menu
            __('Context Statistics', 'shuriken-reviews'),
            __('Context Stats', 'shuriken-reviews'),
            'manage_options',
            'shuriken-reviews-context-stats',
            array($this, 'render_context_stats_page')
        );

    }

    /**
     * Register screen options for the ratings page
     *
     * @return void
     * @since 1.8.0
     */
    public function ratings_screen_options(): void {
        $screen = get_current_screen();
        add_filter("manage_{$screen->id}_columns", array($this, 'get_ratings_columns'));

        add_screen_option('per_page', array(
            'label'   => __('Ratings', 'shuriken-reviews'),
            'default' => Shuriken_Database::RATINGS_PER_PAGE_DEFAULT,
            'option'  => 'shuriken_ratings_per_page',
        ));
    }

    /**
     * Get column definitions for the ratings list table
     *
     * Used by WordPress Screen Options to render column toggles.
     *
     * @return array
     * @since 1.8.0
     */
    public function get_ratings_columns(): array {
        return array(
            'type'      => __('Type', 'shuriken-reviews'),
            'shortcode' => __('Shortcode', 'shuriken-reviews'),
            'stats'     => __('Rating', 'shuriken-reviews'),
        );
    }

    /**
     * Save screen options
     *
     * @param mixed  $screen_option The value to save.
     * @param string $option        The option name.
     * @param int    $value         The option value.
     * @return mixed
     * @since 1.8.0
     */
    public function save_screen_options(mixed $screen_option, string $option, int $value): mixed {
        if ($option === 'shuriken_ratings_per_page') {
            return absint($value);
        }
        return $screen_option;
    }

    /**
     * Handle rating form submissions before any output
     *
     * This runs on admin_init to allow proper redirects.
     *
     * @return void
     * @since 1.3.5
     */
    public function handle_rating_forms(): void {
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
            $rating_type = isset($_POST['rating_type']) ? sanitize_text_field($_POST['rating_type']) : 'stars';

            if ($rating_type === 'mirror') {
                // Mirror: read source, clear parent, type inherited from source in DB layer
                $mirror_of = isset($_POST['mirror_of']) && !empty($_POST['mirror_of']) ? intval($_POST['mirror_of']) : null;
                $parent_id = null;
                $rating_type = 'stars'; // Placeholder — create_rating() inherits from source
                $scale = Shuriken_Database::RATING_SCALE_DEFAULT;
                $effect_type = 'positive';
                $display_only = false;
            } else {
                // Non-mirror: no mirror_of
                $mirror_of = null;
                $scale = isset($_POST['scale']) ? intval($_POST['scale']) : Shuriken_Database::RATING_SCALE_DEFAULT;
                $effect_type = isset($_POST['effect_type']) ? sanitize_text_field($_POST['effect_type']) : 'positive';
                $display_only = isset($_POST['display_only']) && $_POST['display_only'] === '1';

                // Sub-rating checkbox determines parent_id
                if (isset($_POST['is_sub_rating']) && $_POST['is_sub_rating'] === '1') {
                    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
                } else {
                    $parent_id = null;
                }
            }

            if (!empty($name)) {
                $result = $this->db->create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of, $rating_type, $scale);
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
    private function is_plugin_page(string|array $page_slugs): bool {
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
    public function enqueue_ratings_scripts(string $hook): void {
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
    public function enqueue_analytics_scripts(string $hook): void {
        // Check using page slug - works regardless of language/locale
        $allowed_pages = array(
            'shuriken-reviews-analytics',
            'shuriken-reviews-item-stats',
            'shuriken-reviews-voter-activity',
            'shuriken-reviews-context-stats'
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
    /**
     * Enqueue scripts and styles for the Settings admin page
     *
     * @param string $hook The current admin page hook (unused, kept for hook signature).
     * @return void
     * @since 1.10.0
     */
    public function enqueue_settings_scripts(string $hook): void {
        // Check using page slug - works regardless of language/locale
        if (!$this->is_plugin_page('shuriken-reviews-settings')) {
            return;
        }

        // Enqueue settings CSS
        wp_enqueue_style(
            'shuriken-admin-settings',
            plugins_url('assets/css/admin-settings.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Enqueue settings JS
        wp_enqueue_script(
            'shuriken-admin-settings',
            plugins_url('assets/js/admin-settings.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array('jquery'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );

        wp_localize_script('shuriken-admin-settings', 'shurikenSettings', array(
            'dismissNonce' => wp_create_nonce('shuriken_dismiss_rate_limit_warning'),
        ));
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * Dismiss the rate-limit warning banner via AJAX.
     *
     * @return void
     * @since 1.10.0
     */
    public function dismiss_rate_limit_warning(): void {
        check_ajax_referer('shuriken_dismiss_rate_limit_warning');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        update_option('shuriken_rate_limit_warning_dismissed', '1');
        wp_send_json_success();
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
    public function render_ratings_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/ratings.php';
    }

    /**
     * Render the Settings page
     *
     * @return void
     * @since 1.2.0
     */
    public function render_settings_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Render the Analytics page
     *
     * @return void
     * @since 1.3.0
     */
    public function render_analytics_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/analytics.php';
    }

    /**
     * Render the Item Statistics page
     *
     * @return void
     * @since 1.3.0
     */
    public function render_item_stats_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/item-stats.php';
    }

    /**
     * Render the Voter Activity page
     *
     * @return void
     * @since 1.9.1
     */
    public function render_voter_activity_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/voter-activity.php';
    }

    /**
     * Render the Context Statistics page
     *
     * @return void
     * @since 1.15.0
     */
    public function render_context_stats_page(): void {
        include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/context-stats.php';
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
    public function export_ratings(): void {
        // Check nonce and permissions
        if (!isset($_POST['shuriken_export_nonce']) || 
            !wp_verify_nonce($_POST['shuriken_export_nonce'], 'shuriken_export_data')) {
            wp_die(__('Security check failed', 'shuriken-reviews'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data', 'shuriken-reviews'));
        }

        // Get all ratings with their stats
        $ratings = $this->db->get_ratings_for_export();

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
    public function export_item_votes(): void {
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

        // Get rating info
        $rating = $this->db->get_rating($rating_id);

        if (!$rating) {
            wp_die(__('Rating not found', 'shuriken-reviews'));
        }

        // Get all votes for this rating
        $votes = $this->db->get_votes_for_export($rating_id);

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

    /**
     * Handle CSV export of voter's votes
     *
     * @return void
     * @since 1.9.1
     */
    public function export_voter_votes(): void {
        // Check nonce and permissions
        if (!isset($_POST['shuriken_export_voter_nonce']) || 
            !wp_verify_nonce($_POST['shuriken_export_voter_nonce'], 'shuriken_export_voter_votes')) {
            wp_die(__('Security check failed', 'shuriken-reviews'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data', 'shuriken-reviews'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $user_ip = isset($_POST['user_ip']) ? sanitize_text_field($_POST['user_ip']) : '';

        if ($user_id <= 0 && empty($user_ip)) {
            wp_die(__('Invalid voter identifier', 'shuriken-reviews'));
        }

        $is_member = $user_id > 0;

        // Get voter info for filename
        if ($is_member) {
            $user_info = shuriken_voter_analytics()->get_user_info($user_id);
            $voter_name = $user_info ? sanitize_title($user_info->display_name) : 'user-' . $user_id;
        } else {
            $voter_name = 'guest-' . sanitize_title(str_replace('.', '-', $user_ip));
        }

        // Get all votes for this voter
        $votes = shuriken_voter_analytics()->get_voter_votes_for_export($user_id, $user_ip);

        // Set headers for CSV download
        $filename = 'shuriken-voter-votes-' . $voter_name . '-' . date('Y-m-d') . '.csv';
        
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
            __('Rating Item', 'shuriken-reviews'),
            __('Rating ID', 'shuriken-reviews'),
            __('Rating Value', 'shuriken-reviews'),
            __('Date & Time', 'shuriken-reviews'),
            __('Last Modified', 'shuriken-reviews')
        ));

        // Write data rows
        foreach ($votes as $vote) {
            fputcsv($output, array(
                $vote->id,
                $vote->rating_name,
                $vote->rating_id,
                $vote->rating_value,
                $vote->date_created,
                $vote->date_modified
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
function shuriken_admin(): Shuriken_Admin {
    return Shuriken_Admin::get_instance();
}

