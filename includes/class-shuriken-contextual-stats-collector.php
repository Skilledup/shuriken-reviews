<?php
/**
 * Shuriken Contextual Stats Collector
 *
 * Request-scoped batch pre-fetch for contextual rating stats during SSR.
 *
 * @package Shuriken_Reviews
 * @since 1.15.7
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Contextual_Stats_Collector
 *
 * Gathers source IDs per context group during render, batch-fetches vote totals once,
 * then denormalizes display_average per widget scale in get().
 *
 * @since 1.15.7
 */
class Shuriken_Contextual_Stats_Collector {

    /**
     * @var bool Whether the collector is active for this request.
     */
    private bool $active = false;

    /**
     * @var array<string, array<int, true>> Pending source IDs per context group.
     */
    private array $pending = array();

    /**
     * @var array<string, array<int, object>> Flushed raw stats (scale-independent): group_key => [source_id => stats].
     */
    private array $flushed = array();

    /**
     * Constructor
     *
     * @param Shuriken_Rating_Repository $ratings Rating repository.
     */
    public function __construct(
        private readonly Shuriken_Rating_Repository $ratings,
    ) {}

    /**
     * Activate the collector for the current frontend render request.
     *
     * @return void
     */
    public function activate(): void {
        $this->active = true;
    }

    /**
     * Whether the collector should be used instead of per-widget queries.
     *
     * @return bool
     */
    public function is_active(): bool {
        if (!$this->active || is_admin()) {
            return false;
        }

        if (wp_doing_ajax()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        return true;
    }

    /**
     * Register a rating source ID for batch pre-fetch.
     *
     * Idempotent — safe to call multiple times for the same source ID.
     * The $scale argument is accepted for call-site symmetry but is not used as a
     * cache key; display scale is applied in get().
     *
     * @param int    $source_id    Rating source ID (original, not mirror).
     * @param int    $context_id   Context post/entity ID.
     * @param string $context_type Context type.
     * @param int    $scale        Display scale (applied on get(), not at register time).
     * @return void
     */
    public function register(int $source_id, int $context_id, string $context_type, int $scale): void {
        if ($source_id <= 0 || $context_id <= 0 || $context_type === '') {
            return;
        }

        $group_key = $this->group_key($context_id, $context_type);
        $this->pending[$group_key][$source_id] = true;
    }

    /**
     * Get contextual stats for a rating, flushing pending IDs for the group on miss.
     *
     * Raw vote totals are cached per source ID; display_average is always computed
     * from the requested $scale so the same rating can render at different scales.
     *
     * @param int    $source_id    Rating source ID.
     * @param int    $context_id   Context post/entity ID.
     * @param string $context_type Context type.
     * @param int    $scale        Display scale.
     * @return object Stats object with total_votes, total_rating, average, display_average.
     */
    public function get(int $source_id, int $context_id, string $context_type, int $scale): object {
        $group_key = $this->group_key($context_id, $context_type);
        $scale = max(1, $scale);

        if (!isset($this->flushed[$group_key][$source_id])) {
            $this->flush_pending($context_id, $context_type);
        }

        if (isset($this->flushed[$group_key][$source_id])) {
            return $this->with_display_scale(clone $this->flushed[$group_key][$source_id], $scale);
        }

        return $this->empty_stats($scale);
    }

    /**
     * Flush all pending source IDs for a context group via a single batch query.
     *
     * @param int    $context_id   Context post/entity ID.
     * @param string $context_type Context type.
     * @return void
     */
    public function flush_group(int $context_id, string $context_type): void {
        $this->flush_pending($context_id, $context_type);
    }

    /**
     * Scan post content and register all contextual rating widgets before block render.
     *
     * @param string $content Post content.
     * @return string Unmodified content (filter passthrough).
     */
    public function scan_content_for_registrations(string $content): string {
        if ($content === '') {
            return $content;
        }

        $this->activate();

        $this->scan_block_comments($content);
        $this->scan_shortcodes($content);

        return $content;
    }

    /**
     * Register rating IDs from a block instance before its render callback runs.
     *
     * @param string|null $pre_render  Pre-render content (passthrough).
     * @param array       $parsed_block Parsed block array.
     * @param \WP_Block   $block        Block instance.
     * @return string|null
     */
    public function register_from_block(?string $pre_render, array $parsed_block, \WP_Block $block): ?string {
        $block_name = $parsed_block['blockName'] ?? '';
        if ($block_name !== 'shuriken-reviews/rating' && $block_name !== 'shuriken-reviews/grouped-rating') {
            return $pre_render;
        }

        $this->activate();

        $attributes = $parsed_block['attrs'] ?? array();
        $context = $this->resolve_block_context($attributes, $block);
        if ($context === null) {
            return $pre_render;
        }

        [$context_id, $context_type] = $context;

        if ($block_name === 'shuriken-reviews/rating') {
            $rating_id = isset($attributes['ratingId']) ? absint($attributes['ratingId']) : 0;
            if ($rating_id > 0) {
                $this->register_rating_ids(array($rating_id), $context_id, $context_type);
            }
            return $pre_render;
        }

        $this->register_grouped_block_ids($attributes, $context_id, $context_type);

        return $pre_render;
    }

    /**
     * Flush pending stats for a context group that are not yet in the flushed map.
     *
     * @param int    $context_id   Context post/entity ID.
     * @param string $context_type Context type.
     * @return void
     */
    private function flush_pending(int $context_id, string $context_type): void {
        $group_key = $this->group_key($context_id, $context_type);
        $pending = $this->pending[$group_key] ?? array();

        $to_fetch = array();

        foreach ($pending as $source_id => $_registered) {
            if (!isset($this->flushed[$group_key][$source_id])) {
                $to_fetch[] = (int) $source_id;
            }
        }

        if (empty($to_fetch)) {
            return;
        }

        // Vote totals are scale-independent; display_average is applied per-widget in get().
        $batch = $this->ratings->get_contextual_stats_batch($to_fetch, $context_id, $context_type);

        if (!isset($this->flushed[$group_key])) {
            $this->flushed[$group_key] = array();
        }

        foreach ($to_fetch as $source_id) {
            $this->flushed[$group_key][$source_id] = $batch[$source_id] ?? $this->empty_stats(Shuriken_Database::RATING_SCALE_DEFAULT);
        }
    }

    /**
     * Build a stable group key for a context tuple.
     *
     * @param int    $context_id   Context ID.
     * @param string $context_type Context type.
     * @return string
     */
    private function group_key(int $context_id, string $context_type): string {
        return $context_id . ':' . $context_type;
    }

    /**
     * Return a zeroed stats object matching REST controller behavior.
     *
     * @param int $scale Display scale.
     * @return object
     */
    private function empty_stats(int $scale): object {
        $stats = new \stdClass();
        $stats->total_votes = 0;
        $stats->total_rating = 0.0;
        $stats->average = 0;
        $stats->display_average = 0.0;
        return $this->with_display_scale($stats, $scale);
    }

    /**
     * Apply display-scale denormalization to a stats object.
     *
     * @param object $stats Raw stats with normalized average.
     * @param int    $scale Requested display scale.
     * @return object Same object with display_average set.
     */
    private function with_display_scale(object $stats, int $scale): object {
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);
        return $stats;
    }

