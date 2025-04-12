<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'shuriken_ratings';

// Handle form submissions
if (isset($_POST['create_rating'])) {
    $name = sanitize_text_field($_POST['rating_name']);
    $wpdb->insert($table_name, array('name' => $name));
    echo '<div class="notice notice-success"><p>' . esc_html__('Rating created successfully!', 'shuriken-reviews') . '</p></div>';
}

if (isset($_POST['update_rating'])) {
    $id = intval($_POST['rating_id']);
    $name = sanitize_text_field($_POST['rating_name']);
    $wpdb->update($table_name, array('name' => $name), array('id' => $id));
    echo '<div class="notice notice-success"><p>' . esc_html__('Rating updated successfully!', 'shuriken-reviews') . '</p></div>';
}

if (isset($_POST['delete_rating'])) {
    $id = intval($_POST['rating_id']);
    $wpdb->delete($table_name, array('id' => $id));
    echo '<div class="notice notice-success"><p>' . esc_html__('Rating deleted successfully!', 'shuriken-reviews') . '</p></div>';
}

// Get all ratings
$ratings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1><?php esc_html_e('Ratings Management', 'shuriken-reviews'); ?></h1>
    
    <h2><?php esc_html_e('Create New Rating', 'shuriken-reviews'); ?></h2>
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th><label for="rating_name"><?php esc_html_e('Rating Name', 'shuriken-reviews'); ?></label></th>
                <td>
                    <input type="text" name="rating_name" id="rating_name" class="regular-text" required>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="create_rating" class="button button-primary" value="<?php esc_attr_e('Create Rating', 'shuriken-reviews'); ?>">
        </p>
    </form>

    <h2><?php esc_html_e('Existing Ratings', 'shuriken-reviews'); ?></h2>
    <table class="wp-list-table widefat fixed striped shuriken-ratings-table">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e('ID', 'shuriken-reviews'); ?></th>
                <th class="column-name"><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                <th class="column-shortcode"><?php esc_html_e('Shortcode', 'shuriken-reviews'); ?></th>
                <th class="column-rating"><?php esc_html_e('Average Rating', 'shuriken-reviews'); ?></th>
                <th class="column-votes"><?php esc_html_e('Total Votes', 'shuriken-reviews'); ?></th>
                <th class="column-actions"><?php esc_html_e('Actions', 'shuriken-reviews'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ratings as $rating): 
                $average = $rating->total_votes > 0 ? round($rating->total_rating / $rating->total_votes, 1) : 0;
            ?>
                <tr>
                    <td><?php echo esc_html($rating->id); ?></td>
                    <td>
                        <form method="post" action="" id="rating-form-<?php echo esc_attr($rating->id); ?>">
                            <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating->id); ?>">
                            <input type="text" name="rating_name" value="<?php echo esc_attr($rating->name); ?>" style="width: 100%;" class="regular-text">
                        </form>
                    </td>
                    <td><code>[shuriken_rating id="<?php echo esc_attr($rating->id); ?>"]</code></td>
                    <td><?php printf(esc_html__('%s/5', 'shuriken-reviews'), $average); ?></td>
                    <td><?php echo esc_html($rating->total_votes); ?></td>
                    <td>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" name="update_rating" class="button" form="rating-form-<?php echo esc_attr($rating->id); ?>">
                                <?php esc_html_e('Update', 'shuriken-reviews'); ?>
                            </button>
                            <button type="submit" name="delete_rating" class="button" form="rating-form-<?php echo esc_attr($rating->id); ?>"
                                onclick="return confirm('<?php esc_attr_e('Are you sure?', 'shuriken-reviews'); ?>');">
                                <?php esc_html_e('Delete', 'shuriken-reviews'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
