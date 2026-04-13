<?php
/**
 * Shuriken Reviews AJAX Class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_AJAX
 *
 * Registers and handles AJAX endpoints.
 *
 * @since 1.7.0
 */
class Shuriken_AJAX {

    /**
     * @var Shuriken_AJAX Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var Shuriken_Database_Interface Database instance
     */
    private Shuriken_Database_Interface $db;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null $db Database instance (optional, for dependency injection).
     */
    public function __construct(?Shuriken_Database_Interface $db = null) {
        $this->db = $db ?: shuriken_db();
        
        // Rating submission
        add_action('wp_ajax_submit_rating', array($this, 'handle_submit_rating'));
        add_action('wp_ajax_nopriv_submit_rating', array($this, 'handle_submit_rating'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_AJAX
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self(shuriken_db());
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
     * Initialize AJAX handlers
     *
     * @return void
     */
    public static function init(): void {
        self::get_instance();
    }

    /**
     * Handle rating form submissions
     *
     * Updates or inserts the user's rating for a specific item.
     *
     * @return void
     * @since 1.1.0
     */
    public function handle_submit_rating(): void {
        try {
            $allow_guest_voting = get_option('shuriken_allow_guest_voting', '0') === '1';
            
            /**
             * Filter whether guest voting is allowed.
             *
             * @since 1.7.0
             * @param bool $allow_guest_voting Whether guest voting is allowed.
             */
            $allow_guest_voting = apply_filters('shuriken_allow_guest_voting', $allow_guest_voting);
            
            // Check if user is logged in or guest voting is allowed
            if (!is_user_logged_in() && !$allow_guest_voting) {
                throw Shuriken_Permission_Exception::guest_not_allowed();
            }

            // Check nonce
            if (!check_ajax_referer('shuriken-reviews-nonce', 'nonce', false)) {
                throw new Shuriken_Exception(__('Invalid nonce', 'shuriken-reviews'), 'invalid_nonce');
            }

            if (!isset($_POST['rating_id'])) {
                throw Shuriken_Validation_Exception::required_field('rating_id');
            }
            
            if (!isset($_POST['rating_value'])) {
                throw Shuriken_Validation_Exception::required_field('rating_value');
            }
        } catch (Shuriken_Exception_Interface $e) {
            Shuriken_Exception_Handler::handle_ajax_exception($e);
            return;
        }

        $user_id = get_current_user_id();
        $user_ip = is_user_logged_in() ? null : $this->get_user_ip();
        $rating_id = intval($_POST['rating_id']);
        $rating_value = floatval($_POST['rating_value']);
        $max_stars = isset($_POST['max_stars']) ? intval($_POST['max_stars']) : Shuriken_Database::RATING_SCALE_DEFAULT;

        // Context for post-scoped voting
        $context_id = isset($_POST['context_id']) ? absint($_POST['context_id']) : null;
        $context_type = isset($_POST['context_type']) ? sanitize_key($_POST['context_type']) : null;

        // Validate context: both must be provided or neither
        if (($context_id && !$context_type) || (!$context_id && $context_type)) {
            $context_id = null;
            $context_type = null;
        }

        // Validate context_type against allowed values
        $allowed_context_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
        if ($context_type && !in_array($context_type, $allowed_context_types, true)) {
            $context_id = null;
            $context_type = null;
        }

        try {
            // Get the rating first to apply the filter
            $rating = $this->db->get_rating($rating_id);
            if (!$rating) {
                throw Shuriken_Not_Found_Exception::rating($rating_id);
            }

            /**
             * Filter the maximum number of stars displayed and accepted for voting.
             *
             * This should match the filter used in the shortcode/block rendering.
             * Votes are normalized to a 1-5 scale internally (for stars/numeric types).
             *
             * @since 1.7.0
             * @param int    $max_stars The maximum number of stars. Default 5.
             * @param object $rating    The rating object.
             */

            // Type-aware validation and normalization
            $rating_type = isset($rating->rating_type) ? $rating->rating_type : 'stars';

            /** @since 1.14.0 */
            $rating_type = apply_filters('shuriken_rating_type', $rating_type, $rating);

            $type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;

            // Binary types have a fixed scale — skip scale filtering
            if ($type_enum->isBinary()) {
                $max_stars = 1;
            } else {
                $max_stars_filtered = apply_filters('shuriken_rating_max_stars', intval($rating->scale), $rating);

                /** @since 1.14.0 */
                $max_stars_filtered = apply_filters('shuriken_rating_scale', $max_stars_filtered, $rating, $rating_type);
                $max_stars_filtered = max(1, intval($max_stars_filtered));
                
                // Use the filtered value (server-side filter takes precedence)
                // This ensures security even if client sends wrong max_stars
                $max_stars = $max_stars_filtered;
            }

            $normalized_value = Shuriken_Database::normalize_vote_value($rating_value, $rating_type, $max_stars);

            // Check if this rating is display-only
            if (!empty($rating->display_only)) {
                throw Shuriken_Logic_Exception::display_only_rating();
            }


            // Check rate limits
            $rate_limiter = shuriken_rate_limiter();
            $rate_limiter->can_vote($user_id, $user_ip, $rating_id, $context_id, $context_type);

            /**
             * Filter whether the user can submit this vote.
             *
             * Return false to prevent the vote from being submitted.
             * Return a WP_Error to provide a custom error message.
             *
             * @since 1.7.0
             * @param bool|WP_Error $can_vote     Whether the user can vote. Default true.
             * @param int           $rating_id    The rating ID.
             * @param float         $rating_value The rating value (in the display scale, e.g., 1-10 for 10-star).
             * @param int           $user_id      The user ID (0 for guests).
             * @param object        $rating       The rating object.
             * @param int|null      $context_id   The context post/entity ID (null for global votes).
             * @param string|null   $context_type The context type, e.g. 'post', 'product' (null for global votes).
             */
            $can_vote = apply_filters('shuriken_can_submit_vote', true, $rating_id, $rating_value, $user_id, $rating, $context_id, $context_type);
            
            if (is_wp_error($can_vote)) {
                throw Shuriken_Permission_Exception::voting_not_allowed($can_vote->get_error_message());
            }
            
            if ($can_vote === false) {
                throw Shuriken_Permission_Exception::voting_not_allowed();
            }
        } catch (Shuriken_Exception_Interface $e) {
            Shuriken_Exception_Handler::handle_ajax_exception($e);
            return;
        }

        /**
         * Fires before a vote is submitted.
         *
         * @since 1.7.0
         * @param int         $rating_id        The rating ID.
         * @param float       $rating_value     The rating value (in the display scale).
         * @param float       $normalized_value The normalized value (1-5 scale for storage).
         * @param int         $user_id          The user ID (0 for guests).
         * @param string      $user_ip          The user IP (for guests).
         * @param object      $rating           The rating object.
         * @param int         $max_stars        The maximum stars for this rating.
         * @param int|null    $context_id       The context post/entity ID (null for global votes).
         * @param string|null $context_type     The context type, e.g. 'post', 'product' (null for global votes).
         */
        do_action('shuriken_before_submit_vote', $rating_id, $rating_value, $normalized_value, $user_id, $user_ip, $rating, $max_stars, $context_id, $context_type);

        try {
            // Check if the user has already voted
            $existing_vote = $this->db->get_user_vote($rating_id, $user_id, $user_ip, $context_id, $context_type);
            $is_update = !empty($existing_vote);

            if ($existing_vote) {
                // Update the existing vote (use normalized value for storage)
                $this->db->update_vote(
                    $existing_vote->id,
                    $rating_id,
                    $existing_vote->rating_value,
                    $normalized_value
                );

                /**
                 * Fires after a vote is updated.
                 *
                 * @since 1.7.0
                 * @param int         $vote_id          The vote ID.
                 * @param int         $rating_id        The rating ID.
                 * @param float       $old_value        The previous rating value (normalized 1-5 scale).
                 * @param float       $new_value        The new rating value (in display scale).
                 * @param float       $normalized_value The new normalized value (1-5 scale).
                 * @param int         $user_id          The user ID (0 for guests).
                 * @param object      $rating           The rating object.
                 * @param int         $max_stars        The maximum stars for this rating.
                 * @param int|null    $context_id       The context post/entity ID (null for global votes).
                 * @param string|null $context_type     The context type, e.g. 'post', 'product' (null for global votes).
                 */
                do_action('shuriken_vote_updated', $existing_vote->id, $rating_id, $existing_vote->rating_value, $rating_value, $normalized_value, $user_id, $rating, $max_stars, $context_id, $context_type);
            } else {
                // Insert a new vote (use normalized value for storage)
                $this->db->create_vote($rating_id, $normalized_value, $user_id, $user_ip, $context_id, $context_type);

                /**
                 * Fires after a new vote is created.
                 *
                 * @since 1.7.0
                 * @param int         $rating_id        The rating ID.
                 * @param float       $rating_value     The rating value (in display scale).
                 * @param float       $normalized_value The normalized value (1-5 scale for storage).
                 * @param int         $user_id          The user ID (0 for guests).
                 * @param string      $user_ip          The user IP (for guests).
                 * @param object      $rating           The rating object.
                 * @param int         $max_stars        The maximum stars for this rating.
                 * @param int|null    $context_id       The context post/entity ID (null for global votes).
                 * @param string|null $context_type     The context type, e.g. 'post', 'product' (null for global votes).
                 */
                do_action('shuriken_vote_created', $rating_id, $rating_value, $normalized_value, $user_id, $user_ip, $rating, $max_stars, $context_id, $context_type);
            }

            // If this is a sub-rating, recalculate the parent rating
            if (!empty($rating->parent_id)) {
                $this->db->recalculate_parent_rating($rating->parent_id);
            }

            // Get updated rating data
            $updated_rating = $this->db->get_rating($rating_id);

        } catch (Shuriken_Exception_Interface $e) {
            Shuriken_Exception_Handler::handle_ajax_exception($e);
            return;
        }

        // For contextual votes, return context-scoped stats instead of global totals
        if ($context_id !== null && $context_type !== null) {
            $ctx_stats = $this->db->get_contextual_stats($rating_id, $context_id, $context_type, $max_stars);
            $ctx_total_votes = $ctx_stats->total_votes;
            $ctx_total_rating = $ctx_stats->total_rating;
            $ctx_average = $ctx_stats->average;
            $ctx_display_average = $ctx_stats->display_average;
        } else {
            $ctx_total_votes = $updated_rating->total_votes;
            $ctx_total_rating = $updated_rating->total_rating;
            $ctx_average = $updated_rating->average;
            $ctx_display_average = $updated_rating->display_average;
        }

        // Calculate scaled average for the response (type-aware)
        if ($type_enum === Shuriken_Rating_Type::LikeDislike) {
            // For like/dislike: total_rating = like count, average = approval rate
            $scaled_average = $ctx_total_votes > 0 
                ? round(($ctx_total_rating / $ctx_total_votes) * 100) 
                : 0;
        } elseif ($type_enum === Shuriken_Rating_Type::Approval) {
            $scaled_average = $ctx_total_votes;
        } else {
            $scaled_average = $ctx_display_average;
        }

        // Build response data with both normalized and scaled values
        $response_data = array(
            'new_average' => $ctx_average,                       // Normalized (1-5 scale)
            'new_scaled_average' => $scaled_average,             // Scaled to max_stars
            'new_total_votes' => $ctx_total_votes,
            'max_stars' => $max_stars,
            'rating_type' => $rating_type,
        );

        // Also send parent data if applicable
        if (!empty($rating->parent_id)) {
            $parent_rating = $this->db->get_rating($rating->parent_id);
            if ($parent_rating) {
                $parent_type = isset($parent_rating->rating_type) ? $parent_rating->rating_type : 'stars';
                $parent_type = apply_filters('shuriken_rating_type', $parent_type, $parent_rating);

                // Binary types have a fixed scale
                $parent_type_enum = Shuriken_Rating_Type::tryFrom($parent_type) ?? Shuriken_Rating_Type::Stars;
                if ($parent_type_enum->isBinary()) {
                    $parent_max_stars = 1;
                } else {
                    $parent_max_stars = apply_filters('shuriken_rating_max_stars', intval($parent_rating->scale), $parent_rating);
                    $parent_max_stars = apply_filters('shuriken_rating_scale', $parent_max_stars, $parent_rating, $parent_type);
                }

                // Use contextual stats for parent too if in context mode
                if ($context_id !== null && $context_type !== null) {
                    $parent_ctx = $this->db->get_contextual_stats($parent_rating->source_id, $context_id, $context_type, $parent_max_stars);
                    $response_data['parent_id'] = $parent_rating->id;
                    $response_data['parent_average'] = $parent_ctx->average;
                    $response_data['parent_scaled_average'] = $parent_ctx->display_average;
                    $response_data['parent_total_votes'] = $parent_ctx->total_votes;
                    $response_data['parent_max_stars'] = $parent_max_stars;
                } else {
                    $response_data['parent_id'] = $parent_rating->id;
                    $response_data['parent_average'] = $parent_rating->average;
                    $response_data['parent_scaled_average'] = $parent_rating->display_average;
                    $response_data['parent_total_votes'] = $parent_rating->total_votes;
                    $response_data['parent_max_stars'] = $parent_max_stars;
                }
            }
        }

        /**
         * Filter the vote submission response data.
         *
         * @since 1.7.0
         * @param array  $response_data  The response data.
         * @param int    $rating_id      The rating ID.
         * @param float  $rating_value   The rating value (in display scale).
         * @param bool   $is_update      Whether this was an update or new vote.
         * @param object $updated_rating The updated rating object.
         * @param int    $max_stars      The maximum stars for this rating.
         */
        $response_data = apply_filters('shuriken_vote_response_data', $response_data, $rating_id, $rating_value, $is_update, $updated_rating, $max_stars);

        /**
         * Fires after a vote is successfully submitted.
         *
         * @since 1.7.0
         * @param int         $rating_id        The rating ID.
         * @param float       $rating_value     The rating value (in display scale).
         * @param float       $normalized_value The normalized value (1-5 scale).
         * @param int         $user_id          The user ID (0 for guests).
         * @param bool        $is_update        Whether this was an update or new vote.
         * @param object      $updated_rating   The rating object with updated totals.
         * @param int         $max_stars        The maximum stars for this rating.
         * @param int|null    $context_id       The context post/entity ID (null for global votes).
         * @param string|null $context_type     The context type, e.g. 'post', 'product' (null for global votes).
         */
        do_action('shuriken_after_submit_vote', $rating_id, $rating_value, $normalized_value, $user_id, $is_update, $updated_rating, $max_stars, $context_id, $context_type);

        wp_send_json_success($response_data);
    }

    /**
     * Get the user's IP address, checking for proxies
     *
     * @return string The user's IP address.
     * @since 1.2.0
     */
    private function get_user_ip(): string {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // HTTP_X_FORWARDED_FOR can contain multiple IPs, take the first one
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        // Validate IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }

        /**
         * Filter the detected user IP address.
         *
         * @since 1.13.1
         * @param string $ip Detected IP address.
         */
        return apply_filters('shuriken_get_user_ip', $ip);
    }
}

/**
 * Helper function to get AJAX instance
 *
 * @return Shuriken_AJAX
 */
function shuriken_ajax(): Shuriken_AJAX {
    return Shuriken_AJAX::get_instance();
}

