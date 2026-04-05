<?php
if (!defined('ABSPATH')) exit;

// Note: Assets are enqueued via admin_enqueue_scripts hook in class-shuriken-admin.php
// This ensures they load properly regardless of WordPress language settings

// Get database instance
$db = shuriken_db();

/**
 * Get the type class for a rating type: 'continuous' or 'binary'
 */
function shuriken_get_type_class(string $type): string {
    return in_array($type, array('like_dislike', 'approval'), true) ? 'binary' : 'continuous';
}

$user_id = get_current_user_id();
$per_page = get_user_meta($user_id, 'shuriken_ratings_per_page', true);
$per_page = $per_page ? absint($per_page) : 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Handle bulk actions
if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['rating_ids']) && check_admin_referer('shuriken_bulk_ratings', 'shuriken_bulk_nonce')) {
    $ids = array_map('intval', $_POST['rating_ids']);
    $deleted = $db->delete_ratings($ids);
    
    if ($deleted) {
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             sprintf(esc_html(_n('%s rating deleted.', '%s ratings deleted.', $deleted, 'shuriken-reviews')), number_format_i18n($deleted)) . 
             '</p></div>';
    }
}

// Handle single actions
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['rating_id']) && check_admin_referer('shuriken_delete_rating_' . intval($_GET['rating_id']))) {
    $id = intval($_GET['rating_id']);
    $result = $db->delete_rating($id);
    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rating deleted successfully!', 'shuriken-reviews') . '</p></div>';
    }
}

// Show success message after redirect
if (isset($_GET['message']) && $_GET['message'] === 'created') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rating created successfully!', 'shuriken-reviews') . '</p></div>';
}

