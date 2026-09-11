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

        // Get the registered script object to preserve dependencies
        global $wp_scripts;
        $original_script = isset($wp_scripts->registered[$script]) ? $wp_scripts->registered[$script] : null;
        $dependencies = $original_script ? $original_script->deps : array();

        // Get library details from CDNJS API or use stored data
        $library_data = cdnjs_get_library_data($script, $version);

        if (!$library_data) {
            continue;
        }

        $cdn_url = $library_data['url'];
        $sri_hash = $library_data['sri'];

        // Deregister the original script
        wp_deregister_script($script);

        // Register with CDN URL, preserving dependencies
        wp_register_script($script, $cdn_url, $dependencies, $version, true);

        // Add SRI integrity attribute for security
        if (!empty($sri_hash)) {
            add_filter('script_loader_tag', function($tag, $handle) use ($script, $sri_hash) {
                if ($handle === $script) {
                    $tag = str_replace('<script ', '<script integrity="' . esc_attr($sri_hash) . '" crossorigin="anonymous" ', $tag);
                }
                return $tag;
            }, 10, 2);
        }

        // Add fallback mechanism
        cdnjs_add_fallback($script, $library_data);

        // Enqueue the script
        wp_enqueue_script($script);

        // Track performance
        cdnjs_track_script_load($script, $cdn_url);
    }
}

/**
 * Get library data from CDNJS API with caching
 */
function cdnjs_get_library_data($library, $version) {
    $requested_filename = cdnjs_get_configured_filename($library);
    $transient_key = 'cdnjs_lib_' . md5($library . '|' . $version . '|' . $requested_filename);
    $cached_data = get_transient($transient_key);

    if ($cached_data !== false) {
        return $cached_data;
    }

    // Try to fetch from CDNJS API
    $api_url = 'https://api.cdnjs.com/libraries/' . rawurlencode($library) . '/' . rawurlencode($version);
    $response = wp_remote_get($api_url, array('timeout' => 5));

    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $files = isset($data['files']) && is_array($data['files']) ? $data['files'] : array();
        $default_filename = $requested_filename ? '' : cdnjs_get_library_default_filename($library);
        $filename = $requested_filename ?: cdnjs_select_library_filename($library, $files, $default_filename);

        if (!empty($filename)) {
            $sri_hashes = isset($data['sri']) && is_array($data['sri']) ? $data['sri'] : array();
            $sri_hash = isset($sri_hashes[$filename]) ? $sri_hashes[$filename] : '';

            $library_data = cdnjs_build_library_data($library, $version, $filename);

            // CDNJS metadata can occasionally contain a hash that does not
            // match the bytes served by the CDN. Verify it once per cache
            // refresh so browsers are not given an integrity value that will
            // block an otherwise valid script.
            $library_data['sri'] = cdnjs_verify_sri_hash($library_data['url'], $sri_hash);

            // Cache for 7 days.
            set_transient($transient_key, $library_data, 7 * DAY_IN_SECONDS);

            return $library_data;
        }
    }

    // Use a best-effort URL during an API failure, but retry the API sooner so
    // a temporary outage does not leave the library without SRI for a week.
    $library_data = cdnjs_construct_manual_url($library, $version);
    set_transient($transient_key, $library_data, HOUR_IN_SECONDS);

    return $library_data;
}

/**
 * Verify a CDNJS integrity value against the selected asset's response body.
 */
function cdnjs_verify_sri_hash($url, $sri_hash) {
    if (empty($sri_hash) || !preg_match('/^(sha(?:256|384|512))-([A-Za-z0-9+\/=]+)$/', $sri_hash, $matches)) {
        return '';
    }

    $response = wp_remote_get($url, array('timeout' => 10));

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return '';
    }

    $algorithm = $matches[1];
    $computed_hash = $algorithm . '-' . base64_encode(
        hash($algorithm, wp_remote_retrieve_body($response), true)
    );

    return hash_equals($sri_hash, $computed_hash) ? $sri_hash : '';
}

/**
 * Get the entry-point filename published in a library's CDNJS metadata.
 */
function cdnjs_get_library_default_filename($library) {
    $api_url = add_query_arg(
        'fields',
        'filename',
        'https://api.cdnjs.com/libraries/' . rawurlencode($library)
    );
    $response = wp_remote_get($api_url, array('timeout' => 5));

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return '';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    return isset($data['filename']) && is_string($data['filename']) ? trim($data['filename'], '/') : '';
}

/**
 * Get the configured asset filename for a library.
 *
 * Supports both filename arrays keyed by library and the numeric format used
 * by newly added rows in version 2.0.0.
 */
function cdnjs_get_configured_filename($library) {
    $options = get_option('cdnjs_script_loader_settings', array());

    if (!empty($options['filenames'][$library])) {
        return trim($options['filenames'][$library], '/');
    }

    if (!empty($options['scripts']) && is_array($options['scripts'])) {
        $index = array_search($library, $options['scripts'], true);

        if (false !== $index && !empty($options['filenames'][$index])) {
            return trim($options['filenames'][$index], '/');
        }
    }

    return '';
}

