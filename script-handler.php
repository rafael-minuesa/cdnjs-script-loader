<?php
add_action('wp_enqueue_scripts', 'cdnjs_replace_scripts', 999);

/**
 * Replace registered scripts with CDNJS CDN versions
 * Includes automatic fallback to local copies if CDN fails
 */
function cdnjs_replace_scripts() {
    $options = get_option('cdnjs_script_loader_settings');

    if (empty($options['scripts']) || !is_array($options['scripts'])) {
        return;
    }

    foreach ($options['scripts'] as $index => $script) {
        if (empty($script)) {
            continue;
        }

        $script = sanitize_text_field($script);
        $version = isset($options['versions'][$index]) ? sanitize_text_field($options['versions'][$index]) : '';

        if (empty($version)) {
            continue;
        }

        // WordPress registers `jquery` as a dependency-only alias. Replace the
        // actual source handle so jQuery Core is not loaded alongside the CDN
        // copy and the alias can continue to load jQuery Migrate as intended.
        global $wp_scripts;
        $script_handle = $script;

        if (
            'jquery' === $script
            && isset($wp_scripts->registered['jquery'])
            && empty($wp_scripts->registered['jquery']->src)
            && isset($wp_scripts->registered['jquery-core'])
        ) {
            $script_handle = 'jquery-core';
        }

        // Update an existing registration in place so its dependencies,
        // placement, inline data, translations, and loading strategy survive.
        $original_script = isset($wp_scripts->registered[$script_handle]) ? $wp_scripts->registered[$script_handle] : null;

        // Get library details from CDNJS API or use stored data
        $library_data = cdnjs_get_library_data($script, $version);

        if (!$library_data) {
            continue;
        }

        $cdn_url = $library_data['url'];
        $sri_hash = $library_data['sri'];

        if ($original_script) {
            $original_script->src = $cdn_url;
            $original_script->ver = $version;
        } else {
            wp_register_script($script_handle, $cdn_url, array(), $version, true);
        }

        // Add SRI integrity attribute for security
        if (!empty($sri_hash)) {
            add_filter('script_loader_tag', function($tag, $handle) use ($script_handle, $sri_hash) {
                if ($handle === $script_handle) {
                    $attributes = 'integrity="' . esc_attr($sri_hash) . '" crossorigin="anonymous"';
                    $tag = cdnjs_add_attributes_to_script_tag($tag, $script_handle, $attributes);
                }
                return $tag;
            }, 10, 2);
        }

        // Enqueue the script
        wp_enqueue_script($script);

        // Add fallback mechanism
        cdnjs_add_fallback($script_handle, $library_data, $script);

        // Track performance
        cdnjs_track_script_load($script_handle, $cdn_url, $script);
    }
}

/**
 * Add attributes only to the external tag for a registered script handle.
 *
 * WordPress may pass translations and before/after inline blocks alongside the
 * external tag through script_loader_tag, so a broad string replacement can
 * accidentally decorate an inline script instead.
 */
function cdnjs_add_attributes_to_script_tag($tag, $handle, $attributes) {
    $tag_id = $handle . '-js';
    $pattern = "/<script\\b(?=[^>]*\\bid=([\"'])" . preg_quote($tag_id, '/') . "\\1)[^>]*>/i";
    $updated_tag = preg_replace_callback(
        $pattern,
        function($matches) use ($attributes) {
            return substr($matches[0], 0, -1) . ' ' . $attributes . '>';
        },
        $tag,
        1
    );

    return null === $updated_tag ? $tag : $updated_tag;
}

/**
 * Get library data from CDNJS API with caching
 */
