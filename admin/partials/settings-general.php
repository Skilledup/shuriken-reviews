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
    
    update_option('shuriken_allow_guest_voting', $allow_guest_voting);
    update_option('shuriken_archive_sort_enabled', $archive_sort_enabled);
    update_option('shuriken_archive_sort_rating', $archive_sort_rating_id);
    update_option('shuriken_archive_sort_orderby', $archive_sort_orderby);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . 
        esc_html__('Settings saved successfully!', 'shuriken-reviews') . 
        '</p></div>';
}
?>

<form method="post" action="" class="shuriken-settings-form">
    <?php wp_nonce_field('shuriken_general_settings', 'shuriken_general_nonce'); ?>
    
    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon">🗳️</span>
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
            <span class="settings-card-icon">📊</span>
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
    
    <div class="shuriken-settings-submit">
        <button type="submit" name="save_general_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>
