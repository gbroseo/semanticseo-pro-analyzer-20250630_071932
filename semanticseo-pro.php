const VERSION     = '1.0.0';
    const TEXT_DOMAIN = 'semanticseo-pro';
    private static $instance = null;

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants() {
        if ( ! defined( 'SESP_VERSION' ) ) {
            define( 'SESP_VERSION', self::VERSION );
        }
        if ( ! defined( 'SESP_PLUGIN_FILE' ) ) {
            define( 'SESP_PLUGIN_FILE', __FILE__ );
        }
        if ( ! defined( 'SESP_PLUGIN_DIR' ) ) {
            define( 'SESP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        }
        if ( ! defined( 'SESP_PLUGIN_URL' ) ) {
            define( 'SESP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        }
        if ( ! defined( 'SESP_TEXT_DOMAIN' ) ) {
            define( 'SESP_TEXT_DOMAIN', self::TEXT_DOMAIN );
        }
    }

    private function includes() {
        if ( file_exists( SESP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
            require_once SESP_PLUGIN_DIR . 'vendor/autoload.php';
        }
        require_once SESP_PLUGIN_DIR . 'includes/class-sesp-admin.php';
        require_once SESP_PLUGIN_DIR . 'includes/class-sesp-frontend.php';
        require_once SESP_PLUGIN_DIR . 'includes/class-sesp-api.php';
        require_once SESP_PLUGIN_DIR . 'includes/class-sesp-cron.php';
    }

    private function init_hooks() {
        register_activation_hook( SESP_PLUGIN_FILE, array( __CLASS__, 'activatePlugin' ) );
        register_deactivation_hook( SESP_PLUGIN_FILE, array( __CLASS__, 'deactivatePlugin' ) );
        register_uninstall_hook( SESP_PLUGIN_FILE, array( __CLASS__, 'uninstallPlugin' ) );
        add_action( 'plugins_loaded', array( $this, 'loadTextDomain' ) );
        add_action( 'init', array( $this, 'initPlugin' ) );
    }

    public static function activatePlugin() {
        $defaults = array(
            'api_key'           => '',
            'analysis_schedule' => 'hourly',
            'export_format'     => 'csv',
        );
        if ( false === get_option( 'sesp_options' ) ) {
            add_option( 'sesp_options', $defaults );
        }
        if ( ! wp_next_scheduled( 'sesp_schedule_analysis' ) ) {
            wp_schedule_event( time(), 'hourly', 'sesp_schedule_analysis' );
        }
    }

    public static function deactivatePlugin() {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
            wp_clear_scheduled_hook( 'sesp_schedule_analysis' );
        } else {
            $timestamp = wp_next_scheduled( 'sesp_schedule_analysis' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'sesp_schedule_analysis' );
            }
        }
    }

    public static function uninstallPlugin() {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
            wp_clear_scheduled_hook( 'sesp_schedule_analysis' );
        }
        delete_option( 'sesp_options' );
    }

    public function loadTextDomain() {
        load_plugin_textdomain(
            SESP_TEXT_DOMAIN,
            false,
            dirname( plugin_basename( SESP_PLUGIN_FILE ) ) . '/languages'
        );
    }

    public function initPlugin() {
        if ( class_exists( 'SESP_Admin' ) ) {
            SESP_Admin::get_instance();
        }
        if ( class_exists( 'SESP_Frontend' ) ) {
            SESP_Frontend::get_instance();
        }
        if ( class_exists( 'SESP_API' ) ) {
            SESP_API::get_instance();
        }
        if ( class_exists( 'SESP_Cron' ) ) {
            SESP_Cron::get_instance();
            add_action( 'sesp_schedule_analysis', array( 'SESP_Cron', 'run_scheduled_analysis' ) );
        }
    }
}

SemanticSEO_Pro::instance();