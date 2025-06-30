public function __construct() {
        $this->job_model = new Job_Model();
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'listScheduledJobs' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'scheduleJob' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'name' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_string( $param ) && strlen( $param ) > 0;
                        },
                    ),
                    'schedule_time' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return strtotime( $param ) !== false;
                        },
                    ),
                    'recurrence' => array(
                        'required'          => false,
                        'validate_callback' => function( $param ) {
                            $allowed = array( 'hourly', 'twicedaily', 'daily' );
                            return in_array( $param, $allowed, true );
                        },
                    ),
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'cancelJob' ),
            'permission_callback' => array( $this, 'permissions_check' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );
    }

    public function permissions_check( $request ) {
        return is_user_logged_in() && current_user_can( 'manage_jobs' );
    }

    public function listScheduledJobs( $request ) {
        $user_id = get_current_user_id();
        $jobs    = $this->job_model->get_jobs_by_user( $user_id );
        return rest_ensure_response( $jobs );
    }

    public function scheduleJob( $request ) {
        $params     = $request->get_json_params();
        $name       = sanitize_text_field( $params['name'] );
        $time       = sanitize_text_field( $params['schedule_time'] );
        $recurrence = isset( $params['recurrence'] ) ? sanitize_text_field( $params['recurrence'] ) : '';
        $timestamp  = strtotime( $time );

        if ( ! $timestamp ) {
            return new WP_Error( 'invalid_time', 'Invalid schedule_time', array( 'status' => 400 ) );
        }

        $now = current_time( 'timestamp' );
        if ( $timestamp <= $now ) {
            return new WP_Error( 'invalid_time', 'schedule_time must be in the future', array( 'status' => 400 ) );
        }

        $user_id = get_current_user_id();
        $job_data = array(
            'user_id'      => $user_id,
            'name'         => $name,
            'scheduled_at' => date( 'Y-m-d H:i:s', $timestamp ),
            'recurrence'   => $recurrence,
            'status'       => 'scheduled',
            'created_at'   => current_time( 'mysql' ),
        );

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );
        $job_id = $this->job_model->create_job( $job_data );
        if ( ! $job_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Could not schedule job', array( 'status' => 500 ) );
        }

        $hook = 'semanticseopro_run_job';
        $args = array( 'job_id' => $job_id );

        try {
            if ( $recurrence ) {
                wp_schedule_event( $timestamp, $recurrence, $hook, $args );
            } else {
                wp_schedule_single_event( $timestamp, $hook, $args );
            }

            $scheduled = wp_next_scheduled( $hook, $args );
            if ( ! $scheduled ) {
                throw new Exception( 'Cron scheduling failed' );
            }

            $wpdb->query( 'COMMIT' );
        } catch ( Exception $e ) {
            if ( method_exists( $this->job_model, 'delete_job' ) ) {
                $this->job_model->delete_job( $job_id );
            } else {
                $this->job_model->update_job( $job_id, array(
                    'status'     => 'failed',
                    'updated_at' => current_time( 'mysql' ),
                ) );
            }
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'schedule_error', 'Could not schedule cron job', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'job_id' => $job_id ) );
    }

    public function cancelJob( $request ) {
        $id      = (int) $request['id'];
        $user_id = get_current_user_id();
        $job     = $this->job_model->get_job_by_id( $id );

        if ( ! $job || $job->user_id != $user_id ) {
            return new WP_Error( 'not_found', 'Job not found', array( 'status' => 404 ) );
        }

        $hook = 'semanticseopro_run_job';
        $args = array( 'job_id' => $id );

        if ( $job->recurrence ) {
            wp_clear_scheduled_hook( $hook, $args );
        } else {
            $timestamp = strtotime( $job->scheduled_at );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook, $args );
            }
        }

        $updated = $this->job_model->update_job( $id, array(
            'status'     => 'canceled',
            'updated_at' => current_time( 'mysql' ),
        ) );

        if ( ! $updated ) {
            return new WP_Error( 'db_error', 'Could not cancel job', array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'canceled' => true ) );
    }
}

new ScheduleJobController();