public static function init() {
        add_action('admin_menu', [__CLASS__, 'registerAdminPages']);
        add_action('admin_init', [__CLASS__, 'registerSettings']);
        add_action('admin_post_ssa_update_settings', [__CLASS__, 'updateSettings']);
        add_filter('cron_schedules', [__CLASS__, 'addCronSchedules']);
    }

    public static function registerAdminPages() {
        add_menu_page(
            __('SemanticSEO Pro Analyzer', 'semanticseo-pro'),
            __('SemanticSEO Pro', 'semanticseo-pro'),
            'manage_options',
            'ssa_dashboard',
            [__CLASS__, 'showDashboard'],
            'dashicons-chart-area',
            80
        );
        add_submenu_page(
            'ssa_dashboard',
            __('Settings', 'semanticseo-pro'),
            __('Settings', 'semanticseo-pro'),
            'manage_options',
            'ssa_settings',
            [__CLASS__, 'showSettingsPage']
        );
    }

    public static function registerSettings() {
        register_setting('ssa_settings_group', 'ssa_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('ssa_settings_group', 'ssa_endpoint_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
        register_setting('ssa_settings_group', 'ssa_analysis_interval', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 24,
        ]);
        register_setting('ssa_settings_group', 'ssa_schedule_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitizeBoolean'],
            'default' => false,
        ]);
    }

    public static function addCronSchedules($schedules) {
        $interval_hours = get_option('ssa_analysis_interval', 24);
        $interval_hours = max(1, absint($interval_hours));
        if (!isset($schedules['ssa_interval'])) {
            $schedules['ssa_interval'] = [
                'interval' => $interval_hours * HOUR_IN_SECONDS,
                'display'  => sprintf(_n('%d Hour', '%d Hours', $interval_hours, 'semanticseo-pro'), $interval_hours),
            ];
        }
        return $schedules;
    }

    public static function sanitizeBoolean($value) {
        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function showSettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions to access this page.', 'semanticseo-pro'));
        }
        $api_key           = get_option('ssa_api_key', '');
        $endpoint_url      = get_option('ssa_endpoint_url', '');
        $analysis_interval = get_option('ssa_analysis_interval', 24);
        $schedule_enabled  = get_option('ssa_schedule_enabled', false);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SemanticSEO Pro Analyzer Settings', 'semanticseo-pro'); ?></h1>
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'semanticseo-pro'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php
                settings_fields('ssa_settings_group');
                do_settings_sections('ssa_settings_group');
                wp_nonce_field('ssa_update_settings', 'ssa_nonce');
                ?>
                <input type="hidden" name="action" value="ssa_update_settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ssa_api_key"><?php esc_html_e('TextRazor API Key', 'semanticseo-pro'); ?></label>
                        </th>
                        <td>
                            <input name="ssa_api_key" type="text" id="ssa_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ssa_endpoint_url"><?php esc_html_e('API Endpoint URL', 'semanticseo-pro'); ?></label>
                        </th>
                        <td>
                            <input name="ssa_endpoint_url" type="url" id="ssa_endpoint_url" value="<?php echo esc_attr($endpoint_url); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ssa_analysis_interval"><?php esc_html_e('Analysis Interval (Hours)', 'semanticseo-pro'); ?></label>
                        </th>
                        <td>
                            <input name="ssa_analysis_interval" type="number" id="ssa_analysis_interval" value="<?php echo esc_attr($analysis_interval); ?>" class="small-text" min="1">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ssa_schedule_enabled"><?php esc_html_e('Enable Recurring Analysis', 'semanticseo-pro'); ?></label>
                        </th>
                        <td>
                            <input name="ssa_schedule_enabled" type="checkbox" id="ssa_schedule_enabled" value="1" <?php checked($schedule_enabled); ?>>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function updateSettings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions to perform this action.', 'semanticseo-pro'));
        }
        check_admin_referer('ssa_update_settings', 'ssa_nonce');

        $api_key           = sanitize_text_field($_POST['ssa_api_key'] ?? '');
        $endpoint_url      = esc_url_raw($_POST['ssa_endpoint_url'] ?? '');
        $analysis_interval = max(1, absint($_POST['ssa_analysis_interval'] ?? 24));
        $schedule_enabled  = isset($_POST['ssa_schedule_enabled']);

        update_option('ssa_api_key', $api_key);
        update_option('ssa_endpoint_url', $endpoint_url);
        update_option('ssa_analysis_interval', $analysis_interval);
        update_option('ssa_schedule_enabled', $schedule_enabled);

        $hook = 'ssa_recurring_analysis';
        if ($schedule_enabled) {
            if (wp_next_scheduled($hook)) {
                wp_clear_scheduled_hook($hook);
            }
            wp_schedule_event(time() + ($analysis_interval * HOUR_IN_SECONDS), 'ssa_interval', $hook);
        } else {
            if (wp_next_scheduled($hook)) {
                wp_clear_scheduled_hook($hook);
            }
        }

        $redirect_url = add_query_arg('settings-updated', 'true', menu_page_url('ssa_settings', false));
        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function showDashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions to access this page.', 'semanticseo-pro'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SemanticSEO Pro Analyzer Dashboard', 'semanticseo-pro'); ?></h1>
            <div id="ssa-dashboard-root"></div>
        </div>
        <?php
    }

    public static function onActivate() {
        $schedule_enabled  = get_option('ssa_schedule_enabled', false);
        if ($schedule_enabled) {
            $analysis_interval = max(1, absint(get_option('ssa_analysis_interval', 24)));
            if (!wp_next_scheduled('ssa_recurring_analysis')) {
                wp_schedule_event(time() + ($analysis_interval * HOUR_IN_SECONDS), 'ssa_interval', 'ssa_recurring_analysis');
            }
        }
    }

    public static function onDeactivate() {
        $hook = 'ssa_recurring_analysis';
        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }
    }
}

register_activation_hook(__FILE__, ['PluginController', 'onActivate']);
register_deactivation_hook(__FILE__, ['PluginController', 'onDeactivate']);
PluginController::init();