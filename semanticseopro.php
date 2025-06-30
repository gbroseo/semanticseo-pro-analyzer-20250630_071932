function semanticseopro_loadTextDomain() {
    load_plugin_textdomain(
        'semanticseopro',
        false,
        dirname( plugin_basename( SEMANTICSEOPRO_PLUGIN_FILE ) ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'semanticseopro_loadTextDomain' );

function semanticseopro_registerPostType() {
    $labels = array(
        'name'               => __( 'Analyses', 'semanticseopro' ),
        'singular_name'      => __( 'Analysis', 'semanticseopro' ),
        'add_new_item'       => __( 'Add New Analysis', 'semanticseopro' ),
        'edit_item'          => __( 'Edit Analysis', 'semanticseopro' ),
        'new_item'           => __( 'New Analysis', 'semanticseopro' ),
        'view_item'          => __( 'View Analysis', 'semanticseopro' ),
        'search_items'       => __( 'Search Analyses', 'semanticseopro' ),
        'not_found'          => __( 'No analyses found', 'semanticseopro' ),
        'not_found_in_trash' => __( 'No analyses found in Trash', 'semanticseopro' ),
    );
    $args = array(
        'labels'      => $labels,
        'public'      => false,
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-chart-line',
        'supports'    => array( 'title', 'editor', 'custom-fields' ),
        'has_archive' => false,
    );
    register_post_type( 'semanticseopro_analysis', $args );
}

function semanticseopro_initPlugin() {
    semanticseopro_registerPostType();
    if ( function_exists( 'register_rest_route' ) ) {
        register_rest_route(
            'semanticseopro/v1',
            '/analyze',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => 'semanticseopro_handleAnalyze',
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            )
        );
    }
    if ( is_admin() ) {
        add_action( 'admin_menu', 'semanticseopro_registerAdminMenu' );
    }
}
add_action( 'init', 'semanticseopro_initPlugin' );

function semanticseopro_activatePlugin() {
    semanticseopro_registerPostType();
    $defaults = array(
        'textrazor_api_key' => '',
        'schedule_interval' => 'daily',
    );
    $options = get_option( 'semanticseopro_options', null );
    if ( ! is_array( $options ) ) {
        add_option( 'semanticseopro_options', $defaults );
        $options = $defaults;
    }
    if ( ! wp_next_scheduled( 'semanticseopro_scheduled_event' ) ) {
        $interval = isset( $options['schedule_interval'] ) ? $options['schedule_interval'] : 'daily';
        $schedules = wp_get_schedules();
        if ( ! isset( $schedules[ $interval ] ) ) {
            $interval = 'daily';
        }
        wp_schedule_event( time(), $interval, 'semanticseopro_scheduled_event' );
    }
    flush_rewrite_rules();
}
register_activation_hook( SEMANTICSEOPRO_PLUGIN_FILE, 'semanticseopro_activatePlugin' );

function semanticseopro_deactivatePlugin() {
    $timestamp = wp_next_scheduled( 'semanticseopro_scheduled_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'semanticseopro_scheduled_event' );
    }
    flush_rewrite_rules();
}
register_deactivation_hook( SEMANTICSEOPRO_PLUGIN_FILE, 'semanticseopro_deactivatePlugin' );

function semanticseopro_registerAdminMenu() {
    add_menu_page(
        __( 'SemanticSEO Pro', 'semanticseopro' ),
        __( 'SemanticSEO Pro', 'semanticseopro' ),
        'manage_options',
        'semanticseopro',
        'semanticseopro_renderAdminPage',
        'dashicons-chart-line',
        75
    );
}

function semanticseopro_renderAdminPage() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'SemanticSEO Pro Dashboard', 'semanticseopro' ); ?></h1>
        <div id="semanticseopro-app"></div>
    </div>
    <?php
}

function semanticseopro_handleAnalyze( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $options = get_option( 'semanticseopro_options', array() );
    if ( ! is_array( $options ) ) {
        $options = array();
    }
    $apiKey = isset( $options['textrazor_api_key'] ) ? trim( $options['textrazor_api_key'] ) : '';
    if ( empty( $apiKey ) ) {
        return new WP_Error(
            'no_api_key',
            __( 'TextRazor API key not set.', 'semanticseopro' ),
            array( 'status' => 400 )
        );
    }
    $content = isset( $params['content'] ) ? $params['content'] : '';
    if ( empty( $content ) ) {
        return new WP_Error(
            'no_content',
            __( 'No content provided for analysis.', 'semanticseopro' ),
            array( 'status' => 400 )
        );
    }
    try {
        $result = SemanticSEOPro\Core\Analyzer::analyze( $content, $apiKey );
        return rest_ensure_response( $result );
    } catch ( Exception $e ) {
        return new WP_Error(
            'analysis_error',
            $e->getMessage(),
            array( 'status' => 500 )
        );
    }
}

add_action( 'semanticseopro_scheduled_event', 'semanticseopro_runScheduledAnalyses' );
function semanticseopro_runScheduledAnalyses() {
    if ( class_exists( 'SemanticSEOPro\Core\Scheduler' ) ) {
        SemanticSEOPro\Core\Scheduler::run();
    }
}