function cdnjs_get_library_data($library, $version) {
    $transient_key = 'cdnjs_lib_' . md5($library . $version);
    $cached_data = get_transient($transient_key);

    if ($cached_data !== false) {
        return $cached_data;
    }

    // Try to fetch from CDNJS API
    $api_url = 'https://api.cdnjs.com/libraries/' . urlencode($library) . '/' . urlencode($version);
    $response = wp_remote_get($api_url, array('timeout' => 5));

    if (is_wp_error($response)) {
        // Fallback to manual URL construction
        return cdnjs_construct_manual_url($library, $version);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['sri']) && !empty($data['url'])) {
        $library_data = array(
            'url' => $data['url'],
            'sri' => $data['sri'],
            'filename' => basename($data['url'])
        );

        // Cache for 7 days
        set_transient($transient_key, $library_data, 7 * DAY_IN_SECONDS);

        return $library_data;
    }

    return cdnjs_construct_manual_url($library, $version);
}

/**
 * Manually construct CDNJS URL when API is unavailable
 */
function cdnjs_construct_manual_url($library, $version) {
    // Common patterns for library filenames
    $patterns = array(
        $library . '.min.js',
        $library . '.js',
        'index.min.js',
        strtolower($library) . '.min.js'
    );

    // Use stored custom filename if available
    $options = get_option('cdnjs_script_loader_settings');
    if (!empty($options['filenames'][$library])) {
        $filename = $options['filenames'][$library];
    } else {
        $filename = $patterns[0]; // Default to first pattern
    }

    return array(
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/' . $library . '/' . $version . '/' . $filename,
        'sri' => '', // No SRI hash available without API
        'filename' => $filename
    );
}

/**
 * Add JavaScript fallback mechanism for CDN failure
 */
function cdnjs_add_fallback($script_handle, $library_data, $library = '') {
    $options = get_option('cdnjs_script_loader_settings');
    $library = $library ?: $script_handle;

    // Check if local fallback is enabled and exists
    if (empty($options['enable_fallback'])) {
        return;
    }

    $local_path = cdnjs_get_local_fallback_path($library);

    if (file_exists($local_path)) {
        $local_url = cdnjs_get_local_fallback_url($library);
        $failure_url = add_query_arg(
            array(
                'action' => 'cdnjs_track_failure',
                'script' => $library,
            ),
            admin_url('admin-ajax.php')
        );

        add_filter('script_loader_tag', function($tag, $handle) use ($script_handle, $local_url, $failure_url) {
            if ($handle !== $script_handle) {
                return $tag;
            }

            // document.write() creates a new parser-inserted script, preserving
            // execution order for scripts that depend on this library.
            $fallback_markup = '<script src="' . esc_url($local_url) . '"></script>';
            $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
            $onerror = 'this.onerror=null;'
                . 'if(navigator.sendBeacon){navigator.sendBeacon(' . wp_json_encode($failure_url, $json_flags) . ');}'
                . 'document.write(' . wp_json_encode($fallback_markup, $json_flags) . ');';
            $attributes = 'onerror="' . esc_attr($onerror) . '"';

            return cdnjs_add_attributes_to_script_tag($tag, $script_handle, $attributes);
        }, 10, 2);
    }
}

/**
 * Track script load performance
 */
