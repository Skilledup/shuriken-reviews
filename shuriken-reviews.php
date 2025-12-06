<?php
/**
 * Plugin Name: Shuriken Reviews
 * Description: Boosts wordpress comments with a added functionalities.
 * Version: 1.4.3
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
    define('SHURIKEN_REVIEWS_VERSION', '1.4.3');
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
 * Loads the plugin text domain for translations.
 * 
 * @since 1.1.2
 */
add_action('plugins_loaded', 'shuriken_reviews_load_textdomain');

function shuriken_reviews_load_textdomain() {
    load_plugin_textdomain(
        'shuriken-reviews',
        false,
        dirname(SHURIKEN_REVIEWS_PLUGIN_BASENAME) . '/languages'
    );
}

/**
 * Include WordPress comments system functions.
 * 
 * @since 1.2.0
 */
require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/comments.php';

/**
 * Include Database class for core database operations.
 * 
 * @since 1.3.5
 */
require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-database.php';

/**
 * Include Analytics class for reusable statistics functions.
 * 
 * @since 1.3.0
 */
require_once SHURIKEN_REVIEWS_PLUGIN_DIR . 'includes/class-shuriken-analytics.php';

/**
 * Registers the Shuriken Rating FSE block.
 *
 * @return void
 * @since 1.1.9
 */
function shuriken_reviews_register_block() {
    // Register the editor script with proper dependencies
    wp_register_script(
        'shuriken-rating-editor',
        SHURIKEN_REVIEWS_PLUGIN_URL . 'blocks/shuriken-rating/index.js',
        array(
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-api-fetch'
        ),
        SHURIKEN_REVIEWS_VERSION,
        true
    );

    // Register the block with explicit render callback
    register_block_type(SHURIKEN_REVIEWS_PLUGIN_DIR . 'blocks/shuriken-rating', array(
        'render_callback' => 'shuriken_reviews_render_rating_block',
    ));
}
add_action('init', 'shuriken_reviews_register_block');

/**
 * Render callback for the Shuriken Rating block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string Rendered block output.
 * @since 1.1.9
 */
