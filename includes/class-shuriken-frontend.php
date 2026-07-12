<?php
/**
 * Shuriken Reviews Frontend Class
 *
 * Handles frontend scripts, styles, and localization.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Frontend
 *
 * Manages frontend assets and functionality.
 *
 * @since 1.7.0
 */
class Shuriken_Frontend {

    /**
     * @var Shuriken_Frontend Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Whether frontend assets have been enqueued this request.
     */
    private static bool $assets_enqueued = false;

    /**
     * Per-block view data accumulated during render (keyed by rating ID).
     *
     * @var array<string, array>
     */
    private static array $block_view_data = array();

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', $this->register_assets(...), 5);
        add_action('wp_enqueue_scripts', $this->maybe_force_enqueue_assets(...), 10);
        add_action('wp_print_footer_scripts', $this->localize_block_view_data(...), 9);

        // SSR contextual stats batch pre-fetch (Step 8b)
        if (!is_admin()) {
            $collector = shuriken_contextual_stats_collector();
            add_filter('the_content', array($collector, 'scan_content_for_registrations'), 1);
            add_filter('pre_render_block', array($collector, 'register_from_block'), 10, 3);
        }

        // Archive sorting by rating
        if (get_option('shuriken_archive_sort_enabled', '0') === '1') {
            add_action('pre_get_posts', $this->sort_archives_by_rating(...));
        }
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Frontend
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize frontend
     *
     * @return void
     */
    public static function init(): void {
        self::get_instance();
    }