function cdnjs_track_script_load($script_handle, $url, $library = '') {
    $library = $library ?: $script_handle;
    $config = wp_json_encode(
        array(
            'elementId' => $script_handle . '-js',
            'endpoint' => add_query_arg('action', 'cdnjs_track_performance', admin_url('admin-ajax.php')),
            'script' => $library,
            'url' => $url,
        ),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    // The CDN resource has already completed when an "after" inline script
    // runs, so read its buffered Resource Timing entry directly.
    $monitoring_script = '(function(c){'
        . 'if(!window.performance||!performance.getEntriesByType||!navigator.sendBeacon){return;}'
        . 'var element=document.getElementById(c.elementId);'
        . 'var resourceUrl=element&&element.src?element.src:c.url;'
        . 'var entries=performance.getEntriesByType("resource");'
        . 'var entry=null;'
        . 'for(var i=entries.length-1;i>=0;i--){'
        . 'if(entries[i].name===resourceUrl||entries[i].name.indexOf(c.url)===0){entry=entries[i];break;}'
        . '}'
        . 'if(!entry){return;}'
        . 'navigator.sendBeacon(c.endpoint,JSON.stringify({script:c.script,duration:entry.duration}));'
        . '}(' . $config . '));';

    wp_add_inline_script($script_handle, $monitoring_script, 'after');
}

/**
 * Get local fallback file path
 */
function cdnjs_get_local_fallback_path($script) {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/cdnjs-fallbacks/' . $script . '.min.js';
}

/**
 * Get local fallback URL
 */
function cdnjs_get_local_fallback_url($script) {
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'] . '/cdnjs-fallbacks/' . $script . '.min.js';
}

/**
 * AJAX handler for tracking CDN failures
 */
add_action('wp_ajax_nopriv_cdnjs_track_failure', 'cdnjs_handle_failure_tracking');
add_action('wp_ajax_cdnjs_track_failure', 'cdnjs_handle_failure_tracking');

function cdnjs_handle_failure_tracking() {
    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        status_header(405);
        wp_die();
    }

    $script = isset($_GET['script']) ? sanitize_text_field(wp_unslash($_GET['script'])) : '';

    if (empty($script) || !cdnjs_is_configured_script($script)) {
        status_header(400);
        wp_die();
    }

    $failures = get_option('cdnjs_failures', array());

    if (!is_array($failures)) {
        $failures = array();
    }

    if (!isset($failures[$script]) || !is_array($failures[$script])) {
        $failures[$script] = array('count' => 0, 'last_failure' => '');
    }

    $failure_count = isset($failures[$script]['count']) ? absint($failures[$script]['count']) : 0;
    $failures[$script]['count'] = min(PHP_INT_MAX, $failure_count + 1);
    $failures[$script]['last_failure'] = current_time('mysql');

    update_option('cdnjs_failures', $failures);

    wp_die();
}

/**
 * AJAX handler for tracking performance
 */
add_action('wp_ajax_nopriv_cdnjs_track_performance', 'cdnjs_handle_performance_tracking');
add_action('wp_ajax_cdnjs_track_performance', 'cdnjs_handle_performance_tracking');

function cdnjs_handle_performance_tracking() {
    if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
        status_header(405);
        wp_die();
    }

    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    $script = isset($data['script']) ? sanitize_text_field($data['script']) : '';
    $duration = isset($data['duration']) && is_numeric($data['duration']) ? (float) $data['duration'] : -1;

    if (empty($script) || !cdnjs_is_configured_script($script) || $duration < 0 || $duration > 600000) {
        status_header(400);
        wp_die();
    }

    $performance = get_option('cdnjs_performance', array());

    if (!is_array($performance)) {
        $performance = array();
    }

    if (!isset($performance[$script]) || !is_array($performance[$script])) {
        $performance[$script] = array(
            'loads' => 0,
            'total_duration' => 0,
            'avg_duration' => 0
        );
    }

    $loads = isset($performance[$script]['loads']) ? absint($performance[$script]['loads']) : 0;
    $total_duration = isset($performance[$script]['total_duration']) ? (float) $performance[$script]['total_duration'] : 0;

    $performance[$script]['loads'] = min(PHP_INT_MAX, $loads + 1);
    $performance[$script]['total_duration'] = max(0, $total_duration) + $duration;
    $performance[$script]['avg_duration'] = $performance[$script]['total_duration'] / $performance[$script]['loads'];

    update_option('cdnjs_performance', $performance);

    wp_die();
}

/**
 * Determine whether a telemetry event belongs to a configured script.
 */
function cdnjs_is_configured_script($script) {
    $options = get_option('cdnjs_script_loader_settings', array());

    if (empty($options['scripts']) || !is_array($options['scripts'])) {
        return false;
    }

    $configured_scripts = array_map('sanitize_text_field', $options['scripts']);

    return in_array($script, $configured_scripts, true);
}
