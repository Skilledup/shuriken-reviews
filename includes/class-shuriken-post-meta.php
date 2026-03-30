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
        $position = get_option('shuriken_content_injection_position', 'after');

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
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post Current post object.
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('shuriken_meta_box', 'shuriken_meta_box_nonce');

        $linked_ids = $this->get_linked_rating_ids($post->ID);
        $all_ratings = $this->db->get_all_ratings();
        ?>
        <div class="shuriken-meta-box-inner">
            <p class="description">
                <?php esc_html_e('Select ratings to display with this content.', 'shuriken-reviews'); ?>
            </p>
            <div class="shuriken-rating-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; margin-top: 8px;">
                <?php if (empty($all_ratings)): ?>
                    <p class="description">
                        <?php esc_html_e('No ratings found. Create ratings first.', 'shuriken-reviews'); ?>
                    </p>
                <?php else: ?>
                    <?php foreach ($all_ratings as $rating): ?>
                        <?php
                        // Skip sub-ratings and mirrors — only show top-level and standalone
                        if (!empty($rating->parent_id) || !empty($rating->mirror_of)) {
                            continue;
                        }
                        ?>
                        <label style="display: block; margin-bottom: 4px;">
                            <input type="checkbox"
                                   name="shuriken_rating_ids[]"
                                   value="<?php echo esc_attr($rating->id); ?>"
                                   <?php checked(in_array((int) $rating->id, $linked_ids, true)); ?>>
                            <?php echo esc_html($rating->name); ?>
                            <?php if (isset($rating->rating_type) && $rating->rating_type !== 'stars'): ?>
                                <small>(<?php echo esc_html($rating->rating_type); ?>)</small>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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

        // Check post type support
        if (!in_array(get_post_type($post_id), $this->get_supported_post_types(), true)) {
            return $content;
        }

        $rating_ids = $this->get_linked_rating_ids($post_id);
        if (empty($rating_ids)) {
            return $content;
        }

        // Build shortcode HTML
        $ratings_html = '<div class="shuriken-post-ratings">';
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

            $scale = isset($rating->scale) ? intval($rating->scale) : 5;
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
