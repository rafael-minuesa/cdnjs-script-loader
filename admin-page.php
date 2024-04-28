<?php
add_action('admin_menu', 'cdnjs_script_loader_menu');

function cdnjs_script_loader_menu() {
    add_options_page('CDNJS Script Loader Settings', 'CDNJS Script Loader', 'manage_options', 'cdnjs-script-loader', 'cdnjs_script_loader_options_page');
}

function cdnjs_script_loader_options_page() {
    ?>
    <div class="wrap">
        <h2>CDNJS Script Loader</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('cdnjs_script_loader_options');
            do_settings_sections('cdnjs_script_loader');
            ?>
            <div id="script-fields">
                <div class="script-field">
                    <input type="text" name="cdnjs_script_loader_settings[scripts][]" placeholder="Script Name (e.g., jquery)" />
                    <input type="text" name="cdnjs_script_loader_settings[versions][]" placeholder="Version (e.g., 3.5.1)" />
                </div>
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
    // Sanitize each input field
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

function cdnjs_script_loader_admin_scripts() {
    wp_enqueue_script('cdnjs-admin-js', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'cdnjs_script_loader_admin_scripts');
