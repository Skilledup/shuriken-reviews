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
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10);

        // Archive sorting by rating
        if (get_option('shuriken_archive_sort_enabled', '0') === '1') {
            add_action('pre_get_posts', array($this, 'sort_archives_by_rating'));
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
     * Enqueue frontend scripts and styles
     *
     * @return void
     * @since 1.1.0
     */
    public function enqueue_scripts(): void {
        // Enqueue styles
        wp_enqueue_style(
            'shuriken-reviews',
            plugins_url('assets/css/shuriken-reviews.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Enqueue main script
        wp_enqueue_script(
            'shuriken-reviews',
            plugins_url('assets/js/shuriken-reviews.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array('jquery'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );
        
        // Localize script with data and translations
        wp_localize_script('shuriken-reviews', 'shurikenReviews', $this->get_localized_data());
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
     * @since 1.15.0
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

        global $wpdb;
        $votes_table = $wpdb->prefix . 'shuriken_votes';
        $post_type = $query->get('post_type') ?: 'post';

        // Use a subquery to compute per-post scores for this rating
        add_filter('posts_join', function (string $join, \WP_Query $q) use ($query, $votes_table, $rating_id, $post_type) {
            if ($q !== $query) {
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

        add_filter('posts_orderby', function (string $orderby_clause, \WP_Query $q) use ($query, $orderby) {
            if ($q !== $query) {
                return $orderby_clause;
            }
            if ($orderby === 'votes') {
                return 'COALESCE(shuriken_scores.shuriken_votes, 0) DESC, ' . $orderby_clause;
            }
            // Default: average
            return 'CASE WHEN COALESCE(shuriken_scores.shuriken_votes, 0) = 0 THEN 0 ELSE (shuriken_scores.shuriken_total / shuriken_scores.shuriken_votes) END DESC, ' . $orderby_clause;
        }, 10, 2);
    }

    /**
     * Get localized data for JavaScript
     *
     * @return array
     */
    private function get_localized_data(): array {
        $data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('shuriken-reviews-nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'logged_in' => is_user_logged_in(),
            'allow_guest_voting' => get_option('shuriken_allow_guest_voting', '0') === '1',
            'login_url' => wp_login_url(),
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

