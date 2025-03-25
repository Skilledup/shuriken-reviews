<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'shuriken_ratings';

// Handle form submissions
if (isset($_POST['create_rating'])) {
    $name = sanitize_text_field($_POST['rating_name']);
    $wpdb->insert($table_name, array('name' => $name));
    echo '<div class="notice notice-success"><p>Rating created successfully!</p></div>';
}

if (isset($_POST['update_rating'])) {
    $id = intval($_POST['rating_id']);
    $name = sanitize_text_field($_POST['rating_name']);
    $wpdb->update($table_name, array('name' => $name), array('id' => $id));
    echo '<div class="notice notice-success"><p>Rating updated successfully!</p></div>';
}

if (isset($_POST['delete_rating'])) {
    $id = intval($_POST['rating_id']);
    $wpdb->delete($table_name, array('id' => $id));
    echo '<div class="notice notice-success"><p>Rating deleted successfully!</p></div>';
}

// Get all ratings
$ratings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Shuriken Reviews Settings</h1>
    
    <h2>Create New Rating</h2>
    <form method="post" action="">
        <table class="form-table">
            <tr>
                <th><label for="rating_name">Rating Name</label></th>
                <td>
                    <input type="text" name="rating_name" id="rating_name" class="regular-text" required>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="create_rating" class="button button-primary" value="Create Rating">
        </p>
    </form>

    <h2>Existing Ratings</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Shortcode</th>
                <th>Average Rating</th>
                <th>Total Votes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ratings as $rating): 
                $average = $rating->total_votes > 0 ? round($rating->total_rating / $rating->total_votes, 1) : 0;
            ?>
                <tr>
                    <td><?php echo $rating->id; ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="rating_id" value="<?php echo $rating->id; ?>">
                            <input type="text" name="rating_name" value="<?php echo esc_attr($rating->name); ?>" class="regular-text">
                            <input type="submit" name="update_rating" class="button" value="Update">
                        </form>
                    </td>
                    <td><code>[shuriken_rating id="<?php echo $rating->id; ?>"]</code></td>
                    <td><?php echo $average; ?>/5</td>
                    <td><?php echo $rating->total_votes; ?></td>
                    <td>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="rating_id" value="<?php echo $rating->id; ?>">
                            <input type="submit" name="delete_rating" class="button" value="Delete" onclick="return confirm('Are you sure?');">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