    /**
     * Parse blocks in stored content and register contextual ratings.
     *
     * @param string $content Post content.
     * @return void
     */
    private function scan_block_comments(string $content): void {
        if (!function_exists('parse_blocks')) {
            return;
        }

        $this->scan_parsed_blocks(parse_blocks($content));
    }

    /**
     * Recursively scan parsed blocks and register contextual rating IDs.
     *
     * @param array $blocks Parsed block list.
     * @return void
     */
    private function scan_parsed_blocks(array $blocks): void {
        foreach ($blocks as $block) {
            $block_name = $block['blockName'] ?? '';

            if ($block_name === 'shuriken-reviews/rating' || $block_name === 'shuriken-reviews/grouped-rating') {
                $attributes = $block['attrs'] ?? array();
                $context = $this->resolve_static_context($attributes);

                if ($context !== null) {
                    [$context_id, $context_type] = $context;

                    if ($block_name === 'shuriken-reviews/rating') {
                        $rating_id = isset($attributes['ratingId']) ? absint($attributes['ratingId']) : 0;
                        if ($rating_id > 0) {
                            $this->register_rating_ids(array($rating_id), $context_id, $context_type);
                        }
                    } else {
                        $this->register_grouped_block_ids($attributes, $context_id, $context_type);
                    }
                }
            }

            if (!empty($block['innerBlocks'])) {
                $this->scan_parsed_blocks($block['innerBlocks']);
            }
        }
    }

    /**
     * Parse shortcodes in stored content and register contextual ratings.
     *
     * @param string $content Post content.
     * @return void
     */
    private function scan_shortcodes(string $content): void {
        if (preg_match_all('/\[shuriken_rating\b([^\]]*)\]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $atts = shortcode_parse_atts($match[1]) ?: array();
                $this->register_from_shortcode_atts($atts, false);
            }
        }

        if (preg_match_all('/\[shuriken_grouped_rating\b([^\]]*)\]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $atts = shortcode_parse_atts($match[1]) ?: array();
                $this->register_from_shortcode_atts($atts, true);
            }
        }
    }

