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

        // Build CSS variables from colour attributes
        $style_vars = array();
        if (!empty($attributes['accentColor'])) {
            $style_vars[] = '--shuriken-user-accent: ' . esc_attr($attributes['accentColor']);
        }
        if (!empty($attributes['starColor'])) {
            $style_vars[] = '--shuriken-user-star-color: ' . esc_attr($attributes['starColor']);
        }

        // Use the shared render method from Shortcodes class
        $html = shuriken_shortcodes()->render_rating_html($rating, $title_tag, $anchor_tag);

        // Wrap with block wrapper attributes for proper Gutenberg integration
        return $this->wrap_with_block_attributes($html, $rating, $anchor_tag, $style_vars);
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
        $child_layout = isset($attributes['childLayout']) ? sanitize_key($attributes['childLayout']) : 'grid';

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

        // Build CSS variables from simplified attributes (accent + star color)
        $style_vars = array();

        if (!empty($attributes['accentColor'])) {
            $style_vars[] = '--shuriken-user-accent: ' . esc_attr($attributes['accentColor']);
        }
        if (!empty($attributes['starColor'])) {
            $style_vars[] = '--shuriken-user-star-color: ' . esc_attr($attributes['starColor']);
        }

        // Layout class
        $layout_class = ($child_layout === 'list') ? ' is-layout-list' : '';

        // Render parent rating
        $html = '<div class="shuriken-rating-group' . esc_attr($layout_class) . '">';
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
            'class' => 'shuriken-rating-group' . $layout_class,
            'data-parent-id' => esc_attr($rating->source_id),
        );

        // Add style attribute with CSS variables if any exist
        if (!empty($style_vars)) {
            $wrapper_attrs['style'] = implode('; ', $style_vars) . ';';
        }

        // Wrap with block wrapper attributes (this applies block supports like spacing, block styles, etc.)
        $wrapper_attributes = get_block_wrapper_attributes($wrapper_attrs);

        if ($anchor_tag) {
            $wrapper_attributes = str_replace('class="', 'id="' . esc_attr($anchor_tag) . '" class="', $wrapper_attributes);
        }

        // Replace the opening div with our block-wrapped version
        $html = preg_replace(
            '/^<div\s+class="shuriken-rating-group[^"]*"/',
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
     * @param array  $style_vars Optional CSS variable declarations (e.g. '--shuriken-user-accent: #ff0000').
     * @return string HTML wrapped with block attributes.
     */
    private function wrap_with_block_attributes($html, $rating, $anchor_tag = '', $style_vars = array()) {
        // The shared render method already outputs a complete div with classes.
        // We need to merge the block wrapper attributes with the existing ones.

        // Trim leading/trailing whitespace produced by ob_start() templates
        // so the ^ anchor in the regex below matches the opening <div>.
        $html = trim($html);

        // Extract the class from the rendered HTML
        if (preg_match('/class="([^"]*)"/', $html, $matches)) {
            $existing_classes = $matches[1];
            
            // Build wrapper attributes array
            $wrapper_args = array(
                'class' => $existing_classes,
                'data-id' => esc_attr($rating->source_id),
            );

            // Add inline style with CSS variables if any exist
            if (!empty($style_vars)) {
                $wrapper_args['style'] = implode('; ', $style_vars) . ';';
            }

            // Get block wrapper attributes with merged classes
            $wrapper_attributes = get_block_wrapper_attributes($wrapper_args);

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

