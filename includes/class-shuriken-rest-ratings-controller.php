<?php
/**
 * Shuriken Reviews REST Ratings Controller
 *
 * Handles all rating CRUD, hierarchy, mirror, search, and batch endpoints.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_REST_Ratings_Controller
 *
 * @since 1.15.5
 */
class Shuriken_REST_Ratings_Controller {

    use Shuriken_REST_Permissions;

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
     * Register rating-related REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Ratings collection endpoint
        register_rest_route($this->namespace, '/ratings', array(
            array(
                'methods'             => 'GET',
                'callback'            => $this->get_ratings(...),
                'permission_callback' => $this->can_edit_posts(...),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => $this->create_rating(...),
                'permission_callback' => $this->can_manage_options(...),
                'args'                => $this->get_rating_create_args(),
            ),
        ));

        // Single rating endpoint
        register_rest_route($this->namespace, '/ratings/(?P<id>\d+)', array(
            array(
                'methods'             => 'GET',
                'callback'            => $this->get_single_rating(...),
                'permission_callback' => $this->can_edit_posts(...),
                'args'                => $this->get_rating_id_args(),
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => $this->update_rating(...),
                'permission_callback' => $this->can_manage_options(...),
                'args'                => $this->get_rating_update_args(),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => $this->delete_rating(...),
                'permission_callback' => $this->can_manage_options(...),
                'args'                => $this->get_rating_id_args(),
            ),
        ));

        // Parent ratings endpoint
        register_rest_route($this->namespace, '/ratings/parents', array(
            'methods'             => 'GET',
            'callback'            => $this->get_parent_ratings(...),
            'permission_callback' => $this->can_edit_posts(...),
        ));

        // Mirrorable ratings endpoint
        register_rest_route($this->namespace, '/ratings/mirrorable', array(
            'methods'             => 'GET',
            'callback'            => $this->get_mirrorable_ratings(...),
            'permission_callback' => $this->can_edit_posts(...),
        ));

