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
    
    update_option('shuriken_allow_guest_voting', $allow_guest_voting);
    
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
    
    <div class="shuriken-settings-submit">
        <button type="submit" name="save_general_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>