/**
 * Select the most likely JavaScript entry point from a CDNJS asset list.
 */
function cdnjs_select_library_filename($library, $files, $default_filename = '') {
    if (!is_array($files)) {
        return '';
    }

    $files = array_values(array_filter($files, 'is_string'));

    if (!empty($default_filename) && in_array($default_filename, $files, true)) {
        return $default_filename;
    }

    $preferred_files = array(
        $library . '.min.js',
        strtolower($library) . '.min.js',
        $library . '.js',
        strtolower($library) . '.js',
        'index.min.js',
        'index.js',
    );

    foreach ($preferred_files as $preferred_file) {
        if (in_array($preferred_file, $files, true)) {
            return $preferred_file;
        }
    }

    foreach (array('/\.min\.js$/i', '/\.js$/i') as $pattern) {
        foreach ($files as $file) {
            if (preg_match($pattern, $file)) {
                return $file;
            }
        }
    }

    return '';
}

/**
 * Build the public CDN URL and metadata for a selected asset.
 */
function cdnjs_build_library_data($library, $version, $filename, $sri_hash = '') {
    $encoded_filename = implode('/', array_map('rawurlencode', explode('/', trim($filename, '/'))));

    return array(
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/' . rawurlencode($library) . '/' . rawurlencode($version) . '/' . $encoded_filename,
        'sri' => $sri_hash,
        'filename' => $filename,
    );
}

/**
 * Manually construct CDNJS URL when API is unavailable.
 */
function cdnjs_construct_manual_url($library, $version) {
    $filename = cdnjs_get_configured_filename($library);

    if (empty($filename)) {
        $filename = $library . '.min.js';
    }

    return cdnjs_build_library_data($library, $version, $filename);
}

/**
 * Add JavaScript fallback mechanism for CDN failure
 */
function cdnjs_add_fallback($script, $library_data) {
    $options = get_option('cdnjs_script_loader_settings');

    // Check if local fallback is enabled and exists
    if (empty($options['enable_fallback'])) {
        return;
    }

    $local_path = cdnjs_get_local_fallback_path($script);

    if (file_exists($local_path)) {
        $local_url = cdnjs_get_local_fallback_url($script);

        // Add inline script to check if CDN loaded, fallback to local if not
        $fallback_script = "
        (function() {
            var cdnScript = document.querySelector('script[src*=\"{$script}\"]');
            if (cdnScript) {
                cdnScript.onerror = function() {
                    console.warn('CDN failed for {$script}, loading local fallback');
                    var fallback = document.createElement('script');
                    fallback.src = '{$local_url}';
                    document.head.appendChild(fallback);

                    // Track the failure
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('" . admin_url('admin-ajax.php') . "?action=cdnjs_track_failure&script={$script}');
                    }
                };
            }
        })();
        ";

        wp_add_inline_script($script, $fallback_script, 'after');
    }
}

/**
 * Track script load performance
 */
function cdnjs_track_script_load($script, $url) {
    // Add performance monitoring script
    $monitoring_script = "
    (function() {
        if (window.PerformanceObserver) {
            var observer = new PerformanceObserver(function(list) {
                var entries = list.getEntries();
                entries.forEach(function(entry) {
                    if (entry.name.indexOf('{$script}') !== -1) {
                        var data = {
                            script: '{$script}',
                            duration: entry.duration,
                            transferSize: entry.transferSize || 0,
                            timestamp: Date.now()
                        };

                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(
                                '" . admin_url('admin-ajax.php') . "?action=cdnjs_track_performance',
                                JSON.stringify(data)
                            );
                        }
                    }
                });
            });
            observer.observe({ entryTypes: ['resource'] });
        }
    })();
    ";

    wp_add_inline_script($script, $monitoring_script, 'after');
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
    $script = isset($_GET['script']) ? sanitize_text_field($_GET['script']) : '';

    if (empty($script)) {
        wp_die();
    }

    $failures = get_option('cdnjs_failures', array());

    if (!isset($failures[$script])) {
        $failures[$script] = array('count' => 0, 'last_failure' => '');
    }

    $failures[$script]['count']++;
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
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);

    if (empty($data['script'])) {
        wp_die();
    }

    $performance = get_option('cdnjs_performance', array());

    if (!isset($performance[$data['script']])) {
        $performance[$data['script']] = array(
            'loads' => 0,
            'total_duration' => 0,
            'avg_duration' => 0
        );
    }

    $performance[$data['script']]['loads']++;
    $performance[$data['script']]['total_duration'] += floatval($data['duration']);
    $performance[$data['script']]['avg_duration'] = $performance[$data['script']]['total_duration'] / $performance[$data['script']]['loads'];

    update_option('cdnjs_performance', $performance);

    wp_die();
}
