public static function loadFooter(): void {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script(
                'semanticseo-pro-footer',
                SEMANTICSEO_PRO_URL . 'assets/js/footer.min.js',
                [ 'jquery' ],
                SEMANTICSEO_PRO_VERSION,
                true
            );
            wp_enqueue_style(
                'semanticseo-pro-footer',
                SEMANTICSEO_PRO_URL . 'assets/css/footer.min.css',
                [],
                SEMANTICSEO_PRO_VERSION
            );
        } else {
            if ( defined( 'SEMANTICSEO_PRO_URL' ) ) {
                $assets_url = rtrim( SEMANTICSEO_PRO_URL, '/' ) . '/assets';
            } elseif ( function_exists( 'plugin_dir_url' ) ) {
                $assets_url = dirname( plugin_dir_url( __FILE__ ) ) . '/assets';
            } else {
                $assets_url = '/assets';
            }

            $version  = defined( 'SEMANTICSEO_PRO_VERSION' ) ? SEMANTICSEO_PRO_VERSION : '';
            $css_file = '/css/footer.min.css' . ( $version !== '' ? '?ver=' . rawurlencode( $version ) : '' );
            $js_file  = '/js/footer.min.js'  . ( $version !== '' ? '?ver=' . rawurlencode( $version ) : '' );

            $css_href = $assets_url . $css_file;
            $js_src   = $assets_url . $js_file;

            if ( function_exists( 'esc_url' ) ) {
                $css_href = esc_url( $css_href );
                $js_src   = esc_url( $js_src );
            } else {
                $css_href = htmlspecialchars( $css_href, ENT_QUOTES, 'UTF-8' );
                $js_src   = htmlspecialchars( $js_src, ENT_QUOTES, 'UTF-8' );
            }

            echo '<link rel="stylesheet" href="' . $css_href . '">' . "\n";
            echo '<script src="' . $js_src . '"></script>' . "\n";
        }
    }

    public static function renderFooter(): void {
        $year = function_exists( 'date_i18n' ) ? date_i18n( 'Y' ) : gmdate( 'Y' );

        if ( function_exists( 'get_bloginfo' ) ) {
            $site_name = get_bloginfo( 'name' );
        } elseif ( defined( 'SEMANTICSEO_PRO_SITE_NAME' ) ) {
            $site_name = SEMANTICSEO_PRO_SITE_NAME;
        } else {
            $site_name = '';
        }

        $escaped_year      = function_exists( 'esc_html' ) ? esc_html( $year ) : htmlspecialchars( $year, ENT_QUOTES, 'UTF-8' );
        $escaped_site_name = function_exists( 'esc_html' ) ? esc_html( $site_name ) : htmlspecialchars( $site_name, ENT_QUOTES, 'UTF-8' );

        $message         = function_exists( '__' ) ? __( 'All rights reserved.', 'semanticseo-pro' ) : 'All rights reserved.';
        $escaped_message = function_exists( 'esc_html' ) ? esc_html( $message ) : htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );

        echo '<footer class="semanticseo-pro-footer"><div class="container"><p>&copy; ' . $escaped_year . ' ' . $escaped_site_name . ' &mdash; ' . $escaped_message . '</p></div></footer>';
    }
}

if ( function_exists( 'add_action' ) ) {
    add_action( 'wp_enqueue_scripts', [ Footer::class, 'loadFooter' ] );
    add_action( 'wp_footer',        [ Footer::class, 'renderFooter' ] );
}