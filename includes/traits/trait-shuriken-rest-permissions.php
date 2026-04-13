<?php
/**
 * REST API permission callbacks shared across all controllers.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Shuriken_REST_Permissions
 *
 * @since 1.15.5
 */
trait Shuriken_REST_Permissions {

    /**
     * Check if user can edit posts
     *
     * @param WP_REST_Request $request The request object (optional).
     * @return bool
     */
    public function can_edit_posts(\WP_REST_Request $request = null): bool {
        return current_user_can('edit_posts');
    }

    /**
     * Check if user can manage options (write operations)
     *
     * Filterable via `shuriken_rest_manage_capability` to allow editors/authors on multi-author sites.
     * Default requires `manage_options` (administrator).
     *
     * @param WP_REST_Request $request The request object (optional).
     * @return bool
     */
    public function can_manage_options(\WP_REST_Request $request = null): bool {
        $stored = get_option('shuriken_rest_write_capability', 'manage_options');
        if ($stored === 'custom') {
            $stored = get_option('shuriken_rest_write_capability_custom', '') ?: 'manage_options';
        }
        $capability = apply_filters('shuriken_rest_manage_capability', $stored);
        return current_user_can($capability);
    }
}
