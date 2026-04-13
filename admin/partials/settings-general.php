<?php
/**
 * General Settings Tab Partial
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_general_settings'])) {
    if (!wp_verify_nonce($_POST['shuriken_general_nonce'], 'shuriken_general_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }
    
    $allow_guest_voting = isset($_POST['allow_guest_voting']) ? '1' : '0';
    $archive_sort_enabled = isset($_POST['shuriken_archive_sort_enabled']) ? '1' : '0';
    $archive_sort_rating_id = isset($_POST['shuriken_archive_sort_rating']) ? absint($_POST['shuriken_archive_sort_rating']) : 0;
    $archive_sort_orderby = isset($_POST['shuriken_archive_sort_orderby']) && in_array($_POST['shuriken_archive_sort_orderby'], array('average', 'votes'), true)
        ? sanitize_key($_POST['shuriken_archive_sort_orderby'])
        : 'average';
    $comments_system_enabled  = isset($_POST['shuriken_comments_system_enabled']) ? '1' : '0';
    $exclude_author_comments  = isset($_POST['shuriken_exclude_author_comments']) ? '1' : '0';
    $exclude_reply_comments   = isset($_POST['shuriken_exclude_reply_comments']) ? '1' : '0';

    $allowed_capabilities = array('manage_options', 'edit_others_posts', 'edit_posts', 'custom');
    $rest_write_cap = isset($_POST['shuriken_rest_write_capability'])
        ? sanitize_key($_POST['shuriken_rest_write_capability'])
        : 'manage_options';
    if (!in_array($rest_write_cap, $allowed_capabilities, true)) {
        $rest_write_cap = 'manage_options';
    }
    $rest_write_cap_custom = '';
    if ($rest_write_cap === 'custom') {
        $rest_write_cap_custom = isset($_POST['shuriken_rest_write_capability_custom'])
            ? sanitize_key($_POST['shuriken_rest_write_capability_custom'])
            : '';
        // Fall back to manage_options if custom field was left blank
        if (empty($rest_write_cap_custom)) {
            $rest_write_cap = 'manage_options';
        }
    }
    
    update_option('shuriken_allow_guest_voting', $allow_guest_voting);
    update_option('shuriken_archive_sort_enabled', $archive_sort_enabled);
    update_option('shuriken_archive_sort_rating', $archive_sort_rating_id);
    update_option('shuriken_archive_sort_orderby', $archive_sort_orderby);
    update_option('shuriken_comments_system_enabled', $comments_system_enabled);
    update_option('shuriken_exclude_author_comments', $exclude_author_comments);
    update_option('shuriken_exclude_reply_comments', $exclude_reply_comments);
    update_option('shuriken_rest_write_capability', $rest_write_cap);
    update_option('shuriken_rest_write_capability_custom', $rest_write_cap_custom);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . 
        esc_html__('Settings saved successfully!', 'shuriken-reviews') . 
        '</p></div>';
}
?>

<form method="post" action="" class="shuriken-settings-form">
    <?php wp_nonce_field('shuriken_general_settings', 'shuriken_general_nonce'); ?>
    
    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon"><?php Shuriken_Icons::render('vote'); ?></span>
            <h3><?php esc_html_e('Voting Settings', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label for="allow_guest_voting"><?php esc_html_e('Guest Voting', 'shuriken-reviews'); ?></label>
                    <label class="shuriken-toggle">
                        <input type="checkbox" 
                               name="allow_guest_voting" 
                               id="allow_guest_voting"
                               value="1"
                               <?php checked('1', get_option('shuriken_allow_guest_voting', '0')); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('Allow guests to vote without logging in. Guest votes are tracked by IP address to prevent multiple votes on the same item.', 'shuriken-reviews'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon"><?php Shuriken_Icons::render('bar-chart-2'); ?></span>
            <h3><?php esc_html_e('Archive Sorting', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label for="shuriken_archive_sort_enabled"><?php esc_html_e('Sort Archives by Rating', 'shuriken-reviews'); ?></label>
                    <label class="shuriken-toggle">
                        <input type="checkbox"
                               name="shuriken_archive_sort_enabled"
                               id="shuriken_archive_sort_enabled"
                               value="1"
                               <?php checked('1', get_option('shuriken_archive_sort_enabled', '0')); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('When enabled, archive pages will be sorted by per-post rating scores using contextual votes.', 'shuriken-reviews'); ?>
                </p>
            </div>
            <div class="settings-field">
                <label for="shuriken_archive_sort_rating"><?php esc_html_e('Rating to Sort By', 'shuriken-reviews'); ?></label>
                <select name="shuriken_archive_sort_rating" id="shuriken_archive_sort_rating">
                    <option value="0"><?php esc_html_e('— Select a Rating —', 'shuriken-reviews'); ?></option>
                    <?php
                    $all_ratings = shuriken_db()->get_all_ratings('name', 'ASC');
                    $saved_rating = get_option('shuriken_archive_sort_rating', 0);
                    foreach ($all_ratings as $r) :
                        // Skip display-only parents and mirrors
                        if (!empty($r->display_only) || !empty($r->mirror_of)) {
                            continue;
                        }
                    ?>
                        <option value="<?php echo esc_attr($r->id); ?>" <?php selected($saved_rating, $r->id); ?>>
                            <?php echo esc_html($r->name); ?> (ID: <?php echo esc_html($r->id); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="settings-field-description">
                    <?php esc_html_e('Choose which rating\'s contextual votes should determine archive order.', 'shuriken-reviews'); ?>
                </p>
            </div>
            <div class="settings-field">
                <label for="shuriken_archive_sort_orderby"><?php esc_html_e('Order By', 'shuriken-reviews'); ?></label>
                <select name="shuriken_archive_sort_orderby" id="shuriken_archive_sort_orderby">
                    <?php $saved_orderby = get_option('shuriken_archive_sort_orderby', 'average'); ?>
                    <option value="average" <?php selected($saved_orderby, 'average'); ?>><?php esc_html_e('Average Rating', 'shuriken-reviews'); ?></option>
                    <option value="votes" <?php selected($saved_orderby, 'votes'); ?>><?php esc_html_e('Total Votes', 'shuriken-reviews'); ?></option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- WordPress Comments System Card -->
    <div class="shuriken-settings-card shuriken-settings-card-highlight">
        <div class="settings-card-header">
            <span class="settings-card-icon"><?php Shuriken_Icons::render('message-square'); ?></span>
            <h3><?php esc_html_e('WordPress Comments System', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label for="shuriken_comments_system_enabled"><?php esc_html_e('Enable Comments System Modifier (Beta)', 'shuriken-reviews'); ?></label>
                    <label class="shuriken-toggle">
                        <input type="checkbox"
                               name="shuriken_comments_system_enabled"
                               id="shuriken_comments_system_enabled"
                               value="1"
                               <?php checked('1', get_option('shuriken_comments_system_enabled', '')); ?>
                               data-controls="comments-system-options">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('When enabled, Shuriken Reviews modifies the WordPress comments system — filtering the Latest Comments block and turning it into a Swiper slider. Disable to leave WordPress comments completely unmodified.', 'shuriken-reviews'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Comments System Options (collapsible) -->
    <div id="comments-system-options" class="shuriken-settings-collapsible <?php echo get_option('shuriken_comments_system_enabled', '') === '1' ? 'is-expanded' : ''; ?>">
        <div class="shuriken-settings-card">
            <div class="settings-card-header">
                <span class="settings-card-icon"><?php Shuriken_Icons::render('wrench'); ?></span>
                <h3><?php esc_html_e('Latest Comments Block Filtering', 'shuriken-reviews'); ?></h3>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <div class="settings-field-header">
                        <label for="shuriken_exclude_author_comments"><?php esc_html_e('Exclude Author Comments', 'shuriken-reviews'); ?></label>
                        <label class="shuriken-toggle">
                            <input type="checkbox"
                                   name="shuriken_exclude_author_comments"
                                   id="shuriken_exclude_author_comments"
                                   value="1"
                                   <?php checked('1', get_option('shuriken_exclude_author_comments', '1')); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p class="settings-field-description">
                        <?php esc_html_e('When enabled, comments made by post authors will not appear in the Latest Comments block.', 'shuriken-reviews'); ?>
                    </p>
                </div>
                <div class="settings-field">
                    <div class="settings-field-header">
                        <label for="shuriken_exclude_reply_comments"><?php esc_html_e('Show Only Top-Level Comments', 'shuriken-reviews'); ?></label>
                        <label class="shuriken-toggle">
                            <input type="checkbox"
                                   name="shuriken_exclude_reply_comments"
                                   id="shuriken_exclude_reply_comments"
                                   value="1"
                                   <?php checked('1', get_option('shuriken_exclude_reply_comments', '1')); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <p class="settings-field-description">
                        <?php esc_html_e('When enabled, only top-level comments are shown in the Latest Comments block — replies are hidden.', 'shuriken-reviews'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- REST API Access Card -->
    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon"><?php Shuriken_Icons::render('key-round'); ?></span>
            <h3><?php esc_html_e('REST API Access', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <label for="shuriken_rest_write_capability"><?php esc_html_e('Write Operations (POST / PUT / DELETE)', 'shuriken-reviews'); ?></label>
                <?php
                $saved_cap = get_option('shuriken_rest_write_capability', 'manage_options');
                ?>
                <select name="shuriken_rest_write_capability"
                        id="shuriken_rest_write_capability"
                        data-controls-custom="rest-write-cap-custom">
                    <option value="manage_options" <?php selected($saved_cap, 'manage_options'); ?>>
                        <?php esc_html_e('Administrator (manage_options)', 'shuriken-reviews'); ?>
                    </option>
                    <option value="edit_others_posts" <?php selected($saved_cap, 'edit_others_posts'); ?>>
                        <?php esc_html_e('Editor (edit_others_posts)', 'shuriken-reviews'); ?>
                    </option>
                    <option value="edit_posts" <?php selected($saved_cap, 'edit_posts'); ?>>
                        <?php esc_html_e('Author (edit_posts)', 'shuriken-reviews'); ?>
                    </option>
                    <option value="custom" <?php selected($saved_cap, 'custom'); ?>>
                        <?php esc_html_e('Custom…', 'shuriken-reviews'); ?>
                    </option>
                </select>
                <div id="rest-write-cap-custom"
                     class="settings-field settings-field-inline <?php echo $saved_cap === 'custom' ? '' : 'hidden'; ?>"
                     style="margin-top:8px;">
                    <label for="shuriken_rest_write_capability_custom"><?php esc_html_e('Custom capability slug', 'shuriken-reviews'); ?></label>
                    <input type="text"
                           name="shuriken_rest_write_capability_custom"
                           id="shuriken_rest_write_capability_custom"
                           value="<?php echo esc_attr(get_option('shuriken_rest_write_capability_custom', '')); ?>"
                           placeholder="e.g. manage_ratings"
                           class="regular-text">
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('Minimum capability required to create, update, or delete ratings via the REST API. Defaults to Administrator. Useful for multi-author sites that need editors or authors to manage ratings programmatically.', 'shuriken-reviews'); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="shuriken-settings-submit">
        <button type="submit" name="save_general_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>

<script>
(function() {
    var sel = document.getElementById('shuriken_rest_write_capability');
    var customDiv = document.getElementById('rest-write-cap-custom');
    if (sel && customDiv) {
        sel.addEventListener('change', function() {
            customDiv.classList.toggle('hidden', sel.value !== 'custom');
        });
    }
})();
</script>
