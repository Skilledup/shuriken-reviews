<?php
/**
 * Shuriken Reviews REST API Class
 *
 * Handles all REST API endpoints for the plugin.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_REST_API
 *
 * Registers and handles REST API endpoints.
 *
 * @since 1.7.0
 */
class Shuriken_REST_API {

    /**
     * @var Shuriken_REST_API Singleton instance
     */
    private static $instance = null;

    /**
     * REST API namespace
     */
    const NAMESPACE = 'shuriken-reviews/v1';

    /**
     * Constructor
     */
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // Skip nonce verification for public endpoints BEFORE authentication runs
        add_filter('rest_authentication_errors', array($this, 'rest_authentication_errors'), 5, 1);
    }
    
    /**
     * Handle REST API authentication errors
     * 
     * Bypasses nonce verification for public endpoints like /nonce and /ratings/stats
     * that need to work without authentication (e.g., for cached pages with stale nonces)
     *
     * @param WP_Error|null|bool $result Authentication result.
     * @return WP_Error|null|bool
     */
    public function rest_authentication_errors($result) {
        // Check if this is a request to our public endpoints
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
        
        // Allow public endpoints to bypass nonce verification entirely
        if (strpos($request_uri, '/shuriken-reviews/v1/nonce') !== false ||
            strpos($request_uri, '/shuriken-reviews/v1/ratings/stats') !== false) {
            // Return true to allow the request without authentication
            return true;
        }
        
        // If there's already a result, use it
        if ($result !== null) {
            return $result;
        }
        
        // If user is logged in via cookie, allow it
        if (is_user_logged_in()) {
            return true;
        }
        
        // Otherwise, let WordPress handle it
        return null;
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_REST_API
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the REST API
     *
     * @return void
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Register REST API routes
     *
     * @return void
     * @since 1.7.0
     */
    public function register_routes() {
        // Ratings collection endpoint
        register_rest_route(self::NAMESPACE, '/ratings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_ratings'),
                'permission_callback' => array($this, 'can_edit_posts'),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'create_rating'),
                'permission_callback' => array($this, 'can_manage_options'),
                'args'                => $this->get_rating_create_args(),
            ),
        ));

        // Single rating endpoint
        register_rest_route(self::NAMESPACE, '/ratings/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_single_rating'),
                'permission_callback' => array($this, 'can_edit_posts'),
                'args'                => $this->get_rating_id_args(),
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => array($this, 'update_rating'),
                'permission_callback' => array($this, 'can_manage_options'),
                'args'                => $this->get_rating_update_args(),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array($this, 'delete_rating'),
                'permission_callback' => array($this, 'can_manage_options'),
                'args'                => $this->get_rating_id_args(),
            ),
        ));

        // Parent ratings endpoint
        register_rest_route(self::NAMESPACE, '/ratings/parents', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_parent_ratings'),
            'permission_callback' => array($this, 'can_edit_posts'),
        ));

        // Mirrorable ratings endpoint
        register_rest_route(self::NAMESPACE, '/ratings/mirrorable', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_mirrorable_ratings'),
            'permission_callback' => array($this, 'can_edit_posts'),
        ));

        // Search ratings endpoint (for AJAX autocomplete)
        register_rest_route(self::NAMESPACE, '/ratings/search', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'search_ratings'),
            'permission_callback' => array($this, 'can_edit_posts'),
            'args'                => array(
                'q' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Search term to match against rating names',
                ),
                'limit' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Maximum number of results to return',
                ),
                'type' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'all',
                    'enum'              => array('all', 'parents', 'mirrorable'),
                    'description'       => 'Filter by rating type',
                ),
            ),
        ));

        // Get child ratings of a parent
        register_rest_route(self::NAMESPACE, '/ratings/(?P<id>\d+)/children', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_child_ratings'),
            'permission_callback' => array($this, 'can_edit_posts'),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Parent rating ID',
                ),
            ),
        ));

        // Public stats endpoint (bypasses cache)
        register_rest_route(self::NAMESPACE, '/ratings/stats', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_rating_stats'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Comma-separated list of rating IDs',
                ),
            ),
        ));

        // Public nonce endpoint (bypasses cache)
        register_rest_route(self::NAMESPACE, '/nonce', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_fresh_nonce'),
            'permission_callback' => '__return_true',
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
    public function can_edit_posts($request = null) {
        // WordPress REST API handles authentication automatically
        // This will work with cookie auth, application passwords, etc.
        return current_user_can('edit_posts');
    }

    /**
     * Check if user can manage options
     *
     * @param WP_REST_Request $request The request object (optional).
     * @return bool
     */
    public function can_manage_options($request = null) {
        // WordPress REST API handles authentication automatically
        // This will work with cookie auth, application passwords, etc.
        return current_user_can('manage_options');
    }

    // =========================================================================
    // Argument Definitions
    // =========================================================================

    /**
     * Get rating ID argument definition
     *
     * @return array
     */
    private function get_rating_id_args() {
        return array(
            'id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
        );
    }

    /**
     * Get rating creation argument definitions
     *
     * @return array
     */
    private function get_rating_create_args() {
        return array(
            'name' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'parent_id' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'mirror_of' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'effect_type' => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => 'positive',
                'enum'              => array('positive', 'negative'),
            ),
            'display_only' => array(
                'required'          => false,
                'type'              => 'boolean',
                'default'           => false,
            ),
        );
    }

    /**
     * Get rating update argument definitions
     *
     * @return array
     */
    private function get_rating_update_args() {
        return array(
            'id' => array(
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'name' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'parent_id' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'mirror_of' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'effect_type' => array(
                'required'          => false,
                'type'              => 'string',
                'enum'              => array('positive', 'negative'),
            ),
            'display_only' => array(
                'required'          => false,
                'type'              => 'boolean',
            ),
        );
    }

    // =========================================================================
    // REST API Callbacks
    // =========================================================================

    /**
     * Get all ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_ratings($request) {
        $ratings = shuriken_db()->get_all_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Create a new rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_rating($request) {
        try {
            $name = $request->get_param('name');
            $parent_id = $request->get_param('parent_id');
            $mirror_of = $request->get_param('mirror_of');
            $effect_type = $request->get_param('effect_type') ?: 'positive';
            $display_only = $request->get_param('display_only') ?: false;
            
            // Convert 0 to null for parent_id and mirror_of
            $parent_id = $parent_id ? intval($parent_id) : null;
            $mirror_of = $mirror_of ? intval($mirror_of) : null;
            
            $new_id = shuriken_db()->create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of);
            
            if ($new_id === false) {
                throw new Shuriken_Database_Exception(
                    __('Failed to create rating.', 'shuriken-reviews'),
                    'create_rating'
                );
            }
            
            $rating = shuriken_db()->get_rating($new_id);
            
            return rest_ensure_response($rating);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get a single rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_single_rating($request) {
        try {
            $id = $request->get_param('id');
            $rating = shuriken_db()->get_rating($id);
            
            if (!$rating) {
                throw new Shuriken_Not_Found_Exception(
                    __('Rating not found.', 'shuriken-reviews'),
                    'rating',
                    $id
                );
            }
            
            return rest_ensure_response($rating);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Update a rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_rating($request) {
        try {
            $id = $request->get_param('id');
            $db = shuriken_db();
            
            // Check if rating exists
            $existing = $db->get_rating($id);
            if (!$existing) {
                throw new Shuriken_Not_Found_Exception(
                    __('Rating not found.', 'shuriken-reviews'),
                    'rating',
                    $id
                );
            }
            
            // Build update data array
            $update_data = array();
            
            if ($request->has_param('name') && $request->get_param('name') !== '') {
                $update_data['name'] = $request->get_param('name');
            }
            
            if ($request->has_param('parent_id')) {
                $parent_id = $request->get_param('parent_id');
                $update_data['parent_id'] = $parent_id ? intval($parent_id) : null;
            }
            
            if ($request->has_param('mirror_of')) {
                $mirror_of = $request->get_param('mirror_of');
                $update_data['mirror_of'] = $mirror_of ? intval($mirror_of) : null;
            }
            
            if ($request->has_param('effect_type')) {
                $update_data['effect_type'] = $request->get_param('effect_type');
            }
            
            if ($request->has_param('display_only')) {
                $update_data['display_only'] = $request->get_param('display_only');
            }
            
            if (empty($update_data)) {
                throw new Shuriken_Validation_Exception(
                    __('No data provided for update.', 'shuriken-reviews'),
                    'update_data'
                );
            }
            
            $result = $db->update_rating($id, $update_data);
            
            if ($result === false) {
                throw new Shuriken_Database_Exception(
                    __('Failed to update rating.', 'shuriken-reviews'),
                    'update_rating'
                );
            }
            
            // Recalculate parent rating if this is a sub-rating
            if (!empty($update_data['parent_id'])) {
                $db->recalculate_parent_rating($update_data['parent_id']);
            }
            // Also recalculate old parent if parent changed
            if (!empty($existing->parent_id) && (empty($update_data['parent_id']) || $existing->parent_id != $update_data['parent_id'])) {
                $db->recalculate_parent_rating($existing->parent_id);
            }
            
            $rating = $db->get_rating($id);
            
            return rest_ensure_response($rating);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Delete a rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_rating($request) {
        try {
            $id = $request->get_param('id');
            $db = shuriken_db();
            
            // Check if rating exists
            $existing = $db->get_rating($id);
            if (!$existing) {
                throw new Shuriken_Not_Found_Exception(
                    __('Rating not found.', 'shuriken-reviews'),
                    'rating',
                    $id
                );
            }

            // Store parent_id before deletion for recalculation
            $parent_id = $existing->parent_id;
            
            // Delete the rating
            $result = $db->delete_rating($id);
            
            if ($result === false) {
                throw new Shuriken_Database_Exception(
                    __('Failed to delete rating.', 'shuriken-reviews'),
                    'delete_rating'
                );
            }

            // Recalculate parent rating if this was a sub-rating
            if (!empty($parent_id)) {
                $db->recalculate_parent_rating($parent_id);
            }
            
            return rest_ensure_response(array(
                'deleted' => true,
                'id' => $id
            ));
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get parent ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_parent_ratings($request) {
        $ratings = shuriken_db()->get_parent_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Get mirrorable ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_mirrorable_ratings($request) {
        $ratings = shuriken_db()->get_mirrorable_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Get child ratings of a parent rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.9.0
     */
    public function get_child_ratings($request) {
        try {
            $parent_id = $request->get_param('id');
            
            if (!$parent_id) {
                throw new Shuriken_Validation_Exception(
                    __('Parent rating ID is required.', 'shuriken-reviews'),
                    'id'
                );
            }
            
            // Verify parent exists
            $parent = shuriken_db()->get_rating($parent_id);
            if (!$parent) {
                throw new Shuriken_Not_Found_Exception(
                    __('Parent rating not found.', 'shuriken-reviews'),
                    'rating',
                    $parent_id
                );
            }
            
            $ratings = shuriken_db()->get_child_ratings($parent_id);
            return rest_ensure_response($ratings);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Search ratings by name (for AJAX autocomplete)
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.9.0
     */
    public function search_ratings($request) {
        try {
            $search_term = $request->get_param('q');
            $limit = $request->get_param('limit');
            $type = $request->get_param('type');
            
            // Validate type parameter
            $valid_types = array('all', 'parents', 'mirrorable');
            if (!in_array($type, $valid_types, true)) {
                throw new Shuriken_Validation_Exception(
                    __('Invalid type parameter. Must be one of: all, parents, mirrorable.', 'shuriken-reviews'),
                    'type'
                );
            }
            
            // Validate limit
            if ($limit < 1 || $limit > 100) {
                throw new Shuriken_Validation_Exception(
                    __('Limit must be between 1 and 100.', 'shuriken-reviews'),
                    'limit'
                );
            }
            
            $ratings = shuriken_db()->search_ratings($search_term, $limit, $type);
            return rest_ensure_response($ratings);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get fresh rating statistics (bypasses cache)
     * 
     * Optimized to use batch query instead of individual queries.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_rating_stats($request) {
        try {
            $ids_string = $request->get_param('ids');
            $ids = array_map('intval', explode(',', $ids_string));
            $ids = array_filter($ids); // Remove any invalid IDs
            
            if (empty($ids)) {
                throw new Shuriken_Validation_Exception(
                    __('No valid rating IDs provided.', 'shuriken-reviews'),
                    'ids'
                );
            }
            
            // Use batch method for efficiency (single query instead of N queries)
            $ratings = shuriken_db()->get_ratings_by_ids($ids);
            
            $stats = array();
            foreach ($ratings as $id => $rating) {
                $stats[$id] = array(
                    'average' => $rating->average,
                    'total_votes' => $rating->total_votes,
                    'source_id' => $rating->source_id,
                );
            }
            
            return rest_ensure_response($stats);
        } catch (Shuriken_Exception $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get a fresh nonce (bypasses cache)
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_fresh_nonce($request) {
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

/**
 * Helper function to get REST API instance
 *
 * @return Shuriken_REST_API
 */
function shuriken_rest_api() {
    return Shuriken_REST_API::get_instance();
}

