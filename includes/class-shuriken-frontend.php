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
    private static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10);
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Frontend
     */
    public static function get_instance() {
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
    public static function init() {
        self::get_instance();
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @return void
     * @since 1.1.0
     */
    public function enqueue_scripts() {
        // Enqueue styles
        wp_enqueue_style(
            'shuriken-reviews',
            SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/css/shuriken-reviews.css',
            array(),
            SHURIKEN_REVIEWS_VERSION
        );
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Enqueue main script
        wp_enqueue_script(
            'shuriken-reviews',
            SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/js/shuriken-reviews.js',
            array('jquery'),
            SHURIKEN_REVIEWS_VERSION,
            true
        );
        
        // Localize script with data and translations
        wp_localize_script('shuriken-reviews', 'shurikenReviews', $this->get_localized_data());
    }

    /**
     * Get localized data for JavaScript
     *
     * @return array
     */
    private function get_localized_data() {
        return array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('shuriken-reviews-nonce'),
            'logged_in' => is_user_logged_in(),
            'allow_guest_voting' => get_option('shuriken_allow_guest_voting', '0') === '1',
            'login_url' => wp_login_url(),
            'i18n' => $this->get_i18n_strings(),
        );
    }

    /**
     * Get internationalized strings for JavaScript
     *
     * @return array
     */
    private function get_i18n_strings() {
        return array(
            /* translators: %s: Login URL */
            'pleaseLogin' => __('Please <a href="%s">login</a> to rate', 'shuriken-reviews'),
            'thankYou' => __('Thank you for rating!', 'shuriken-reviews'),
            /* translators: 1: Average rating value out of 5, 2: Total number of votes */
            'averageRating' => __('Average: %1$s/5 (%2$s votes)', 'shuriken-reviews'),
            /* translators: %s: Error message */
            'error' => __('Error: %s', 'shuriken-reviews'),
            'genericError' => __('Error submitting rating. Please try again.', 'shuriken-reviews'),
        );
    }
}

/**
 * Helper function to get frontend instance
 *
 * @return Shuriken_Frontend
 */
function shuriken_frontend() {
    return Shuriken_Frontend::get_instance();
}

