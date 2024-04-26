<?php
/*
Plugin Name: CDNJS Script Loader
Plugin URI: http://prowoos.com/
Description: Allows loading JavaScript libraries from cdnjs
Version: 1.1
Author: Rafael Minuesa
Author URI: http://prowoos.com/
License: GPL2
*/

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
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'cdnjs_script_loader_admin_init');

function cdnjs_script_loader_admin_init() {
    register_setting('cdnjs_script_loader_options', 'cdnjs_script_loader_settings');
    add_settings_section('script_settings', 'Script Configuration', 'cdnjs_script_loader_section_text', 'cdnjs_script_loader');
    add_settings_field('cdnjs_script_loader_script', 'Scripts to Load', 'cdnjs_script_loader_setting_string', 'cdnjs_script_loader', 'script_settings');
    add_settings_field('cdnjs_script_loader_versions', 'Script Versions', 'cdnjs_script_loader_setting_versions', 'cdnjs_script_loader', 'script_settings');
}

function cdnjs_script_loader_setting_versions() {
    $options = get_option('cdnjs_script_loader_settings');
    echo "<input id='versions' name='cdnjs_script_loader_settings[versions]' size='40' type='text' value='{$options['versions']}' />";
    echo '<p>Enter the versions for each script respectively, separated by commas (e.g., 3.4.1, 4.5.2).</p>';
}

function cdnjs_script_loader_setting_string() {
    $options = get_option('cdnjs_script_loader_settings');
    echo "<input id='scripts' name='cdnjs_script_loader_settings[scripts]' size='40' type='text' value='{$options['scripts']}' />";
}

add_action('wp_enqueue_scripts', 'replace_with_cdnjs', 999);

function replace_with_cdnjs() {
    $options = get_option('cdnjs_script_loader_settings');
    if (!empty($options['scripts']) && !empty($options['versions'])) {
        $scripts = explode(',', $options['scripts']);
        $versions = explode(',', $options['versions']);
        foreach ($scripts as $index => $script) {
            $version = trim($versions[$index]) ?? 'latest'; // Default to 'latest' if no version is specified
            wp_deregister_script(trim($script));
            wp_register_script(trim($script), 'https://cdnjs.cloudflare.com/ajax/libs/' . trim($script) . '/' . $version . '/' . trim($script) . '.min.js', array(), null, true);
            wp_enqueue_script(trim($script));
        }
    }
}

function cdnjs_script_loader_sanitize($input) {
    $new_input = array();
    $new_input['scripts'] = sanitize_text_field($input['scripts']);
    $new_input['versions'] = sanitize_text_field($input['versions']);
    return $new_input;
}

register_setting('cdnjs_script_loader_options', 'cdnjs_script_loader_settings', 'cdnjs_script_loader_sanitize');