    /**
     * Register ratings from parsed shortcode attributes.
     *
     * @param array $atts     Shortcode attributes.
     * @param bool  $grouped  Whether this is a grouped rating shortcode.
     * @return void
     */
    private function register_from_shortcode_atts(array $atts, bool $grouped): void {
        $rating_id = isset($atts['id']) ? absint($atts['id']) : 0;
        if ($rating_id <= 0) {
            return;
        }

        $context_id = isset($atts['context_id']) ? absint($atts['context_id']) : 0;
        $context_type = isset($atts['context_type']) ? sanitize_key($atts['context_type']) : '';

        if (!$context_id || !$context_type || !$this->is_allowed_context_type($context_type)) {
            return;
        }

        if ($grouped) {
            $ids = array($rating_id);
            $children = $this->ratings->get_sub_ratings($rating_id);
            foreach ($children as $child) {
                $ids[] = (int) $child->id;
            }
            $this->register_rating_ids($ids, $context_id, $context_type);
            return;
        }

        $this->register_rating_ids(array($rating_id), $context_id, $context_type);
    }

    /**
     * Register all rating IDs referenced by a grouped block configuration.
     *
     * @param array $attributes   Block attributes.
     * @param int   $context_id   Context ID.
     * @param string $context_type Context type.
     * @return void
     */
    private function register_grouped_block_ids(array $attributes, int $context_id, string $context_type): void {
        $rating_id = isset($attributes['ratingId']) ? absint($attributes['ratingId']) : 0;
        $mirror_id = isset($attributes['mirrorId']) ? absint($attributes['mirrorId']) : 0;
        $sub_ratings = isset($attributes['subRatings']) && is_array($attributes['subRatings'])
            ? $attributes['subRatings']
            : array();

        $ids = array_filter(array($rating_id, $mirror_id));

        if (!empty($sub_ratings)) {
            foreach ($sub_ratings as $sr_config) {
                if (!is_array($sr_config) || !isset($sr_config['id'])) {
                    continue;
                }

                $visible = isset($sr_config['visible']) ? (bool) $sr_config['visible'] : true;
                if (!$visible) {
                    continue;
                }

                $ids[] = absint($sr_config['id']);

                $sr_mirror_id = isset($sr_config['mirrorId']) ? absint($sr_config['mirrorId']) : 0;
                if ($sr_mirror_id > 0) {
                    $ids[] = $sr_mirror_id;
                }
            }
        } elseif ($rating_id > 0) {
            $children = $this->ratings->get_sub_ratings($rating_id);
            foreach ($children as $child) {
                $ids[] = (int) $child->id;
            }
        }

        $this->register_rating_ids($ids, $context_id, $context_type);
    }

    /**
     * Load ratings and register their source IDs with resolved display scales.
     *
     * @param array  $rating_ids   Rating IDs to register.
     * @param int    $context_id   Context ID.
     * @param string $context_type Context type.
     * @return void
     */
    private function register_rating_ids(array $rating_ids, int $context_id, string $context_type): void {
        $rating_ids = array_values(array_unique(array_filter(array_map('absint', $rating_ids))));
        if (empty($rating_ids)) {
            return;
        }

        $ratings = $this->ratings->get_ratings_by_ids($rating_ids);
        foreach ($ratings as $rating) {
            $source_id = (int) $rating->source_id;
            $scale = Shuriken_Shortcodes::resolve_render_scale($rating);
            $this->register($source_id, $context_id, $context_type, $scale);
        }
    }

    /**
     * Resolve context from block attributes using dynamic block context (Query Loop).
     *
     * @param array     $attributes Block attributes.
     * @param \WP_Block $block      Block instance.
     * @return array{0: int, 1: string}|null [context_id, context_type] or null.
     */
    private function resolve_block_context(array $attributes, \WP_Block $block): ?array {
        if (!empty($attributes['postContext'])) {
            $context_id = isset($block->context['postId']) ? absint($block->context['postId']) : get_the_ID();
            $context_type = isset($block->context['postType'])
                ? sanitize_key($block->context['postType'])
                : (string) get_post_type($context_id);

            if ($context_id > 0 && $context_type !== '' && $this->is_allowed_context_type($context_type)) {
                return array($context_id, $context_type);
            }
        }

        return null;
    }

    /**
     * Resolve context from block attributes for static content scan (no block instance).
     *
     * @param array $attributes Block attributes from stored JSON.
     * @return array{0: int, 1: string}|null
     */
    private function resolve_static_context(array $attributes): ?array {
        if (!empty($attributes['postContext'])) {
            $context_id = get_the_ID();
            $context_type = $context_id ? (string) get_post_type($context_id) : '';

            if ($context_id > 0 && $context_type !== '' && $this->is_allowed_context_type($context_type)) {
                return array((int) $context_id, $context_type);
            }
        }

        return null;
    }

    /**
     * Check whether a context type is allowed for contextual voting.
     *
     * @param string $context_type Context type.
     * @return bool
     */
    private function is_allowed_context_type(string $context_type): bool {
        $allowed = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
        return in_array($context_type, $allowed, true);
    }
}
