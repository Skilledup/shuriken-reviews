<?php
/**
 * Content Settings Tab Partial
 *
 * Settings for post meta box and content injection.
 *
 * @package Shuriken_Reviews
 * @since 1.12.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_content_settings'])) {
    if (!wp_verify_nonce($_POST['shuriken_content_nonce'], 'shuriken_content_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }

    // Post types
    $post_types = isset($_POST['meta_box_post_types']) && is_array($_POST['meta_box_post_types'])
        ? array_map('sanitize_key', $_POST['meta_box_post_types'])
        : array();
    update_option('shuriken_meta_box_post_types', $post_types);

    // Injection position
    $position = isset($_POST['content_injection_position'])
        ? sanitize_key($_POST['content_injection_position'])
        : 'after';
    if (!in_array($position, array('before', 'after', 'disabled'), true)) {
        $position = 'after';
    }
    update_option('shuriken_content_injection_position', $position);

    echo '<div class="notice notice-success is-dismissible"><p>' .
        esc_html__('Settings saved successfully!', 'shuriken-reviews') .
        '</p></div>';
}

// Current values
$saved_post_types = get_option('shuriken_meta_box_post_types', array('post', 'page'));
if (!is_array($saved_post_types)) {
    $saved_post_types = array('post', 'page');
}
$saved_position = get_option('shuriken_content_injection_position', 'after');

// Available public post types
$available_post_types = get_post_types(array('public' => true), 'objects');
?>

<form method="post" action="" class="shuriken-settings-form">
    <?php wp_nonce_field('shuriken_content_settings', 'shuriken_content_nonce'); ?>

    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon">📦</span>
            <h3><?php esc_html_e('Post Meta Box', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label><?php esc_html_e('Enabled Post Types', 'shuriken-reviews'); ?></label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('Select which post types show the Shuriken Ratings meta box in the editor.', 'shuriken-reviews'); ?>
                </p>
                <div style="margin-top: 8px;">
                    <?php foreach ($available_post_types as $pt): ?>
                        <label style="display: block; margin-bottom: 4px;">
                            <input type="checkbox"
                                   name="meta_box_post_types[]"
                                   value="<?php echo esc_attr($pt->name); ?>"
                                   <?php checked(in_array($pt->name, $saved_post_types, true)); ?>>
                            <?php echo esc_html($pt->labels->singular_name); ?>
                            <code style="font-size: 11px; color: #94a3b8;"><?php echo esc_html($pt->name); ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon">📍</span>
            <h3><?php esc_html_e('Content Injection', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <div class="settings-field">
                <div class="settings-field-header">
                    <label for="content_injection_position"><?php esc_html_e('Rating Position', 'shuriken-reviews'); ?></label>
                </div>
                <p class="settings-field-description">
                    <?php esc_html_e('Where to automatically display linked ratings relative to the post content. Choose "Disabled" to only use shortcodes manually.', 'shuriken-reviews'); ?>
                </p>
                <select name="content_injection_position" id="content_injection_position" style="margin-top: 8px;">
                    <option value="before" <?php selected($saved_position, 'before'); ?>>
                        <?php esc_html_e('Before content', 'shuriken-reviews'); ?>
                    </option>
                    <option value="after" <?php selected($saved_position, 'after'); ?>>
                        <?php esc_html_e('After content', 'shuriken-reviews'); ?>
                    </option>
                    <option value="disabled" <?php selected($saved_position, 'disabled'); ?>>
                        <?php esc_html_e('Disabled (manual shortcodes only)', 'shuriken-reviews'); ?>
                    </option>
                </select>
            </div>
        </div>
    </div>

    <div class="shuriken-settings-submit">
        <button type="submit" name="save_content_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>
