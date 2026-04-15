<?php
/**
 * Date range filter bar partial for admin pages
 *
 * Renders the standard filter-row style date filter bar used by
 * analytics, context-stats, and voter-activity pages.
 *
 * @var string $form_id            Form element ID
 * @var string $id_prefix          Prefix for element IDs (e.g. 'ctx_', 'voter_', or '')
 * @var string $hidden_fields_html Pre-rendered hidden input markup (excluding range_type)
 * @var string $clear_url          URL for the "Clear" link
 *
 * Expected in scope: $range_type, $preset_value, $start_date, $end_date, $date_range_label
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

if (!defined('ABSPATH')) {
    exit;
}

$id_prefix = $id_prefix ?? '';
?>
<!-- Date Range Filter -->
<div class="shuriken-filter-bar">
    <form method="get" action="" id="<?php echo esc_attr($form_id); ?>">
        <?php echo $hidden_fields_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by caller ?>
        <input type="hidden" name="range_type" id="<?php echo esc_attr($id_prefix); ?>range_type" value="<?php echo esc_attr($range_type); ?>">

        <div class="filter-row">
            <label for="<?php echo esc_attr($id_prefix); ?>date_range"><?php esc_html_e('Time Period:', 'shuriken-reviews'); ?></label>
            <select name="date_range" id="<?php echo esc_attr($id_prefix); ?>date_range" class="preset-select">
                <option value="7" <?php selected($preset_value, '7'); ?>><?php esc_html_e('Last 7 Days', 'shuriken-reviews'); ?></option>
                <option value="30" <?php selected($preset_value, '30'); ?>><?php esc_html_e('Last 30 Days', 'shuriken-reviews'); ?></option>
                <option value="90" <?php selected($preset_value, '90'); ?>><?php esc_html_e('Last 90 Days', 'shuriken-reviews'); ?></option>
                <option value="365" <?php selected($preset_value, '365'); ?>><?php esc_html_e('Last Year', 'shuriken-reviews'); ?></option>
                <option value="all" <?php selected($preset_value, 'all'); ?>><?php esc_html_e('All Time', 'shuriken-reviews'); ?></option>
                <option value="custom" <?php selected($range_type, 'custom'); ?>><?php esc_html_e('Custom Range...', 'shuriken-reviews'); ?></option>
            </select>

            <div class="custom-date-range" style="<?php echo $range_type === 'custom' ? '' : 'display: none;'; ?>">
                <label for="<?php echo esc_attr($id_prefix); ?>start_date"><?php esc_html_e('From:', 'shuriken-reviews'); ?></label>
                <input type="date" name="start_date" id="<?php echo esc_attr($id_prefix); ?>start_date" value="<?php echo esc_attr($start_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">

                <label for="<?php echo esc_attr($id_prefix); ?>end_date"><?php esc_html_e('To:', 'shuriken-reviews'); ?></label>
                <input type="date" name="end_date" id="<?php echo esc_attr($id_prefix); ?>end_date" value="<?php echo esc_attr($end_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">

                <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'shuriken-reviews'); ?></button>
            </div>
        </div>

        <?php if ($range_type === 'custom' && ($start_date || $end_date)) : ?>
        <div class="current-range-label">
            <?php Shuriken_Icons::render('calendar', array('width' => 18, 'height' => 18)); ?>
            <?php echo esc_html($date_range_label); ?>
            <a href="<?php echo esc_url($clear_url); ?>" class="clear-filter">
                <?php esc_html_e('Clear', 'shuriken-reviews'); ?>
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>
