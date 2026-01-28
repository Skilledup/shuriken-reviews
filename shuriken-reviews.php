<?php
/**
 * Plugin Name: Shuriken Reviews
 * Description: A powerful and flexible rating system for WordPress.
 * Version: 1.9.0-6
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Skilledup Hub
 * Author URI: https://skilledup.ir
 * License: GPL2
 * Text Domain: shuriken-reviews
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin constants
 */
if (!defined('SHURIKEN_REVIEWS_VERSION')) {
    define('SHURIKEN_REVIEWS_VERSION', '1.9.0-6');
}

if (!defined('SHURIKEN_REVIEWS_DB_VERSION')) {
    define('SHURIKEN_REVIEWS_DB_VERSION', '1.4.0');
}

if (!defined('SHURIKEN_REVIEWS_PLUGIN_FILE')) {
    define('SHURIKEN_REVIEWS_PLUGIN_FILE', __FILE__);
}

if (!defined('SHURIKEN_REVIEWS_PLUGIN_DIR')) {
    define('SHURIKEN_REVIEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SHURIKEN_REVIEWS_PLUGIN_URL')) {
    define('SHURIKEN_REVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('SHURIKEN_REVIEWS_PLUGIN_BASENAME')) {
    define('SHURIKEN_REVIEWS_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main plugin class
 *
 * @since 1.7.0
 */
final class Shuriken_Reviews {

    /**
     * @var Shuriken_Reviews Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Shuriken_Reviews
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     *
     * @return void
     */
    private function load_dependencies() {
        // Exceptions
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-database-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-validation-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-not-found-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-permission-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-logic-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-configuration-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-rate-limit-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/exceptions/class-shuriken-integration-exception.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-exception-handler.php';
        
        // Interfaces
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/interfaces/interface-shuriken-database.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/interfaces/interface-shuriken-analytics.php';
        
        // Core classes
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-database.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-analytics.php';
        
        // Dependency injection container
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-container.php';
        
        // Feature modules
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-rest-api.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-shortcodes.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-block.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-ajax.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-frontend.php';
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-admin.php';
        
        // Legacy/additional functionality
        require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/comments.php';
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Handle database migrations
        add_action('plugins_loaded', array($this, 'maybe_upgrade_database'));
        
        // Initialize modules
        add_action('plugins_loaded', array($this, 'init_modules'));
        
        // Activation hook
        register_activation_hook(SHURIKEN_REVIEWS_PLUGIN_FILE, array($this, 'activate'));
    }

    /**
     * Load plugin text domain for translations
     *
     * @return void
     * @since 1.1.2
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'shuriken-reviews',
            false,
            dirname(SHURIKEN_REVIEWS_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Check and run database migrations if needed
     *
     * @return void
     * @since 1.2.0
     */
    public function maybe_upgrade_database() {
        // Skip if already at current version
        $current_version = get_option('shuriken_reviews_db_version', '0');
        if (version_compare($current_version, SHURIKEN_REVIEWS_DB_VERSION, '>=')) {
            return;
        }
        
        $db = shuriken_db();
        
        // Check if tables exist before attempting migration
        if (!$db->tables_exist()) {
            return;
        }
        
        // Run migrations
        $db->run_migrations($current_version);
        
        // Add the new option if it doesn't exist
        add_option('shuriken_allow_guest_voting', '0');
        
        // Update version
        update_option('shuriken_reviews_db_version', SHURIKEN_REVIEWS_DB_VERSION);
    }

    /**
     * Initialize all plugin modules
     *
     * @return void
     */
    public function init_modules() {
        $container = shuriken_container();
        
        // Initialize REST API
        Shuriken_REST_API::init();
        
        // Initialize shortcodes
        Shuriken_Shortcodes::init();
        
        // Initialize Gutenberg block
        Shuriken_Block::init();
        
        // Initialize AJAX handlers
        Shuriken_AJAX::init();
        
        // Initialize frontend assets
        Shuriken_Frontend::init();
        
        // Initialize admin (only in admin context)
        if (is_admin()) {
            Shuriken_Admin::init();
        }
    }

    /**
     * Plugin activation handler
     *
     * Creates the necessary database tables for storing ratings and votes.
     *
     * @return void
     * @since 1.1.0
     */
    public function activate() {
        // Add error logging since WP_DEBUG is enabled
        error_log('Creating Shuriken Reviews tables...');

        try {
            $db = shuriken_db();
            
            if (!$db->create_tables()) {
                error_log('Failed to create Shuriken Reviews tables.');
                throw new Exception('Failed to create required database tables');
            }

            error_log('Shuriken Reviews tables created successfully');

            // Initialize plugin options
            add_option('shuriken_exclude_author_comments', '1');
            add_option('shuriken_exclude_reply_comments', '1');
            add_option('shuriken_allow_guest_voting', '0');
            add_option('shuriken_reviews_db_version', SHURIKEN_REVIEWS_DB_VERSION);

        } catch (Exception $e) {
            error_log('Error creating Shuriken Reviews tables: ' . $e->getMessage());
            // Optionally deactivate the plugin
            deactivate_plugins(SHURIKEN_REVIEWS_PLUGIN_BASENAME);
            wp_die('Error creating required database tables. Please check the error log for details.');
        }
    }
}

/**
 * Initialize the plugin
 *
 * @return Shuriken_Reviews
 */
function shuriken_reviews() {
    return Shuriken_Reviews::get_instance();
}

// Start the plugin
shuriken_reviews();
