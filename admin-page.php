<?php
add_action('admin_menu', 'cdnjs_script_loader_menu');

function cdnjs_script_loader_menu() {
    add_options_page('CDNJS Script Loader Settings', 'CDNJS Script Loader', 'manage_options', 'cdnjs-script-loader', 'cdnjs_script_loader_options_page');
}

function cdnjs_script_loader_options_page() {
    $options = get_option('cdnjs_script_loader_settings');
    $performance = get_option('cdnjs_performance', array());
    $failures = get_option('cdnjs_failures', array());

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'libraries';
    ?>
    <div class="wrap">
        <h1>CDNJS Script Loader</h1>

        <?php settings_errors('cdnjs_messages'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=cdnjs-script-loader&tab=libraries" class="nav-tab <?php echo $active_tab === 'libraries' ? 'nav-tab-active' : ''; ?>">Libraries</a>
            <a href="?page=cdnjs-script-loader&tab=fallback" class="nav-tab <?php echo $active_tab === 'fallback' ? 'nav-tab-active' : ''; ?>">Fallback</a>
            <a href="?page=cdnjs-script-loader&tab=performance" class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>">Performance</a>
        </h2>

        <?php if ($active_tab === 'libraries'): ?>
            <div class="tab-content">
                <p class="description">To check available scripts and their versions, visit <a href="https://cdnjs.com/" target="_blank">cdnjs.com</a>.</p>

                <form action="options.php" method="post">
                    <?php
                    settings_fields('cdnjs_script_loader_options');
                    do_settings_sections('cdnjs_script_loader');
                    ?>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 30%">Library Name</th>
                                <th style="width: 20%">Version</th>
                                <th style="width: 30%">Custom Filename (optional)</th>
                                <th style="width: 20%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="script-fields">
                            <?php if (!empty($options['scripts']) && is_array($options['scripts'])): ?>
                                <?php foreach ($options['scripts'] as $index => $script): ?>
                                    <tr class="script-field">
                                        <td>
                                            <input type="text" class="regular-text" name="cdnjs_script_loader_settings[scripts][]" placeholder="e.g., jquery" value="<?php echo esc_attr($script); ?>" required />
                                        </td>
                                        <td>
                                            <input type="text" class="regular-text" name="cdnjs_script_loader_settings[versions][]" placeholder="e.g., 3.7.1" value="<?php echo esc_attr($options['versions'][$index]); ?>" required />
                                        </td>
                                        <td>
                                            <input type="text" class="regular-text" name="cdnjs_script_loader_settings[filenames][<?php echo esc_attr($script); ?>]" placeholder="e.g., jquery.min.js" value="<?php echo isset($options['filenames'][$script]) ? esc_attr($options['filenames'][$script]) : ''; ?>" />
                                        </td>
                                        <td>
                                            <button type="button" class="button remove-script-field">Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="script-field">
                                    <td>
                                        <input type="text" class="regular-text" name="cdnjs_script_loader_settings[scripts][]" placeholder="e.g., jquery" />
                                    </td>
                                    <td>
                                        <input type="text" class="regular-text" name="cdnjs_script_loader_settings[versions][]" placeholder="e.g., 3.7.1" />
                                    </td>
                                    <td>
                                        <input type="text" class="regular-text" name="cdnjs_script_loader_settings[filenames][]" placeholder="e.g., jquery.min.js" />
                                    </td>
                                    <td>
                                        <button type="button" class="button remove-script-field">Remove</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p>
                        <button type="button" id="add-script-field" class="button">Add Library</button>
                    </p>

                    <?php submit_button('Save Libraries'); ?>
                </form>
            </div>

        <?php elseif ($active_tab === 'fallback'): ?>
            <div class="tab-content">
                <h2>Local Fallback Settings</h2>
                <p class="description">Enable automatic fallback to local copies when CDN fails. Upload local copies of your libraries for reliability.</p>

                <form action="options.php" method="post" enctype="multipart/form-data">
                    <?php settings_fields('cdnjs_script_loader_options'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_fallback">Enable Fallback</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_fallback" name="cdnjs_script_loader_settings[enable_fallback]" value="1" <?php checked(!empty($options['enable_fallback'])); ?> />
                                    Automatically load local copies if CDN fails
                                </label>
                                <p class="description">When enabled, the plugin will detect CDN failures and load local fallback copies.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save Fallback Settings'); ?>
                </form>

                <hr />

                <h3>Upload Fallback Files</h3>
                <p class="description">Upload local copies of your libraries. Files should be named <code>library-name.min.js</code></p>

                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('cdnjs_upload_fallback', 'cdnjs_fallback_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="fallback_file">Library File</label>
                            </th>
                            <td>
                                <input type="file" id="fallback_file" name="fallback_file" accept=".js" />
                                <p class="description">Upload a JavaScript file (.js)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="fallback_script_name">Library Name</label>
                            </th>
                            <td>
                                <input type="text" id="fallback_script_name" name="fallback_script_name" class="regular-text" placeholder="e.g., jquery" />
                                <p class="description">Must match the library name from the Libraries tab</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Upload Fallback File', 'secondary'); ?>
                </form>

                <hr />

                <h3>Uploaded Fallback Files</h3>
                <?php
                $upload_dir = wp_upload_dir();
                $fallback_dir = $upload_dir['basedir'] . '/cdnjs-fallbacks/';

                if (is_dir($fallback_dir)) {
                    $files = glob($fallback_dir . '*.js');
                    if (!empty($files)):
                ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Library</th>
                                <th>File Size</th>
                                <th>Last Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file):
                                $filename = basename($file);
                                $library_name = str_replace('.min.js', '', $filename);
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($library_name); ?></code></td>
                                    <td><?php echo size_format(filesize($file)); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', filemtime($file)); ?></td>
                                    <td>
                                        <a href="?page=cdnjs-script-loader&tab=fallback&delete=<?php echo urlencode($library_name); ?>&_wpnonce=<?php echo wp_create_nonce('delete_fallback_' . $library_name); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this fallback file?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php
                    else:
                        echo '<p>No fallback files uploaded yet.</p>';
                    endif;
                } else {
                    echo '<p>Fallback directory does not exist yet. It will be created when you upload your first file.</p>';
                }
                ?>
            </div>

        <?php elseif ($active_tab === 'performance'): ?>
            <div class="tab-content">
                <h2>Performance Dashboard</h2>
                <p class="description">Monitor CDN performance and reliability for your libraries.</p>

                <?php if (empty($performance) && empty($failures)): ?>
                    <div class="notice notice-info">
                        <p>No performance data collected yet. Data will appear here as visitors load your site.</p>
                    </div>
                <?php else: ?>

                    <h3>Library Performance</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Library</th>
                                <th>Total Loads</th>
                                <th>Avg Load Time</th>
                                <th>Failures</th>
                                <th>Last Failure</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_scripts = array_unique(array_merge(
                                array_keys($performance),
                                array_keys($failures)
                            ));

                            foreach ($all_scripts as $script):
                                $perf = isset($performance[$script]) ? $performance[$script] : array('loads' => 0, 'avg_duration' => 0);
                                $fail = isset($failures[$script]) ? $failures[$script] : array('count' => 0, 'last_failure' => '');

                                $failure_rate = $perf['loads'] > 0 ? ($fail['count'] / $perf['loads']) * 100 : 0;
                                $status_class = $failure_rate > 10 ? 'error' : ($failure_rate > 5 ? 'warning' : 'success');
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($script); ?></strong></td>
                                    <td><?php echo number_format($perf['loads']); ?></td>
                                    <td><?php echo number_format($perf['avg_duration'], 2); ?> ms</td>
                                    <td><?php echo number_format($fail['count']); ?> (<?php echo number_format($failure_rate, 1); ?>%)</td>
                                    <td><?php echo $fail['last_failure'] ? esc_html($fail['last_failure']) : 'Never'; ?></td>
                                    <td>
                                        <span class="dashicons dashicons-<?php echo $status_class === 'success' ? 'yes-alt' : ($status_class === 'warning' ? 'warning' : 'dismiss'); ?>" style="color: <?php echo $status_class === 'success' ? 'green' : ($status_class === 'warning' ? 'orange' : 'red'); ?>;"></span>
                                        <?php echo $status_class === 'success' ? 'Good' : ($status_class === 'warning' ? 'Warning' : 'Critical'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top: 20px;">
                        <a href="?page=cdnjs-script-loader&tab=performance&reset_stats=1&_wpnonce=<?php echo wp_create_nonce('reset_performance_stats'); ?>" class="button" onclick="return confirm('Are you sure you want to reset all performance statistics?');">Reset Statistics</a>
                    </p>

                <?php endif; ?>
            </div>
        <?php endif; ?>
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

    if (isset($input['filenames']) && is_array($input['filenames'])) {
        foreach ($input['filenames'] as $key => $filename) {
            $new_input['filenames'][$key] = sanitize_text_field($filename);
        }
    }

    if (isset($input['enable_fallback'])) {
        $new_input['enable_fallback'] = 1;
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

/**
 * Handle file upload for fallback files
 */
add_action('admin_init', 'cdnjs_handle_fallback_upload');

function cdnjs_handle_fallback_upload() {
    if (!isset($_POST['cdnjs_fallback_nonce']) || !wp_verify_nonce($_POST['cdnjs_fallback_nonce'], 'cdnjs_upload_fallback')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (empty($_FILES['fallback_file']) || empty($_POST['fallback_script_name'])) {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Please provide both a file and library name.', 'error');
        return;
    }

    $script_name = sanitize_text_field($_POST['fallback_script_name']);
    $file = $_FILES['fallback_file'];

    // Validate file type
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if ($file_ext !== 'js') {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Only JavaScript files (.js) are allowed.', 'error');
        return;
    }

    // Create fallback directory if it doesn't exist
    $upload_dir = wp_upload_dir();
    $fallback_dir = $upload_dir['basedir'] . '/cdnjs-fallbacks/';

    if (!file_exists($fallback_dir)) {
        wp_mkdir_p($fallback_dir);
    }

    // Move uploaded file
    $target_file = $fallback_dir . $script_name . '.min.js';

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Fallback file uploaded successfully for: ' . $script_name, 'success');
    } else {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Failed to upload fallback file.', 'error');
    }
}

/**
 * Handle fallback file deletion
 */
add_action('admin_init', 'cdnjs_handle_fallback_delete');

function cdnjs_handle_fallback_delete() {
    if (!isset($_GET['delete']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    $script_name = sanitize_text_field($_GET['delete']);

    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_fallback_' . $script_name)) {
        wp_die('Invalid nonce');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/cdnjs-fallbacks/' . $script_name . '.min.js';

    if (file_exists($file_path) && unlink($file_path)) {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Fallback file deleted successfully.', 'success');
    } else {
        add_settings_error('cdnjs_messages', 'cdnjs_message', 'Failed to delete fallback file.', 'error');
    }

    wp_redirect(admin_url('options-general.php?page=cdnjs-script-loader&tab=fallback'));
    exit;
}

/**
 * Handle performance stats reset
 */
add_action('admin_init', 'cdnjs_handle_stats_reset');

function cdnjs_handle_stats_reset() {
    if (!isset($_GET['reset_stats']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    if (!wp_verify_nonce($_GET['_wpnonce'], 'reset_performance_stats')) {
        wp_die('Invalid nonce');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    delete_option('cdnjs_performance');
    delete_option('cdnjs_failures');

    add_settings_error('cdnjs_messages', 'cdnjs_message', 'Performance statistics reset successfully.', 'success');

    wp_redirect(admin_url('options-general.php?page=cdnjs-script-loader&tab=performance'));
    exit;
}
