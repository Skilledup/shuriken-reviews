<?php
if (!defined('ABSPATH')) exit;

if (isset($_POST['save_general_settings'])) {
    if (!wp_verify_nonce($_POST['shuriken_general_nonce'], 'shuriken_general_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }
    
    $allow_guest_voting = isset($_POST['allow_guest_voting']) ? '1' : '0';
    
    update_option('shuriken_allow_guest_voting', $allow_guest_voting);
    
    echo '<div class="notice notice-success"><p>' . 
        esc_html__('Settings saved successfully!', 'shuriken-reviews') . 
        '</p></div>';
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Shuriken Reviews Settings', 'shuriken-reviews'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('shuriken_general_settings', 'shuriken_general_nonce'); ?>
        
        <h2><?php esc_html_e('Voting Settings', 'shuriken-reviews'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Guest Voting', 'shuriken-reviews'); ?></th>
                <td>
                    <fieldset>
                        <label for="allow_guest_voting">
                            <input type="checkbox" 
                                   name="allow_guest_voting" 
                                   id="allow_guest_voting"
                                   value="1"
                                   <?php checked('1', get_option('shuriken_allow_guest_voting', '0')); ?>>
                            <?php esc_html_e('Allow guests to vote without logging in', 'shuriken-reviews'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, visitors who are not logged in can also submit ratings. Guest votes are tracked by IP address to prevent multiple votes.', 'shuriken-reviews'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" 
                   name="save_general_settings" 
                   class="button button-primary" 
                   value="<?php esc_attr_e('Save Settings', 'shuriken-reviews'); ?>">
        </p>
    </form>
</div>
