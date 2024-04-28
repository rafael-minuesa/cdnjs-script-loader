<?php
add_action('admin_menu', 'cdnjs_script_loader_menu');

function cdnjs_script_loader_menu() {
    add_options_page('CDNJS Script Loader Settings', 'CDNJS Script Loader', 'manage_options', 'cdnjs-script-loader', 'cdnjs_script_loader_options_page');
}

function cdnjs_script_loader_options_page() {
    $options = get_option('cdnjs_script_loader_settings');
    ?>
    <div class="wrap">
        <h2>CDNJS Script Loader</h2>
        <h4>To check available scripts and their versions, visit <a href="https://cdnjs.com/" target="_blank">cdnjs.com</a>.</h4>
        <form action="options.php" method="post">
            <?php
            settings_fields('cdnjs_script_loader_options');
            do_settings_sections('cdnjs_script_loader');
            ?>
            <div id="script-fields">
                <?php if (!empty($options['scripts']) && is_array($options['scripts'])): ?>
                    <?php foreach ($options['scripts'] as $index => $script): ?>
                        <div class="script-field">
                            <input type="text" name="cdnjs_script_loader_settings[scripts][]" placeholder="Script Name (e.g., jquery)" value="<?php echo esc_attr($script); ?>" />
                            <input type="text" name="cdnjs_script_loader_settings[versions][]" placeholder="Version (e.g., 3.5.1)" value="<?php echo esc_attr($options['versions'][$index]); ?>" />
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="script-field">
                        <input type="text" name="cdnjs_script_loader_settings[scripts][]" placeholder="Script Name (e.g., jquery)" />
                        <input type="text" name="cdnjs_script_loader_settings[versions][]" placeholder="Version (e.g., 3.5.1)" />
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-script-field">Add More</button>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cdnjs_script_loader_admin_init');

function cdnjs_script_loader_admin_init() {
    register_setting('cdnjs_script_loader_options', 'cdnjs_script_loader_settings', 'cdnjs_script_loader_sanitize');
}

function cdnjs_script_loader_sanitize($input) {
    $new_input = array();
    if (isset($input['scripts']) && is_array($input['scripts'])) {
        foreach ($input['scripts'] as $key => $script) {
            $new_input['scripts'][$key] = sanitize_text_field($script);
        }
    }
    if (isset($input['versions']) && is_array($input['versions'])) {
        foreach ($input['versions'] as $key => $version) {
            $new_input['versions'][$key] = sanitize_text_field($version);
        }
    }
    return $new_input;
}

function cdnjs_script_loader_admin_scripts($hook) {
    if ($hook != 'settings_page_cdnjs-script-loader') {
        return;
    }
    wp_enqueue_script('cdnjs-admin-js', plugin_dir_url(__FILE__) . 'admin-scripts.js', array(), null, true);
}

add_action('admin_enqueue_scripts', 'cdnjs_script_loader_admin_scripts');
