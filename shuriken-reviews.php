<?php
/**
 * Plugin Name: Shuriken Reviews
 * Description: Boosts wordpress comments with a added functionalities.
 * Version: 1.1.5
 * Author: Skilledup Hub
 * Author URI: https://skilledup.ir
 * License: GPL2
 * Text Domain: shuriken-reviews
 * Domain Path: /languages
 */

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
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Modifies the comments query to exclude specific types of comments from the Latest Comments block.
 * Excludes author comments and/or reply comments based on plugin settings.
 * Uses transients to cache author IDs for better performance.
 *
 * @param WP_Comment_Query $query The WordPress comment query object
 * @return void
 * @since 1.0.0
 */

function exclude_comments_from_latest_comments($query) {
    // Only modify queries for Latest Comments block
    if (isset($query->query_vars['number']) && $query->query_vars['number'] > 0) {
        
        // Exclude author comments if enabled
        if (get_option('shuriken_exclude_author_comments', '1')) {
            // Generate a unique transient key based on the current post
            $post_id = get_the_ID();
            $transient_key = 'excluded_author_comments_' . $post_id;
            
            // Try to get cached author ID
            $post_author_id = get_transient($transient_key);
            
            if (false === $post_author_id) {
                // Cache miss - get the post author's ID and cache it
                $post_author_id = get_post_field('post_author', $post_id);
                
                // Cache the author ID for 12 hours
                set_transient($transient_key, $post_author_id, 12 * HOUR_IN_SECONDS);
            }
            
            // Exclude comments from the post author
            if ($post_author_id) {
                $query->query_vars['author__not_in'] = array($post_author_id);
            }
        }

        // Exclude reply comments if enabled
        if (get_option('shuriken_exclude_reply_comments', '1')) {
            $query->query_vars['parent'] = 0; // Only show top-level comments
        }
    }
}
add_action('pre_get_comments', 'exclude_comments_from_latest_comments');

// Clear transients when comments are modified
function clear_author_comments_transients($comment_id) {
    $comment = get_comment($comment_id);
    $post_id = $comment->comment_post_ID;
    delete_transient('excluded_author_comments_' . $post_id);
}

// Hook into comment actions to clear cache
add_action('wp_insert_comment', 'clear_author_comments_transients');
add_action('edit_comment', 'clear_author_comments_transients');
add_action('delete_comment', 'clear_author_comments_transients');


/**
 * Filter block content before it is rendered.
 * 
 * @param string $block_content The block content about to be rendered
 * @param array $block The full block, including name and attributes
 * @return string Modified block content
 * @since 1.0.0
 */

