<?php
if (!defined('ABSPATH')) exit;

if (isset($_POST['save_comments_settings'])) {
    if (!wp_verify_nonce($_POST['shuriken_comments_nonce'], 'shuriken_comments_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }
    
    $exclude_author_comments = isset($_POST['exclude_author_comments']) ? '1' : '0';
    update_option('shuriken_exclude_author_comments', $exclude_author_comments);
    
    echo '<div class="notice notice-success"><p>' . 
        esc_html__('Comments settings saved successfully!', 'shuriken-reviews') . 
        '</p></div>';
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Comments Settings', 'shuriken-reviews'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('shuriken_comments_settings', 'shuriken_comments_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Latest Comments Block', 'shuriken-reviews'); ?></th>
                <td>
                    <label for="exclude_author_comments">
                        <input type="checkbox" 
                               name="exclude_author_comments" 
                               id="exclude_author_comments"
                               value="1"
                               <?php checked('1', get_option('shuriken_exclude_author_comments', '1')); ?>>
                        <?php esc_html_e('Exclude author comments from Latest Comments block', 'shuriken-reviews'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, comments made by post authors will not appear in the Latest Comments block.', 'shuriken-reviews'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" 
                   name="save_comments_settings" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Save Comments Settings', 'shuriken-reviews'); ?>">
        </p>
    </form>
</div>