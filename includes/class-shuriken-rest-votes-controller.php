<?php
/**
 * Shuriken Reviews REST Votes Controller
 *
 * Handles stats, context-stats, and nonce endpoints.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_REST_Votes_Controller
 *
 * @since 1.15.5
 */
class Shuriken_REST_Votes_Controller {

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface $db        Database service.
     * @param string                      $namespace REST API namespace.
     */
    public function __construct(
        private readonly Shuriken_Database_Interface $db,
        private readonly string $namespace,
    ) {}

    // =========================================================================
    // Route Registration
    // =========================================================================

    /**
     * Register vote/stats-related REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Public stats endpoint (bypasses cache)
        register_rest_route($this->namespace, '/ratings/stats', array(
            'methods'             => 'GET',
            'callback'            => $this->get_rating_stats(...),
            'permission_callback' => '__return_true',
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Comma-separated list of rating IDs',
                ),
                'context_id' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Post/entity ID for contextual stats',
                ),
                'context_type' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                    'description'       => 'Context type (post, page, product, etc.)',
                ),
            ),
        ));

        // Public nonce endpoint (bypasses cache)
        register_rest_route($this->namespace, '/nonce', array(
            'methods'             => 'GET',
            'callback'            => $this->get_fresh_nonce(...),
            'permission_callback' => '__return_true',
        ));

        // Context stats endpoint (editor only — returns ratings with per-context votes)
        register_rest_route($this->namespace, '/context-stats', array(
            'methods'             => 'GET',
            'callback'            => $this->get_context_stats(...),
            'permission_callback' => $this->can_edit_posts(...),
            'args'                => array(
                'context_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Post/entity ID to get contextual ratings for',
                ),
                'context_type' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                    'description'       => 'Context type (post, page, product, etc.)',
                ),
            ),
        ));
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    /**
     * Check if user can edit posts
     *
     * @param WP_REST_Request $request The request object (optional).
     * @return bool
     */
    public function can_edit_posts(\WP_REST_Request $request = null): bool {
        return current_user_can('edit_posts');
    }

    // =========================================================================
    // Handler Methods
    // =========================================================================

    /**
     * Get fresh rating statistics (bypasses cache)
     *
     * Optimized to use batch query instead of individual queries.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_rating_stats(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $ids_string = $request->get_param('ids');
            $ids = array_map('intval', explode(',', $ids_string));
            $ids = array_filter($ids); // Remove any invalid IDs
            
            if (empty($ids)) {
                throw Shuriken_Validation_Exception::required_field('ids');
            }

            $context_id = $request->get_param('context_id');
            $context_type = $request->get_param('context_type');
            $has_context = $context_id && $context_type;

            // Validate context_type against allowed values
            if ($has_context) {
                $allowed_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
                if (!in_array($context_type, $allowed_types, true)) {
                    $has_context = false;
                }
            }
            
            // Always fetch base rating data (for source_id / mirror resolution)
            $ratings = $this->db->get_ratings_by_ids($ids);

            // If contextual, overlay per-context stats
            if ($has_context) {
                // Collect source_ids for context query (mirrors use original's votes)
                $source_ids = array();
                $scales_map = array();
                foreach ($ratings as $rating) {
                    $sid = (int) $rating->source_id;
                    $source_ids[] = $sid;
                    $scales_map[$sid] = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
                }
                $source_ids = array_unique($source_ids);
                $ctx_stats = $this->db->get_contextual_stats_batch($source_ids, (int) $context_id, $context_type, $scales_map);
            }
            
            $stats = array();
            foreach ($ratings as $id => $rating) {
                $source_id = (int) $rating->source_id;

                if ($has_context && isset($ctx_stats[$source_id])) {
                    $ctx = $ctx_stats[$source_id];
                    $stats[$id] = array(
                        'average'         => $ctx->average,
                        'display_average' => $ctx->display_average,
                        'total_votes'     => $ctx->total_votes,
                        'total_rating'    => $ctx->total_rating,
                        'source_id'       => $source_id,
                    );
                } elseif ($has_context) {
                    // Context requested but no votes yet for this context
                    $stats[$id] = array(
                        'average'         => 0,
                        'display_average' => 0,
                        'total_votes'     => 0,
                        'total_rating'    => 0,
                        'source_id'       => $source_id,
                    );
                } else {
                    $stats[$id] = array(
                        'average'         => $rating->average,
                        'display_average' => $rating->display_average,
                        'total_votes'     => $rating->total_votes,
                        'total_rating'    => $rating->total_rating,
                        'source_id'       => $rating->source_id,
                    );
                }
            }
            
            return rest_ensure_response($stats);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get ratings with contextual votes for a specific post/entity
     *
     * Returns all ratings that have per-context votes for the given context,
     * along with their per-context statistics.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.15.5
     */
    public function get_context_stats(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $context_id = (int) $request->get_param('context_id');
            $context_type = $request->get_param('context_type');

            if (!$context_id || !$context_type) {
                throw Shuriken_Validation_Exception::required_field('context_id and context_type');
            }

            $allowed_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
            if (!in_array($context_type, $allowed_types, true)) {
                throw Shuriken_Validation_Exception::out_of_range('context_type', $context_type, 0, 0);
            }

            $ratings = $this->db->get_ratings_for_context($context_id, $context_type);

            $result = array();
            foreach ($ratings as $rating) {
                $result[] = array(
                    'id'              => (int) $rating->id,
                    'name'            => $rating->name,
                    'rating_type'     => $rating->rating_type,
                    'scale'           => (int) $rating->scale,
                    'votes'           => $rating->ctx_votes,
                    'total'           => $rating->ctx_total,
                    'average'         => $rating->ctx_average,
                    'display_average' => $rating->ctx_display_average,
                );
            }

            return rest_ensure_response($result);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get a fresh nonce (bypasses cache)
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_fresh_nonce(\WP_REST_Request $request): \WP_REST_Response {
        // Prevent caching of this endpoint
        nocache_headers();
        
        $nonce = wp_create_nonce('shuriken-reviews-nonce');
        
        return rest_ensure_response(array(
            'nonce' => $nonce,
            'logged_in' => is_user_logged_in(),
            'allow_guest_voting' => get_option('shuriken_allow_guest_voting', '0') === '1',
        ));
    }
}
