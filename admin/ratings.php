<?php
if (!defined('ABSPATH')) exit;

// Enqueue admin styles and scripts
wp_enqueue_style(
    'shuriken-reviews-admin-ratings',
    SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/css/admin-ratings.css',
    array(),
    SHURIKEN_REVIEWS_VERSION
);

wp_enqueue_script(
    'shuriken-reviews-admin-ratings',
    SHURIKEN_REVIEWS_PLUGIN_URL . 'assets/js/admin-ratings.js',
    array('jquery'),
    SHURIKEN_REVIEWS_VERSION,
    true
);

// Localize script for translations
wp_localize_script('shuriken-reviews-admin-ratings', 'shurikenRatingsAdmin', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('shuriken-ratings-admin-nonce'),
    'i18n' => array(
        'confirmDelete' => __('Are you sure you want to delete this rating? This action cannot be undone.', 'shuriken-reviews'),
        'confirmBulkDelete' => __('Are you sure you want to delete the selected ratings? This action cannot be undone.', 'shuriken-reviews'),
        'copied' => __('Shortcode copied to clipboard!', 'shuriken-reviews'),
    )
));

// Get database instance
$db = shuriken_db();

$per_page = 20;
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
            'display_only' => $display_only
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
            <span class="dashicons dashicons-star-filled"></span>
            <h3><?php esc_html_e('No ratings yet', 'shuriken-reviews'); ?></h3>
            <p><?php esc_html_e('Create your first rating to get started!', 'shuriken-reviews'); ?></p>
        </div>
    <?php elseif (empty($ratings) && !empty($search)): ?>
        <div class="shuriken-ratings-empty-state">
            <span class="dashicons dashicons-search"></span>
            <h3><?php esc_html_e('No ratings found', 'shuriken-reviews'); ?></h3>
            <p><?php esc_html_e('Try adjusting your search criteria.', 'shuriken-reviews'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="button"><?php esc_html_e('Clear Search', 'shuriken-reviews'); ?></a>
        </div>
    <?php else: ?>

    <!-- Search Box (separate form, above bulk actions) -->
    <form class="search-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="shuriken-reviews">
        <p class="search-box">
            <label class="screen-reader-text" for="rating-search-input"><?php esc_html_e('Search Ratings:', 'shuriken-reviews'); ?></label>
            <input type="search" id="rating-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search ratings...', 'shuriken-reviews'); ?>">
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search Ratings', 'shuriken-reviews'); ?>">
            <?php if (!empty($search)): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="button"><?php esc_html_e('Clear', 'shuriken-reviews'); ?></a>
            <?php endif; ?>
        </p>
    </form>

    <form id="ratings-filter" method="post">
        <?php wp_nonce_field('shuriken_bulk_ratings', 'shuriken_bulk_nonce'); ?>
        
        <!-- Top Tablenav -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'shuriken-reviews'); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php esc_html_e('Bulk actions', 'shuriken-reviews'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'shuriken-reviews'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'shuriken-reviews'); ?>" onclick="return document.querySelectorAll('input[name=\'rating_ids[]\']:checked').length > 0 ? confirm(shurikenRatingsAdmin.i18n.confirmBulkDelete) : (alert('<?php echo esc_js(__('Please select at least one rating.', 'shuriken-reviews')); ?>'), false);">
            </div>

            <?php
            // Pagination - Top
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
                        
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e('Current Page'); ?></label>
                            <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr($current_page); ?>" size="1" aria-describedby="table-paging">
                            <span class="tablenav-paging-text">
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

        <!-- Ratings Table -->
        <table class="wp-list-table widefat fixed striped table-view-list ratings">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All'); ?></label>
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-type"><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-shortcode"><?php esc_html_e('Shortcode', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-stats"><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php foreach ($ratings as $rating):
                    $average = $rating->total_votes > 0 ? round($rating->total_rating / $rating->total_votes, 1) : 0;
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
                                    <span class="mirror-indicator dashicons dashicons-admin-links" title="<?php printf(esc_attr__('Mirror of: %s', 'shuriken-reviews'), esc_attr($mirror_original_name)); ?>"></span>
                                <?php elseif (!empty($rating->parent_id)): ?>
                                    <span class="sub-indicator dashicons dashicons-arrow-right-alt2" title="<?php printf(esc_attr__('Sub-rating of: %s', 'shuriken-reviews'), esc_attr($parent_name)); ?>"></span>
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
                        <td class="type column-type" data-colname="<?php esc_attr_e('Type', 'shuriken-reviews'); ?>">
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
                        </td>
                        <td class="shortcode column-shortcode" data-colname="<?php esc_attr_e('Shortcode', 'shuriken-reviews'); ?>">
                            <code class="shuriken-copy-shortcode" title="<?php esc_attr_e('Click to copy', 'shuriken-reviews'); ?>">[shuriken_rating id="<?php echo esc_attr($rating->id); ?>"]</code>
                        </td>
                        <td class="stats column-stats" data-colname="<?php esc_attr_e('Rating', 'shuriken-reviews'); ?>">
                            <div class="shuriken-rating-display">
                                <span class="shuriken-rating-stars" title="<?php printf(esc_attr__('%s out of 5', 'shuriken-reviews'), $average); ?>">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $stars_filled) {
                                            echo '<span class="star filled">★</span>';
                                        } elseif ($i == $stars_filled + 1 && $half_star) {
                                            echo '<span class="star half">★</span>';
                                        } else {
                                            echo '<span class="star empty">☆</span>';
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
                        </td>
                    </tr>
                    <!-- Inline Edit Row -->
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
                                        
                                        <?php if (!empty($rating->mirror_of)): ?>
                                        <label class="convert-mirror-label">
                                            <span class="title"><?php esc_html_e('Mirror Status', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <input type="checkbox" name="convert_from_mirror" value="1" class="convert-mirror-checkbox">
                                                <span class="description"><?php esc_html_e('Convert to independent rating (will start with 0 votes)', 'shuriken-reviews'); ?></span>
                                            </span>
                                        </label>
                                        <?php endif; ?>
                                        
                                        <label class="parent-label" style="<?php echo !empty($rating->mirror_of) ? 'display:none;' : ''; ?>">
                                            <span class="title"><?php esc_html_e('Parent', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <select name="parent_id" class="parent-select">
                                                    <option value=""><?php esc_html_e('— None (Standalone) —', 'shuriken-reviews'); ?></option>
                                                    <?php 
                                                    $available_parents = $db->get_parent_ratings($rating->id);
                                                    foreach ($available_parents as $parent): 
                                                    ?>
                                                        <option value="<?php echo esc_attr($parent->id); ?>" <?php selected($rating->parent_id, $parent->id); ?>>
                                                            <?php echo esc_html($parent->name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </span>
                                        </label>
                                        
                                        <label class="effect-type-label" style="<?php echo (empty($rating->parent_id) || !empty($rating->mirror_of)) ? 'display:none;' : ''; ?>">
                                            <span class="title"><?php esc_html_e('Effect', 'shuriken-reviews'); ?></span>
                                            <span class="input-text-wrap">
                                                <select name="effect_type" class="effect-type-select">
                                                    <option value="positive" <?php selected($rating->effect_type, 'positive'); ?>><?php esc_html_e('Positive (adds to parent)', 'shuriken-reviews'); ?></option>
                                                    <option value="negative" <?php selected($rating->effect_type, 'negative'); ?>><?php esc_html_e('Negative (subtracts from parent)', 'shuriken-reviews'); ?></option>
                                                </select>
                                            </span>
                                        </label>
                                        
                                        <label class="display-only-label" style="<?php echo (!empty($rating->parent_id) || !empty($rating->mirror_of)) ? 'display:none;' : ''; ?>">
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
                    <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-type"><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-shortcode"><?php esc_html_e('Shortcode', 'shuriken-reviews'); ?></th>
                    <th scope="col" class="manage-column column-stats"><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
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
                            <label for="mirror_of"><?php esc_html_e('Mirror of', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="mirror_of" id="mirror_of" class="regular-text">
                                <option value=""><?php esc_html_e('— Not a Mirror (Original Rating) —', 'shuriken-reviews'); ?></option>
                                <?php foreach ($mirrorable_ratings as $mirrorable): ?>
                                    <option value="<?php echo esc_attr($mirrorable->id); ?>">
                                        <?php echo esc_html($mirrorable->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select a rating to mirror. Mirrors share the same vote data but have different names/labels.', 'shuriken-reviews'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr id="parent-id-row">
                        <th scope="row">
                            <label for="parent_id"><?php esc_html_e('Parent Rating', 'shuriken-reviews'); ?></label>
                        </th>
                        <td>
                            <select name="parent_id" id="parent_id" class="regular-text">
                                <option value=""><?php esc_html_e('— None (Standalone Rating) —', 'shuriken-reviews'); ?></option>
                                <?php foreach ($parent_ratings as $parent): ?>
                                    <option value="<?php echo esc_attr($parent->id); ?>">
                                        <?php echo esc_html($parent->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select a parent to make this a sub-rating. Sub-ratings contribute to their parent\'s score.', 'shuriken-reviews'); ?>
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
                            <label for="display_only"><?php esc_html_e('Display Only', 'shuriken-reviews'); ?></label>
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