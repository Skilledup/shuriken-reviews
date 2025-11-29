<?php
/**
 * WordPress Comments System Functions
 *
 * Handles modifications to WordPress's native commenting system,
 * including the Latest Comments block customization and filtering.
 *
 * @package Shuriken_Reviews
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modifies the comments query to exclude specific types of comments from the Latest Comments block.
 * Excludes author comments and/or reply comments based on plugin settings.
 * Uses transients to cache author IDs for better performance.
 *
 * @param WP_Comment_Query $query The WordPress comment query object
 * @return void
 * @since 1.0.0
 */
function exclude_comments_from_latest_comments($query) {
    // Only modify queries for Latest Comments block
    if (isset($query->query_vars['number']) && $query->query_vars['number'] > 0) {
        
        // Exclude author comments if enabled
        if (get_option('shuriken_exclude_author_comments', '1')) {
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

        // Exclude reply comments if enabled
        if (get_option('shuriken_exclude_reply_comments', '1')) {
            $query->query_vars['parent'] = 0; // Only show top-level comments
        }
    }
}
add_action('pre_get_comments', 'exclude_comments_from_latest_comments');

/**
 * Clear transients when comments are modified.
 *
 * @param int $comment_id The comment ID.
 * @return void
 * @since 1.0.0
 */
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
 * Enqueue Swiper.js for the comments slider.
 *
 * @return void
 * @since 1.0.0
 */
function enqueue_swiper_for_comments() {
    // Make sure WordPress functions exist before using them
    if (!function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script')) {
        return;
    }
    
    // Enqueue Swiper CSS and JS
    wp_enqueue_style('swiper-style', 'https://unpkg.com/swiper/swiper-bundle.min.css');
    wp_enqueue_script('swiper-script', 'https://unpkg.com/swiper/swiper-bundle.min.js', array('jquery'), null, true);
    
    // Add custom initialization script
    if (function_exists('wp_add_inline_script')) {
        wp_add_inline_script('swiper-script', '
            document.addEventListener("DOMContentLoaded", function() {
                if (document.querySelector(".comments-swiper-container")) {
                    const commentsSwiper = new Swiper(".comments-swiper-container", {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        pagination: {
                            el: ".swiper-pagination",
                            clickable: true,
                        },
                        // Navigation buttons removed as requested
                        breakpoints: {
                            640: {
                                slidesPerView: 2,
                                spaceBetween: 20,
                            },
                            1024: {
                                slidesPerView: 3,
                                spaceBetween: 20,
                            }
                        }
                    });
                }
            });
        ');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_swiper_for_comments');

/**
 * Filter block content before it is rendered.
 * Customizes the Latest Comments block to use a Swiper slider.
 * 
 * @param string $block_content The block content about to be rendered.
 * @param array  $block         The full block, including name and attributes.
 * @return string Modified block content.
 * @since 1.0.0
 */
function customize_latest_comments_block($block_content, $block) {
    if ($block['blockName'] !== 'core/latest-comments') {
        return $block_content;
    }

    // Load the HTML into a DOMDocument
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($block_content, 'HTML-ENTITIES', 'UTF-8'));
    
    // Get the comments list
    $comments_list = $dom->getElementsByTagName('ol')->item(0);
    
    if (!$comments_list) {
        return $block_content;
    }
    
    // Create Swiper container structure
    $swiper_container = $dom->createElement('div');
    $swiper_container->setAttribute('class', 'comments-swiper-container swiper');
    
    $swiper_wrapper = $dom->createElement('div');
    $swiper_wrapper->setAttribute('class', 'swiper-wrapper');
    
    // Move each comment into a swiper slide
    $comments = $comments_list->getElementsByTagName('li');
    $comments_array = array();
    
    // Convert the live NodeList to an array for easier manipulation
    foreach ($comments as $comment) {
        $comments_array[] = $comment;
    }
    
    foreach ($comments_array as $comment) {
        $slide = $dom->createElement('div');
        $slide->setAttribute('class', 'swiper-slide');
        $comment_clone = $comment->cloneNode(true);
        $slide->appendChild($comment_clone);
        $swiper_wrapper->appendChild($slide);
    }
    
    $swiper_container->appendChild($swiper_wrapper);
    
    // Navigation buttons removed as requested
    
    // Add pagination
    $pagination = $dom->createElement('div');
    $pagination->setAttribute('class', 'swiper-pagination');
    $swiper_container->appendChild($pagination);
    
    // Replace the original comments list with our swiper
    $comments_list->parentNode->replaceChild($swiper_container, $comments_list);
    
    // Add CSS styling
    $style = $dom->createElement('style');
    $style->textContent = '
        .comments-swiper-container {
            width: 100%;
            height: auto;
            margin-bottom: 30px;
            padding: 40px 10px;
            position: relative;
        }
        @media (max-width: 767px) {
            .comments-swiper-container {
                padding: 40px 0;
            }
        }
        .swiper-slide {
            height: auto;
            box-sizing: border-box;
        }
        .wp-block-latest-comments__comment {
            border: solid 1px #e0e0e0;
            padding: 15px !important;
            border-radius: 8px !important;
            margin: 0 !important;
            height: 100%;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .wp-block-latest-comments__comment:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 7px rgba(0,0,0,0.1);
        }
        .wp-block-latest-comments__comment-author {
            font-weight: bold !important;
            display: block !important;
            margin-bottom: 5px !important;
            color: #333;
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
            line-height: 1.5;
        }
        .swiper-button-next, .swiper-button-prev {
            color: #333;
        }
        .swiper-pagination-bullet-active {
            background-color: #333;
        }
        /* Hide the default comments list styling */
        .wp-block-latest-comments {
            list-style: none !important;
            padding: 0 !important;
        }
    ';
    $dom->appendChild($style);

    // Convert back to HTML string
    $modified_content = $dom->saveHTML();

    return $modified_content;
}
add_filter('render_block', 'customize_latest_comments_block', 10, 2);