function shuriken_reviews_render_rating_block($attributes, $content, $block) {
    // Ensure attributes is an array
    if (!is_array($attributes)) {
        $attributes = array();
    }

    $rating_id = isset($attributes['ratingId']) ? absint($attributes['ratingId']) : 0;
    $title_tag = isset($attributes['titleTag']) ? sanitize_key($attributes['titleTag']) : 'h2';
    $anchor_tag = isset($attributes['anchorTag']) ? sanitize_html_class($attributes['anchorTag']) : '';

    // Validate title tag
    $allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');
    if (!in_array($title_tag, $allowed_tags, true)) {
        $title_tag = 'h2';
    }

    if (!$rating_id) {
        return '';
    }

    $rating = shuriken_db()->get_rating($rating_id);

    if (!$rating) {
        return '';
    }

    // get_rating() already resolves mirrors - it returns original's vote data
    // but preserves the mirror's name and mirror_of field
    $is_mirror = !empty($rating->mirror_of);
    $is_display_only = !empty($rating->display_only);
    
    $css_classes = 'shuriken-rating';
    if ($is_display_only) {
        $css_classes .= ' display-only';
    }
    if ($is_mirror) {
        $css_classes .= ' mirror-rating';
    }

    // Get block wrapper attributes - use source_id for data-id (original's ID for mirrors)
    $wrapper_attributes = get_block_wrapper_attributes(array(
        'class' => $css_classes,
        'data-id' => esc_attr($rating->source_id),
    ));

    if ($anchor_tag) {
        $wrapper_attributes = str_replace('class="', 'id="' . esc_attr($anchor_tag) . '" class="', $wrapper_attributes);
    }

    ob_start();
    ?>
    <div <?php echo $wrapper_attributes; ?>>
        <div class="shuriken-rating-wrapper">
            <<?php echo esc_html($title_tag); ?> class="rating-title">
                <?php echo esc_html($rating->name); ?>
            </<?php echo esc_html($title_tag); ?>>
            
            <div class="stars<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Rating stars', 'shuriken-reviews'); ?>">
                <?php for ($i = 1; $i <= 5; $i++) : ?>
                    <span class="star" 
                          data-value="<?php echo esc_attr($i); ?>" 
                          <?php if (!$is_display_only): ?>
                          role="button" 
                          tabindex="0"
                          aria-label="<?php printf(esc_attr__('Rate %d out of 5', 'shuriken-reviews'), $i); ?>"
                          <?php else: ?>
                          aria-label="<?php printf(esc_attr__('%d out of 5', 'shuriken-reviews'), $i); ?>"
                          <?php endif; ?>>
                        ★
                    </span>
                <?php endfor; ?>
            </div>

            <div class="rating-stats" data-average="<?php echo esc_attr($rating->average); ?>">
                <?php
                printf(
                    /* translators: 1: Average rating value out of 5, 2: Total number of votes */
                    esc_html__('Average: %1$s/5 (%2$s votes)', 'shuriken-reviews'),
                    esc_html($rating->average),
                    esc_html($rating->total_votes)
                );
                ?>
            </div>
            
            <?php if ($is_display_only): ?>
            <div class="display-only-notice">
                <?php esc_html_e('Calculated from sub-ratings', 'shuriken-reviews'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Registers REST API endpoints for ratings management.
 *
 * @return void
 * @since 1.1.9
 */
function shuriken_reviews_register_rest_routes() {
    register_rest_route('shuriken-reviews/v1', '/ratings', array(
        array(
            'methods'             => 'GET',
            'callback'            => 'shuriken_reviews_get_ratings',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ),
        array(
            'methods'             => 'POST',
            'callback'            => 'shuriken_reviews_create_rating',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'args'                => array(
                'name' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ),
    ));
}
add_action('rest_api_init', 'shuriken_reviews_register_rest_routes');

/**
 * REST API callback: Get all ratings.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response
 * @since 1.1.9
 */
function shuriken_reviews_get_ratings($request) {
    $ratings = shuriken_db()->get_all_ratings();
    return rest_ensure_response($ratings);
}

/**
 * REST API callback: Create a new rating.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 * @since 1.1.9
 */
function shuriken_reviews_create_rating($request) {
    $name = $request->get_param('name');
    
    $new_id = shuriken_db()->create_rating($name);
    
    if ($new_id === false) {
        return new WP_Error(
            'create_failed',
            __('Failed to create rating.', 'shuriken-reviews'),
            array('status' => 500)
        );
    }
    
    $rating = shuriken_db()->get_rating($new_id);
    
    return rest_ensure_response($rating);
}

/**
 * Activation hook for the Shuriken Reviews plugin.
 * Creates the necessary database tables for storing ratings and votes.
 *
 * @return void
 * @since 1.1.0
 */
function shuriken_reviews_activate() {
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

register_activation_hook(SHURIKEN_REVIEWS_PLUGIN_FILE, 'shuriken_reviews_activate');

/**
 * Deactivation hook for the Shuriken Reviews plugin.
 * Cleans up the database tables created by the plugin.
 *
 * @return void
 * @since 1.1.3
 */
if (!function_exists('shuriken_reviews_manual_install')) {
    /**
     * Handles database migrations and upgrades.
     * Checks actual schema state rather than version to ensure idempotent upgrades.
     *
     * @return void
     * @since 1.2.0
     */
    function shuriken_reviews_manual_install() {
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
    add_action('plugins_loaded', 'shuriken_reviews_manual_install');
}

/**
 * Handle rating form submissions before any output.
 * This runs on admin_init to allow proper redirects.
 *
 * @return void
 * @since 1.3.5
 */
function shuriken_reviews_handle_rating_forms() {
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
add_action('admin_init', 'shuriken_reviews_handle_rating_forms');

/**
 * Adds the Shuriken Reviews menu to the WordPress admin dashboard.
 *
 * @return void
 * @since 1.1.0
 */
function shuriken_reviews_menu() {
    add_menu_page(
        __('Shuriken Reviews', 'shuriken-reviews'),
        __('Shuriken Reviews', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews',
        'shuriken_reviews_ratings_page', // Changed to ratings page as default
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
        'shuriken_reviews_ratings_page'
    );

    // Add Comments Settings submenu
    add_submenu_page(
        'shuriken-reviews',
        __('Comments Settings', 'shuriken-reviews'),
        __('Comments Settings', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews-comments',
        'shuriken_reviews_comments_page'
    );

    // Add Analytics submenu
    add_submenu_page(
        'shuriken-reviews',
        __('Stats & Analytics', 'shuriken-reviews'),
        __('Analytics', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews-analytics',
        'shuriken_reviews_analytics_page'
    );

    // Add Settings submenu
    add_submenu_page(
        'shuriken-reviews',
        __('Settings', 'shuriken-reviews'),
        __('Settings', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews-settings',
        'shuriken_reviews_settings_page'
    );

    // Add hidden Item Stats page (no menu item, accessed via link)
    add_submenu_page(
        null, // Hidden - no parent menu
        __('Item Statistics', 'shuriken-reviews'),
        __('Item Stats', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews-item-stats',
        'shuriken_reviews_item_stats_page'
    );
}
add_action('admin_menu', 'shuriken_reviews_menu');

/**
 * Displays the Shuriken Reviews Ratings page.
 *
 * @return void
 * @since 1.1.5
 */
function shuriken_reviews_ratings_page() {
    include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/ratings.php';
}

/**
 * Displays the Shuriken Reviews Comments Settings page.
 *
 * @return void
 * @since 1.1.5
 */
function shuriken_reviews_comments_page() {
    include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/comments.php';
}

/**
 * Displays the Shuriken Reviews Settings page.
 *
 * @return void
 * @since 1.2.0
 */
function shuriken_reviews_settings_page() {
    include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/settings.php';
}

/**
 * Displays the Shuriken Reviews Analytics page.
 *
 * @return void
 * @since 1.3.0
 */
function shuriken_reviews_analytics_page() {
    include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/analytics.php';
}

/**
 * Displays the Shuriken Reviews Item Statistics page.
 *
 * @return void
 * @since 1.3.0
 */
function shuriken_reviews_item_stats_page() {
    include SHURIKEN_REVIEWS_PLUGIN_DIR . 'admin/item-stats.php';
}

/**
 * Enqueues scripts and styles for the analytics admin page.
 *
 * @param string $hook The current admin page hook.
 * @return void
 * @since 1.3.0
 */
function shuriken_reviews_analytics_scripts($hook) {
    // Load on analytics page and item stats page
    $allowed_hooks = array(
        'shuriken-reviews_page_shuriken-reviews-analytics',
        'admin_page_shuriken-reviews-item-stats'
    );
    
    if (!in_array($hook, $allowed_hooks, true)) {
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
        SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/css/admin-analytics.css',
        array(),
        SHURIKEN_REVIEWS_VERSION
    );

    // Enqueue analytics JS
    wp_enqueue_script(
        'shuriken-admin-analytics',
        SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/js/admin-analytics.js',
        array('jquery', 'chartjs'),
        SHURIKEN_REVIEWS_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'shuriken_reviews_analytics_scripts');

/**
 * Handles CSV export of ratings data.
 *
 * @return void
 * @since 1.3.0
 */
function shuriken_reviews_export_ratings() {
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
add_action('admin_post_shuriken_export_ratings', 'shuriken_reviews_export_ratings');

/**
 * Handles CSV export of individual item votes.
 *
 * @return void
 * @since 1.3.0
 */
function shuriken_reviews_export_item_votes() {
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
add_action('admin_post_shuriken_export_item_votes', 'shuriken_reviews_export_item_votes');

/**
 * Registers the [shuriken_rating] shortcode.
 * Displays an interactive rating interface for a specific item with stars and vote count.
 *
 * @param array $atts {
 *     Shortcode attributes.
 *     
 *     @type int    $id         Required. The ID of the rating to display.
 *     @type string $tag        Optional. HTML tag to wrap the rating title. Default 'h2'.
 *     @type string $anchor_tag  Optional. ID attribute for anchor linking. Default Empty.
 * }
 * @return string HTML content for the rating interface.
 * @since 1.1.0
 * 
 * @example [shuriken_rating id="1" tag="h2" anchor_tag="rating-1"]
 */
function shuriken_rating_shortcode($atts) {
    // Validate and sanitize attributes with proper defaults and parsing
    $atts = shortcode_atts(array(
        'id' => 0,
        'tag' => 'h2',
        'anchor_tag' => ''
    ), $atts, 'shuriken_rating');

    // Validate ID is numeric and positive
    $id = absint($atts['id']);
    if (!$id) {
        return '';
    }

    // Validate tag is allowed HTML tag
    $allowed_tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');
    $tag = in_array(strtolower($atts['tag']), $allowed_tags) ? $atts['tag'] : 'h2';

    // Sanitize anchor tag
    $anchor_id = !empty($atts['anchor_tag']) ? sanitize_html_class($atts['anchor_tag']) : '';

    // Get rating data
    $rating = shuriken_db()->get_rating($id);

    if (!$rating) {
        return '';
    }

    // get_rating() already resolves mirrors - it returns original's vote data
    // but preserves the mirror's name and mirror_of field
    $is_mirror = !empty($rating->mirror_of);
    $is_display_only = !empty($rating->display_only);
    
    $css_classes = 'shuriken-rating';
    if ($is_display_only) {
        $css_classes .= ' display-only';
    }
    if ($is_mirror) {
        $css_classes .= ' mirror-rating';
    }
    
    // Start output buffering
    ob_start();
    ?>
    <div class="<?php echo esc_attr($css_classes); ?>" data-id="<?php echo esc_attr($rating->source_id); ?>" <?php echo $anchor_id ? 'id="' . $anchor_id . '"' : ''; ?>>
        <div class="shuriken-rating-wrapper">
            <<?php echo tag_escape($tag); ?> class="rating-title">
                <?php echo esc_html($rating->name); ?>
            </<?php echo tag_escape($tag); ?>>
            
            <div class="stars<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Rating stars', 'shuriken-reviews'); ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star" 
                          data-value="<?php echo $i; ?>" 
                          <?php if (!$is_display_only): ?>
                          role="button" 
                          tabindex="0"
                          aria-label="<?php printf(esc_attr__('Rate %d out of 5', 'shuriken-reviews'), $i); ?>"
                          <?php else: ?>
                          aria-label="<?php printf(esc_attr__('%d out of 5', 'shuriken-reviews'), $i); ?>"
                          <?php endif; ?>>
                        ★
                    </span>
                <?php endfor; ?>
            </div>

            <div class="rating-stats" data-average="<?php echo esc_attr($rating->average); ?>">
                <?php 
                printf(
                    /* translators: 1: Average rating value out of 5, 2: Total number of votes */
                    esc_html__('Average: %1$s/5 (%2$s votes)', 'shuriken-reviews'),
                    esc_html($rating->average),
                    esc_html($rating->total_votes)
                );
                ?>
            </div>
            
            <?php if ($is_display_only): ?>
            <div class="display-only-notice">
                <?php esc_html_e('Calculated from sub-ratings', 'shuriken-reviews'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('shuriken_rating', 'shuriken_rating_shortcode');

/**
 * Gets the user's IP address, checking for proxies.
 *
 * @return string The user's IP address.
 * @since 1.2.0
 */
function shuriken_get_user_ip() {
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

/**
 * Handles the AJAX request to submit a rating.
 * Updates or inserts the user's rating for a specific item.
 *
 * @return void
 * @since 1.1.0
 */
function handle_submit_rating() {
    $allow_guest_voting = get_option('shuriken_allow_guest_voting', '0') === '1';
    
    // Check if user is logged in or guest voting is allowed
    if (!is_user_logged_in() && !$allow_guest_voting) {
        wp_send_json_error('You must be logged in to rate');
        return;
    }

    // Check nonce
    if (!check_ajax_referer('shuriken-reviews-nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    if (!isset($_POST['rating_id']) || !isset($_POST['rating_value'])) {
        wp_send_json_error('Missing required fields');
        return;
    }

    $db = shuriken_db();
    $user_id = get_current_user_id();
    $user_ip = is_user_logged_in() ? null : shuriken_get_user_ip();
    $rating_id = intval($_POST['rating_id']);
    $rating_value = intval($_POST['rating_value']);

    // Get the rating to check if it's display-only
    $rating = $db->get_rating($rating_id);
    if (!$rating) {
        wp_send_json_error('Rating not found');
        return;
    }

    // Check if this rating is display-only
    if (!empty($rating->display_only)) {
        wp_send_json_error('This rating is display-only and cannot be voted on directly');
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
            wp_send_json_error('Failed to update vote');
            return;
        }
    } else {
        // Insert a new vote
        $result = $db->create_vote($rating_id, $rating_value, $user_id, $user_ip);

        if (!$result) {
            wp_send_json_error('Failed to submit vote');
            return;
        }
    }

    // If this is a sub-rating, recalculate the parent rating
    if (!empty($rating->parent_id)) {
        $db->recalculate_parent_rating($rating->parent_id);
    }

    // Get updated rating data
    $updated_rating = $db->get_rating($rating_id);

    // Also send parent data if applicable
    $response_data = array(
        'new_average' => $updated_rating->average,
        'new_total_votes' => $updated_rating->total_votes
    );

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
add_action('wp_ajax_submit_rating', 'handle_submit_rating');
add_action('wp_ajax_nopriv_submit_rating', 'handle_submit_rating');

/**
 * Enqueues the necessary scripts and styles for the Shuriken Reviews plugin.
 *
 * @return void
 * @since 1.1.0
 */
function shuriken_reviews_scripts() {
    wp_enqueue_style(
        'shuriken-reviews',
        SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/css/shuriken-reviews.css',
        array(),
        SHURIKEN_REVIEWS_VERSION
    );
    
    wp_enqueue_script('jquery');
    
    wp_enqueue_script(
        'shuriken-reviews',
        SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/js/shuriken-reviews.js',
        array('jquery'),
        SHURIKEN_REVIEWS_VERSION,
        true
    );
    
    wp_localize_script('shuriken-reviews', 'shurikenReviews', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('shuriken-reviews-nonce'),
        'logged_in' => is_user_logged_in(),
        'allow_guest_voting' => get_option('shuriken_allow_guest_voting', '0') === '1',
        'login_url' => wp_login_url(),
        'i18n' => array(
            /* translators: %s: Login URL */
            'pleaseLogin' => __('Please <a href="%s">login</a> to rate', 'shuriken-reviews'),
            'thankYou' => __('Thank you for rating!', 'shuriken-reviews'),
            /* translators: 1: Average rating value out of 5, 2: Total number of votes */
            'averageRating' => __('Average: %1$s/5 (%2$s votes)', 'shuriken-reviews'),
            /* translators: %s: Error message */
            'error' => __('Error: %s', 'shuriken-reviews'),
            'genericError' => __('Error submitting rating. Please try again.', 'shuriken-reviews')
        )
    ));
}
add_action('wp_enqueue_scripts', 'shuriken_reviews_scripts', 10);