// Handle inline edit
if (isset($_POST['inline_edit']) && check_admin_referer('shuriken_inline_edit', 'shuriken_inline_nonce')) {
    $id = intval($_POST['rating_id']);
    $name = sanitize_text_field($_POST['rating_name']);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $effect_type = isset($_POST['effect_type']) ? sanitize_text_field($_POST['effect_type']) : 'positive';
    $display_only = isset($_POST['display_only']) && $_POST['display_only'] === '1';
    $convert_from_mirror = isset($_POST['convert_from_mirror']) && $_POST['convert_from_mirror'] === '1';

    if (!empty($name) && $id) {
        $update_data = array(
            'name' => $name,
            'parent_id' => $parent_id,
            'effect_type' => $effect_type,
            'display_only' => $display_only,
            'rating_type' => isset($_POST['rating_type']) ? sanitize_text_field($_POST['rating_type']) : 'stars',
            'scale' => isset($_POST['scale']) ? intval($_POST['scale']) : 5
        );
        
        // If converting from mirror, clear the mirror_of field
        if ($convert_from_mirror) {
            $update_data['mirror_of'] = null;
        }
        
        $result = $db->update_rating($id, $update_data);
        if ($result) {
            // Recalculate parent rating if this is a sub-rating
            if ($parent_id) {
                $db->recalculate_parent_rating($parent_id);
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rating updated successfully!', 'shuriken-reviews') . '</p></div>';
        }
    }
}

// Get all parent ratings for dropdown
$parent_ratings = $db->get_parent_ratings();

// Get all mirrorable ratings for dropdown
$mirrorable_ratings = $db->get_mirrorable_ratings();

// Search functionality
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$ratings_result = $db->get_ratings_paginated($per_page, $current_page, $search);
$ratings = $ratings_result->ratings;
$total_items = $ratings_result->total_count;
$total_pages = $ratings_result->total_pages;

// Get context usage counts (how many distinct posts/entities per rating)
$context_counts = $db->get_context_usage_counts();

// Hidden columns for Screen Options
$hidden_columns = get_hidden_columns(get_current_screen());
$col_class = function($col) use ($hidden_columns) {
    return in_array($col, $hidden_columns, true) ? ' hidden' : '';
};
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Ratings', 'shuriken-reviews'); ?></h1>
    <a href="#add-new-rating" class="page-title-action" onclick="document.getElementById('rating_name').focus(); return false;">
        <?php esc_html_e('Add New', 'shuriken-reviews'); ?>
    </a>
    
    <?php if (!empty($search)): ?>
        <span class="subtitle">
            <?php printf(esc_html__('Search results for: %s', 'shuriken-reviews'), '<strong>' . esc_html($search) . '</strong>'); ?>
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <?php if (empty($ratings) && empty($search)): ?>
        <div class="shuriken-ratings-empty-state">
            <?php Shuriken_Icons::render('star', array('width' => 100, 'height' => 100)); ?>
            <h3><?php esc_html_e('No ratings yet', 'shuriken-reviews'); ?></h3>
            <p><?php esc_html_e('Create your first rating to get started!', 'shuriken-reviews'); ?></p>
        </div>
    <?php elseif (empty($ratings) && !empty($search)): ?>
        <div class="shuriken-ratings-empty-state">
            <?php Shuriken_Icons::render('search', array('width' => 100, 'height' => 100)); ?>
            <h3><?php esc_html_e('No ratings found', 'shuriken-reviews'); ?></h3>
            <p><?php esc_html_e('Try adjusting your search criteria.', 'shuriken-reviews'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="button"><?php esc_html_e('Clear Search', 'shuriken-reviews'); ?></a>
        </div>
    <?php else: ?>

    <!-- Unified Toolbar: flex row with bulk-left, search-right -->
    <div class="shuriken-ratings-toolbar">

        <div class="toolbar-left">
            <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'shuriken-reviews'); ?></label>
            <select name="action" id="bulk-action-selector-top" form="ratings-filter">
                <option value="-1"><?php esc_html_e('Bulk actions', 'shuriken-reviews'); ?></option>
                <option value="delete"><?php esc_html_e('Delete', 'shuriken-reviews'); ?></option>
            </select>
            <input type="submit" form="ratings-filter" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'shuriken-reviews'); ?>" onclick="return document.querySelectorAll('input[name=\'rating_ids[]\']:checked').length > 0 ? confirm(shurikenRatingsAdmin.i18n.confirmBulkDelete) : (alert('<?php echo esc_js(__('Please select at least one rating.', 'shuriken-reviews')); ?>'), false);">
            <?php if ($total_items > 0): ?>
                <span class="displaying-num"><?php printf(esc_html(_n('%s item', '%s items', $total_items, 'shuriken-reviews')), number_format_i18n($total_items)); ?></span>
            <?php endif; ?>
        </div>

        <div class="toolbar-right">
            <form class="search-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="shuriken-reviews">
                <div class="search-box">
                    <label class="screen-reader-text" for="rating-search-input"><?php esc_html_e('Search Ratings:', 'shuriken-reviews'); ?></label>
                    <input type="search" id="rating-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search ratings...', 'shuriken-reviews'); ?>">
                    <button type="submit" class="search-submit-btn" aria-label="<?php esc_attr_e('Search Ratings', 'shuriken-reviews'); ?>">
                        <?php Shuriken_Icons::render('search', array('width' => 15, 'height' => 15)); ?>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="clear-search-btn" aria-label="<?php esc_attr_e('Clear search', 'shuriken-reviews'); ?>">
                            <?php Shuriken_Icons::render('x', array('width' => 15, 'height' => 15)); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    </div><!-- /.shuriken-ratings-toolbar -->

    <form id="ratings-filter" method="post">
        <?php wp_nonce_field('shuriken_bulk_ratings', 'shuriken_bulk_nonce'); ?>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="pagination-links">
                    <?php
                    if ($current_page > 1) {
                        printf('<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(remove_query_arg('paged')), __('First page'), '&laquo;');
                    } else {
                        printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&laquo;');
                    }
                    if ($current_page > 1) {
                        printf('<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', max(1, $current_page - 1))), __('Previous page'), '&lsaquo;');
                    } else {
                        printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&lsaquo;');
                    }
                    ?>
                    <span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e('Current Page'); ?></label>
                        <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="1" aria-describedby="table-paging">
                        <span class="tablenav-paging-text"><?php esc_html_e('of'); ?> <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span></span>
                    </span>
                    <?php
                    if ($current_page < $total_pages) {
                        printf('<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', min($total_pages, $current_page + 1))), __('Next page'), '&rsaquo;');
                    } else {
                        printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&rsaquo;');
                    }
                    if ($current_page < $total_pages) {
                        printf('<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                            esc_url(add_query_arg('paged', $total_pages)), __('Last page'), '&raquo;');
                    } else {
                        printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&raquo;');
                    }
                    ?>
                </span>
            </div>
            <br class="clear">
        </div>
        <?php endif; ?>

        <!-- Ratings Table -->
        <table class="wp-list-table widefat fixed striped table-view-list ratings">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All'); ?></label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="type" class="manage-column column-type<?php echo $col_class('type'); ?>"><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="shortcode" class="manage-column column-shortcode<?php echo $col_class('shortcode'); ?>"><?php esc_html_e('Shortcode', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="stats" class="manage-column column-stats<?php echo $col_class('stats'); ?>"><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php foreach ($ratings as $rating):
                    $r_type_early = isset($rating->rating_type) ? $rating->rating_type : 'stars';
                    $r_scale_early = isset($rating->scale) ? intval($rating->scale) : 5;
                    $average = isset($rating->display_average) ? $rating->display_average : 0;
                    $stars_filled = floor($average);
                    $half_star = ($average - $stars_filled) >= 0.5;
                    $edit_link = '#inline-edit-' . $rating->id;
                    $delete_url = wp_nonce_url(
                        admin_url('admin.php?page=shuriken-reviews&action=delete&rating_id=' . $rating->id),
                        'shuriken_delete_rating_' . $rating->id
                    );
                    
                    // Get parent name if exists
                    $parent_name = '';
                    if (!empty($rating->parent_id)) {
                        $parent = $db->get_rating($rating->parent_id);
                        if ($parent) {
                            $parent_name = $parent->name;
                        }
                    }
                    
                    // Get mirror original name if exists
                    $mirror_original_name = '';
                    if (!empty($rating->mirror_of)) {
                        $mirror_original = $db->get_rating($rating->mirror_of);
                        if ($mirror_original) {
                            $mirror_original_name = $mirror_original->name;
                        }
                    }
                    
                    // Get sub-ratings count
                    $sub_ratings = $db->get_sub_ratings($rating->id);
                    $sub_count = count($sub_ratings);
                    
                    // Get mirrors count
                    $mirrors = $db->get_mirrors($rating->id);
                    $mirror_count = count($mirrors);
                ?>
                    <tr id="rating-<?php echo esc_attr($rating->id); ?>" class="iedit <?php echo !empty($rating->parent_id) ? 'sub-rating' : ''; ?> <?php echo !empty($rating->mirror_of) ? 'mirror-rating' : ''; ?>">
                        <th scope="row" class="check-column">
                            <label class="screen-reader-text" for="cb-select-<?php echo esc_attr($rating->id); ?>">
                                <?php printf(esc_html__('Select %s', 'shuriken-reviews'), esc_html($rating->name)); ?>
                            </label>
                            <input id="cb-select-<?php echo esc_attr($rating->id); ?>" type="checkbox" name="rating_ids[]" value="<?php echo esc_attr($rating->id); ?>">
                        </th>
                        <td class="name column-name has-row-actions column-primary" data-colname="<?php esc_attr_e('Name', 'shuriken-reviews'); ?>">
                            <strong>
                                <?php if (!empty($rating->mirror_of)): ?>
                                    <span class="mirror-indicator" title="<?php printf(esc_attr__('Mirror of: %s', 'shuriken-reviews'), esc_attr($mirror_original_name)); ?>"><?php Shuriken_Icons::render('link', array('width' => 14, 'height' => 14)); ?></span>
                                <?php elseif (!empty($rating->parent_id)): ?>
                                    <span class="sub-indicator" title="<?php printf(esc_attr__('Sub-rating of: %s', 'shuriken-reviews'), esc_attr($parent_name)); ?>"><?php Shuriken_Icons::render('chevron-right', array('width' => 14, 'height' => 14)); ?></span>
                                <?php endif; ?>
                                <a class="row-title" href="<?php echo esc_attr($edit_link); ?>" aria-label="<?php printf(esc_attr__('Edit "%s"', 'shuriken-reviews'), esc_attr($rating->name)); ?>">
                                    <?php echo esc_html($rating->name); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="id"><?php printf(esc_html__('ID: %d', 'shuriken-reviews'), $rating->id); ?> | </span>
                                <span class="edit">
                                    <a href="<?php echo esc_attr($edit_link); ?>" class="editinline" aria-label="<?php printf(esc_attr__('Edit "%s"', 'shuriken-reviews'), esc_attr($rating->name)); ?>">
                                        <?php esc_html_e('Edit', 'shuriken-reviews'); ?>
                                    </a> | 
                                </span>
                                <span class="trash">
                                    <a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" aria-label="<?php printf(esc_attr__('Delete "%s"', 'shuriken-reviews'), esc_attr($rating->name)); ?>" onclick="return confirm(shurikenRatingsAdmin.i18n.confirmDelete);">
                                        <?php esc_html_e('Delete', 'shuriken-reviews'); ?>
                                    </a>
                                </span>
                            </div>
                            <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details'); ?></span></button>
                        </td>
                        <td class="type column-type<?php echo $col_class('type'); ?>" data-colname="<?php esc_attr_e('Type', 'shuriken-reviews'); ?>">
                            <?php if (!empty($rating->mirror_of)): ?>
                                <span class="rating-type mirror-badge">
                                    <?php esc_html_e('Mirror', 'shuriken-reviews'); ?>
                                </span>
                                <div class="mirror-info">
                                    <?php printf(esc_html__('of: %s', 'shuriken-reviews'), esc_html($mirror_original_name)); ?>
                                </div>
                            <?php elseif (!empty($rating->parent_id)): ?>
                                <span class="rating-type sub-rating-badge <?php echo esc_attr($rating->effect_type); ?>">
                                    <?php 
                                    if ($rating->effect_type === 'negative') {
                                        esc_html_e('Sub (Negative)', 'shuriken-reviews');
                                    } else {
                                        esc_html_e('Sub (Positive)', 'shuriken-reviews');
                                    }
                                    ?>
                                </span>
                                <div class="parent-info">
                                    <?php printf(esc_html__('Parent: %s', 'shuriken-reviews'), esc_html($parent_name)); ?>
                                </div>
                                <?php
                                // Type-compatibility warning for sub-ratings
                                if ($parent) {
                                    $child_class = shuriken_get_type_class(isset($rating->rating_type) ? $rating->rating_type : 'stars');
                                    $parent_class = shuriken_get_type_class(isset($parent->rating_type) ? $parent->rating_type : 'stars');
                                    if ($child_class !== $parent_class) :
                                ?>
                                    <div class="shuriken-type-warning" title="<?php esc_attr_e('Mixing binary and continuous rating types may produce incorrect aggregated scores.', 'shuriken-reviews'); ?>">
                                        <?php Shuriken_Icons::render('triangle-alert', array('width' => 16, 'height' => 16)); ?>
                                        <?php esc_html_e('Type mismatch', 'shuriken-reviews'); ?>
                                    </div>
                                <?php endif; } ?>
                            <?php elseif ($rating->display_only): ?>
                                <span class="rating-type parent-badge display-only">
                                    <?php esc_html_e('Display Only', 'shuriken-reviews'); ?>
                                </span>
                                <?php if ($sub_count > 0): ?>
                                    <div class="sub-count">
                                        <?php printf(esc_html(_n('%d sub-rating', '%d sub-ratings', $sub_count, 'shuriken-reviews')), $sub_count); ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($sub_count > 0): ?>
                                <span class="rating-type parent-badge">
                                    <?php esc_html_e('Parent', 'shuriken-reviews'); ?>
                                </span>
                                <div class="sub-count">
                                    <?php printf(esc_html(_n('%d sub-rating', '%d sub-ratings', $sub_count, 'shuriken-reviews')), $sub_count); ?>
                                </div>
                                <?php if ($mirror_count > 0): ?>
                                    <div class="mirror-count">
                                        <?php printf(esc_html(_n('%d mirror', '%d mirrors', $mirror_count, 'shuriken-reviews')), $mirror_count); ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="rating-type standalone-badge">
                                    <?php esc_html_e('Standalone', 'shuriken-reviews'); ?>
                                </span>
                                <?php if ($mirror_count > 0): ?>
                                    <div class="mirror-count">
                                        <?php printf(esc_html(_n('%d mirror', '%d mirrors', $mirror_count, 'shuriken-reviews')), $mirror_count); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php 
                            // Show rating type badge
                            $type_label = isset($rating->rating_type) ? $rating->rating_type : 'stars';
                            $type_labels = array(
                                'stars' => __('Stars', 'shuriken-reviews'),
                                'like_dislike' => __('Like/Dislike', 'shuriken-reviews'),
                                'numeric' => __('Numeric', 'shuriken-reviews'),
                                'approval' => __('Approval', 'shuriken-reviews'),
                            );
                            $type_display = isset($type_labels[$type_label]) ? $type_labels[$type_label] : $type_labels['stars'];
                            $scale_display = isset($rating->scale) ? intval($rating->scale) : 5;
                            ?>
                            <div class="rating-type-info">
                                <span class="rating-type-badge rating-type-<?php echo esc_attr($type_label); ?>"><?php echo esc_html($type_display); ?></span>
                                <?php if ($type_label === 'stars' || $type_label === 'numeric'): ?>
                                    <span class="rating-scale-badge"><?php printf(esc_html__('1-%d', 'shuriken-reviews'), $scale_display); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Show contextual usage badge
                            $ctx_count = isset($context_counts[(int) $rating->id]) ? $context_counts[(int) $rating->id] : 0;
                            if ($ctx_count > 0) :
                            ?>
                            <div class="context-usage-info">
                                <span class="context-usage-badge" title="<?php esc_attr_e('Per-post voting is active on this many posts/pages', 'shuriken-reviews'); ?>">
                                    <?php Shuriken_Icons::render('map-pin', array('width' => 14, 'height' => 14)); ?> <?php printf(esc_html(_n('%d post', '%d posts', $ctx_count, 'shuriken-reviews')), $ctx_count); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="shortcode column-shortcode<?php echo $col_class('shortcode'); ?>" data-colname="<?php esc_attr_e('Shortcode', 'shuriken-reviews'); ?>">
                            <code class="shuriken-copy-shortcode" title="<?php esc_attr_e('Click to copy', 'shuriken-reviews'); ?>">[shuriken_rating id="<?php echo esc_attr($rating->id); ?>"]</code>
                        </td>
                        <td class="stats column-stats<?php echo $col_class('stats'); ?>" data-colname="<?php esc_attr_e('Rating', 'shuriken-reviews'); ?>">
                            <?php
                            $r_type = isset($rating->rating_type) ? $rating->rating_type : 'stars';
                            $r_scale = isset($rating->scale) ? intval($rating->scale) : 5;
                            if ($r_type === 'like_dislike'):
                                $likes = intval($rating->total_rating);
                                $dislikes = intval($rating->total_votes) - $likes;
                            ?>
                            <div class="shuriken-rating-display">
                                <span class="like-dislike-stats">
                                    <span class="like-stat"><?php Shuriken_Icons::render('thumbs-up', array('width' => 14, 'height' => 14)); ?> <?php echo number_format_i18n($likes); ?></span>
                                    <span class="dislike-stat"><?php Shuriken_Icons::render('thumbs-down', array('width' => 14, 'height' => 14)); ?> <?php echo number_format_i18n($dislikes); ?></span>
                                </span>
                                <span class="rating-text">
                                    <?php
                                    $pct = $rating->total_votes > 0 ? round($likes / $rating->total_votes * 100) : 0;
                                    printf(
                                        esc_html__('%1$s%% positive (%2$s votes)', 'shuriken-reviews'),
                                        $pct,
                                        number_format_i18n($rating->total_votes)
                                    );
                                    ?>
                                </span>
                            </div>
                            <?php elseif ($r_type === 'approval'): ?>
                            <div class="shuriken-rating-display">
                                <span class="approval-stats"><?php Shuriken_Icons::render('thumbs-up', array('width' => 14, 'height' => 14)); ?> <?php echo number_format_i18n($rating->total_votes); ?></span>
                                <span class="rating-text">
                                    <?php printf(esc_html__('%s upvotes', 'shuriken-reviews'), number_format_i18n($rating->total_votes)); ?>
                                </span>
                            </div>
                            <?php elseif ($r_type === 'numeric'):
                                $fill_pct  = $r_scale > 0 ? min(100, round($average / $r_scale * 100)) : 0;
                            ?>
                            <div class="shuriken-rating-display shuriken-numeric-admin">
                                <div class="numeric-admin-bar" title="<?php printf(esc_attr__('%1$s out of %2$d', 'shuriken-reviews'), $average, $r_scale); ?>">
                                    <div class="numeric-admin-fill" style="width:<?php echo esc_attr($fill_pct); ?>%"></div>
                                </div>
                                <span class="rating-text">
                                    <strong><?php echo esc_html($average); ?></strong>/<?php echo esc_html($r_scale); ?>
                                    <span class="rating-votes">(<?php echo number_format_i18n($rating->total_votes); ?> <?php esc_html_e('votes', 'shuriken-reviews'); ?>)</span>
                                </span>
                            </div>
                            <?php else: ?>
                            <div class="shuriken-rating-display">
                                <span class="shuriken-rating-stars" title="<?php printf(esc_attr__('%1$s out of %2$d', 'shuriken-reviews'), $average, $r_scale); ?>">
                                    <?php
                                    $star_icons = Shuriken_Icons::rating_symbols(16);
                                    for ($i = 1; $i <= $r_scale; $i++) {
                                        if ($i <= $stars_filled) {
                                            echo '<span class="star filled">' . $star_icons['star_filled'] . '</span>';
                                        } elseif ($i == $stars_filled + 1 && $half_star) {
                                            echo '<span class="star half">' . $star_icons['star_filled'] . '</span>';
                                        } else {
                                            echo '<span class="star empty">' . $star_icons['star_empty'] . '</span>';
                                        }
                                    }
                                    ?>
                                </span>
                                <span class="rating-text">
                                    <?php
                                    printf(
                                        esc_html__('%1$s (%2$s votes)', 'shuriken-reviews'),
                                        '<strong>' . esc_html($average) . '</strong>',
                                        number_format_i18n($rating->total_votes)
                                    );
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Inline Edit Row -->
                    <?php
                    $has_votes = intval($rating->total_votes) > 0;
                    $is_mirror = !empty($rating->mirror_of);
                    $type_locked = $has_votes || $is_mirror;
                    $cur_type = isset($rating->rating_type) ? $rating->rating_type : 'stars';
                    $cur_scale = isset($rating->scale) ? intval($rating->scale) : 5;
                    ?>
                    <tr id="inline-edit-<?php echo esc_attr($rating->id); ?>" class="inline-edit-row inline-edit-row-rating hidden">
                        <td colspan="5" class="colspanchange">
                            <form method="post" class="inline-edit-form">
                                <?php wp_nonce_field('shuriken_inline_edit', 'shuriken_inline_nonce'); ?>
                                <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating->id); ?>">
                                
                                <fieldset class="inline-edit-col">
                                    <legend class="inline-edit-legend"><?php esc_html_e('Quick Edit', 'shuriken-reviews'); ?></legend>
                                    <div class="inline-edit-col">
                                        <label>
                                            <span class="title"><?php esc_html_e('Name', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <input type="text" name="rating_name" class="ptitle" value="<?php echo esc_attr($rating->name); ?>">
                                            </span>
                                        </label>
                                        
                                        <label>
                                            <span class="title"><?php esc_html_e('Type', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <?php if ($type_locked): ?>
                                                    <input type="hidden" name="rating_type" value="<?php echo esc_attr($cur_type); ?>">
                                                    <span class="rating-type-locked">
                                                        <?php
                                                        $type_labels = array(
                                                            'stars' => __('Stars', 'shuriken-reviews'),
                                                            'like_dislike' => __('Like / Dislike', 'shuriken-reviews'),
                                                            'numeric' => __('Numeric', 'shuriken-reviews'),
                                                            'approval' => __('Approval', 'shuriken-reviews'),
                                                        );
                                                        echo esc_html($type_labels[$cur_type] ?? $cur_type);
                                                        if ($is_mirror) {
                                                            echo ' — <em>' . esc_html__('inherited from source', 'shuriken-reviews') . '</em>';
                                                        } elseif ($has_votes) {
                                                            echo ' — <em>' . esc_html__('locked (has votes)', 'shuriken-reviews') . '</em>';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    <select name="rating_type" class="rating-type-select">
                                                        <option value="stars" <?php selected($cur_type, 'stars'); ?>><?php esc_html_e('Stars', 'shuriken-reviews'); ?></option>
                                                        <option value="like_dislike" <?php selected($cur_type, 'like_dislike'); ?>><?php esc_html_e('Like / Dislike', 'shuriken-reviews'); ?></option>
                                                        <option value="numeric" <?php selected($cur_type, 'numeric'); ?>><?php esc_html_e('Numeric', 'shuriken-reviews'); ?></option>
                                                        <option value="approval" <?php selected($cur_type, 'approval'); ?>><?php esc_html_e('Approval (Upvote)', 'shuriken-reviews'); ?></option>
                                                    </select>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                        
                                        <?php if (!$type_locked): ?>
                                        <label class="scale-label">
                                            <span class="title"><?php esc_html_e('Scale', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <input type="number" name="scale" class="small-text" value="<?php echo esc_attr($cur_scale); ?>" min="2" max="<?php echo ($cur_type === 'numeric') ? '100' : '10'; ?>">
                                            </span>
                                        </label>
                                        <?php else: ?>
                                        <input type="hidden" name="scale" value="<?php echo esc_attr($cur_scale); ?>">
                                        <?php endif; ?>
                                        
                                        <?php if ($is_mirror): ?>
                                        <label class="convert-mirror-label">
                                            <span class="title"><?php esc_html_e('Mirror Status', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <input type="checkbox" name="convert_from_mirror" value="1" class="convert-mirror-checkbox">
                                                <span class="description"><?php esc_html_e('Convert to independent rating (will start with 0 votes)', 'shuriken-reviews'); ?></span>
                                            </span>
                                        </label>
                                        <?php endif; ?>
                                        
                                        <label class="parent-label" style="<?php echo $is_mirror ? 'display:none;' : ''; ?>">
                                            <span class="title"><?php esc_html_e('Parent', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <select name="parent_id" class="parent-select">
                                                    <option value=""><?php esc_html_e('— None (Standalone) —', 'shuriken-reviews'); ?></option>
                                                    <?php 
                                                    $available_parents = $db->get_parent_ratings($rating->id);
                                                    foreach ($available_parents as $parent): 
                                                    ?>
                                                        <option value="<?php echo esc_attr($parent->id); ?>" data-rating-type="<?php echo esc_attr(isset($parent->rating_type) ? $parent->rating_type : 'stars'); ?>" <?php selected($rating->parent_id, $parent->id); ?>>
                                                            <?php echo esc_html($parent->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="shuriken-inline-type-warning shuriken-type-warning" style="display:none; margin-top: 4px;">
                                                    <?php Shuriken_Icons::render('triangle-alert', array('width' => 16, 'height' => 16)); ?>
                                                    <?php esc_html_e('Type mismatch: Mixing binary and continuous types.', 'shuriken-reviews'); ?>
                                                </div>
                                            </span>
                                        </label>
                                        
                                        <label class="effect-type-label" style="<?php echo (empty($rating->parent_id) || $is_mirror) ? 'display:none;' : ''; ?>">
                                            <span class="title"><?php esc_html_e('Effect', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <select name="effect_type" class="effect-type-select">
                                                    <option value="positive" <?php selected($rating->effect_type, 'positive'); ?>><?php esc_html_e('Positive (adds to parent)', 'shuriken-reviews'); ?></option>
                                                    <option value="negative" <?php selected($rating->effect_type, 'negative'); ?>><?php esc_html_e('Negative (subtracts from parent)', 'shuriken-reviews'); ?></option>
                                                </select>
                                            </span>
                                        </label>
                                        
                                        <label class="display-only-label" style="<?php echo (!empty($rating->parent_id) || $is_mirror) ? 'display:none;' : ''; ?>">
                                            <span class="title"><?php esc_html_e('Display Only', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <input type="checkbox" name="display_only" value="1" <?php checked($rating->display_only, 1); ?>>
                                                <span class="description"><?php esc_html_e('Visitors cannot vote directly (only via sub-ratings)', 'shuriken-reviews'); ?></span>
                                            </span>
                                        </label>
                                    </div>
                                </fieldset>
                                
                                <div class="submit inline-edit-save">
                                    <button type="button" class="button cancel alignleft"><?php esc_html_e('Cancel', 'shuriken-reviews'); ?></button>
                                    <button type="submit" name="inline_edit" class="button button-primary save alignright"><?php esc_html_e('Update', 'shuriken-reviews'); ?></button>
                                    <span class="spinner"></span>
                                    <br class="clear">
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e('Select All'); ?></label>
                        <input id="cb-select-all-2" type="checkbox">
                    </td>
                    <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="type" class="manage-column column-type<?php echo $col_class('type'); ?>"><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="shortcode" class="manage-column column-shortcode<?php echo $col_class('shortcode'); ?>"><?php esc_html_e('Shortcode', 'shuriken-reviews'); ?></th>
                    <th scope="col" id="stats" class="manage-column column-stats<?php echo $col_class('stats'); ?>"><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Bottom Tablenav -->
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'shuriken-reviews'); ?></label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php esc_html_e('Bulk actions', 'shuriken-reviews'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'shuriken-reviews'); ?></option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'shuriken-reviews'); ?>" onclick="this.form.action.value = this.form.action2.value; return document.querySelectorAll('input[name=\'rating_ids[]\']:checked').length > 0 ? confirm(shurikenRatingsAdmin.i18n.confirmBulkDelete) : (alert('<?php echo esc_js(__('Please select at least one rating.', 'shuriken-reviews')); ?>'), false);">
            </div>

            <?php
            // Pagination - Bottom
            if ($total_pages > 1) {
                ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(esc_html(_n('%s item', '%s items', $total_items, 'shuriken-reviews')), number_format_i18n($total_items)); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        // First page
                        if ($current_page > 1) {
                            printf('<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url(remove_query_arg('paged')),
                                __('First page'),
                                '&laquo;'
                            );
                        } else {
                            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&laquo;');
                        }
                        
                        // Previous page
                        if ($current_page > 1) {
                            printf('<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url(add_query_arg('paged', max(1, $current_page - 1))),
                                __('Previous page'),
                                '&lsaquo;'
                            );
                        } else {
                            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&lsaquo;');
                        }
                        ?>
                        
                        <span class="screen-reader-text"><?php esc_html_e('Current Page'); ?></span>
                        <span id="table-paging" class="paging-input">
                            <span class="tablenav-paging-text">
                                <?php echo esc_html($current_page); ?>
                                <?php esc_html_e('of'); ?>
                                <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span>
                            </span>
                        </span>
                        
                        <?php
                        // Next page
                        if ($current_page < $total_pages) {
                            printf('<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url(add_query_arg('paged', min($total_pages, $current_page + 1))),
                                __('Next page'),
                                '&rsaquo;'
                            );
                        } else {
                            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&rsaquo;');
                        }
                        
                        // Last page
                        if ($current_page < $total_pages) {
                            printf('<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url(add_query_arg('paged', $total_pages)),
                                __('Last page'),
                                '&raquo;'
                            );
                        } else {
                            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span>', '&raquo;');
                        }
                        ?>
                    </span>
                </div>
                <?php
            }
            ?>
            <br class="clear">
        </div>
    </form>
    <?php endif; ?>

    <!-- Add New Rating Form -->
    <div id="add-new-rating" class="shuriken-ratings-form-wrap">
        <h2><?php esc_html_e('Add New Rating', 'shuriken-reviews'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>">
            <?php wp_nonce_field('shuriken_create_rating', 'shuriken_rating_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="rating_name"><?php esc_html_e('Rating Name', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="rating_name" 
                                   id="rating_name" 
                                   class="regular-text" 
                                   required
                                   placeholder="<?php esc_attr_e('Enter rating name...', 'shuriken-reviews'); ?>">
                            <p class="description">
                                <?php esc_html_e('Enter a descriptive name for this rating. This will be displayed to users.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="rating_type"><?php esc_html_e('Rating Type', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="rating_type" id="rating_type" class="regular-text">
                                <option value="stars"><?php esc_html_e('Stars', 'shuriken-reviews'); ?></option>
                                <option value="like_dislike"><?php esc_html_e('Like / Dislike', 'shuriken-reviews'); ?></option>
                                <option value="numeric"><?php esc_html_e('Numeric', 'shuriken-reviews'); ?></option>
                                <option value="approval"><?php esc_html_e('Approval (Upvote)', 'shuriken-reviews'); ?></option>
                                <option value="mirror"><?php esc_html_e('Mirror', 'shuriken-reviews'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose how visitors interact with this rating. A mirror shares vote data with an existing rating.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="mirror-source-row" style="display: none;">
                        <th scope="row">
                            <label for="mirror_of"><?php esc_html_e('Mirror Source', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="mirror_of" id="mirror_of" class="regular-text">
                                <option value=""><?php esc_html_e('— Select a rating to mirror —', 'shuriken-reviews'); ?></option>
                                <?php foreach ($mirrorable_ratings as $mirrorable): ?>
                                    <option value="<?php echo esc_attr($mirrorable->id); ?>">
                                        <?php echo esc_html($mirrorable->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select the rating to mirror. Mirrors share the same vote data and inherit type/scale from the source.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="scale-row">
                        <th scope="row">
                            <label for="scale"><?php esc_html_e('Scale', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="scale" id="scale" class="small-text" value="5" min="2" max="10">
                            <p class="description" id="scale-description">
                                <?php esc_html_e('Number of stars (2-10) or numeric slider max (2-100).', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="sub-rating-row">
                        <th scope="row">
                            <?php esc_html_e('Sub-rating', 'shuriken-reviews'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_sub_rating" name="is_sub_rating" value="1">
                                <?php esc_html_e('This is a sub-rating of another rating', 'shuriken-reviews'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr id="parent-id-row" style="display: none;">
                        <th scope="row">
                            <label for="parent_id"><?php esc_html_e('Parent Rating', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="parent_id" id="parent_id" class="regular-text">
                                <?php foreach ($parent_ratings as $parent): ?>
                                    <option value="<?php echo esc_attr($parent->id); ?>" data-rating-type="<?php echo esc_attr(isset($parent->rating_type) ? $parent->rating_type : 'stars'); ?>">
                                        <?php echo esc_html($parent->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="add-new-type-warning" class="shuriken-type-warning" style="display:none; margin-top: 6px;">
                                <?php Shuriken_Icons::render('triangle-alert', array('width' => 16, 'height' => 16)); ?>
                                <?php esc_html_e('Type mismatch: Mixing binary and continuous rating types may produce incorrect aggregated scores.', 'shuriken-reviews'); ?>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Select the parent rating. Sub-ratings contribute to their parent\'s score.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="effect-type-row" style="display: none;">
                        <th scope="row">
                            <label for="effect_type"><?php esc_html_e('Effect on Parent', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="effect_type" id="effect_type" class="regular-text">
                                <option value="positive"><?php esc_html_e('Positive — Votes add to parent rating', 'shuriken-reviews'); ?></option>
                                <option value="negative"><?php esc_html_e('Negative — Votes subtract from parent rating', 'shuriken-reviews'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Positive: Higher votes improve parent score. Negative: Higher votes lower parent score (e.g., "Difficulty" or "Price").', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="display-only-row">
                        <th scope="row">
                            <?php esc_html_e('Display Only', 'shuriken-reviews'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="display_only" id="display_only" value="1">
                                <?php esc_html_e('Make this rating display-only (no direct voting)', 'shuriken-reviews'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Enable this for parent ratings where visitors should only vote via sub-ratings.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" 
                       name="create_rating" 
                       class="button button-primary" 
                       value="<?php esc_attr_e('Add New Rating', 'shuriken-reviews'); ?>">
            </p>
        </form>
    </div>
</div>