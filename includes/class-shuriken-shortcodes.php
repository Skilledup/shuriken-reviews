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
    private static ?self $instance = null;

    /**
     * Allowed HTML tags for rating title
     */
    const ALLOWED_TITLE_TAGS = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p', 'span');

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface $db Database instance.
     */
    public function __construct(
        private readonly Shuriken_Database_Interface $db,
    ) {
        
        add_shortcode('shuriken_rating', $this->render_rating(...));
        add_shortcode('shuriken_grouped_rating', $this->render_grouped_rating(...));
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Shortcodes
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
     * Initialize shortcodes
     *
     * @return void
     */
    public static function init(): void {
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
     *     @type int    $id           Required. The ID of the rating to display.
     *     @type string $tag          Optional. HTML tag to wrap the rating title. Default 'h2'.
     *     @type string $anchor_tag   Optional. ID attribute for anchor linking. Default empty.
     *     @type string $style        Optional. Preset style name (classic, card, minimal, dark, outlined). Default empty.
     *     @type string $accent_color Optional. Hex color for accent elements. Default empty.
     *     @type string $star_color   Optional. Hex color for active stars. Default empty.
     *     @type int    $context_id   Optional. Post/entity ID for per-context voting. Default 0 (global).
     *     @type string $context_type Optional. Context type for per-context voting (e.g. 'post', 'page', 'product'). Default empty.
     * }
     * @return string HTML content for the rating interface.
     * @since 1.1.0
     * 
     * @example [shuriken_rating id="1" tag="h2" anchor_tag="rating-1"]
     * @example [shuriken_rating id="1" style="card" accent_color="#e74c3c" star_color="#f39c12"]
     * @example [shuriken_rating id="1" context_id="42" context_type="post"]
     */
    public function render_rating(array|string $atts): string {
        // Validate and sanitize attributes with proper defaults and parsing
        $atts = shortcode_atts(array(
            'id'           => 0,
            'tag'          => 'h2',
            'anchor_tag'   => '',
            'style'        => '',
            'accent_color' => '',
            'star_color'   => '',
            'button_color' => '',
            'hide_title'   => '',
            'context_id'   => 0,
            'context_type' => '',
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

        // Resolve optional contextual voting parameters
        $context_id   = absint($atts['context_id']);
        $context_type = sanitize_key($atts['context_type']);
        if ($context_id && $context_type) {
            $allowed_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
            if (!in_array($context_type, $allowed_types, true)) {
                $context_id   = 0;
                $context_type = '';
            }
        } else {
            $context_id   = 0;
            $context_type = '';
        }

        // Get rating data
        $rating = $this->db->get_rating($id);

        if (!$rating) {
            return '';
        }

        // Resolve hide_title flag
        $hide_title = filter_var($atts['hide_title'], FILTER_VALIDATE_BOOLEAN);

        $html = $this->render_rating_html(
            $rating,
            $tag,
            $anchor_id,
            $context_id   ?: null,
            $context_type ?: null,
            $hide_title
        );

        return $this->wrap_with_style_attributes($html, $atts);
    }

    /**
     * Render rating HTML
     *
     * @param object      $rating       Rating object from database.
     * @param string      $tag          HTML tag for title.
     * @param string      $anchor_id    Optional anchor ID.
     * @param int|null    $context_id   Optional post/entity ID for contextual stats.
     * @param string|null $context_type Optional context type ('post', 'product', etc.).
     * @param bool        $hide_title   Whether to hide the title and description.
     * @return string Rendered HTML.
     */
    public function render_rating_html(object $rating, string $tag = 'h2', string $anchor_id = '', ?int $context_id = null, ?string $context_type = null, bool $hide_title = false): string {
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
         * @param int    $max_stars The maximum number of stars. Default from rating scale.
         * @param object $rating    The rating object.
         */
        $rating_type = isset($rating->rating_type) ? $rating->rating_type : 'stars';
        $scale = isset($rating->scale) ? intval($rating->scale) : Shuriken_Database::RATING_SCALE_DEFAULT;

        /**
         * Filter the resolved rating type before rendering.
         *
         * Allows overriding which rendering mode is used for a rating.
         * For example, force all ratings to render as stars, or switch
         * a specific rating to a different type.
         *
         * @since 1.14.0
         * @param string $rating_type The rating type: 'stars', 'like_dislike', 'numeric', or 'approval'.
         * @param object $rating      The rating object.
         */
        $rating_type = apply_filters('shuriken_rating_type', $rating_type, $rating);

        $type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;

        // Binary types have a fixed scale — skip scale filtering
        if ($type_enum->isBinary()) {
            $max_stars = 1;
        } else {
            // Legacy filter (stars-specific, kept for backward compatibility)
            $max_stars = apply_filters('shuriken_rating_max_stars', $scale, $rating);

            /**
             * Filter the rating scale for any rating type.
             *
             * This is the type-aware replacement for `shuriken_rating_max_stars`.
             * For stars, it controls the number of stars (2-10).
             * For numeric, it controls the slider maximum (2-100).
             * Not applied to binary types (like_dislike, approval) which always use scale 1.
             *
             * @since 1.14.0
             * @param int    $scale       The rating scale. Default from rating settings.
             * @param object $rating      The rating object.
             * @param string $rating_type The rating type: 'stars' or 'numeric'.
             */
            $max_stars = apply_filters('shuriken_rating_scale', $max_stars, $rating, $rating_type);

            // Ensure scale is at least 1
            $max_stars = max(1, intval($max_stars));
        }

        /**
         * Filter the star character/symbol used for star-type ratings.
         *
         * Only applies to the 'stars' rating type.
         *
         * @since 1.7.0
         * @param string $star_symbol The star symbol. Default SVG star icon.
         * @param object $rating      The rating object.
         */
        $icons = Shuriken_Icons::rating_symbols();
        $star_symbol = apply_filters('shuriken_rating_star_symbol', $icons['star_filled'], $rating);

        // If contextual, overlay per-context stats onto the rating object
        $context_attrs = '';
        if ($context_id !== null && $context_type !== null) {
            $ctx_stats = shuriken_db()->get_contextual_stats((int) $rating->source_id, $context_id, $context_type, $max_stars);

            /**
             * Filter the per-context stats before they are applied to the rating for rendering.
             *
             * @since 1.6.0
             * @param object      $ctx_stats    Stats object with `total_votes`, `total_rating`, `average`, and `display_average` properties.
             * @param object      $rating       The rating object (stats not yet overlaid).
             * @param int         $context_id   The context post/entity ID.
             * @param string      $context_type The context type, e.g. 'post', 'product'.
             */
            $ctx_stats = apply_filters('shuriken_contextual_stats', $ctx_stats, $rating, $context_id, $context_type);

            $rating->total_votes = $ctx_stats->total_votes;
            $rating->total_rating = $ctx_stats->total_rating;
            $rating->average = $ctx_stats->average;
            $rating->display_average = $ctx_stats->display_average;
            $context_attrs = ' data-context-id="' . esc_attr($context_id) . '" data-context-type="' . esc_attr($context_type) . '"';
        }
        
        // Use the pre-computed display_average from the data layer
        $scaled_average = round($rating->display_average, 1);
        
        // Start output buffering
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_classes); ?>" data-id="<?php echo esc_attr($rating->source_id); ?>" data-max-stars="<?php echo esc_attr($max_stars); ?>" data-rating-type="<?php echo esc_attr($rating_type); ?>"<?php echo $context_attrs; ?> <?php echo $anchor_id ? 'id="' . esc_attr($anchor_id) . '"' : ''; ?>>
            <div class="shuriken-rating-wrapper">
                <?php if (!$hide_title): ?>
                <<?php echo tag_escape($tag); ?> class="rating-title">
                    <?php echo esc_html($rating->name); ?>
                </<?php echo tag_escape($tag); ?>>
                <?php if (!empty($rating->label_description)): ?>
                <p class="rating-description"><?php echo esc_html($rating->label_description); ?></p>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($rating_type === 'like_dislike'):
                    /**
                     * Filter the like/dislike symbols.
                     *
                     * @since 1.14.0
                     * @param array  $symbols Array with 'like' and 'dislike' keys. Default: SVG thumbs-up/thumbs-down icons.
                     * @param object $rating  The rating object.
                     */
                    $ld_icons = Shuriken_Icons::rating_symbols();
                    $ld_symbols = apply_filters('shuriken_like_dislike_symbols', array('like' => $ld_icons['thumbs_up'], 'dislike' => $ld_icons['thumbs_down']), $rating);
                ?>
                <div class="shuriken-like-dislike<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Like or Dislike', 'shuriken-reviews'); ?>">
                    <?php if (!$is_display_only): ?>
                    <button type="button" class="shuriken-btn shuriken-like-btn" data-value="1" aria-label="<?php esc_attr_e('Like', 'shuriken-reviews'); ?>">
                        <span class="shuriken-thumb"><?php echo $ld_symbols['like']; ?></span>
                        <span class="shuriken-count shuriken-like-count"><?php echo esc_html($rating->total_rating); ?></span>
                    </button>
                    <button type="button" class="shuriken-btn shuriken-dislike-btn" data-value="0" aria-label="<?php esc_attr_e('Dislike', 'shuriken-reviews'); ?>">
                        <span class="shuriken-thumb"><?php echo $ld_symbols['dislike']; ?></span>
                        <span class="shuriken-count shuriken-dislike-count"><?php echo esc_html($rating->total_votes - $rating->total_rating); ?></span>
                    </button>
                    <?php else: ?>
                    <?php
                    $ld_approval_pct = $rating->total_votes > 0 ? round(($rating->total_rating / $rating->total_votes) * 100) : 0;
                    ?>
                    <span class="shuriken-btn shuriken-like-btn" aria-label="<?php esc_attr_e('Approval rate', 'shuriken-reviews'); ?>">
                        <span class="shuriken-thumb"><?php echo $ld_symbols['like']; ?></span>
                        <span class="shuriken-count shuriken-approval-pct"><?php echo esc_html($ld_approval_pct . '%'); ?></span>
                    </span>
                    <span class="shuriken-vote-summary">
                        <?php printf(esc_html__('(%s votes)', 'shuriken-reviews'), esc_html($rating->total_votes)); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!$is_display_only): ?>
                <div class="shuriken-feedback" role="status" aria-live="polite"></div>
                <?php endif; ?>
                
                <?php elseif ($rating_type === 'approval'):
                    /**
                     * Filter the approval/upvote symbol.
                     *
                     * @since 1.14.0
                     * @param string $symbol The upvote symbol. Default SVG chevron-up icon.
                     * @param object $rating The rating object.
                     */
                    $approval_icons = Shuriken_Icons::rating_symbols();
                    $approval_symbol = apply_filters('shuriken_approval_symbol', $approval_icons['chevron_up'], $rating);
                ?>
                <div class="shuriken-approval<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Upvote', 'shuriken-reviews'); ?>">
                    <?php if (!$is_display_only): ?>
                    <button type="button" class="shuriken-btn shuriken-upvote-btn" data-value="1" aria-label="<?php esc_attr_e('Upvote', 'shuriken-reviews'); ?>">
                        <span class="shuriken-thumb"><?php echo $approval_symbol; ?></span>
                        <span class="shuriken-count shuriken-upvote-count"><?php echo esc_html($rating->total_votes); ?></span>
                    </button>
                    <?php else: ?>
                    <span class="shuriken-btn shuriken-upvote-btn" aria-label="<?php esc_attr_e('Upvotes', 'shuriken-reviews'); ?>">
                        <span class="shuriken-thumb"><?php echo $approval_symbol; ?></span>
                        <span class="shuriken-count shuriken-upvote-count"><?php echo esc_html($rating->total_votes); ?></span>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!$is_display_only): ?>
                <div class="shuriken-feedback" role="status" aria-live="polite"></div>
                <?php endif; ?>
                
                <?php elseif ($rating_type === 'numeric'):
                    /**
                     * Filter the numeric rating submit button label.
                     *
                     * @since 1.14.0
                     * @param string $label  The submit button label. Default 'Rate'.
                     * @param object $rating The rating object.
                     */
                    $numeric_submit_label = apply_filters('shuriken_numeric_submit_label', __('Rate', 'shuriken-reviews'), $rating);
                ?>
                <div class="shuriken-numeric<?php echo $is_display_only ? ' display-only-stars' : ''; ?>" role="group" aria-label="<?php esc_attr_e('Numeric rating', 'shuriken-reviews'); ?>">
                    <?php if (!$is_display_only): ?>
                    <input type="range" 
                           class="shuriken-slider" 
                           min="1" 
                           max="<?php echo esc_attr($max_stars); ?>" 
                           value="<?php echo esc_attr(max(1, round($scaled_average))); ?>" 
                           step="1" 
                           aria-label="<?php printf(esc_attr__('Rate from 1 to %d', 'shuriken-reviews'), $max_stars); ?>">
                    <span class="shuriken-slider-value"><?php echo esc_html(max(1, round($scaled_average))); ?></span>
                    <span class="shuriken-slider-max">/ <?php echo esc_html($max_stars); ?></span>
                    <button type="button" class="shuriken-slider-submit" aria-label="<?php esc_attr_e('Submit rating', 'shuriken-reviews'); ?>">
                        <?php echo esc_html($numeric_submit_label); ?>
                    </button>
                    <?php else: ?>
                    <span class="shuriken-numeric-display">
                        <span class="shuriken-numeric-value"><?php echo esc_html(round($scaled_average, 1)); ?></span>
                        <span class="shuriken-slider-max">/ <?php echo esc_html($max_stars); ?></span>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="rating-stats" data-average="<?php echo esc_attr($rating->average); ?>" data-scaled-average="<?php echo esc_attr($scaled_average); ?>">
                    <?php 
                    printf(
                        /* translators: 1: Average rating value, 2: Maximum scale, 3: Total number of votes */
                        esc_html__('Average: %1$s/%2$s (%3$s votes)', 'shuriken-reviews'),
                        esc_html($scaled_average),
                        esc_html($max_stars),
                        esc_html($rating->total_votes)
                    );
                    ?>
                </div>
                
                <?php else: /* stars */ ?>
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
                            <?php echo wp_kses($star_symbol, shuriken_svg_allowed_tags()); ?>
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
                <?php endif; ?>
                
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

    /**
     * Render the [shuriken_grouped_rating] shortcode
     *
     * Displays a parent rating with its sub-ratings in a grouped layout.
     *
     * @param array $atts {
     *     Shortcode attributes.
     *
     *     @type int    $id           Required. The ID of the parent rating to display.
     *     @type string $tag          Optional. HTML tag for the parent title. Default 'h2'.
     *     @type string $anchor_tag   Optional. ID attribute for anchor linking. Default empty.
     *     @type string $style        Optional. Preset style (gradient, minimal, boxed, dark, outlined). Default empty.
     *     @type string $accent_color Optional. Hex color for accent elements. Default empty.
     *     @type string $star_color   Optional. Hex color for active stars. Default empty.
     *     @type string $layout       Optional. Child layout: 'grid' or 'list'. Default 'grid'.
     *     @type int    $context_id   Optional. Post/entity ID for per-context voting. Default 0 (global).
     *     @type string $context_type Optional. Context type for per-context voting (e.g. 'post', 'page', 'product'). Default empty.
     * }
     * @return string HTML content for the grouped rating interface.
     *
     * @example [shuriken_grouped_rating id="1"]
     * @example [shuriken_grouped_rating id="1" style="dark" layout="list" accent_color="#e74c3c"]
     * @example [shuriken_grouped_rating id="1" context_id="42" context_type="post"]
     */
    public function render_grouped_rating(array|string $atts): string {
        $atts = shortcode_atts(array(
            'id'           => 0,
            'tag'          => 'h2',
            'anchor_tag'   => '',
            'style'        => '',
            'accent_color' => '',
            'star_color'   => '',
            'button_color' => '',
            'gap'          => '',
            'layout'       => 'grid',
            'hide_title'   => '',
            'context_id'   => 0,
            'context_type' => '',
        ), $atts, 'shuriken_grouped_rating');

        $id = absint($atts['id']);
        if (!$id) {
            return '';
        }

        $tag = in_array(strtolower($atts['tag']), self::ALLOWED_TITLE_TAGS, true) ? $atts['tag'] : 'h2';
        $anchor_id = !empty($atts['anchor_tag']) ? sanitize_html_class($atts['anchor_tag']) : '';
        $layout = ($atts['layout'] === 'list') ? 'list' : 'grid';

        // Resolve optional contextual voting parameters
        $context_id   = absint($atts['context_id']);
        $context_type = sanitize_key($atts['context_type']);
        if ($context_id && $context_type) {
            $allowed_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
            if (!in_array($context_type, $allowed_types, true)) {
                $context_id   = 0;
                $context_type = '';
            }
        } else {
            $context_id   = 0;
            $context_type = '';
        }
        $ctx_id   = $context_id   ?: null;
        $ctx_type = $context_type ?: null;

        $parent = $this->db->get_rating($id);
        if (!$parent) {
            return '';
        }

        $child_ratings = $this->db->get_sub_ratings($id);

        // Build layout class
        $layout_class = ($layout === 'list') ? ' is-layout-list' : '';

        // Build CSS variables
        $style_vars = $this->build_style_vars($atts);
        $style_attr = $style_vars ? ' style="' . esc_attr($style_vars) . '"' : '';

        // Build preset class
        $style_class = $this->get_preset_class($atts['style']);

        // Build wrapper
        $html = '<div class="shuriken-rating-group' . esc_attr($layout_class . $style_class) . '"'
              . ($anchor_id ? ' id="' . esc_attr($anchor_id) . '"' : '')
              . $style_attr . '>';

        // Resolve hide_title flag
        $hide_title = filter_var($atts['hide_title'], FILTER_VALIDATE_BOOLEAN);

        // Render parent
        $parent_html = $this->render_rating_html($parent, $tag, '', $ctx_id, $ctx_type, $hide_title);
        $parent_html = preg_replace('/class="shuriken-rating/', 'class="shuriken-rating parent-rating', $parent_html, 1);
        $html .= $parent_html;

        // Render children
        if (!empty($child_ratings)) {
            $html .= '<div class="shuriken-child-ratings">';
            foreach ($child_ratings as $child) {
                $child_html = $this->render_rating_html($child, 'h4', '', $ctx_id, $ctx_type, $hide_title);
                $child_html = preg_replace('/class="shuriken-rating/', 'class="shuriken-rating child-rating', $child_html, 1);
                $html .= $child_html;
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Wrap shortcode HTML with preset style class and CSS custom properties.
     *
     * @param string $html The rendered rating HTML.
     * @param array  $atts Shortcode attributes containing style, accent_color, star_color.
     * @return string Modified HTML with style attributes applied.
     */
    private function wrap_with_style_attributes(string $html, array $atts): string {
        $style_class = $this->get_preset_class(!empty($atts['style']) ? $atts['style'] : '');
        $style_vars = $this->build_style_vars($atts);

        if (!$style_class && !$style_vars) {
            return $html;
        }

        // Add preset class to the outer div
        if ($style_class) {
            $html = preg_replace(
                '/class="shuriken-rating/',
                'class="shuriken-rating' . esc_attr($style_class),
                $html,
                1
            );
        }

        // Add inline CSS variables
        if ($style_vars) {
            $html = preg_replace(
                '/(<div\s+class="shuriken-rating[^"]*")/',
                '$1 style="' . esc_attr($style_vars) . '"',
                $html,
                1
            );
        }

        return $html;
    }

    /**
     * Build CSS custom property string from shortcode color attributes.
     *
     * @param array $atts Shortcode attributes.
     * @return string CSS custom properties string, e.g. "--shuriken-user-accent: #e74c3c; --shuriken-user-star-color: #f39c12;"
     */
    private function build_style_vars(array $atts): string {
        $vars = array();

        if (!empty($atts['accent_color']) && sanitize_hex_color($atts['accent_color'])) {
            $vars[] = '--shuriken-user-accent: ' . sanitize_hex_color($atts['accent_color']);
        }
        if (!empty($atts['star_color']) && sanitize_hex_color($atts['star_color'])) {
            $vars[] = '--shuriken-user-star-color: ' . sanitize_hex_color($atts['star_color']);
        }
        if (!empty($atts['button_color']) && sanitize_hex_color($atts['button_color'])) {
            $vars[] = '--shuriken-button-color: ' . sanitize_hex_color($atts['button_color']);
        }
        if (!empty($atts['gap'])) {
            $vars[] = '--shuriken-gap: ' . esc_attr($atts['gap']);
        }

        return $vars ? implode('; ', $vars) . ';' : '';
    }

    /**
     * Get the is-style-* class string for a preset name.
     *
     * @param string $style Preset name.
     * @return string CSS class string with leading space, or empty string.
     */
    private function get_preset_class(string $style): string {
        if (empty($style)) {
            return '';
        }
        $style = sanitize_html_class($style);
        return $style ? ' is-style-' . $style : '';
    }
}

/**
 * Helper function to get shortcodes instance
 *
 * @return Shuriken_Shortcodes
 */
function shuriken_shortcodes(): Shuriken_Shortcodes {
    return Shuriken_Shortcodes::get_instance();
}

