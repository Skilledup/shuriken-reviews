<?php
/**
 * Shuriken Reviews Shortcodes Class
 *
 * Handles all shortcode registrations and rendering.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Shortcodes
 *
 * Registers and renders shortcodes.
 *
 * @since 1.7.0
 */
class Shuriken_Shortcodes {

    /**
     * @var Shuriken_Shortcodes Singleton instance
     */
    private static $instance = null;

    /**
     * @var Shuriken_Database_Interface Database instance
     */
    private $db;

    /**
     * Allowed HTML tags for rating title
     */
    const ALLOWED_TITLE_TAGS = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null $db Database instance (optional, for dependency injection).
     */
    public function __construct($db = null) {
        $this->db = $db ?: shuriken_db();
        
        add_shortcode('shuriken_rating', array($this, 'render_rating'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Shortcodes
     */
    public static function get_instance() {
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
    public function get_db() {
        return $this->db;
    }

    /**
     * Initialize shortcodes
     *
     * @return void
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Render the [shuriken_rating] shortcode
     *
     * Displays an interactive rating interface for a specific item with stars and vote count.
     *
     * @param array $atts {
     *     Shortcode attributes.
     *     
     *     @type int    $id         Required. The ID of the rating to display.
     *     @type string $tag        Optional. HTML tag to wrap the rating title. Default 'h2'.
     *     @type string $anchor_tag Optional. ID attribute for anchor linking. Default empty.
     * }
     * @return string HTML content for the rating interface.
     * @since 1.1.0
     * 
     * @example [shuriken_rating id="1" tag="h2" anchor_tag="rating-1"]
     */
    public function render_rating($atts) {
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
        $tag = in_array(strtolower($atts['tag']), self::ALLOWED_TITLE_TAGS, true) ? $atts['tag'] : 'h2';

        // Sanitize anchor tag
        $anchor_id = !empty($atts['anchor_tag']) ? sanitize_html_class($atts['anchor_tag']) : '';

        // Get rating data
        $rating = $this->db->get_rating($id);

        if (!$rating) {
            return '';
        }

        return $this->render_rating_html($rating, $tag, $anchor_id);
    }

    /**
     * Render rating HTML
     *
     * @param object $rating    Rating object from database.
     * @param string $tag       HTML tag for title.
     * @param string $anchor_id Optional anchor ID.
     * @return string Rendered HTML.
     */
    public function render_rating_html($rating, $tag = 'h2', $anchor_id = '') {
        /**
         * Filter the rating data before rendering.
         *
         * @since 1.7.0
         * @param object $rating    The rating object.
         * @param string $tag       The HTML tag for the title.
         * @param string $anchor_id The anchor ID.
         */
        $rating = apply_filters('shuriken_rating_data', $rating, $tag, $anchor_id);

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

        /**
         * Filter the CSS classes for the rating container.
         *
         * @since 1.7.0
         * @param string $css_classes The CSS classes string.
         * @param object $rating      The rating object.
         */
        $css_classes = apply_filters('shuriken_rating_css_classes', $css_classes, $rating);

        /**
         * Filter the maximum number of stars displayed and accepted for voting.
         *
         * This filter controls both the visual display AND the voting scale.
         * Votes are automatically normalized to a 1-5 scale internally.
         * For example, with 10 stars: user clicks star 8 → stored as 4.0 (8/10 * 5)
         *
         * @since 1.7.0
         * @param int    $max_stars The maximum number of stars. Default 5.
         * @param object $rating    The rating object.
         */
        $max_stars = apply_filters('shuriken_rating_max_stars', 5, $rating);
        
        // Ensure max_stars is at least 1
        $max_stars = max(1, intval($max_stars));

        /**
         * Filter the star character/symbol.
         *
         * @since 1.7.0
         * @param string $star_symbol The star symbol. Default '★'.
         * @param object $rating      The rating object.
         */
        $star_symbol = apply_filters('shuriken_rating_star_symbol', '★', $rating);
        
        // Calculate the scaled average for display (convert from 5-scale to custom scale)
        $scaled_average = ($rating->average / 5) * $max_stars;
        $scaled_average = round($scaled_average, 1);
        
        // Start output buffering
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_classes); ?>" data-id="<?php echo esc_attr($rating->source_id); ?>" data-max-stars="<?php echo esc_attr($max_stars); ?>" <?php echo $anchor_id ? 'id="' . esc_attr($anchor_id) . '"' : ''; ?>>
            <div class="shuriken-rating-wrapper">
                <<?php echo tag_escape($tag); ?> class="rating-title">
                    <?php echo esc_html($rating->name); ?>
                </<?php echo tag_escape($tag); ?>>
                
                <div class="stars<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Rating stars', 'shuriken-reviews'); ?>">
                    <?php for ($i = 1; $i <= $max_stars; $i++): ?>
                        <span class="star" 
                              data-value="<?php echo $i; ?>" 
                              <?php if (!$is_display_only): ?>
                              role="button" 
                              tabindex="0"
                              aria-label="<?php printf(esc_attr__('Rate %1$d out of %2$d', 'shuriken-reviews'), $i, $max_stars); ?>"
                              <?php else: ?>
                              aria-label="<?php printf(esc_attr__('%1$d out of %2$d', 'shuriken-reviews'), $i, $max_stars); ?>"
                              <?php endif; ?>>
                            <?php echo esc_html($star_symbol); ?>
                        </span>
                    <?php endfor; ?>
                </div>

                <div class="rating-stats" data-average="<?php echo esc_attr($rating->average); ?>" data-scaled-average="<?php echo esc_attr($scaled_average); ?>">
                    <?php 
                    printf(
                        /* translators: 1: Average rating value, 2: Maximum stars, 3: Total number of votes */
                        esc_html__('Average: %1$s/%2$s (%3$s votes)', 'shuriken-reviews'),
                        esc_html($scaled_average),
                        esc_html($max_stars),
                        esc_html($rating->total_votes)
                    );
                    ?>
                </div>
                
                <?php if ($is_display_only): ?>
                <div class="display-only-notice">
                    <?php esc_html_e('Calculated from sub-ratings', 'shuriken-reviews'); ?>
                </div>
                <?php endif; ?>

                <?php
                /**
                 * Fires after the rating stats, inside the rating wrapper.
                 *
                 * @since 1.7.0
                 * @param object $rating The rating object.
                 */
                do_action('shuriken_after_rating_stats', $rating);
                ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        /**
         * Filter the complete rating HTML output.
         *
         * @since 1.7.0
         * @param string $html      The rendered HTML.
         * @param object $rating    The rating object.
         * @param string $tag       The HTML tag for the title.
         * @param string $anchor_id The anchor ID.
         */
        return apply_filters('shuriken_rating_html', $html, $rating, $tag, $anchor_id);
    }
}

/**
 * Helper function to get shortcodes instance
 *
 * @return Shuriken_Shortcodes
 */
function shuriken_shortcodes() {
    return Shuriken_Shortcodes::get_instance();
}

