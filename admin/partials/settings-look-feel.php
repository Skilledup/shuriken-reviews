<?php
/**
 * Look & Feel Settings Tab Partial
 *
 * @package Shuriken_Reviews
 * @since 1.15.8
 */

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['save_look_feel_settings'])) {
    $nonce = isset($_POST['shuriken_look_feel_nonce']) ? sanitize_text_field(wp_unslash($_POST['shuriken_look_feel_nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'shuriken_look_feel_settings')) {
        wp_die(__('Invalid nonce specified', 'shuriken-reviews'));
    }

    $custom_icons = get_option('shuriken_custom_rating_icons', array());
    $custom_icons = is_array($custom_icons) ? $custom_icons : array();
    $removed_custom_icons = isset($_POST['shuriken_remove_custom_icons']) && is_array($_POST['shuriken_remove_custom_icons'])
        ? wp_unslash($_POST['shuriken_remove_custom_icons'])
        : array();
    foreach ($removed_custom_icons as $icon_key) {
        if (!is_string($icon_key)) {
            continue;
        }
        $icon_key = sanitize_key($icon_key);
        if (strpos($icon_key, 'custom-') === 0) {
            unset($custom_icons[$icon_key]);
        }
    }
    $pending_icon_keys = array();
    $posted_custom_icons = isset($_POST['shuriken_new_custom_icons']) && is_array($_POST['shuriken_new_custom_icons'])
        ? wp_unslash($_POST['shuriken_new_custom_icons'])
        : array();

    foreach (array_slice($posted_custom_icons, 0, 30, true) as $index => $posted_icon) {
        if (!is_array($posted_icon) || !isset($posted_icon['label'], $posted_icon['markup']) || !is_string($posted_icon['label']) || !is_string($posted_icon['markup'])) {
            continue;
        }

        $label = sanitize_text_field($posted_icon['label']);
        $markup = trim($posted_icon['markup']);
        if ($label === '' || $markup === '') {
            continue;
        }

        if (preg_match('/^<svg\b/i', $markup)) {
            $markup = shuriken_sanitize_rating_svg($markup);
            if (!preg_match('/^<svg\b/i', $markup)) {
                continue;
            }
            $type = 'svg';
        } else {
            if (strpos($markup, '<') !== false || strpos($markup, '>') !== false) {
                continue;
            }
            $markup = sanitize_text_field($markup);
            if ($markup === '') {
                continue;
            }
            $type = 'text';
        }

        $icon_key = 'custom-' . substr(hash('sha256', $type . ':' . $label . ':' . $markup), 0, 16);
        $custom_icons[$icon_key] = array('label' => $label, 'type' => $type, 'markup' => $markup);
        $pending_icon_keys[(string) $index] = $icon_key;
    }
    update_option('shuriken_custom_rating_icons', $custom_icons);

    $rating_icon_options = Shuriken_Icons::rating_icon_options();
    $defaults = array(
        'shuriken_star_icon' => 'star-solid',
        'shuriken_like_icon' => 'thumbs-up',
        'shuriken_dislike_icon' => 'thumbs-down',
        'shuriken_approval_icon' => 'chevron-up',
    );
    foreach ($defaults as $option_name => $default_icon) {
        $posted_selection = isset($_POST[$option_name]) ? wp_unslash($_POST[$option_name]) : '';
        $selected_icon = is_string($posted_selection) ? sanitize_key($posted_selection) : $default_icon;
        if (strpos($selected_icon, 'pending-') === 0) {
            $pending_index = substr($selected_icon, 8);
            $selected_icon = $pending_icon_keys[$pending_index] ?? $default_icon;
        }
        update_option($option_name, isset($rating_icon_options[$selected_icon]) ? $selected_icon : $default_icon);
    }

    do_action('shuriken_save_settings', 'look-feel', $_POST);
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'shuriken-reviews') . '</p></div>';
}

$rating_icon_options = Shuriken_Icons::rating_icon_options();
$custom_icons = get_option('shuriken_custom_rating_icons', array());
$custom_icons = is_array($custom_icons) ? $custom_icons : array();
$icon_settings = array(
    array('option' => 'shuriken_star_icon', 'label' => __('Star Rating Icon', 'shuriken-reviews'), 'default' => 'star-solid'),
    array('option' => 'shuriken_like_icon', 'label' => __('Like Icon', 'shuriken-reviews'), 'default' => 'thumbs-up'),
    array('option' => 'shuriken_dislike_icon', 'label' => __('Dislike Icon', 'shuriken-reviews'), 'default' => 'thumbs-down'),
    array('option' => 'shuriken_approval_icon', 'label' => __('Approval Icon', 'shuriken-reviews'), 'default' => 'chevron-up'),
);
?>

<form method="post" action="" class="shuriken-settings-form">
    <?php wp_nonce_field('shuriken_look_feel_settings', 'shuriken_look_feel_nonce'); ?>

    <div class="shuriken-settings-card">
        <div class="settings-card-header">
            <span class="settings-card-icon"><?php Shuriken_Icons::render('sparkles'); ?></span>
            <h3><?php esc_html_e('Rating Icons', 'shuriken-reviews'); ?></h3>
        </div>
        <div class="settings-card-body">
            <?php foreach ($icon_settings as $icon_setting) :
                $selected_icon = get_option($icon_setting['option'], $icon_setting['default']);
                if (!is_string($selected_icon) || !isset($rating_icon_options[$selected_icon])) {
                    $selected_icon = $icon_setting['default'];
                }
            ?>
                <fieldset class="settings-field shuriken-icon-choice-field">
                    <legend><?php echo esc_html($icon_setting['label']); ?></legend>
                    <details class="shuriken-icon-picker">
                        <summary class="shuriken-icon-picker-summary">
                            <?php echo Shuriken_Icons::render_rating_icon($selected_icon, 22); ?>
                            <span><?php echo esc_html($rating_icon_options[$selected_icon]); ?></span>
                        </summary>
                        <div class="shuriken-icon-picker-panel">
                            <label class="screen-reader-text" for="search-<?php echo esc_attr($icon_setting['option']); ?>"><?php esc_html_e('Search icons', 'shuriken-reviews'); ?></label>
                            <input type="search"
                                   class="shuriken-icon-search"
                                   id="search-<?php echo esc_attr($icon_setting['option']); ?>"
                                   placeholder="<?php esc_attr_e('Search icons…', 'shuriken-reviews'); ?>">
                            <div class="shuriken-icon-choices">
                                <?php foreach ($rating_icon_options as $icon_name => $icon_label) : ?>
                                    <label class="shuriken-icon-choice" data-icon-search="<?php echo esc_attr(strtolower($icon_label . ' ' . $icon_name)); ?>">
                                        <input type="radio"
                                               name="<?php echo esc_attr($icon_setting['option']); ?>"
                                               value="<?php echo esc_attr($icon_name); ?>"
                                               <?php checked($selected_icon, $icon_name); ?>>
                                        <span class="shuriken-icon-choice-preview">
                                            <?php echo Shuriken_Icons::render_rating_icon($icon_name, 22); ?>
                                            <span><?php echo esc_html($icon_label); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                </fieldset>
            <?php endforeach; ?>

            <div class="shuriken-custom-icon-controls">
                <button type="button" class="button" id="shuriken-toggle-custom-icon">+ <?php esc_html_e('Add custom icon', 'shuriken-reviews'); ?></button>
                <div class="shuriken-custom-icon-form" id="shuriken-custom-icon-form" hidden>
                    <label for="shuriken-custom-icon-label"><?php esc_html_e('Icon name', 'shuriken-reviews'); ?></label>
                    <input type="text" id="shuriken-custom-icon-label" maxlength="60" class="regular-text">
                    <label for="shuriken-custom-icon-type"><?php esc_html_e('Icon type', 'shuriken-reviews'); ?></label>
                    <select id="shuriken-custom-icon-type">
                        <option value="emoji"><?php esc_html_e('Emoji or text symbol', 'shuriken-reviews'); ?></option>
                        <option value="svg"><?php esc_html_e('SVG markup', 'shuriken-reviews'); ?></option>
                    </select>
                    <label for="shuriken-custom-icon-markup" id="shuriken-custom-icon-markup-label"><?php esc_html_e('Emoji or text symbol', 'shuriken-reviews'); ?></label>
                    <textarea id="shuriken-custom-icon-markup" rows="4" class="large-text code"></textarea>
                    <button type="button" class="button button-secondary" id="shuriken-stage-custom-icon"><?php esc_html_e('Add to icon list', 'shuriken-reviews'); ?></button>
                    <p class="description"><?php esc_html_e('Custom icons are saved with these settings. SVGs are restricted to safe inline SVG elements and attributes.', 'shuriken-reviews'); ?></p>
                </div>
                <div id="shuriken-staged-custom-icons" aria-live="polite"></div>
            </div>
            <p class="settings-field-description">
                <?php esc_html_e('Search the complete built-in icon set or add your own emoji and SVG symbols. Existing custom filters can still override these choices.', 'shuriken-reviews'); ?>
            </p>
        </div>
    </div>

    <?php if ($custom_icons) : ?>
        <div class="shuriken-settings-card">
            <div class="settings-card-header">
                <span class="settings-card-icon"><?php Shuriken_Icons::render('database'); ?></span>
                <h3><?php esc_html_e('Saved Custom Icons', 'shuriken-reviews'); ?></h3>
            </div>
            <div class="settings-card-body">
                <p class="settings-field-description"><?php esc_html_e('Select icons to remove, then save. Ratings using a removed icon revert to their default icon.', 'shuriken-reviews'); ?></p>
                <div class="shuriken-saved-icon-list">
                    <?php foreach ($custom_icons as $icon_key => $icon) :
                        if (!is_string($icon_key) || strpos($icon_key, 'custom-') !== 0 || !is_array($icon) || !isset($icon['label']) || !is_string($icon['label'])) {
                            continue;
                        }
                    ?>
                        <label class="shuriken-saved-icon">
                            <input type="checkbox" name="shuriken_remove_custom_icons[]" value="<?php echo esc_attr($icon_key); ?>">
                            <?php echo Shuriken_Icons::render_rating_icon($icon_key, 22); ?>
                            <span><?php echo esc_html($icon['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php do_action('shuriken_after_settings_card', $current_tab); ?>

    <div class="shuriken-settings-submit">
        <button type="submit" name="save_look_feel_settings" class="button button-primary button-large">
            <?php esc_html_e('Save Settings', 'shuriken-reviews'); ?>
        </button>
    </div>
</form>

<script>
(function() {
    document.querySelectorAll('.shuriken-icon-search').forEach(function(search) {
        search.addEventListener('input', function() {
            var query = search.value.trim().toLowerCase();
            search.closest('.shuriken-icon-picker-panel').querySelectorAll('.shuriken-icon-choice').forEach(function(choice) {
                choice.hidden = choice.dataset.iconSearch.indexOf(query) === -1;
            });
        });
    });

    document.querySelectorAll('.shuriken-icon-picker').forEach(function(picker) {
        picker.addEventListener('change', function(event) {
            if (event.target.matches('input[type="radio"]')) {
                picker.querySelector('summary').innerHTML = event.target.closest('.shuriken-icon-choice').querySelector('.shuriken-icon-choice-preview').innerHTML;
                picker.open = false;
            }
        });
    });

    var customForm = document.getElementById('shuriken-custom-icon-form');
    var customType = document.getElementById('shuriken-custom-icon-type');
    var customMarkupLabel = document.getElementById('shuriken-custom-icon-markup-label');
    var customMarkup = document.getElementById('shuriken-custom-icon-markup');
    var stagedCount = 0;
    document.getElementById('shuriken-toggle-custom-icon').addEventListener('click', function() {
        customForm.hidden = !customForm.hidden;
    });
    customType.addEventListener('change', function() {
        var isSvg = customType.value === 'svg';
        customMarkupLabel.textContent = isSvg ? '<?php echo esc_js(__('SVG markup', 'shuriken-reviews')); ?>' : '<?php echo esc_js(__('Emoji or text symbol', 'shuriken-reviews')); ?>';
        customMarkup.placeholder = isSvg ? '<svg viewBox="0 0 24 24"><path d="..." /></svg>' : '★';
    });

    function createSafeSvgPreview(markup) {
        var parsed = new DOMParser().parseFromString(markup, 'image/svg+xml');
        var sourceRoot = parsed.documentElement;
        if (sourceRoot.localName !== 'svg' || parsed.querySelector('parsererror')) {
            return null;
        }

        var allowedAttributes = {
            svg: ['viewBox', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'],
            path: ['d', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule'],
            circle: ['cx', 'cy', 'r', 'fill', 'stroke', 'stroke-width'],
            line: ['x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width'],
            polyline: ['points', 'fill', 'stroke', 'stroke-width'],
            polygon: ['points', 'fill', 'stroke', 'stroke-width'],
            rect: ['x', 'y', 'width', 'height', 'rx', 'ry', 'fill', 'stroke', 'stroke-width']
        };
        var svgNamespace = 'http://www.w3.org/2000/svg';

        function copySafeNode(source) {
            var tagName = source.localName;
            if (!allowedAttributes[tagName]) {
                return null;
            }
            var node = document.createElementNS(svgNamespace, tagName);
            Array.from(source.attributes).forEach(function(attribute) {
                if (allowedAttributes[tagName].indexOf(attribute.name) !== -1 && !/url\s*\(/i.test(attribute.value)) {
                    node.setAttribute(attribute.name, attribute.value);
                }
            });
            Array.from(source.children).forEach(function(child) {
                var safeChild = copySafeNode(child);
                if (safeChild) {
                    node.appendChild(safeChild);
                }
            });
            return node;
        }

        return copySafeNode(sourceRoot);
    }

    document.getElementById('shuriken-stage-custom-icon').addEventListener('click', function() {
        var label = document.getElementById('shuriken-custom-icon-label').value.trim();
        var markup = customMarkup.value.trim();
        var isSvg = customType.value === 'svg';
        if (!label || !markup || (isSvg && !/^<svg\b/i.test(markup)) || (!isSvg && /[<>]/.test(markup))) {
            customMarkup.setCustomValidity('<?php echo esc_js(__('Enter a name and a valid emoji/text symbol or SVG.', 'shuriken-reviews')); ?>');
            customMarkup.reportValidity();
            return;
        }
        customMarkup.setCustomValidity('');

        var index = stagedCount++;
        var hiddenFields = document.createElement('div');
        hiddenFields.hidden = true;
        [['label', label], ['markup', markup]].forEach(function(field) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'shuriken_new_custom_icons[' + index + '][' + field[0] + ']';
            input.value = field[1];
            hiddenFields.appendChild(input);
        });
        document.querySelector('.shuriken-settings-form').appendChild(hiddenFields);

        document.querySelectorAll('.shuriken-icon-choices').forEach(function(list) {
            var choice = document.createElement('label');
            choice.className = 'shuriken-icon-choice';
            choice.dataset.iconSearch = (label + ' custom').toLowerCase();
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = list.closest('.shuriken-icon-choice-field').querySelector('input[type="radio"]').name;
            radio.value = 'pending-' + index;
            var preview = document.createElement('span');
            preview.className = 'shuriken-icon-choice-preview';
            var iconPreview = isSvg ? createSafeSvgPreview(markup) : null;
            if (!iconPreview) {
                iconPreview = document.createElement('span');
                iconPreview.className = 'shuriken-custom-icon-placeholder';
                iconPreview.textContent = isSvg ? 'SVG' : markup;
            }
            var title = document.createElement('span');
            title.textContent = label;
            preview.append(iconPreview, title);
            choice.append(radio, preview);
            list.appendChild(choice);
        });

        var staged = document.createElement('p');
        staged.textContent = label + ' ' + '<?php echo esc_js(__('added to the icon pickers. Select it, then save settings.', 'shuriken-reviews')); ?>';
        document.getElementById('shuriken-staged-custom-icons').appendChild(staged);
        document.getElementById('shuriken-custom-icon-label').value = '';
        customMarkup.value = '';
    });
})();
</script>