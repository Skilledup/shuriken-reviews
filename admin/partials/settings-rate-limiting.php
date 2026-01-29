<?php
/**
 * Rate Limiting Settings Tab Partial
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_rate_limiting_settings'])) {
    if (!wp_verify_nonce($_POST['shuriken_rate_limiting_nonce'], 'shuriken_rate_limiting_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }
    
    // Save all rate limiting options
    $enabled = isset($_POST['rate_limiting_enabled']) ? '1' : '0';
    $vote_cooldown = max(0, intval($_POST['vote_cooldown']));
    $hourly_limit = max(1, intval($_POST['hourly_vote_limit']));
    $daily_limit = max(1, intval($_POST['daily_vote_limit']));
    $guest_hourly_limit = max(1, intval($_POST['guest_hourly_limit']));
    $guest_daily_limit = max(1, intval($_POST['guest_daily_limit']));
    
    update_option('shuriken_rate_limiting_enabled', $enabled);
    update_option('shuriken_vote_cooldown', $vote_cooldown);
    update_option('shuriken_hourly_vote_limit', $hourly_limit);
    update_option('shuriken_daily_vote_limit', $daily_limit);
    update_option('shuriken_guest_hourly_limit', $guest_hourly_limit);
    update_option('shuriken_guest_daily_limit', $guest_daily_limit);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . 
        esc_html__('Rate limiting settings saved successfully!', 'shuriken-reviews') . 
        '</p></div>';
}

// Get current values with defaults
$enabled = get_option('shuriken_rate_limiting_enabled', '0');
$vote_cooldown = get_option('shuriken_vote_cooldown', 60);
$hourly_limit = get_option('shuriken_hourly_vote_limit', 30);
$daily_limit = get_option('shuriken_daily_vote_limit', 100);
$guest_hourly_limit = get_option('shuriken_guest_hourly_limit', 10);
$guest_daily_limit = get_option('shuriken_guest_daily_limit', 30);
?>

<form method="post" action="" class="shuriken-settings-form">
    <?php wp_nonce_field('shuriken_rate_limiting_settings', 'shuriken_rate_limiting_nonce'); ?>
    
    <!-- Enable Rate Limiting Card -->
    <div class="shuriken-settings-card shuriken-settings-card-highlight">
        <div class="settings-card-header">
            <span class="settings-card-icon">🛡️</span>
            <h3><?php esc_html_e('Rate Limiting', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label for="rate_limiting_enabled"><?php esc_html_e('Enable Rate Limiting', 'shuriken-reviews'); ?></label>
                    <label class="shuriken-toggle">
                        <input type="checkbox" 
                               name="rate_limiting_enabled" 
                               id="rate_limiting_enabled"
                               value="1"
                               <?php checked('1', $enabled); ?>
                               data-controls="rate-limiting-options">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('Prevent voting abuse by limiting how often users can vote. When disabled, users can vote without any restrictions.', 'shuriken-reviews'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Rate Limiting Options (collapsible) -->
    <div id="rate-limiting-options" class="shuriken-settings-collapsible <?php echo $enabled === '1' ? 'is-expanded' : ''; ?>">
        
        <!-- Vote Cooldown Card -->
        <div class="shuriken-settings-card">
            <div class="settings-card-header">
                <span class="settings-card-icon">⏱️</span>
                <h3><?php esc_html_e('Vote Cooldown', 'shuriken-reviews'); ?></h3>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <div class="settings-field-header">
                        <label for="vote_cooldown"><?php esc_html_e('Cooldown Period', 'shuriken-reviews'); ?></label>
                    </div>
                    <div class="settings-input-group">
                        <input type="number" 
                               name="vote_cooldown" 
                               id="vote_cooldown"
                               value="<?php echo esc_attr($vote_cooldown); ?>"
                               min="0"
                               max="86400"
                               class="small-text">
                        <span class="input-suffix"><?php esc_html_e('seconds', 'shuriken-reviews'); ?></span>
                    </div>
                    <p class="settings-field-description">
                        <?php esc_html_e('Minimum time between votes on the same rating item. Set to 0 to disable cooldown. Recommended: 60 seconds.', 'shuriken-reviews'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Member Limits Card -->
        <div class="shuriken-settings-card">
            <div class="settings-card-header">
                <span class="settings-card-icon">👤</span>
                <h3><?php esc_html_e('Member Limits', 'shuriken-reviews'); ?></h3>
            </div>
            <div class="settings-card-body">
                <p class="settings-card-intro">
                    <?php esc_html_e('Limits for logged-in users (identified by user ID).', 'shuriken-reviews'); ?>
                </p>
                <div class="settings-fields-row">
                    <div class="settings-field">
                        <div class="settings-field-header">
                            <label for="hourly_vote_limit"><?php esc_html_e('Hourly Limit', 'shuriken-reviews'); ?></label>
                        </div>
                        <div class="settings-input-group">
                            <input type="number" 
                                   name="hourly_vote_limit" 
                                   id="hourly_vote_limit"
                                   value="<?php echo esc_attr($hourly_limit); ?>"
                                   min="1"
                                   max="1000"
                                   class="small-text">
                            <span class="input-suffix"><?php esc_html_e('votes/hour', 'shuriken-reviews'); ?></span>
                        </div>
                    </div>
                    <div class="settings-field">
                        <div class="settings-field-header">
                            <label for="daily_vote_limit"><?php esc_html_e('Daily Limit', 'shuriken-reviews'); ?></label>
                        </div>
                        <div class="settings-input-group">
                            <input type="number" 
                                   name="daily_vote_limit" 
                                   id="daily_vote_limit"
                                   value="<?php echo esc_attr($daily_limit); ?>"
                                   min="1"
                                   max="10000"
                                   class="small-text">
                            <span class="input-suffix"><?php esc_html_e('votes/day', 'shuriken-reviews'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Guest Limits Card -->
        <div class="shuriken-settings-card">
            <div class="settings-card-header">
                <span class="settings-card-icon">👥</span>
                <h3><?php esc_html_e('Guest Limits', 'shuriken-reviews'); ?></h3>
            </div>
            <div class="settings-card-body">
                <p class="settings-card-intro">
                    <?php esc_html_e('Limits for guests (identified by IP address). These are typically stricter to prevent abuse.', 'shuriken-reviews'); ?>
                </p>
                <div class="settings-fields-row">
                    <div class="settings-field">
                        <div class="settings-field-header">
                            <label for="guest_hourly_limit"><?php esc_html_e('Hourly Limit', 'shuriken-reviews'); ?></label>
                        </div>
                        <div class="settings-input-group">
                            <input type="number" 
                                   name="guest_hourly_limit" 
                                   id="guest_hourly_limit"
                                   value="<?php echo esc_attr($guest_hourly_limit); ?>"
                                   min="1"
                                   max="1000"
                                   class="small-text">
                            <span class="input-suffix"><?php esc_html_e('votes/hour', 'shuriken-reviews'); ?></span>
                        </div>
                    </div>
                    <div class="settings-field">
                        <div class="settings-field-header">
                            <label for="guest_daily_limit"><?php esc_html_e('Daily Limit', 'shuriken-reviews'); ?></label>
                        </div>
                        <div class="settings-input-group">
                            <input type="number" 
                                   name="guest_daily_limit" 
                                   id="guest_daily_limit"
                                   value="<?php echo esc_attr($guest_daily_limit); ?>"
                                   min="1"
                                   max="10000"
                                   class="small-text">
                            <span class="input-suffix"><?php esc_html_e('votes/day', 'shuriken-reviews'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Developer Note -->
    <div class="shuriken-settings-note">
        <span class="note-icon">💡</span>
        <div class="note-content">
            <strong><?php esc_html_e('For Developers:', 'shuriken-reviews'); ?></strong>
            <p><?php 
                printf(
                    /* translators: %s: filter hook name */
                    esc_html__('Use the %s filter to bypass rate limiting for specific users or roles.', 'shuriken-reviews'),
                    '<code>shuriken_bypass_rate_limit</code>'
                ); 
            ?></p>
        </div>
    </div>
    
    <div class="shuriken-settings-submit">
        <button type="submit" name="save_rate_limiting_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>