        // Search ratings endpoint (for AJAX autocomplete)
        register_rest_route($this->namespace, '/ratings/search', array(
            'methods'             => 'GET',
            'callback'            => $this->search_ratings(...),
            'permission_callback' => $this->can_edit_posts(...),
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
                    'enum'              => array('all', 'parents', 'mirrorable', 'parents_and_mirrors'),
                    'description'       => 'Filter by rating type',
                ),
            ),
        ));

        // Get child ratings of a parent
        register_rest_route($this->namespace, '/ratings/(?P<id>\d+)/children', array(
            'methods'             => 'GET',
            'callback'            => $this->get_child_ratings(...),
            'permission_callback' => $this->can_edit_posts(...),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Parent rating ID',
                ),
            ),
        ));

        // Get mirrors of a rating
        register_rest_route($this->namespace, '/ratings/(?P<id>\d+)/mirrors', array(
            'methods'             => 'GET',
            'callback'            => $this->get_rating_mirrors(...),
            'permission_callback' => $this->can_edit_posts(...),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => 'Rating ID to get mirrors for',
                ),
            ),
        ));

        // Batch ratings endpoint (fetch multiple by IDs, editor only)
        register_rest_route($this->namespace, '/ratings/batch', array(
            'methods'             => 'GET',
            'callback'            => $this->get_ratings_batch(...),
            'permission_callback' => $this->can_edit_posts(...),
            'args'                => array(
                'ids' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Comma-separated list of rating IDs (max 50)',
                ),
            ),
        ));
    }

    // =========================================================================
    // Argument Definitions
    // =========================================================================

    /**
     * Get rating ID argument definition
     *
     * @return array
     */
    private function get_rating_id_args(): array {
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
    private function get_rating_create_args(): array {
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
            'rating_type' => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => 'stars',
                'enum'              => Shuriken_Rating_Type::values(),
            ),
            'scale' => array(
                'required'          => false,
                'type'              => 'integer',
                'default'           => 5,
                'sanitize_callback' => 'absint',
            ),
            'label_description' => array(
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    /**
     * Get rating update argument definitions
     *
     * @return array
     */
    private function get_rating_update_args(): array {
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
            'rating_type' => array(
                'required'          => false,
                'type'              => 'string',
                'enum'              => Shuriken_Rating_Type::values(),
            ),
            'scale' => array(
                'required'          => false,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ),
            'label_description' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }

    // =========================================================================
    // Handler Methods
    // =========================================================================

    /**
     * Get all ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_ratings(\WP_REST_Request $request): \WP_REST_Response {
        $ratings = $this->db->get_all_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Create a new rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_rating(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $name = $request->get_param('name');
            $parent_id = $request->get_param('parent_id');
            $mirror_of = $request->get_param('mirror_of');
            $effect_type = $request->get_param('effect_type') ?: 'positive';
            $display_only = $request->get_param('display_only') ?: false;
            $rating_type = $request->get_param('rating_type') ?: 'stars';
            $scale = $request->get_param('scale') ?: Shuriken_Database::RATING_SCALE_DEFAULT;
            $label_description = $request->get_param('label_description') ?: null;
            
            // Convert 0 to null for parent_id and mirror_of
            $parent_id = $parent_id ? intval($parent_id) : null;
            $mirror_of = $mirror_of ? intval($mirror_of) : null;
            
            $new_id = $this->db->create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of, $rating_type, $scale, $label_description);
            
            if ($new_id === false) {
                throw Shuriken_Database_Exception::insert_failed('ratings');
            }
            
            $rating = $this->db->get_rating($new_id);
            $response_data = (array) $rating;

            // Warn if mirror inherited a different type/scale from its source
            if ($mirror_of) {
                $source = $this->db->get_rating($mirror_of);
                if ($source) {
                    $source_type = $source->rating_type ?? 'stars';
                    $source_scale = (int) ($source->scale ?? Shuriken_Database::RATING_SCALE_DEFAULT);
                    if ($rating_type !== $source_type || (int) $scale !== $source_scale) {
                        $response_data['mirror_notice'] = sprintf(
                            /* translators: 1: source type, 2: source scale */
                            __('Mirror inherited type "%1$s" (scale %2$d) from the source rating.', 'shuriken-reviews'),
                            $source_type,
                            $source_scale
                        );
                    }
                }
            }

            // Warn if child type is incompatible with parent type
            if ($parent_id) {
                $parent = $this->db->get_rating($parent_id);
                if ($parent) {
                    $parent_type_enum = Shuriken_Rating_Type::tryFrom($parent->rating_type ?? 'stars') ?? Shuriken_Rating_Type::Stars;
                    $child_type_enum  = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;
                    if ($parent_type_enum->typeClass() !== $child_type_enum->typeClass()) {
                        $response_data['type_warning'] = __('This sub-rating type is incompatible with the parent\'s type. Aggregated scores may be incorrect.', 'shuriken-reviews');
                    }
                }
            }

            return rest_ensure_response($response_data);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get a single rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_single_rating(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $id = $request->get_param('id');
            $rating = $this->db->get_rating($id);
            
            if (!$rating) {
                throw Shuriken_Not_Found_Exception::rating($id);
            }
            
            return rest_ensure_response($rating);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Update a rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_rating(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $id = $request->get_param('id');
            
            // Check if rating exists
            $existing = $this->db->get_rating($id);
            if (!$existing) {
                throw Shuriken_Not_Found_Exception::rating($id);
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
            
            if ($request->has_param('rating_type')) {
                $update_data['rating_type'] = $request->get_param('rating_type');
            }
            
            if ($request->has_param('scale')) {
                $update_data['scale'] = $request->get_param('scale');
            }
            
            if ($request->has_param('label_description')) {
                $desc = $request->get_param('label_description');
                $update_data['label_description'] = ($desc === '' || $desc === null) ? null : $desc;
            }
            
            if (empty($update_data)) {
                throw Shuriken_Validation_Exception::required_field('update_data');
            }
            
            $result = $this->db->update_rating($id, $update_data);
            
            if ($result === false) {
                throw Shuriken_Database_Exception::update_failed('ratings', $id);
            }
            
            // Recalculate parent rating if this is a sub-rating
            if (!empty($update_data['parent_id'])) {
                $this->db->recalculate_parent_rating($update_data['parent_id']);
            }
            // Also recalculate old parent if parent changed
            if (!empty($existing->parent_id) && (empty($update_data['parent_id']) || $existing->parent_id != $update_data['parent_id'])) {
                $this->db->recalculate_parent_rating($existing->parent_id);
            }
            
            $rating = $this->db->get_rating($id);
            $response_data = (array) $rating;

            // Warn if child type is incompatible with parent type
            $effective_parent_id = isset($update_data['parent_id']) ? $update_data['parent_id'] : ($existing->parent_id ?? null);
            $effective_type = isset($update_data['rating_type']) ? $update_data['rating_type'] : ($rating->rating_type ?? 'stars');
            if ($effective_parent_id) {
                $parent = $this->db->get_rating($effective_parent_id);
                if ($parent) {
                    $parent_type_enum = Shuriken_Rating_Type::tryFrom($parent->rating_type ?? 'stars') ?? Shuriken_Rating_Type::Stars;
                    $child_type_enum  = Shuriken_Rating_Type::tryFrom($effective_type) ?? Shuriken_Rating_Type::Stars;
                    if ($parent_type_enum->typeClass() !== $child_type_enum->typeClass()) {
                        $response_data['type_warning'] = __('This sub-rating type is incompatible with the parent\'s type. Aggregated scores may be incorrect.', 'shuriken-reviews');
                    }
                }
            }

            return rest_ensure_response($response_data);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Delete a rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function delete_rating(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $id = $request->get_param('id');
            
            // Check if rating exists
            $existing = $this->db->get_rating($id);
            if (!$existing) {
                throw Shuriken_Not_Found_Exception::rating($id);
            }

            // Store parent_id before deletion for recalculation
            $parent_id = $existing->parent_id;
            
            // Delete the rating
            $result = $this->db->delete_rating($id);
            
            if ($result === false) {
                throw Shuriken_Database_Exception::delete_failed('ratings', $id);
            }

            // Recalculate parent rating if this was a sub-rating
            if (!empty($parent_id)) {
                $this->db->recalculate_parent_rating($parent_id);
            }
            
            return rest_ensure_response(array(
                'deleted' => true,
                'id' => $id
            ));
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get parent ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_parent_ratings(\WP_REST_Request $request): \WP_REST_Response {
        $ratings = $this->db->get_parent_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Get mirrorable ratings
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_mirrorable_ratings(\WP_REST_Request $request): \WP_REST_Response {
        $ratings = $this->db->get_mirrorable_ratings();
        return rest_ensure_response($ratings);
    }

    /**
     * Get child ratings of a parent rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.9.0
     */
    public function get_child_ratings(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $parent_id = $request->get_param('id');
            
            if (!$parent_id) {
                throw Shuriken_Validation_Exception::required_field('id');
            }
            
            // Verify parent exists
            $parent = $this->db->get_rating($parent_id);
            if (!$parent) {
                throw Shuriken_Not_Found_Exception::parent_rating($parent_id);
            }
            
            $ratings = $this->db->get_child_ratings($parent_id);
            return rest_ensure_response($ratings);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Get mirrors of a rating
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 2.1.0
     */
    public function get_rating_mirrors(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $id = $request->get_param('id');

            if (!$id) {
                throw Shuriken_Validation_Exception::required_field('id');
            }

            // Verify rating exists
            $rating = $this->db->get_rating($id);
            if (!$rating) {
                throw Shuriken_Not_Found_Exception::rating($id);
            }

            $mirrors = $this->db->get_mirrors($id);
            return rest_ensure_response($mirrors);
        } catch (Shuriken_Exception_Interface $e) {
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
    public function search_ratings(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $search_term = $request->get_param('q');
            $limit = $request->get_param('limit');
            $type = $request->get_param('type');
            
            // Validate type parameter
            $valid_types = array('all', 'parents', 'mirrorable', 'parents_and_mirrors');
            if (!in_array($type, $valid_types, true)) {
                throw Shuriken_Validation_Exception::invalid_value('type', $type, 'all, parents, mirrorable, or parents_and_mirrors');
            }
            
            // Validate limit
            if ($limit < 1 || $limit > Shuriken_Database::SEARCH_LIMIT_MAX) {
                throw Shuriken_Validation_Exception::out_of_range('limit', $limit, 1, Shuriken_Database::SEARCH_LIMIT_MAX);
            }
            
            $ratings = $this->db->search_ratings($search_term, $limit, $type);
            return rest_ensure_response($ratings);
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }

    /**
     * Batch-fetch multiple ratings by ID
     *
     * Returns full rating objects with mirror vote data resolved.
     * Capped at 50 IDs per request to prevent abuse.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     * @since 1.11.1
     */
    public function get_ratings_batch(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        try {
            $ids_string = $request->get_param('ids');
            $ids = array_map('intval', explode(',', $ids_string));
            $ids = array_filter($ids);

            if (empty($ids)) {
                throw Shuriken_Validation_Exception::required_field('ids');
            }

            if (count($ids) > Shuriken_Database::BATCH_IDS_MAX) {
                throw Shuriken_Validation_Exception::out_of_range('ids count', count($ids), 1, Shuriken_Database::BATCH_IDS_MAX);
            }

            $ratings = $this->db->get_ratings_by_ids($ids);
            return rest_ensure_response(array_values($ratings));
        } catch (Shuriken_Exception_Interface $e) {
            return Shuriken_Exception_Handler::handle_rest_exception($e);
        }
    }
}
