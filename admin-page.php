<?php
add_action('admin_menu', 'cdnjs_script_loader_menu');

function cdnjs_script_loader_menu() {
    add_options_page('CDNJS Script Loader Settings', 'CDNJS Script Loader', 'manage_options', 'cdnjs-script-loader', 'cdnjs_script_loader_options_page');
}

function cdnjs_script_loader_options_page() {
    ?>
    <div class="wrap">
        <h2>CDNJS Script Loader</h2>
        <p>To check available scripts and their versions, visit <a href="https://cdnjs.com/" target="_blank">cdnjs.com</a>.</p>
        <form action="options.php" method="post">
            <?php
            settings_fields('cdnjs_script_loader_options');
            do_settings_sections('cdnjs_script_loader');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cdnjs_script_loader_admin_init');

function cdnjs_script_loader_admin_init() {
    register_setting('cdnjs_script_loader_options', 'cdnjs_script_loader_settings', 'cdnjs_script_loader_sanitize');
    add_settings_section('script_settings', 'Script Configuration', 'cdnjs_script_loader_section_text', 'cdnjs_script_loader');
    add_settings_field('cdnjs_script_loader_script', 'Scripts to Load', 'cdnjs_script_loader_setting_string', 'cdnjs_script_loader', 'script_settings');
    add_settings_field('cdnjs_script_loader_versions', 'Script Versions', 'cdnjs_script_loader_setting_versions', 'cdnjs_script_loader', 'script_settings');
}

function cdnjs_script_loader_section_text() {
    echo '<p>Enter the scripts you want to load from CDNJS (comma separated, e.g., jquery, bootstrap).</p>';
}

function cdnjs_script_loader_setting_string() {
    $options = get_option('cdnjs_script_loader_settings');
    $scripts = is_array($options) && isset($options['scripts']) ? $options['scripts'] : '';
    echo "<input id='scripts' name='cdnjs_script_loader_settings[scripts]' size='40' type='text' value='{$scripts}' />";
}

function cdnjs_script_loader_setting_versions() {
    $options = get_option('cdnjs_script_loader_settings');
    $versions = is_array($options) && isset($options['versions']) ? $options['versions'] : '';
    echo "<input id='versions' name='cdnjs_script_loader_settings[versions]' size='40' type='text' value='{$versions}' />";
    echo '<p>Enter the versions for each script respectively, separated by commas (e.g., 3.4.1, 4.5.2).</p>';
}

function cdnjs_script_loader_sanitize($input) {
    $new_input = array();
    $new_input['scripts'] = sanitize_text_field($input['scripts']);
    $new_input['versions'] = sanitize_text_field($input['versions']);
    return $new_input;
}
