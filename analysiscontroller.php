public function init() {
        add_action( 'admin_menu', [ $this, 'registerMenus' ] );
        add_action( 'admin_post_handle_analysis', [ $this, 'handleAnalysisRequest' ] );
        add_action( 'admin_post_export_csv', [ $this, 'exportCsv' ] );
        add_action( 'admin_post_schedule_analysis', [ $this, 'scheduleAnalysis' ] );
    }

    public function registerMenus() {
        add_menu_page(
            __( 'Semantic Analysis', 'semanticseo-pro' ),
            __( 'Analysis', 'semanticseo-pro' ),
            'manage_options',
            'semanticseo-analysis',
            [ $this, 'showAnalysisForm' ],
            'dashicons-chart-area'
        );
        add_submenu_page(
            null,
            __( 'Analysis Results', 'semanticseo-pro' ),
            __( 'Analysis Results', 'semanticseo-pro' ),
            'manage_options',
            'semanticseo-analysis-results',
            [ $this, 'viewResults' ]
        );
    }

    public function showAnalysisForm() {
        include SEMANTICSEO_PLUGIN_DIR . 'templates/analysis-form.php';
    }

    public function handleAnalysisRequest() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'semanticseo-pro' ) );
        }
        check_admin_referer( 'semanticseo_handle_analysis' );

        $input = sanitize_textarea_field( $_POST['input'] ?? '' );
        $items = array_filter( array_map( 'trim', explode( PHP_EOL, $input ) ) );
        if ( empty( $items ) ) {
            wp_redirect( add_query_arg( 'error', 'no_input', admin_url( 'admin.php?page=semanticseo-analysis' ) ) );
            exit;
        }

        $apiKey = get_option( 'semanticseo_textrazor_api_key' );
        if ( empty( $apiKey ) ) {
            wp_redirect( add_query_arg( 'error', 'missing_api_key', admin_url( 'admin.php?page=semanticseo-analysis' ) ) );
            exit;
        }

        $textrazor    = new TextRazorService( $apiKey );
        $model        = new AnalysisModel();
        $ids          = [];
        $failedItems  = [];

        foreach ( $items as $item ) {
            try {
                $result = $textrazor->analyze( $item );
                $ids[]  = $model->save( $item, $result, get_current_user_id() );
            } catch ( \Exception $e ) {
                error_log( $e->getMessage() );
                $failedItems[] = $item;
            }
        }

        $idsParam   = implode( ',', $ids );
        $redirectUrl = admin_url( "admin.php?page=semanticseo-analysis-results&ids={$idsParam}" );
        if ( ! empty( $failedItems ) ) {
            set_transient( 'semanticseo_failed_items_' . get_current_user_id(), $failedItems, 300 );
            $redirectUrl = add_query_arg( 'errors', '1', $redirectUrl );
        }

        wp_redirect( $redirectUrl );
        exit;
    }

    public function viewResults() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'semanticseo-pro' ) );
        }

        if ( isset( $_GET['errors'] ) && '1' === $_GET['errors'] ) {
            $transient_key = 'semanticseo_failed_items_' . get_current_user_id();
            $failedItems   = get_transient( $transient_key );
            delete_transient( $transient_key );
            if ( ! empty( $failedItems ) ) {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Analysis failed for the following items:', 'semanticseo-pro' ) . '</p><ul>';
                foreach ( $failedItems as $failedItem ) {
                    echo '<li>' . esc_html( $failedItem ) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        $ids = [];
        if ( isset( $_GET['ids'] ) ) {
            $ids = array_map( 'absint', explode( ',', sanitize_text_field( $_GET['ids'] ) ) );
        }

        $model = new AnalysisModel();
        $data  = $model->getByIds( $ids );
        include SEMANTICSEO_PLUGIN_DIR . 'templates/analysis-results.php';
    }

    public function exportCsv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'semanticseo-pro' ) );
        }
        check_admin_referer( 'semanticseo_export_csv' );

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id ) {
            wp_die( __( 'Invalid ID', 'semanticseo-pro' ) );
        }

        $model    = new AnalysisModel();
        $data     = $model->getById( $id );
        $exporter = new CsvExporter();
        $exporter->export( $data, "analysis_{$id}.csv" );
        exit;
    }

    public function scheduleAnalysis() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'semanticseo-pro' ) );
        }
        check_admin_referer( 'semanticseo_schedule_analysis' );

        $input     = sanitize_textarea_field( $_POST['input'] ?? '' );
        $frequency = sanitize_text_field( $_POST['frequency'] ?? '' );
        $startTime = sanitize_text_field( $_POST['start_time'] ?? '' );
        $items     = array_filter( array_map( 'trim', explode( PHP_EOL, $input ) ) );

        $validFreq = [ 'hourly', 'daily', 'weekly', 'monthly' ];
        $timestamp = strtotime( $startTime );
        if ( empty( $items ) || ! in_array( $frequency, $validFreq, true ) || ! $timestamp || $timestamp <= time() ) {
            wp_redirect( add_query_arg( 'error', 'invalid_schedule', admin_url( 'admin.php?page=semanticseo-analysis' ) ) );
            exit;
        }

        $model = new ScheduleModel();
        try {
            $model->create( [
                'user_id'    => get_current_user_id(),
                'items'      => maybe_serialize( $items ),
                'frequency'  => $frequency,
                'start_time' => $startTime,
            ] );
        } catch ( \Exception $e ) {
            error_log( $e->getMessage() );
            wp_redirect( add_query_arg( 'error', 'schedule_failed', admin_url( 'admin.php?page=semanticseo-analysis' ) ) );
            exit;
        }

        wp_redirect( add_query_arg( 'success', 'schedule_created', admin_url( 'admin.php?page=semanticseo-analysis' ) ) );
        exit;
    }
}

$controller = new AnalysisController();
$controller->init();