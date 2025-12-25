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
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        // Rating submission
        add_action('wp_ajax_submit_rating', array($this, 'handle_submit_rating'));
        add_action('wp_ajax_nopriv_submit_rating', array($this, 'handle_submit_rating'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_AJAX
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize AJAX handlers
     *
     * @return void
     */
    public static function init() {
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
    public function handle_submit_rating() {
        $allow_guest_voting = get_option('shuriken_allow_guest_voting', '0') === '1';
        
        // Check if user is logged in or guest voting is allowed
        if (!is_user_logged_in() && !$allow_guest_voting) {
            wp_send_json_error(__('You must be logged in to rate', 'shuriken-reviews'));
            return;
        }

        // Check nonce
        if (!check_ajax_referer('shuriken-reviews-nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid nonce', 'shuriken-reviews'));
            return;
        }

        if (!isset($_POST['rating_id']) || !isset($_POST['rating_value'])) {
            wp_send_json_error(__('Missing required fields', 'shuriken-reviews'));
            return;
        }

        $db = shuriken_db();
        $user_id = get_current_user_id();
        $user_ip = is_user_logged_in() ? null : $this->get_user_ip();
        $rating_id = intval($_POST['rating_id']);
        $rating_value = intval($_POST['rating_value']);

        // Validate rating value
        if ($rating_value < 1 || $rating_value > 5) {
            wp_send_json_error(__('Invalid rating value', 'shuriken-reviews'));
            return;
        }

        // Get the rating to check if it's display-only
        $rating = $db->get_rating($rating_id);
        if (!$rating) {
            wp_send_json_error(__('Rating not found', 'shuriken-reviews'));
            return;
        }

        // Check if this rating is display-only
        if (!empty($rating->display_only)) {
            wp_send_json_error(__('This rating is display-only and cannot be voted on directly', 'shuriken-reviews'));
            return;
        }

        // Check if the user has already voted
        $existing_vote = $db->get_user_vote($rating_id, $user_id, $user_ip);

        if ($existing_vote) {
            // Update the existing vote
            $result = $db->update_vote(
                $existing_vote->id,
                $rating_id,
                $existing_vote->rating_value,
                $rating_value
            );

            if (!$result) {
                wp_send_json_error(__('Failed to update vote', 'shuriken-reviews'));
                return;
            }
        } else {
            // Insert a new vote
            $result = $db->create_vote($rating_id, $rating_value, $user_id, $user_ip);

            if (!$result) {
                wp_send_json_error(__('Failed to submit vote', 'shuriken-reviews'));
                return;
            }
        }

        // If this is a sub-rating, recalculate the parent rating
        if (!empty($rating->parent_id)) {
            $db->recalculate_parent_rating($rating->parent_id);
        }

        // Get updated rating data
        $updated_rating = $db->get_rating($rating_id);

        // Build response data
        $response_data = array(
            'new_average' => $updated_rating->average,
            'new_total_votes' => $updated_rating->total_votes
        );

        // Also send parent data if applicable
        if (!empty($rating->parent_id)) {
            $parent_rating = $db->get_rating($rating->parent_id);
            if ($parent_rating) {
                $response_data['parent_id'] = $parent_rating->id;
                $response_data['parent_average'] = $parent_rating->average;
                $response_data['parent_total_votes'] = $parent_rating->total_votes;
            }
        }

        wp_send_json_success($response_data);
    }

    /**
     * Get the user's IP address, checking for proxies
     *
     * @return string The user's IP address.
     * @since 1.2.0
     */
    private function get_user_ip() {
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
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '0.0.0.0';
    }
}

/**
 * Helper function to get AJAX instance
 *
 * @return Shuriken_AJAX
 */
function shuriken_ajax() {
    return Shuriken_AJAX::get_instance();
}

