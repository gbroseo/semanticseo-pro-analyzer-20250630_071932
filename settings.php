const OPTION_KEY = 'semanticseo_pro_settings';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'registerSettings' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'registerRestRoutes' ) );
    }

    public static function registerSettings() {
        $defaults = self::getDefaultSettings();
        register_setting(
            'semanticseo_pro',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitizeSettings' ),
                'default'           => $defaults,
            )
        );

        add_settings_section(
            'semanticseo_pro_main',
            __( 'SemanticSEO Pro Settings', 'semanticseo-pro' ),
            '__return_false',
            'semanticseo_pro'
        );

        add_settings_field(
            'textrazor_api_key',
            __( 'TextRazor API Key', 'semanticseo-pro' ),
            array( __CLASS__, 'fieldInput' ),
            'semanticseo_pro',
            'semanticseo_pro_main',
            array(
                'label_for' => 'textrazor_api_key',
                'type'      => 'password',
            )
        );

        add_settings_field(
            'analysis_language',
            __( 'Analysis Language', 'semanticseo-pro' ),
            array( __CLASS__, 'fieldSelect' ),
            'semanticseo_pro',
            'semanticseo_pro_main',
            array(
                'label_for' => 'analysis_language',
                'options'   => array(
                    'en' => __( 'English', 'semanticseo-pro' ),
                    'es' => __( 'Spanish', 'semanticseo-pro' ),
                    'fr' => __( 'French', 'semanticseo-pro' ),
                    'de' => __( 'German', 'semanticseo-pro' ),
                    'it' => __( 'Italian', 'semanticseo-pro' ),
                ),
            )
        );

        add_settings_field(
            'recurring_interval',
            __( 'Recurring Analysis Interval', 'semanticseo-pro' ),
            array( __CLASS__, 'fieldSelect' ),
            'semanticseo_pro',
            'semanticseo_pro_main',
            array(
                'label_for' => 'recurring_interval',
                'options'   => array(
                    'hourly'     => __( 'Hourly', 'semanticseo-pro' ),
                    'twicedaily' => __( 'Twice Daily', 'semanticseo-pro' ),
                    'daily'      => __( 'Daily', 'semanticseo-pro' ),
                    'weekly'     => __( 'Weekly', 'semanticseo-pro' ),
                ),
            )
        );

        add_settings_field(
            'export_format',
            __( 'Export Format', 'semanticseo-pro' ),
            array( __CLASS__, 'fieldSelect' ),
            'semanticseo_pro',
            'semanticseo_pro_main',
            array(
                'label_for' => 'export_format',
                'options'   => array(
                    'csv'  => __( 'CSV', 'semanticseo-pro' ),
                    'json' => __( 'JSON', 'semanticseo-pro' ),
                ),
            )
        );

        add_settings_field(
            'enable_subscription',
            __( 'Enable Subscription Management', 'semanticseo-pro' ),
            array( __CLASS__, 'fieldCheckbox' ),
            'semanticseo_pro',
            'semanticseo_pro_main',
            array(
                'label_for' => 'enable_subscription',
            )
        );
    }

    public static function getDefaultSettings() {
        return array(
            'textrazor_api_key'   => '',
            'analysis_language'   => 'en',
            'recurring_interval'  => 'daily',
            'export_format'       => 'csv',
            'enable_subscription' => false,
        );
    }

    public static function sanitizeSettings( $input ) {
        $defaults = self::getDefaultSettings();
        $output   = array();

        $output['textrazor_api_key'] = sanitize_text_field( $input['textrazor_api_key'] ?? '' );

        $allowed_languages = array( 'en', 'es', 'fr', 'de', 'it' );
        $lang = $input['analysis_language'] ?? $defaults['analysis_language'];
        $output['analysis_language'] = in_array( $lang, $allowed_languages, true ) ? $lang : $defaults['analysis_language'];

        $allowed_intervals = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
        $interval = $input['recurring_interval'] ?? $defaults['recurring_interval'];
        $output['recurring_interval'] = in_array( $interval, $allowed_intervals, true ) ? $interval : $defaults['recurring_interval'];

        $allowed_formats = array( 'csv', 'json' );
        $format = $input['export_format'] ?? $defaults['export_format'];
        $output['export_format'] = in_array( $format, $allowed_formats, true ) ? $format : $defaults['export_format'];

        $output['enable_subscription'] = ! empty( $input['enable_subscription'] );

        return $output;
    }

    public static function fieldInput( $args ) {
        $options = get_option( self::OPTION_KEY, self::getDefaultSettings() );
        $value   = esc_attr( $options[ $args['label_for'] ] ?? '' );
        $type    = $args['type'] ?? 'text';
        printf(
            '<input type="%s" id="%s" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr( $type ),
            esc_attr( $args['label_for'] ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['label_for'] ),
            $value
        );
    }

    public static function fieldSelect( $args ) {
        $options = get_option( self::OPTION_KEY, self::getDefaultSettings() );
        $current = $options[ $args['label_for'] ] ?? '';
        echo '<select id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $args['label_for'] ) . ']">';
        foreach ( $args['options'] as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $current, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    public static function fieldCheckbox( $args ) {
        $options = get_option( self::OPTION_KEY, self::getDefaultSettings() );
        $checked = ! empty( $options[ $args['label_for'] ] );
        printf(
            '<input type="checkbox" id="%s" name="%s[%s]" value="1"%s />',
            esc_attr( $args['label_for'] ),
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['label_for'] ),
            checked( $checked, true, false )
        );
    }

    public static function getSettings() {
        $settings = get_option( self::OPTION_KEY, self::getDefaultSettings() );
        return wp_parse_args( $settings, self::getDefaultSettings() );
    }

    public static function saveSettings( $data ) {
        $sanitized = self::sanitizeSettings( (array) $data );
        update_option( self::OPTION_KEY, $sanitized );
        return $sanitized;
    }

    public static function registerRestRoutes() {
        register_rest_route(
            'semanticseo-pro/v1',
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'restGetSettings' ),
                    'permission_callback' => array( __CLASS__, 'permissionsCheck' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'restSaveSettings' ),
                    'permission_callback' => array( __CLASS__, 'permissionsCheck' ),
                ),
            )
        );
    }

    public static function restGetSettings( $request ) {
        $settings = self::getSettings();
        if ( ! empty( $settings['textrazor_api_key'] ) ) {
            $settings['textrazor_api_key'] = '********';
        }
        return rest_ensure_response( $settings );
    }

    public static function restSaveSettings( $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'invalid_data', __( 'Invalid data', 'semanticseo-pro' ), array( 'status' => 400 ) );
        }
        $settings = self::saveSettings( $params );
        return rest_ensure_response( $settings );
    }

    public static function permissionsCheck( $request ) {
        return current_user_can( 'manage_options' );
    }
}

SemanticSEOPro_Settings::init();