    /**
     * Register frontend script and style handles (enqueue on demand).
     *
     * @return void
     * @since 1.15.6
     */
    public function register_assets(): void {
        wp_register_style(
            'shuriken-reviews-frontend',
            plugins_url('assets/css/shuriken-reviews.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        wp_register_script(
            'shuriken-reviews',
            plugins_url('assets/js/shuriken-reviews.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array('jquery'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );
    }

    /**
     * Enqueue frontend assets when ratings are rendered or forced via filter.
     *
     * Idempotent — safe to call from every block/shortcode render callback.
     *
     * @return void
     * @since 1.15.6
     */
    public function enqueue_frontend_assets(): void {
        if (self::$assets_enqueued) {
            return;
        }

        /**
         * Filter whether frontend assets should be enqueued.
         *
         * Return false to skip enqueuing (e.g. custom asset delivery).
         *
         * @since 1.15.6
         * @param bool $enqueue Whether to enqueue assets. Default true.
         */
        if (!apply_filters('shuriken_enqueue_frontend_assets', true)) {
            return;
        }

        self::$assets_enqueued = true;

        wp_enqueue_style('shuriken-reviews-frontend');
        wp_enqueue_script('jquery');
        wp_enqueue_script('shuriken-reviews');
        wp_localize_script('shuriken-reviews', 'shurikenReviews', $this->get_localized_data());
    }

    /**
     * Accumulate per-block view data for frontend add-ons.
     *
     * Called from block render callbacks; output once in the footer before scripts print.
     *
     * @param int   $rating_id Rating ID used as the map key.
     * @param array $data      Filtered block view data.
     * @return void
     * @since 1.15.7
     */
    public static function register_block_view_data(int $rating_id, array $data): void {
        if ($rating_id <= 0) {
            return;
        }

        self::$block_view_data[(string) $rating_id] = $data;
    }

    /**
     * Output consolidated block view data for the frontend script.
     *
     * @return void
     * @since 1.15.7
     */
    public function localize_block_view_data(): void {
        if (empty(self::$block_view_data) || !wp_script_is('shuriken-reviews', 'enqueued')) {
            return;
        }

        wp_localize_script('shuriken-reviews', 'shurikenBlockViewData', self::$block_view_data);
    }

    /**
     * Optionally enqueue assets on every page when forced via filter.
     *
     * @return void
     * @since 1.15.6
     */
    public function maybe_force_enqueue_assets(): void {
        /**
         * Force frontend assets on all pages (legacy / custom HTML injection).
         *
         * @since 1.15.6
         * @param bool $force Whether to enqueue on every frontend page. Default false.
         */
        if (apply_filters('shuriken_force_enqueue_frontend_assets', false)) {
            $this->enqueue_frontend_assets();
        }
    }

    /**
     * Sort archive queries by contextual rating scores
     *
     * Adds a LEFT JOIN to the votes table and orders by the chosen metric
     * (average rating or total votes) for the configured rating ID.
     * Only applies to the main query on archive pages.
     *
     * @param WP_Query $query The query object.
     * @return void
     * @since 1.15.5
     */
    public function sort_archives_by_rating(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query() || !$query->is_archive()) {
            return;
        }

        $rating_id = absint(get_option('shuriken_archive_sort_rating', 0));
        if (!$rating_id) {
            return;
        }

        $orderby = get_option('shuriken_archive_sort_orderby', 'average');
        $order   = get_option('shuriken_archive_sort_order', 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Tag the query so filter callbacks can identify it — including any Query Loop
        // block instances that inherit the main query vars on block themes.
        $query->set('_shuriken_sort', true);

        global $wpdb;
        $votes_table = $wpdb->prefix . 'shuriken_votes';
        $post_type = $query->get('post_type') ?: 'post';

        // WP_Query::generate_cache_key() hashes the *entire* query_vars array (plus
        // the built SQL) to key its "post-queries" result cache. Setting this var is
        // the invalidation: it never needs to be read back. Its value only changes
        // when wp_cache_set_last_changed('shuriken_archive') runs (on contextual vote
        // writes), which changes the hash and busts the cached post ID list — without
        // touching the shared 'posts' last_changed group WP_Query also salts against.
        $query->set('_shuriken_sort_cache_generation', wp_cache_get_last_changed('shuriken_archive'));

        add_filter('posts_join', function (string $join, \WP_Query $q) use ($votes_table, $rating_id, $post_type) {
            if (!$q->get('_shuriken_sort')) {
                return $join;
            }
            global $wpdb;

            $join .= $wpdb->prepare(
                " LEFT JOIN (
                    SELECT context_id,
                           COUNT(*) as shuriken_votes,
                           COALESCE(SUM(rating_value), 0) as shuriken_total
                    FROM {$votes_table}
                    WHERE rating_id = %d AND context_type = %s AND context_id IS NOT NULL
                    GROUP BY context_id
                ) shuriken_scores ON {$wpdb->posts}.ID = shuriken_scores.context_id ",
                $rating_id,
                $post_type
            );
            return $join;
        }, 10, 2);

        add_filter('posts_orderby', function (string $orderby_clause, \WP_Query $q) use ($orderby, $order) {
            if (!$q->get('_shuriken_sort')) {
                return $orderby_clause;
            }
            if ($orderby === 'votes') {
                return 'COALESCE(shuriken_scores.shuriken_votes, 0) ' . $order . ', ' . $orderby_clause;
            }
            // Default: average
            return 'CASE WHEN COALESCE(shuriken_scores.shuriken_votes, 0) = 0 THEN 0 ELSE (shuriken_scores.shuriken_total / shuriken_scores.shuriken_votes) END ' . $order . ', ' . $orderby_clause;
        }, 10, 2);
    }

    /**
     * Get localized data for JavaScript
     *
     * @return array
     */
    private function get_localized_data(): array {
        /**
         * Seconds after SSR render during which client-side stats REST can be skipped.
         *
         * @since 1.15.7
         * @param int $threshold Freshness window in seconds. Default 30.
         */
        $ssr_fresh_threshold = (int) apply_filters('shuriken_ssr_fresh_threshold', 30);

        $data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('shuriken-reviews-nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'logged_in' => is_user_logged_in(),
            'allow_guest_voting' => get_option('shuriken_allow_guest_voting', '0') === '1',
            'login_url' => wp_login_url(),
            'ssr_rendered_at' => time(),
            'ssr_fresh_threshold' => max(1, $ssr_fresh_threshold),
            'i18n' => $this->get_i18n_strings(),
        );

        /**
         * Filter the localized data passed to JavaScript.
         *
         * @since 1.7.0
         * @param array $data The localized data array.
         */
        return apply_filters('shuriken_localized_data', $data);
    }

    /**
     * Get internationalized strings for JavaScript
     *
     * @return array
     */
    private function get_i18n_strings(): array {
        $strings = array(
            /* translators: %s: Login URL */
            'pleaseLogin' => __('Please <a href="%s">login</a> to rate', 'shuriken-reviews'),
            'thankYou' => __('Thank you for rating!', 'shuriken-reviews'),
            /* translators: 1: Average rating value, 2: Maximum stars, 3: Total number of votes */
            'averageRating' => __('Average: %1$s/%2$s (%3$s votes)', 'shuriken-reviews'),
            /* translators: %s: Error message */
            'error' => __('Error: %s', 'shuriken-reviews'),
            'genericError' => __('Error submitting rating. Please try again.', 'shuriken-reviews'),
            'votes' => __('votes', 'shuriken-reviews'),
        );

        /**
         * Filter the i18n strings passed to JavaScript.
         *
         * @since 1.7.0
         * @param array $strings The i18n strings array.
         */
        return apply_filters('shuriken_i18n_strings', $strings);
    }
}

/**
 * Helper function to get frontend instance
 *
 * @return Shuriken_Frontend
 */
function shuriken_frontend(): Shuriken_Frontend {
    return Shuriken_Frontend::get_instance();
}

/**
 * Enqueue frontend rating assets on demand.
 *
 * Called from block render callbacks and shortcodes when ratings are output.
 *
 * @return void
 * @since 1.15.6
 */
function shuriken_enqueue_frontend_assets(): void {
    shuriken_frontend()->enqueue_frontend_assets();
}

/**
 * Register per-block view data for the frontend script.
 *
 * @param int   $rating_id Rating ID.
 * @param array $data      Block view data (after shuriken_block_view_data filter).
 * @return void
 * @since 1.15.7
 */
function shuriken_register_block_view_data(int $rating_id, array $data): void {
    Shuriken_Frontend::register_block_view_data($rating_id, $data);
}
