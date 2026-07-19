<?php
/**
 * Comment form deferred ratings
 *
 * Registers ratings for injection into the WordPress comment form and creates
 * votes scoped to the new comment after it is inserted.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Comment_Form_Ratings
 *
 * Request-scoped registry for comment-form rating widgets. A sibling block with
 * `commentFormContext` registers here; the widget is output via
 * `comment_form_after_fields` and the vote is created on `comment_post`.
 *
 * @since 1.15.6
 */
class Shuriken_Comment_Form_Ratings {

    /**
     * @var Shuriken_Comment_Form_Ratings|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Registered form ratings for this request.
     *
     * @var array<int, array{rating_id: int, attributes: array}>
     */
    private array $pending = array();

    /**
     * Constructor
     *
     * @param Shuriken_Rating_Repository $ratings Rating repository.
     * @param Shuriken_Vote_Repository   $votes   Vote repository.
     */
    public function __construct(
        private readonly Shuriken_Rating_Repository $ratings,
        private readonly Shuriken_Vote_Repository $votes,
    ) {
        // Always listen so we can pre-register from the Comments parent block
        // before core/post-comments-form renders (sibling order independent).
        add_filter('pre_render_block', $this->pre_register_from_comments_block(...), 5, 2);
        add_action('comment_form_after_fields', $this->render_form_fields(...));
        add_action('comment_form_logged_in_after', $this->render_form_fields(...));
        add_action('comment_post', $this->handle_comment_post(...), 10, 3);
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self(shuriken_ratings_repo(), shuriken_votes_repo());
        }
        return self::$instance;
    }

    /**
     * Initialize (ensures hooks are registered).
     *
     * @return void
     */
    public static function init(): void {
        self::get_instance();
    }

    /**
     * Queue a rating for comment-form injection.
     *
     * @param int   $rating_id  Rating ID.
     * @param array $attributes Block attributes (optional styling hints).
     * @return void
     */
    public function register(int $rating_id, array $attributes = array()): void {
        if ($rating_id <= 0) {
            return;
        }

        $this->pending[$rating_id] = array(
            'rating_id'  => $rating_id,
            'attributes' => $attributes,
        );
    }

    /**
     * Before a Comments block renders, pre-register any sibling rating blocks
     * with commentFormContext so form injection works regardless of order.
     *
     * @param string|null $pre_render   Pre-render content.
     * @param array       $parsed_block Parsed block.
     * @return string|null
     */
    public function pre_register_from_comments_block(?string $pre_render, array $parsed_block): ?string {
        $name = $parsed_block['blockName'] ?? '';
        if ($name !== 'core/comments' && $name !== 'core/group') {
            return $pre_render;
        }

        $this->register_comment_form_blocks_recursive($parsed_block['innerBlocks'] ?? array());
        return $pre_render;
    }

    /**
     * Recursively find and register commentFormContext rating blocks.
     *
     * @param array $blocks Parsed inner blocks.
     * @return void
     */
    private function register_comment_form_blocks_recursive(array $blocks): void {
        foreach ($blocks as $block) {
            $name = $block['blockName'] ?? '';
            if ($name === 'shuriken-reviews/rating') {
                $attrs = $block['attrs'] ?? array();
                if (!empty($attrs['commentFormContext'])) {
                    $rating_id = isset($attrs['ratingId']) ? absint($attrs['ratingId']) : 0;
                    $rating_id = shuriken_resolve_comment_rating_id($rating_id, null, get_post());
                    if ($rating_id > 0) {
                        $this->register($rating_id, $attrs);
                    }
                }
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $this->register_comment_form_blocks_recursive($block['innerBlocks']);
            }
        }
    }

    /**
     * Output rating widgets and hidden fields inside the comment form.
     *
     * @return void
     */
    public function render_form_fields(): void {
        if (empty($this->pending)) {
            return;
        }

        // Avoid duplicate output if both logged-in and guest hooks fire.
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        shuriken_enqueue_frontend_assets();

        echo '<div class="shuriken-comment-form-ratings">';

        foreach ($this->pending as $entry) {
            $rating_id = (int) $entry['rating_id'];
            $rating    = $this->ratings->get_rating($rating_id);
            if (!$rating || !empty($rating->display_only)) {
                continue;
            }

            $hide_title = !empty($entry['attributes']['hideTitle']);
            $html = shuriken_shortcodes()->render_rating_html(
                $rating,
                'h3',
                '',
                null,
                null,
                $hide_title
            );

            // Mark as form-deferred: frontend selects locally, does not AJAX-submit.
            $html = preg_replace(
                '/class="shuriken-rating/',
                'class="shuriken-rating shuriken-comment-form-rating',
                $html,
                1
            );
            $html = preg_replace(
                '/(<div[^>]*class="shuriken-rating[^"]*")/',
                '$1 data-comment-form="1"',
                $html,
                1
            );

            echo '<div class="shuriken-comment-form-rating-wrap" data-rating-id="' . esc_attr((string) $rating_id) . '">';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_rating_html is escaped.
            echo $html;
            echo '<input type="hidden" name="shuriken_form_rating_id[]" value="' . esc_attr((string) $rating_id) . '" class="shuriken-form-rating-id" />';
            echo '<input type="hidden" name="shuriken_form_rating_value[' . esc_attr((string) $rating_id) . ']" value="" class="shuriken-form-rating-value" data-rating-id="' . esc_attr((string) $rating_id) . '" />';
            echo '</div>';
        }

        wp_nonce_field('shuriken_comment_form_rating', 'shuriken_form_rating_nonce');
        echo '</div>';
    }

    /**
     * Create deferred votes after a comment is inserted.
     *
     * @param int        $comment_id       New comment ID.
     * @param int|string $comment_approved 1 if approved, 0 if pending, 'spam', etc.
     * @param array      $commentdata      Comment data passed to wp_insert_comment.
     * @return void
     */
    public function handle_comment_post(int $comment_id, $comment_approved = 1, array $commentdata = array()): void {
        if ($comment_id <= 0) {
            return;
        }

        // Skip spam / trash comments
        if ($comment_approved === 'spam' || $comment_approved === 'trash') {
            return;
        }

        if (empty($_POST['shuriken_form_rating_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['shuriken_form_rating_nonce'])), 'shuriken_comment_form_rating')
        ) {
            return;
        }

        $rating_ids = isset($_POST['shuriken_form_rating_id'])
            ? (array) wp_unslash($_POST['shuriken_form_rating_id'])
            : array();
        $values = isset($_POST['shuriken_form_rating_value'])
            ? (array) wp_unslash($_POST['shuriken_form_rating_value'])
            : array();

        if (empty($rating_ids)) {
            return;
        }

        $allow_guest_voting = get_option('shuriken_allow_guest_voting', '0') === '1';
        $allow_guest_voting = apply_filters('shuriken_allow_guest_voting', $allow_guest_voting);

        if (!is_user_logged_in() && !$allow_guest_voting) {
            return;
        }

        $user_id = get_current_user_id();
        $user_ip = is_user_logged_in() ? null : $this->get_user_ip();
        $comment = get_comment($comment_id);
        $post    = $comment ? get_post((int) $comment->comment_post_ID) : null;

        foreach ($rating_ids as $raw_id) {
            $rating_id = absint($raw_id);
            if (!$rating_id) {
                continue;
            }

            // Allow filter override when ID was 0 at registration time (already resolved),
            // and still apply for extensibility when callers pass through.
            $rating_id = shuriken_resolve_comment_rating_id($rating_id, $comment instanceof \WP_Comment ? $comment : null, $post);
            if (!$rating_id) {
                continue;
            }

            $raw_value = $values[$rating_id] ?? $values[(string) $rating_id] ?? '';
            if ($raw_value === '' || $raw_value === null) {
                continue;
            }

            $rating_value = floatval($raw_value);
            if ($rating_value <= 0) {
                continue;
            }

            try {
                $this->create_comment_vote($rating_id, $rating_value, $comment_id, $user_id, $user_ip);
            } catch (Throwable $e) {
                // Fail silently on the comment path — the comment itself succeeded.
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Shuriken comment form vote failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Validate and create a vote scoped to a comment.
     *
     * @param int         $rating_id    Rating ID.
     * @param float       $rating_value Display-scale value.
     * @param int         $comment_id   Comment context ID.
     * @param int         $user_id      User ID (0 for guests).
     * @param string|null $user_ip      Guest IP or null.
     * @return void
     * @throws Shuriken_Exception_Interface On validation / permission / rate-limit failure.
     */
    private function create_comment_vote(
        int $rating_id,
        float $rating_value,
        int $comment_id,
        int $user_id,
        ?string $user_ip
    ): void {
        $context_id   = $comment_id;
        $context_type = 'comment';

        if (!get_comment($context_id)) {
            throw Shuriken_Validation_Exception::invalid_value(
                'context_id',
                $context_id,
                __('an existing comment ID', 'shuriken-reviews')
            );
        }

        $rating = $this->ratings->get_rating($rating_id);
        if (!$rating) {
            throw Shuriken_Not_Found_Exception::rating($rating_id);
        }

        if (!empty($rating->display_only)) {
            throw Shuriken_Logic_Exception::display_only_rating();
        }

        $rating_type = isset($rating->rating_type) ? $rating->rating_type : 'stars';
        $rating_type = apply_filters('shuriken_rating_type', $rating_type, $rating);
        $type_enum   = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;

        if ($type_enum->isBinary()) {
            $max_stars = 1;
        } else {
            $max_stars = apply_filters('shuriken_rating_max_stars', intval($rating->scale), $rating);
            $max_stars = apply_filters('shuriken_rating_scale', $max_stars, $rating, $rating_type);
            $max_stars = max(1, intval($max_stars));
        }

        $normalized_value = Shuriken_Database::normalize_vote_value($rating_value, $rating_type, $max_stars);

        shuriken_rate_limiter()->can_vote($user_id, $user_ip, $rating_id, $context_id, $context_type);

        $can_vote = apply_filters(
            'shuriken_can_submit_vote',
            true,
            $rating_id,
            $rating_value,
            $user_id,
            $rating,
            $context_id,
            $context_type
        );

        if (is_wp_error($can_vote)) {
            throw Shuriken_Permission_Exception::voting_not_allowed($can_vote->get_error_message());
        }
        if ($can_vote === false) {
            throw Shuriken_Permission_Exception::voting_not_allowed();
        }

        do_action(
            'shuriken_before_submit_vote',
            $rating_id,
            $rating_value,
            $normalized_value,
            $user_id,
            $user_ip,
            $rating,
            $max_stars,
            $context_id,
            $context_type
        );

        $existing = $this->votes->get_user_vote($rating_id, $user_id, $user_ip, $context_id, $context_type);
        if ($existing) {
            $this->votes->update_vote(
                $existing->id,
                $rating_id,
                $existing->rating_value,
                $normalized_value
            );
            do_action(
                'shuriken_vote_updated',
                $existing->id,
                $rating_id,
                $existing->rating_value,
                $rating_value,
                $normalized_value,
                $user_id,
                $rating,
                $max_stars,
                $context_id,
                $context_type,
                $user_ip
            );
        } else {
            $this->votes->create_vote($rating_id, $normalized_value, $user_id, $user_ip, $context_id, $context_type);
            do_action(
                'shuriken_vote_created',
                $rating_id,
                $rating_value,
                $normalized_value,
                $user_id,
                $user_ip,
                $rating,
                $max_stars,
                $context_id,
                $context_type
            );
        }

        if (!empty($rating->parent_id)) {
            $this->ratings->recalculate_parent_rating($rating->parent_id);
        }

        $updated = $this->ratings->get_rating($rating_id);
        do_action(
            'shuriken_after_submit_vote',
            $rating_id,
            $rating_value,
            $normalized_value,
            $user_id,
            (bool) $existing,
            $updated,
            $max_stars,
            $context_id,
            $context_type
        );
    }

    /**
     * Get the current user IP (mirrors AJAX handler).
     *
     * @return string
     */
    private function get_user_ip(): string {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            $ip    = trim($parts[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return $ip;
    }
}

/**
 * Helper to get the comment form ratings instance.
 *
 * @return Shuriken_Comment_Form_Ratings
 */
function shuriken_comment_form_ratings(): Shuriken_Comment_Form_Ratings {
    return Shuriken_Comment_Form_Ratings::get_instance();
}

/**
 * Resolve a comment-context rating ID, applying the filter when unset.
 *
 * @param int              $rating_id Rating ID from block/shortcode (0 = unset).
 * @param \WP_Comment|null $comment   Comment object when available.
 * @param \WP_Post|null    $post      Post the comment belongs to (or current post).
 * @return int Resolved rating ID (0 if still unset).
 */
function shuriken_resolve_comment_rating_id(int $rating_id, ?\WP_Comment $comment = null, $post = null): int {
    if ($rating_id > 0) {
        return $rating_id;
    }

    if ($post !== null && !($post instanceof \WP_Post)) {
        $post = get_post($post);
    }
    if ($post === null) {
        $post = get_post();
    }

    /**
     * Filter the rating ID used for comment-context widgets when none is set.
     *
     * Return a positive rating ID based on post type, category, or other rules.
     *
     * @since 1.15.6
     * @param int              $rating_id Current rating ID (0 when unset).
     * @param \WP_Comment|null $comment   Comment being rated, or null on the form.
     * @param \WP_Post|null    $post      Related post object, or null.
     */
    return absint(apply_filters('shuriken_comment_rating_id', 0, $comment, $post));
}
