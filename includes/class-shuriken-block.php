<?php
/**
 * Shuriken Reviews Block Class
 *
 * Handles Gutenberg block registration and rendering.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Block
 *
 * Registers and renders the Gutenberg block.
 *
 * @since 1.7.0
 */
class Shuriken_Block {

    /**
     * @var Shuriken_Block Singleton instance
     */
    private static $instance = null;

    /**
     * Allowed HTML tags for rating title
     */
    const ALLOWED_TITLE_TAGS = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_block'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Block
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the block
     *
     * @return void
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Register the Shuriken Rating FSE block
     *
     * @return void
     * @since 1.1.9
     */
    public function register_block() {
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

        // Register the front-end stylesheet and reuse it for the editor preview
        wp_register_style(
            'shuriken-reviews-frontend',
            SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/css/shuriken-reviews.css',
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Register the editor-specific stylesheet
        wp_register_style(
            'shuriken-rating-editor',
            SHURIKEN_REVIEWS_PLUGIN_URL . 'blocks/shuriken-rating/editor.css',
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Register the block with explicit render callback and attach styles
        register_block_type(SHURIKEN_REVIEWS_PLUGIN_DIR . 'blocks/shuriken-rating', array(
            'render_callback' => array($this, 'render_block'),
            'style' => 'shuriken-reviews-frontend',
            'editor_style' => array('shuriken-reviews-frontend', 'shuriken-rating-editor'),
        ));
    }

    /**
     * Render callback for the Shuriken Rating block
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block content.
     * @param WP_Block $block      Block instance.
     * @return string Rendered block output.
     * @since 1.1.9
     */
    public function render_block($attributes, $content, $block) {
        // Ensure attributes is an array
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $rating_id = isset($attributes['ratingId']) ? absint($attributes['ratingId']) : 0;
        $title_tag = isset($attributes['titleTag']) ? sanitize_key($attributes['titleTag']) : 'h2';
        $anchor_tag = isset($attributes['anchorTag']) ? sanitize_html_class($attributes['anchorTag']) : '';

        // Validate title tag
        if (!in_array($title_tag, self::ALLOWED_TITLE_TAGS, true)) {
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
}

/**
 * Helper function to get block instance
 *
 * @return Shuriken_Block
 */
function shuriken_block() {
    return Shuriken_Block::get_instance();
}

