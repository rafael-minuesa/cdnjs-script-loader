<?php
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
