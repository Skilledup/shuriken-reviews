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
        'icon'  => '⚙️',
        'file'  => 'settings-general.php',
    ),
    'rate-limiting' => array(
        'label' => __('Rate Limiting', 'shuriken-reviews'),
        'icon'  => '🛡️',
        'file'  => 'settings-rate-limiting.php',
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
                <span class="tab-icon"><?php echo esc_html($tab['icon']); ?></span>
                <?php echo esc_html($tab['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Tab Content -->
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
