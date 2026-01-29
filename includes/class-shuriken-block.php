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
        
        add_action('init', array($this, 'register_block'));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Block
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
        // Register the shared ratings store (used by all blocks)
        wp_register_script(
            'shuriken-ratings-store',
            plugins_url('blocks/shared/ratings-store.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(
                'wp-data',
                'wp-api-fetch'
            ),
            SHURIKEN_REVIEWS_VERSION,
            true
        );

        // Register the editor script with proper dependencies
        wp_register_script(
            'shuriken-rating-editor',
            plugins_url('blocks/shuriken-rating/index.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-api-fetch',
                'wp-data',
                'shuriken-ratings-store'
            ),
            SHURIKEN_REVIEWS_VERSION,
            true
        );

        // Register the front-end stylesheet and reuse it for the editor preview
        wp_register_style(
            'shuriken-reviews-frontend',
            plugins_url('assets/css/shuriken-reviews.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Register the editor-specific stylesheet
        wp_register_style(
            'shuriken-rating-editor',
            plugins_url('blocks/shuriken-rating/editor.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Register the block with explicit render callback and attach styles
        register_block_type(SHURIKEN_REVIEWS_PLUGIN_DIR . 'blocks/shuriken-rating', array(
            'render_callback' => array($this, 'render_block'),
            'style' => 'shuriken-reviews-frontend',
            'editor_style' => array('shuriken-reviews-frontend', 'shuriken-rating-editor'),
        ));

        // Register the grouped rating editor script
        wp_register_script(
            'shuriken-grouped-rating-editor',
            plugins_url('blocks/shuriken-grouped-rating/index.js', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-i18n',
                'wp-api-fetch',
                'wp-data',
                'shuriken-ratings-store'
            ),
            SHURIKEN_REVIEWS_VERSION,
            true
        );

        // Register the grouped rating editor stylesheet
        wp_register_style(
            'shuriken-grouped-rating-editor',
            plugins_url('blocks/shuriken-grouped-rating/editor.css', SHURIKEN_REVIEWS_PLUGIN_FILE),
            array(),
            SHURIKEN_REVIEWS_VERSION
        );

        // Register the grouped rating block
        register_block_type(SHURIKEN_REVIEWS_PLUGIN_DIR . 'blocks/shuriken-grouped-rating', array(
            'render_callback' => array($this, 'render_grouped_block'),
            'style' => 'shuriken-reviews-frontend',
            'editor_style' => array('shuriken-reviews-frontend', 'shuriken-grouped-rating-editor'),
        ));
    }

    /**
     * Render callback for the Shuriken Rating block
     *
     * Uses the shared render method from Shuriken_Shortcodes to ensure
     * all hooks and filters work consistently across shortcodes and blocks.
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

        $rating = $this->db->get_rating($rating_id);

        if (!$rating) {
            return '';
        }

        // Use the shared render method from Shortcodes class
        // This ensures all hooks/filters work for both shortcodes and blocks
        $html = shuriken_shortcodes()->render_rating_html($rating, $title_tag, $anchor_tag);

        // Wrap with block wrapper attributes for proper Gutenberg integration
        // We need to extract the inner content and re-wrap it with block attributes
        return $this->wrap_with_block_attributes($html, $rating, $anchor_tag);
    }

    /**
     * Render callback for the Shuriken Grouped Rating block
     *
     * @param array    $attributes Block attributes.
     * @param string   $content    Block content.
     * @param WP_Block $block      Block instance.
     * @return string Rendered block output.
     * @since 1.8.0
     */
    public function render_grouped_block($attributes, $content, $block) {
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

        $rating = $this->db->get_rating($rating_id);

        if (!$rating) {
            return '';
        }

        // Get child ratings
        $child_ratings = $this->db->get_sub_ratings($rating_id);

        // Build CSS variables from custom style attributes
        $style_vars = array();
        
        // Color variables
        if (!empty($attributes['parentTitleColor'])) {
            $style_vars[] = '--shuriken-parent-title-color: ' . esc_attr($attributes['parentTitleColor']);
        }
        if (!empty($attributes['childTitleColor'])) {
            $style_vars[] = '--shuriken-child-title-color: ' . esc_attr($attributes['childTitleColor']);
        }
        if (!empty($attributes['textColor'])) {
            $style_vars[] = '--shuriken-text-color: ' . esc_attr($attributes['textColor']);
        }
        if (!empty($attributes['parentBackgroundColor'])) {
            $style_vars[] = '--shuriken-parent-bg: ' . esc_attr($attributes['parentBackgroundColor']);
        }
        if (!empty($attributes['childBackgroundColor'])) {
            $style_vars[] = '--shuriken-child-bg: ' . esc_attr($attributes['childBackgroundColor']);
        }
        if (!empty($attributes['starActiveColor'])) {
            $style_vars[] = '--shuriken-star-active: ' . esc_attr($attributes['starActiveColor']);
        }
        if (!empty($attributes['starInactiveColor'])) {
            $style_vars[] = '--shuriken-star-inactive: ' . esc_attr($attributes['starInactiveColor']);
        }
        
        // Border variables - Parent
        if (!empty($attributes['parentBorderColor'])) {
            $style_vars[] = '--shuriken-parent-border-color: ' . esc_attr($attributes['parentBorderColor']);
        }
        if (!empty($attributes['parentBorderWidth'])) {
            $style_vars[] = '--shuriken-parent-border-width: ' . esc_attr($attributes['parentBorderWidth']);
        }
        if (!empty($attributes['parentBorderStyle'])) {
            $style_vars[] = '--shuriken-parent-border-style: ' . esc_attr($attributes['parentBorderStyle']);
        }
        if (!empty($attributes['parentBorderRadius'])) {
            $style_vars[] = '--shuriken-parent-border-radius: ' . esc_attr($attributes['parentBorderRadius']);
        }
        
        // Border variables - Child
        if (!empty($attributes['childBorderColor'])) {
            $style_vars[] = '--shuriken-child-border-color: ' . esc_attr($attributes['childBorderColor']);
        }
        if (!empty($attributes['childBorderWidth'])) {
            $style_vars[] = '--shuriken-child-border-width: ' . esc_attr($attributes['childBorderWidth']);
        }
        if (!empty($attributes['childBorderStyle'])) {
            $style_vars[] = '--shuriken-child-border-style: ' . esc_attr($attributes['childBorderStyle']);
        }
        if (!empty($attributes['childBorderRadius'])) {
            $style_vars[] = '--shuriken-child-border-radius: ' . esc_attr($attributes['childBorderRadius']);
        }
        
        // Typography variables
        if (!empty($attributes['parentTitleFontSize'])) {
            $style_vars[] = '--shuriken-parent-title-font-size: ' . esc_attr($attributes['parentTitleFontSize']);
        }
        if (!empty($attributes['parentTitleFontWeight'])) {
            $style_vars[] = '--shuriken-parent-title-font-weight: ' . esc_attr($attributes['parentTitleFontWeight']);
        }
        if (!empty($attributes['childTitleFontSize'])) {
            $style_vars[] = '--shuriken-child-title-font-size: ' . esc_attr($attributes['childTitleFontSize']);
        }
        if (!empty($attributes['childTitleFontWeight'])) {
            $style_vars[] = '--shuriken-child-title-font-weight: ' . esc_attr($attributes['childTitleFontWeight']);
        }
        if (!empty($attributes['textFontSize'])) {
            $style_vars[] = '--shuriken-text-font-size: ' . esc_attr($attributes['textFontSize']);
        }
        
        // Spacing variables
        if (!empty($attributes['parentPadding'])) {
            $style_vars[] = '--shuriken-parent-padding: ' . esc_attr($attributes['parentPadding']);
        }
        if (!empty($attributes['childPadding'])) {
            $style_vars[] = '--shuriken-child-padding: ' . esc_attr($attributes['childPadding']);
        }
        if (!empty($attributes['gapBetweenRatings'])) {
            $style_vars[] = '--shuriken-gap: ' . esc_attr($attributes['gapBetweenRatings']);
        }

        // Render parent rating
        $html = '<div class="shuriken-rating-group">';
        // Add parent-rating class to the wrapper of the parent rating
        $parent_html = shuriken_shortcodes()->render_rating_html($rating, $title_tag, $anchor_tag);
        $html .= preg_replace('/class="shuriken-rating/', 'class="shuriken-rating parent-rating', $parent_html, 1);

        // Render child ratings
        if (!empty($child_ratings)) {
            $html .= '<div class="shuriken-child-ratings">';
            foreach ($child_ratings as $child) {
                $child_html = shuriken_shortcodes()->render_rating_html($child, 'h4');
                // Add child-rating class
                $html .= preg_replace('/class="shuriken-rating/', 'class="shuriken-rating child-rating', $child_html, 1);
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Prepare wrapper attributes with CSS variables
        $wrapper_attrs = array(
            'class' => 'shuriken-rating-group',
            'data-parent-id' => esc_attr($rating->source_id),
        );

        // Add style attribute with CSS variables if any exist
        if (!empty($style_vars)) {
            $wrapper_attrs['style'] = implode('; ', $style_vars) . ';';
        }

        // Wrap with block wrapper attributes (this applies block supports like colors, spacing, etc.)
        $wrapper_attributes = get_block_wrapper_attributes($wrapper_attrs);

        if ($anchor_tag) {
            $wrapper_attributes = str_replace('class="', 'id="' . esc_attr($anchor_tag) . '" class="', $wrapper_attributes);
        }

        // Replace the opening div with our block-wrapped version
        $html = preg_replace(
            '/^<div\s+class="shuriken-rating-group"/',
            '<div ' . $wrapper_attributes,
            $html,
            1
        );

        return $html;
    }

    /**
     * Wrap rating HTML with Gutenberg block wrapper attributes
     *
     * @param string $html       The rating HTML from shared render method.
     * @param object $rating     The rating object.
     * @param string $anchor_tag Optional anchor ID.
     * @return string HTML wrapped with block attributes.
     */
    private function wrap_with_block_attributes($html, $rating, $anchor_tag = '') {
        // The shared render method already outputs a complete div with classes
        // We need to merge the block wrapper attributes with the existing ones
        
        // Extract the class from the rendered HTML
        if (preg_match('/class="([^"]*)"/', $html, $matches)) {
            $existing_classes = $matches[1];
            
            // Get block wrapper attributes with merged classes
            $wrapper_attributes = get_block_wrapper_attributes(array(
                'class' => $existing_classes,
                'data-id' => esc_attr($rating->source_id),
            ));

            // Add anchor ID if present
            if ($anchor_tag) {
                $wrapper_attributes = str_replace('class="', 'id="' . esc_attr($anchor_tag) . '" class="', $wrapper_attributes);
            }

            // Replace the opening div with our block-wrapped version
            // Match the first div tag with class attribute
            $html = preg_replace(
                '/^<div\s+class="[^"]*"\s+data-id="[^"]*"(\s+id="[^"]*")?/',
                '<div ' . $wrapper_attributes,
                $html,
                1
            );
        }

        return $html;
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

