<?php
/**
 * Plugin Name: Shuriken Reviews
 * Description: Boosts wordpress comments with a added functionalities.
 * Version: 1.0.0
 * Author: Skilledup Hub
 * Author URI: https://skilledup.ir
 */

function exclude_author_comments_from_latest_comments($query) {
    // Only modify queries for Latest Comments block
    if (isset($query->query_vars['number']) && $query->query_vars['number'] > 0) {
        
        // Generate a unique transient key based on the current post
        $post_id = get_the_ID();
        $transient_key = 'excluded_author_comments_' . $post_id;
        
        // Try to get cached author ID
        $post_author_id = get_transient($transient_key);
        
        if (false === $post_author_id) {
            // Cache miss - get the post author's ID and cache it
            $post_author_id = get_post_field('post_author', $post_id);
            
            // Cache the author ID for 12 hours
            set_transient($transient_key, $post_author_id, 12 * HOUR_IN_SECONDS);
        }
        
        // Exclude comments from the post author
        if ($post_author_id) {
            $query->query_vars['author__not_in'] = array($post_author_id);
        }
    }
}
add_action('pre_get_comments', 'exclude_author_comments_from_latest_comments');

// Clear transients when comments are modified
function clear_author_comments_transients($comment_id) {
    $comment = get_comment($comment_id);
    $post_id = $comment->comment_post_ID;
    delete_transient('excluded_author_comments_' . $post_id);
}

// Hook into comment actions to clear cache
add_action('wp_insert_comment', 'clear_author_comments_transients');
add_action('edit_comment', 'clear_author_comments_transients');
add_action('delete_comment', 'clear_author_comments_transients');