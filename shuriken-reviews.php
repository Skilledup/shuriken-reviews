<?php
/**
 * Plugin Name: Shuriken Reviews
 * Description: Boosts wordpress comments with a added functionalities.
 * Version: 1.0.0
 * Author: Skilledup Hub
 * Author URI: https://skilledup.ir
 * Text Domain: shuriken-reviews
 * Domain Path: /languages
 */


/**
 * Excludes comments made by post authors from the Latest Comments block in WordPress.
 * This function helps to filter out author responses from the comment feed,
 * showing only visitor comments in the Latest Comments block widget.
 *
 * @since 1.0.0
 * @return void
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


/**
 * Filter block content before it is rendered.
 * 
 * @param string $block_content The block content about to be rendered
 * @param array $block The full block, including name and attributes
 * @return string Modified block content
 * @since 1.0.0
 */

function customize_latest_comments_block($block_content, $block) {
    // Only modify Latest Comments block
    if ($block['blockName'] !== 'core/latest-comments') {
        return $block_content;
    }

    // Parse the existing comments from the block content
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($block_content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    // Add CSS for the grid layout
    $style = $dom->createElement('style');
    $style->textContent = '
        .wp-block-latest-comments {
            display: grid !important;
            grid-template-columns: 1fr !important; /* Default to single column for mobile */
            gap: 1.2rem !important;
        }
        .wp-block-latest-comments .wp-block-latest-comments__comment {
            border: solid 1px #e0e0e0;
            padding: 15px !important;
            border-radius: 8px !important;
            margin: 0 !important;
        }
        .wp-block-latest-comments__comment-author {
            font-weight: bold !important;
            display: block !important;
            margin-bottom: 5px !important;
        }
        .wp-block-latest-comments__comment-date {
            color: #666 !important;
            font-size: 0.9em !important;
            margin-bottom: 10px !important;
            display: block !important;
        }
        .wp-block-latest-comments__comment-excerpt p {
            margin: 0 !important;
            margin-bottom: 10px !important;
        }
        @media (min-width: 768px) {
            .wp-block-latest-comments {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (min-width: 1024px) {
            .wp-block-latest-comments {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
    ';
    $dom->appendChild($style);

    // Convert back to HTML string
    $modified_content = $dom->saveHTML();

    return $modified_content;
}
add_filter('render_block', 'customize_latest_comments_block', 10, 2);