function customize_latest_comments_block($block_content, $block) {
    // Only modify Latest Comments block
    if ($block['blockName'] !== 'core/latest-comments') {
        return $block_content;
    }

    // Parse the existing comments from the block content
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($block_content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    // Add CSS for the grid layout
    $style = $dom->createElement('style');
    $style->textContent = '
        .wp-block-latest-comments {
            display: grid !important;
            grid-template-columns: 1fr !important; /* Default to single column for mobile */
            gap: 1.2rem !important;
        }
        .wp-block-latest-comments .wp-block-latest-comments__comment {
            border: solid 1px #e0e0e0;
            padding: 15px !important;
            border-radius: 8px !important;
            margin: 0 !important;
        }
        .wp-block-latest-comments__comment-author {
            font-weight: bold !important;
            display: block !important;
            margin-bottom: 5px !important;
        }
        .wp-block-latest-comments__comment-date {
            color: #666 !important;
            font-size: 0.9em !important;
            margin-bottom: 10px !important;
            display: block !important;
        }
        .wp-block-latest-comments__comment-excerpt p {
            margin: 0 !important;
            margin-bottom: 10px !important;
        }
        @media (min-width: 768px) {
            .wp-block-latest-comments {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (min-width: 1024px) {
            .wp-block-latest-comments {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
    ';
    $dom->appendChild($style);

    // Convert back to HTML string
    $modified_content = $dom->saveHTML();

    return $modified_content;
}
add_filter('render_block', 'customize_latest_comments_block', 10, 2);

/**
 * Activation hook for the Shuriken Reviews plugin.
 * Creates the necessary database tables for storing ratings and votes.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 * @since 1.1.0
 */
function shuriken_reviews_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'shuriken_ratings';
    $votes_table_name = $wpdb->prefix . 'shuriken_votes';
    
    $charset_collate = $wpdb->get_charset_collate();

    // Add error logging since WP_DEBUG is enabled
    error_log('Creating Shuriken Reviews tables...');

    try {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            total_votes int DEFAULT 0,
            total_rating int DEFAULT 0,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS $votes_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            rating_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            rating_value int NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_vote (rating_id, user_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Verify tables were created
        $ratings_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $votes_exists = $wpdb->get_var("SHOW TABLES LIKE '$votes_table_name'") === $votes_table_name;

        if (!$ratings_exists || !$votes_exists) {
            error_log('Failed to create tables. Tables exist check: ratings=' . ($ratings_exists ? 'yes' : 'no') . ', votes=' . ($votes_exists ? 'yes' : 'no'));
            throw new Exception('Failed to create required database tables');
        }

        error_log('Shuriken Reviews tables created successfully');

        // Initialize plugin options
        add_option('shuriken_exclude_author_comments', '1');
        add_option('shuriken_exclude_reply_comments', '1');
        add_option('shuriken_reviews_db_version', '1.1.3');

    } catch (Exception $e) {
        error_log('Error creating Shuriken Reviews tables: ' . $e->getMessage());
        // Optionally deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Error creating required database tables. Please check the error log for details.');
    }
}

register_activation_hook(__FILE__, 'shuriken_reviews_activate');

/**
 * Deactivation hook for the Shuriken Reviews plugin.
 * Cleans up the database tables created by the plugin.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 * @since 1.1.3
 */
if (!function_exists('shuriken_reviews_manual_install')) {
    function shuriken_reviews_manual_install() {
        if (get_option('shuriken_reviews_db_version') !== '1.1.3') {
            shuriken_reviews_activate();
            update_option('shuriken_reviews_db_version', '1.1.3');
        }
    }
    add_action('plugins_loaded', 'shuriken_reviews_manual_install');
}

/**
 * Adds the Shuriken Reviews menu to the WordPress admin dashboard.
 *
 * @return void
 * @since 1.1.0
 */
function shuriken_reviews_menu() {
    // Add main menu item
    add_menu_page(
        __('Shuriken Reviews', 'shuriken-reviews'),
        __('Shuriken Reviews', 'shuriken-reviews'),
        'manage_options',
        'shuriken-reviews',
        'shuriken_reviews_ratings_page', // Changed to ratings page as default
        'dashicons-star-filled'
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
}
add_action('admin_menu', 'shuriken_reviews_menu');

/**
 * Displays the Shuriken Reviews Ratings page.
 *
 * @return void
 * @since 1.1.5
 */
function shuriken_reviews_ratings_page() {
    include plugin_dir_path(__FILE__) . 'admin/ratings.php';
}

/**
 * Displays the Shuriken Reviews Comments Settings page.
 *
 * @return void
 * @since 1.1.5
 */
function shuriken_reviews_comments_page() {
    include plugin_dir_path(__FILE__) . 'admin/comments.php';
}

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
    global $wpdb;
    $table_name = $wpdb->prefix . 'shuriken_ratings';
    $rating = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, total_votes, total_rating FROM $table_name WHERE id = %d",
        $id
    ));

    if (!$rating) {
        return '';
    }

    // Calculate average rating
    $average = $rating->total_votes > 0 ? round($rating->total_rating / $rating->total_votes, 1) : 0;
    
    // Start output buffering
    ob_start();
    ?>
    <div class="shuriken-rating" data-id="<?php echo esc_attr($rating->id); ?>" <?php echo $anchor_id ? 'id="' . $anchor_id . '"' : ''; ?>>
        <div class="shuriken-rating-wrapper">
            <<?php echo tag_escape($tag); ?> class="rating-title">
                <?php echo esc_html($rating->name); ?>
            </<?php echo tag_escape($tag); ?>>
            
            <div class="stars" role="group" aria-label="<?php esc_attr_e('Rating stars', 'shuriken-reviews'); ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star" 
                          data-value="<?php echo $i; ?>" 
                          role="button" 
                          tabindex="0"
                          aria-label="<?php printf(esc_attr__('Rate %d out of 5', 'shuriken-reviews'), $i); ?>">
                        ★
                    </span>
                <?php endfor; ?>
            </div>

            <div class="rating-stats" data-average="<?php echo esc_attr($average); ?>">
                <?php 
                printf(
                    /* translators: 1: Average rating value out of 5, 2: Total number of votes */
                    esc_html__('Average: %1$s/5 (%2$s votes)', 'shuriken-reviews'),
                    esc_html($average),
                    esc_html($rating->total_votes)
                );
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('shuriken_rating', 'shuriken_rating_shortcode');

/**
 * Handles the AJAX request to submit a rating.
 * Updates or inserts the user's rating for a specific item.
 *
 * @return void
 * @since 1.1.0
 */
function handle_submit_rating() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
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

    global $wpdb;
    $table_name = $wpdb->prefix . 'shuriken_ratings';
    $votes_table_name = $wpdb->prefix . 'shuriken_votes';
    $user_id = get_current_user_id();
    $rating_id = intval($_POST['rating_id']);
    $rating_value = intval($_POST['rating_value']);

    // Check if the user has already voted
    $existing_vote = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $votes_table_name WHERE rating_id = %d AND user_id = %d",
        $rating_id,
        $user_id
    ));

    if ($existing_vote) {
        // Update the existing vote
        $result = $wpdb->update(
            $votes_table_name,
            array('rating_value' => $rating_value),
            array('id' => $existing_vote->id)
        );

        if ($result === false) {
            wp_send_json_error('Database update failed');
            return;
        }

        // Update the total rating and total votes
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET total_rating = total_rating - %d + %d 
            WHERE id = %d",
            $existing_vote->rating_value,
            $rating_value,
            $rating_id
        ));
    } else {
        // Insert a new vote
        $result = $wpdb->insert(
            $votes_table_name,
            array(
                'rating_id' => $rating_id,
                'user_id' => $user_id,
                'rating_value' => $rating_value
            )
        );

        if ($result === false) {
            wp_send_json_error('Database insert failed');
            return;
        }

        // Update the total rating and total votes
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET total_votes = total_votes + 1,
            total_rating = total_rating + %d 
            WHERE id = %d",
            $rating_value,
            $rating_id
        ));
    }

    // Calculate new average rating and total votes
    $rating = $wpdb->get_row($wpdb->prepare(
        "SELECT total_rating, total_votes FROM $table_name WHERE id = %d",
        $rating_id
    ));
    $new_average = $rating->total_votes > 0 ? round($rating->total_rating / $rating->total_votes, 1) : 0;

    wp_send_json_success(array(
        'new_average' => $new_average,
        'new_total_votes' => $rating->total_votes
    ));
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
    $plugin_url = plugin_dir_url(__FILE__);

    wp_enqueue_style(
        'shuriken-reviews',
        $plugin_url . 'assets/css/shuriken-reviews.css',
        array()
    );
    
    wp_enqueue_script('jquery');
    
    wp_enqueue_script(
        'shuriken-reviews',
        $plugin_url . 'assets/js/shuriken-reviews.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('shuriken-reviews', 'shurikenReviews', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('shuriken-reviews-nonce'),
        'logged_in' => is_user_logged_in(),
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