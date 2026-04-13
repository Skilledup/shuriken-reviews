<?php
/**
 * Shuriken Reviews Settings Page
 *
 * Main settings page with tabbed navigation.
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define available tabs
$tabs = array(
    'general' => array(
        'label' => __('General', 'shuriken-reviews'),
        'icon'  => 'settings',
        'file'  => 'settings-general.php',
    ),
    'rate-limiting' => array(
        'label' => __('Rate Limiting', 'shuriken-reviews'),
        'icon'  => 'shield',
        'file'  => 'settings-rate-limiting.php',
    ),
    'about' => array(
        'label' => __('About', 'shuriken-reviews'),
        'icon'  => 'info',
        'file'  => 'settings-about.php',
    ),
);

/**
 * Filter the available settings tabs.
 *
 * @since 1.10.0
 * @param array $tabs Array of tab definitions.
 */
$tabs = apply_filters('shuriken_settings_tabs', $tabs);

// Get current tab from URL, default to 'general'
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

// Validate current tab exists
if (!isset($tabs[$current_tab])) {
    $current_tab = 'general';
}

// Build base URL for tabs
$base_url = admin_url('admin.php?page=shuriken-reviews-settings');
?>

<div class="wrap shuriken-settings">
    <h1><?php esc_html_e('Shuriken Reviews Settings', 'shuriken-reviews'); ?></h1>
    
    <!-- Tab Navigation -->
    <nav class="shuriken-settings-tabs" aria-label="<?php esc_attr_e('Settings tabs', 'shuriken-reviews'); ?>">
        <?php foreach ($tabs as $tab_key => $tab) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, $base_url)); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
               <?php echo $current_tab === $tab_key ? 'aria-current="page"' : ''; ?>>
                <span class="tab-icon"><?php Shuriken_Icons::render($tab['icon'], array('width' => 16, 'height' => 16)); ?></span>
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <?php
    // Dismissible rate-limit warning — shown on every tab until dismissed
    $rate_limit_enabled   = get_option('shuriken_rate_limiting_enabled', '0');
    $rate_limit_dismissed = get_option('shuriken_rate_limit_warning_dismissed', '0');
    if ($rate_limit_enabled !== '1' && $rate_limit_dismissed !== '1') : ?>
    <div class="shuriken-rate-limit-warning" id="shuriken-rate-limit-warning">
        <span class="warning-icon"><?php Shuriken_Icons::render('triangle-alert'); ?></span>
        <div class="warning-content">
            <strong><?php esc_html_e('Rate Limiting is disabled', 'shuriken-reviews'); ?></strong>
            <p><?php
                printf(
                    /* translators: %s: link to rate limiting tab */
                    esc_html__('Your site is currently unprotected against vote spamming and abuse. We strongly recommend %s to safeguard your ratings.', 'shuriken-reviews'),
                    '<a href="' . esc_url(add_query_arg('tab', 'rate-limiting', $base_url)) . '">' . esc_html__('enabling rate limiting', 'shuriken-reviews') . '</a>'
                );
            ?></p>
        </div>
        <button type="button" class="shuriken-dismiss-warning" aria-label="<?php esc_attr_e('Dismiss', 'shuriken-reviews'); ?>">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Tab Content + Sidebar -->
    <div class="shuriken-settings-layout">
        <div class="shuriken-settings-main">
            <div class="shuriken-tab-content">
                <?php
                // Load the partial for the current tab
                $partial_file = plugin_dir_path(__FILE__) . 'partials/' . $tabs[$current_tab]['file'];
                
                if (file_exists($partial_file)) {
                    include $partial_file;
                } else {
                    echo '<div class="notice notice-error"><p>' . 
                        esc_html__('Settings tab file not found.', 'shuriken-reviews') . 
                        '</p></div>';
                }
                ?>
            </div>
        </div>

        <aside class="shuriken-settings-sidebar">
            <?php if ($current_tab === 'general') : ?>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('lightbulb'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Settings not applying?', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('Some themes or plugins may override these options through code. If a setting seems to have no effect, contact your theme author to check for overriding filters.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('users'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Guest Voting', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('Enabling guest voting increases engagement, but make sure rate limiting is turned on to prevent abuse from anonymous users.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('key-round'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('REST API Access', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('Lowering the required capability (e.g. to Author) lets more users manage ratings through the API. Only do this on trusted multi-author sites.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('bar-chart-2'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Archive Sorting', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('This modifies the main archive query. It works with classic themes and with block themes whose Query Loop has "Inherit query from template" enabled.', 'shuriken-reviews'); ?></p>
                        <p><?php esc_html_e('For Query Loop blocks that use a custom query, select the block in the Site Editor and configure the "Shuriken Reviews" panel in its block settings instead.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

            <?php elseif ($current_tab === 'rate-limiting') : ?>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('shield'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Why enable rate limiting?', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('Without rate limiting, a single user or bot can submit hundreds of votes in seconds, skewing your ratings and making them unreliable.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('clock'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Recommended defaults', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('A 60-second cooldown, 30 votes/hour for members, and 10 votes/hour for guests works well for most sites. Adjust based on your traffic.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

                <div class="sidebar-tip">
                    <span class="tip-icon"><?php Shuriken_Icons::render('lightbulb'); ?></span>
                    <div class="tip-content">
                        <strong><?php esc_html_e('Settings not applying?', 'shuriken-reviews'); ?></strong>
                        <p><?php esc_html_e('Some themes or plugins may override rate-limiting behaviour through code. If limits seem to have no effect, contact your theme author to check for overriding filters.', 'shuriken-reviews'); ?></p>
                    </div>
                </div>

            <?php endif; ?>
        </aside>
    </div>
</div>
