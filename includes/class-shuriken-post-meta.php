<?php
/**
 * Shuriken Reviews Post Meta Box Class
 *
 * Links ratings to posts/pages via post meta. Handles meta box rendering,
 * saving, content injection, JSON-LD output, and admin columns.
 *
 * @package Shuriken_Reviews
 * @since 1.12.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Post_Meta
 *
 * @since 1.12.0
 */
class Shuriken_Post_Meta {

    /**
     * Meta key for storing linked rating IDs
     */
    const META_KEY = '_shuriken_rating_ids';

    /**
     * @var Shuriken_Database_Interface
     */
    private Shuriken_Database_Interface $db;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface $db Database instance.
     */
    public function __construct(Shuriken_Database_Interface $db) {
        $this->db = $db;

        // Meta box
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);

        // Content injection
        add_filter('the_content', array($this, 'inject_ratings'), 20);

        // JSON-LD structured data
        add_action('wp_head', array($this, 'output_jsonld'));

        // Admin columns
        add_action('admin_init', array($this, 'register_admin_columns'));

        // REST API meta field
        add_action('rest_api_init', array($this, 'register_rest_field'));
    }

    /**
     * Get post types that support rating meta boxes
     *
     * @return array
     */
    private function get_supported_post_types(): array {
        $post_types = get_option('shuriken_meta_box_post_types', array('post', 'page'));
        if (!is_array($post_types)) {
            $post_types = array('post', 'page');
        }

        /**
         * Filter the post types that support rating meta boxes.
         *
         * @since 1.12.0
         * @param array $post_types Array of post type slugs.
         */
        return apply_filters('shuriken_meta_box_post_types', $post_types);
    }

    /**
     * Get content injection position
     *
     * @return string 'before', 'after', or 'disabled'
     */
    private function get_injection_position(): string {
        $position = get_option('shuriken_content_injection_position', 'disabled');

        /**
         * Filter the content injection position.
         *
         * @since 1.12.0
         * @param string $position 'before', 'after', or 'disabled'.
         */
        return apply_filters('shuriken_content_injection_position', $position);
    }

    /**
     * Register meta box for supported post types
     *
     * @return void
     */
    public function register_meta_box(): void {
        $post_types = $this->get_supported_post_types();

        foreach ($post_types as $post_type) {
            add_meta_box(
                'shuriken-ratings-meta-box',
                __('Shuriken Ratings', 'shuriken-reviews'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }

        // Ensure wp-api-fetch is available for the tag-search meta box
        wp_enqueue_script('wp-api-fetch');
    }

    /**
     * Render meta box content — tag-style search & select UI
     *
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('shuriken_meta_box', 'shuriken_meta_box_nonce');

        $linked_ids = $this->get_linked_rating_ids($post->ID);

        // Pre-load names for already-linked ratings so tags render immediately
        $linked_ratings = array();
        foreach ($linked_ids as $id) {
            $rating = $this->db->get_rating($id);
            if ($rating) {
                $linked_ratings[] = array(
                    'id'   => (int) $rating->id,
                    'name' => $rating->name,
                    'type' => isset($rating->rating_type) ? $rating->rating_type : 'stars',
                );
            }
        }
        ?>
        <div class="shuriken-meta-box-inner" id="shuriken-tag-meta-box">
            <p class="description">
                <?php esc_html_e('Type to search ratings, then click to add.', 'shuriken-reviews'); ?>
            </p>

            <!-- Selected tags -->
            <div class="shuriken-tags-wrap" id="shuriken-selected-tags"></div>

            <!-- Hidden inputs (the actual form data) -->
            <div id="shuriken-hidden-inputs"></div>

            <!-- Search input -->
            <div class="shuriken-tag-search-wrap" style="margin-top: 8px; position: relative;">
                <input type="text"
                       id="shuriken-rating-search"
                       class="widefat"
                       placeholder="<?php esc_attr_e('Search ratings...', 'shuriken-reviews'); ?>"
                       autocomplete="off">
                <ul id="shuriken-rating-suggestions"
                    class="shuriken-suggestions"
                    style="display:none;"></ul>
            </div>
        </div>

        <style>
            .shuriken-tags-wrap {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                margin-top: 8px;
                min-height: 28px;
            }
            .shuriken-tag {
                display: inline-flex;
                align-items: center;
                background: #e0e0e0;
                border-radius: 3px;
                padding: 2px 6px;
                font-size: 12px;
                line-height: 1.6;
                max-width: 100%;
                word-break: break-word;
            }
            .shuriken-tag .shuriken-tag-remove {
                margin-left: 4px;
                cursor: pointer;
                color: #a00;
                font-weight: bold;
                font-size: 14px;
                line-height: 1;
                border: none;
                background: none;
                padding: 0 2px;
            }
            .shuriken-tag .shuriken-tag-remove:hover {
                color: #dc3232;
            }
            .shuriken-tag .shuriken-tag-type {
                color: #888;
                margin-left: 3px;
            }
            .shuriken-suggestions {
                position: absolute;
                z-index: 1000;
                background: #fff;
                border: 1px solid #ddd;
                border-top: none;
                list-style: none;
                margin: 0;
                padding: 0;
                max-height: 180px;
                overflow-y: auto;
                width: 100%;
                box-sizing: border-box;
            }
            .shuriken-suggestions li {
                padding: 6px 8px;
                cursor: pointer;
                font-size: 13px;
            }
            .shuriken-suggestions li:hover,
            .shuriken-suggestions li.active {
                background: #0073aa;
                color: #fff;
            }
            .shuriken-suggestions li.disabled {
                opacity: .5;
                cursor: default;
            }
            .shuriken-suggestions li.disabled:hover {
                background: transparent;
                color: inherit;
            }
        </style>

        <script>
        (function () {
            'use strict';

            var selected    = <?php echo wp_json_encode($linked_ratings); ?>;
            var tagsWrap    = document.getElementById('shuriken-selected-tags');
            var hiddenWrap  = document.getElementById('shuriken-hidden-inputs');
            var searchInput = document.getElementById('shuriken-rating-search');
            var sugList     = document.getElementById('shuriken-rating-suggestions');
            var debounceTimer = null;
            var activeIdx     = -1;
            var suggestions   = [];

            function render() {
                // Tags
                tagsWrap.innerHTML = '';
                selected.forEach(function (r) {
                    var tag = document.createElement('span');
                    tag.className = 'shuriken-tag';
                    tag.textContent = r.name;
                    if (r.type && r.type !== 'stars') {
                        var small = document.createElement('span');
                        small.className = 'shuriken-tag-type';
                        small.textContent = '(' + r.type + ')';
                        tag.appendChild(small);
                    }
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'shuriken-tag-remove';
                    btn.setAttribute('aria-label', 'Remove');
                    btn.textContent = '\u00D7';
                    btn.addEventListener('click', function () {
                        removeRating(r.id);
                    });
                    tag.appendChild(btn);
                    tagsWrap.appendChild(tag);
                });

                // Hidden inputs
                hiddenWrap.innerHTML = '';
                selected.forEach(function (r) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'shuriken_rating_ids[]';
                    input.value = r.id;
                    hiddenWrap.appendChild(input);
                });
            }

            function isSelected(id) {
                return selected.some(function (r) { return r.id === id; });
            }

            function addRating(item) {
                if (isSelected(item.id)) return;
                selected.push(item);
                render();
                searchInput.value = '';
                closeSuggestions();
                searchInput.focus();
            }

            function removeRating(id) {
                selected = selected.filter(function (r) { return r.id !== id; });
                render();
            }

            function closeSuggestions() {
                sugList.style.display = 'none';
                sugList.innerHTML = '';
                suggestions = [];
                activeIdx = -1;
            }

            function showSuggestions(items) {
                suggestions = items;
                activeIdx = -1;
                sugList.innerHTML = '';

                if (!items.length) {
                    var li = document.createElement('li');
                    li.className = 'disabled';
                    li.textContent = <?php echo wp_json_encode(__('No ratings found.', 'shuriken-reviews')); ?>;
                    sugList.appendChild(li);
                    sugList.style.display = 'block';
                    return;
                }

                items.forEach(function (item, idx) {
                    var li = document.createElement('li');
                    var already = isSelected(item.id);
                    li.textContent = item.name;
                    if (item.type && item.type !== 'stars') {
                        li.textContent += ' (' + item.type + ')';
                    }
                    if (already) {
                        li.className = 'disabled';
                        li.textContent += ' \u2713';
                    }
                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        if (!already) addRating(item);
                    });
                    sugList.appendChild(li);
                });
                sugList.style.display = 'block';
            }

            function fetchSuggestions(term) {
                // Using the existing REST search endpoint
                wp.apiFetch({
                    path: '/shuriken-reviews/v1/ratings/search?q=' + encodeURIComponent(term) + '&limit=20&type=all'
                }).then(function (results) {
                    var items = (Array.isArray(results) ? results : []).map(function (r) {
                        return {
                            id: parseInt(r.id, 10),
                            name: r.name,
                            type: r.rating_type || 'stars'
                        };
                    }).filter(function (r) {
                        // Skip sub-ratings and mirrors
                        return true;
                    });
                    showSuggestions(items);
                }).catch(function () {
                    closeSuggestions();
                });
            }

            searchInput.addEventListener('input', function () {
                var term = searchInput.value.trim();
                clearTimeout(debounceTimer);
                if (term.length < 1) {
                    closeSuggestions();
                    return;
                }
                debounceTimer = setTimeout(function () {
                    fetchSuggestions(term);
                }, 300);
            });

            searchInput.addEventListener('keydown', function (e) {
                var lis = sugList.querySelectorAll('li:not(.disabled)');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, lis.length - 1);
                    highlightActive(lis);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, 0);
                    highlightActive(lis);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIdx >= 0 && activeIdx < suggestions.length) {
                        var item = suggestions.filter(function (s) { return !isSelected(s.id); })[activeIdx];
                        if (item) addRating(item);
                    }
                } else if (e.key === 'Escape') {
                    closeSuggestions();
                }
            });

            searchInput.addEventListener('blur', function () {
                // Small delay to allow click events on suggestions
                setTimeout(closeSuggestions, 200);
            });

            function highlightActive(lis) {
                sugList.querySelectorAll('li').forEach(function (li) { li.classList.remove('active'); });
                if (lis[activeIdx]) {
                    lis[activeIdx].classList.add('active');
                    lis[activeIdx].scrollIntoView({ block: 'nearest' });
                }
            }

            // Initial render
            render();
        })();
        </script>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function save_meta_box(int $post_id, \WP_Post $post): void {
        // Verify nonce
        if (!isset($_POST['shuriken_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['shuriken_meta_box_nonce'], 'shuriken_meta_box')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check supported post type
        if (!in_array($post->post_type, $this->get_supported_post_types(), true)) {
            return;
        }

        // Sanitize and save
        if (isset($_POST['shuriken_rating_ids']) && is_array($_POST['shuriken_rating_ids'])) {
            $rating_ids = array_map('intval', $_POST['shuriken_rating_ids']);
            $rating_ids = array_filter($rating_ids, function($id) {
                return $id > 0;
            });
            $rating_ids = array_values(array_unique($rating_ids));
            update_post_meta($post_id, self::META_KEY, $rating_ids);
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }

    /**
     * Get linked rating IDs for a post
     *
     * @param int $post_id Post ID.
     * @return array Array of rating IDs.
     */
    public function get_linked_rating_ids(int $post_id): array {
        $ids = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($ids)) {
            return array();
        }
        return array_map('intval', $ids);
    }

    /**
     * Inject ratings into post content
     *
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function inject_ratings(string $content): string {
        // Only on singular views in main query
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $position = $this->get_injection_position();
        if ($position === 'disabled') {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        // Skip injection if the Post Linked Ratings block is present in this content
        if (has_block('shuriken-reviews/post-linked-ratings', $post_id)) {
            return $content;
        }

        // Skip injection if a contextual rating/grouped-rating block is already in the content
        if (has_block('shuriken-reviews/rating', $post_id) || has_block('shuriken-reviews/grouped-rating', $post_id)) {
            return $content;
        }

        // Check post type support
        if (!in_array(get_post_type($post_id), $this->get_supported_post_types(), true)) {
            return $content;
        }

        $rating_ids = $this->get_linked_rating_ids($post_id);
        if (empty($rating_ids)) {
            return $content;
        }

        // Build wrapper attributes from universal style/color settings
        $wrapper_classes = array('shuriken-post-ratings');
        $style_preset = get_option('shuriken_linked_ratings_style', '');
        if ($style_preset && in_array($style_preset, array('classic', 'card', 'minimal', 'dark', 'outlined'), true)) {
            $wrapper_classes[] = 'is-style-' . $style_preset;
        }

        $style_vars = array();
        $accent_color = get_option('shuriken_linked_ratings_accent_color', '');
        if ($accent_color) {
            $style_vars[] = '--shuriken-user-accent: ' . esc_attr($accent_color);
        }
        $star_color = get_option('shuriken_linked_ratings_star_color', '');
        if ($star_color) {
            $style_vars[] = '--shuriken-user-star-color: ' . esc_attr($star_color);
        }

        $style_attr = !empty($style_vars) ? ' style="' . esc_attr(implode('; ', $style_vars)) . ';"' : '';

        // Build shortcode HTML
        $ratings_html = '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '"' . $style_attr . '>';
        foreach ($rating_ids as $rating_id) {
            $ratings_html .= do_shortcode('[shuriken_rating id="' . intval($rating_id) . '"]');
        }
        $ratings_html .= '</div>';

        if ($position === 'before') {
            return $ratings_html . $content;
        }

        return $content . $ratings_html;
    }

    /**
     * Output JSON-LD structured data for linked ratings
     *
     * @return void
     */
    public function output_jsonld(): void {
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        if (!in_array(get_post_type($post_id), $this->get_supported_post_types(), true)) {
            return;
        }

        $rating_ids = $this->get_linked_rating_ids($post_id);
        if (empty($rating_ids)) {
            return;
        }

        // Get rating data for JSON-LD
        foreach ($rating_ids as $rating_id) {
            $rating = $this->db->get_rating($rating_id);
            if (!$rating || $rating->total_votes < 1) {
                continue;
            }

            // Only output structured data for star/numeric ratings
            $type = isset($rating->rating_type) ? $rating->rating_type : 'stars';
            if ($type !== 'stars' && $type !== 'numeric') {
                continue;
            }

            $scale = isset($rating->scale) ? intval($rating->scale) : Shuriken_Database::RATING_SCALE_DEFAULT;
            $average = $rating->total_votes > 0
                ? round($rating->total_rating / $rating->total_votes, 2)
                : 0;

            $jsonld = array(
                '@context' => 'https://schema.org',
                '@type'    => 'CreativeWork',
                'name'     => get_the_title($post_id),
                'url'      => get_permalink($post_id),
                'aggregateRating' => array(
                    '@type'       => 'AggregateRating',
                    'ratingValue' => $average,
                    'bestRating'  => $scale,
                    'worstRating' => 1,
                    'ratingCount' => intval($rating->total_votes),
                ),
            );

            /**
             * Filter the JSON-LD structured data for a rating.
             *
             * @since 1.12.0
             * @param array  $jsonld    JSON-LD data array.
             * @param object $rating    Rating object.
             * @param int    $post_id   Post ID.
             */
            $jsonld = apply_filters('shuriken_rating_jsonld', $jsonld, $rating, $post_id);

            echo '<script type="application/ld+json">' . wp_json_encode($jsonld, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
    }

    /**
     * Register admin columns for supported post types
     *
     * @return void
     */
    public function register_admin_columns(): void {
        $post_types = $this->get_supported_post_types();

        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_admin_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'render_admin_column'), 10, 2);
        }
    }

    /**
     * Add the Ratings column to the post list table
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_admin_column(array $columns): array {
        $columns['shuriken_ratings'] = __('Ratings', 'shuriken-reviews');
        return $columns;
    }

    /**
     * Render the Ratings admin column
     *
     * @param string $column_name Column identifier.
     * @param int    $post_id     Post ID.
     * @return void
     */
    public function render_admin_column(string $column_name, int $post_id): void {
        if ($column_name !== 'shuriken_ratings') {
            return;
        }

        $rating_ids = $this->get_linked_rating_ids($post_id);
        if (empty($rating_ids)) {
            echo '—';
            return;
        }

        $names = array();
        foreach ($rating_ids as $rid) {
            $rating = $this->db->get_rating($rid);
            if ($rating) {
                $names[] = esc_html($rating->name);
            }
        }

        echo implode(', ', $names);
    }

    /**
     * Register REST API field for post meta
     *
     * @return void
     */
    public function register_rest_field(): void {
        $post_types = $this->get_supported_post_types();

        foreach ($post_types as $post_type) {
            register_rest_field($post_type, 'shuriken_rating_ids', array(
                'get_callback' => function($object) {
                    return $this->get_linked_rating_ids($object['id']);
                },
                'update_callback' => function($value, $object) {
                    if (!is_array($value)) {
                        return;
                    }
                    $ids = array_map('intval', $value);
                    $ids = array_filter($ids, function($id) { return $id > 0; });
                    update_post_meta($object->ID, self::META_KEY, array_values($ids));
                },
                'schema' => array(
                    'description' => __('Linked Shuriken rating IDs', 'shuriken-reviews'),
                    'type'        => 'array',
                    'items'       => array('type' => 'integer'),
                ),
            ));
        }
    }
}
