function ssp_register_shortcode() {
    add_shortcode( 'semanticseo_pro', 'ssp_render_shortcode' );
}

/**
 * Register scripts and styles for the shortcode.
 */
function ssp_register_shortcode_assets() {
    $plugin_dir = plugin_dir_path( __FILE__ );
    $plugin_url = plugin_dir_url( __FILE__ );

    // JavaScript.
    $js_path    = $plugin_dir . 'assets/js/shortcode.js';
    $js_url     = $plugin_url . 'assets/js/shortcode.js';
    $js_version = file_exists( $js_path ) ? filemtime( $js_path ) : false;
    wp_register_script(
        'ssp-shortcode-script',
        $js_url,
        array( 'jquery' ),
        $js_version,
        true
    );

    // Localize script.
    wp_localize_script(
        'ssp-shortcode-script',
        'SemanticSEOPro',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ssp_shortcode_nonce' ),
        )
    );

    // CSS.
    $css_path    = $plugin_dir . 'assets/css/shortcode.css';
    $css_url     = $plugin_url . 'assets/css/shortcode.css';
    $css_version = file_exists( $css_path ) ? filemtime( $css_path ) : false;
    wp_register_style(
        'ssp-shortcode-style',
        $css_url,
        array(),
        $css_version
    );
}

/**
 * Render the shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function ssp_render_shortcode( $atts ) {
    $defaults = array(
        'title'        => '',
        'show_summary' => 'true',
        'limit'        => 10,
    );
    $atts = shortcode_atts( $defaults, $atts, 'semanticseo_pro' );

    // Sanitize inputs.
    $atts['title']        = sanitize_text_field( $atts['title'] );
    $atts['show_summary'] = filter_var( $atts['show_summary'], FILTER_VALIDATE_BOOLEAN );
    $atts['limit']        = absint( $atts['limit'] );

    // Enqueue assets.
    wp_enqueue_style( 'ssp-shortcode-style' );
    wp_enqueue_script( 'ssp-shortcode-script' );

    // Locate template: allow theme override.
    $template = locate_template( 'semanticseo-pro/shortcode-view.php' );
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/shortcode-view.php';
    }
    // Allow filtering of template path.
    $template = apply_filters( 'ssp_shortcode_template', $template, $atts );

    ob_start();
    if ( file_exists( $template ) ) {
        include $template;
    }
    return ob_get_clean();
}