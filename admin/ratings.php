<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'shuriken_ratings';

$per_page = 10; // Number of items per page
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

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

// Add search functionality
$search = isset($_GET['rating_search']) ? sanitize_text_field($_GET['rating_search']) : '';
if (!empty($search)) {
    $total_items = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE name LIKE %s",
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    $ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE name LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d",
        '%' . $wpdb->esc_like($search) . '%',
        $per_page,
        $offset
    ));
} else {
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
}
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

    <!-- Add search form before the create new rating section -->
    <form method="get" class="search-box" style="margin: 1em 0;">
        <input type="hidden" name="page" value="shuriken-reviews">
        <p>
            <input type="search"
                id="rating-search"
                name="rating_search"
                value="<?php echo esc_attr($search); ?>"
                placeholder="<?php esc_attr_e('Search ratings...', 'shuriken-reviews'); ?>"
                class="regular-text">
            <input type="submit"
                class="button"
                value="<?php esc_attr_e('Search Ratings', 'shuriken-reviews'); ?>">
            <?php if (!empty($search)): ?>
                <a href="?page=shuriken-reviews" class="button">
                    <?php esc_html_e('Clear Search', 'shuriken-reviews'); ?>
                </a>
            <?php endif; ?>
        </p>
    </form>

    <!-- Show result count when searching -->
    <?php if (!empty($search)): ?>
        <p>
            <?php
            printf(
                esc_html(_n(
                    'Found %s rating matching your search.',
                    'Found %s ratings matching your search.',
                    count($ratings),
                    'shuriken-reviews'
                )),
                number_format_i18n(count($ratings))
            );
            ?>
        </p>
    <?php endif; ?>

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

    <?php
    $total_pages = ceil($total_items / $per_page);

    if ($total_pages > 1) {
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
            'show_all' => false,
            'end_size' => 1,
            'mid_size' => 2,
            'add_args' => array('rating_search' => $search) // Preserve search parameter
        ));
        echo '</div>';
        echo '</div>';
    }
    ?>
</div>