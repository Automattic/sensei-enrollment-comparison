<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Generate.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

use Sensei\EnrollmentComparisonTool\Traits\Singleton;

/**
 * Generating the snapshot files.
 */
class Generate {
	const PREVIOUS_SNAPSHOT_OPTION   = 'sensei-enrollment-snapshots';
	const CURRENT_SNAPSHOT_TRANSIENT = 'sensei-enrollment-current-snapshot';
	const SCHEDULED_SNAPSHOT_NAME    = 'sensei_enrollment_comparison_tool_generate';
	const COURSES_PER_QUERY          = 5;

	use Singleton;

	/**
	 * Initializes the hooks.
	 */
	public function init() {
		\add_action( self::SCHEDULED_SNAPSHOT_NAME, [ $this, 'process' ] );
	}

	/**
	 * Process a new snapshot.
	 *
	 * @param int $call_level
	 */
	public function process( $call_level = 0 ) {
		$snapshot = $this->get_active_generation();
		if ( ! $snapshot ) {
			trigger_error( 'Process was called without an active snapshot' );
			return;
		}

		if ( $call_level > 5 ) {
			$snapshot->set_error( __( 'An unknown error occurred.', 'sensei-enrollment-comparison-tool' ) );
			$this->end_snapshot( $snapshot );
			return;
		}

		$call_level++;

		switch ( $snapshot->get_stage() ) {
			case 'init':
				$snapshot->init( \wp_count_posts( 'course' )->publish );
				$this->update_snapshot( $snapshot );
				$this->process( $call_level );

				return;
				break;
			case 'process':
				$this->process_snapshot( $snapshot );
				break;
			default:
				$snapshot->set_error( __( 'An unknown stage was entered.', 'sensei-enrollment-comparison-tool' ) );
				$this->end_snapshot( $snapshot );

				return;
				break;
		}

		$this->ensure_scheduled_job();
	}

	/**
	 * Handle the snapshot processing.
	 *
	 * @param Snapshot $snapshot
	 */
	private function process_snapshot( Snapshot $snapshot ) {
		$query_args = [
			'post_type'      => 'course',
			'post_status'    => 'publish',
			'posts_per_page' => self::COURSES_PER_QUERY,
			'offset'         => $snapshot->get_course_offset(),
		];
		$query      = new \WP_Query( $query_args );

		foreach ( $query->get_posts() as $post ) {
			$snapshot->add_course_student_list( $post->ID, $this->get_students( $post->ID ) );
		}

		if ( empty( $query->post_count ) || $snapshot->get_course_offset() >= $snapshot->get_total_courses() ) {
			$this->end_snapshot( $snapshot );
			return;
		}

		$this->update_snapshot( $snapshot );
	}

	/**
	 * Get all the users in the WP instance.
	 *
	 * @return \WP_User[]
	 */
	private function get_all_users() {
		static $users;

		if ( ! isset( $users ) ) {
			$users = \get_users();
		}

		return $users;
	}

	/**
	 * Get all the students enrolled in a course.
	 *
	 * @param int $course_id
	 *
	 * @return array
	 */
	private function get_students( $course_id ) {
		$students = [];
		foreach ( $this->get_all_users() as $user ) {
			if ( $this->is_student_enrolled( $course_id, $user->ID ) ) {
				$students[] = $user->ID;
			}
		}

		sort( $students );

		return $students;
	}

	/**
	 * Check if student is enrolled.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 *
	 * @return bool
	 */
	private function is_student_enrolled( $course_id, $user_id ) {
		if ( $this->is_sensei_3() ) {
			return \Sensei_Course::is_user_enrolled( $course_id, $user_id );
		}

		return \Sensei_Utils::user_started_course( $course_id, $user_id );
	}

	/**
	 * Wrap up a a snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public function end_snapshot( Snapshot $snapshot ) {
		$snapshots = Snapshots::get_snapshot_descriptors();

		$snapshot->end();
		$snapshots[ $snapshot->get_id() ] = $snapshot;

		Snapshots::store( $snapshot );
		\delete_transient( self::CURRENT_SNAPSHOT_TRANSIENT );
		\wp_clear_scheduled_hook( self::SCHEDULED_SNAPSHOT_NAME );
	}

	/**
	 * Store snapshot in db.
	 *
	 * @param Snapshot $snapshot
	 *
	 * @return bool
	 */
	public function update_snapshot( Snapshot $snapshot ) {
		return \set_transient( self::CURRENT_SNAPSHOT_TRANSIENT, \wp_json_encode( $snapshot ) );
	}

	/**
	 * Start a new snapshot.
	 *
	 * @param string|null $friendly_name
	 *
	 * @return bool
	 */
	public function start_snapshot( $friendly_name = null ) {
		if ( $this->get_active_generation() ) {
			return false;
		}

		$transient_result = $this->update_snapshot( Snapshot::start( $friendly_name ) );

		return $transient_result && $this->ensure_scheduled_job();
	}

	/**
	 * Get the current active snapshot.
	 *
	 * @return false|Snapshot
	 */
	public function get_active_generation() {
		$current_snapshot = \get_transient( self::CURRENT_SNAPSHOT_TRANSIENT );

		if ( $current_snapshot ) {
			$current_snapshot = Snapshot::from_json( $current_snapshot );
		}

		if ( empty( $current_snapshot ) || empty( $current_snapshot->get_id() ) ) {
			return false;
		}

		return $current_snapshot;
	}

	/**
	 * Makes sure the snapshot is scheduled.
	 *
	 * @return bool True if not needed or successful.
	 */
	public function ensure_scheduled_job() {
		if ( ! $this->get_active_generation() ) {
			return true;
		}

		$scheduled_event = \wp_get_scheduled_event( self::SCHEDULED_SNAPSHOT_NAME );
		if ( $scheduled_event ) {
			return true;
		}

		return \wp_schedule_single_event( time(), self::SCHEDULED_SNAPSHOT_NAME );
	}

	/**
	 * Returns if generation is currently possible.
	 *
	 * @return true|\WP_Error
	 */
	public function check_ability() {
		if ( ! class_exists( '\Sensei_Main' ) ) {
			return new \WP_Error( 'no-sensei', esc_html__( 'Sensei LMS must be activated.', 'sensei-enrollment-comparison-tool' ) );
		}

		// This is not compatible with Sensei 1.
		if ( $this->is_sensei_1() ) {
			return new \WP_Error( 'sensei-1', esc_html__( 'Sensei v1 is not compatible with this plugin.', 'sensei-enrollment-comparison-tool' ) );
		}

		// If Sensei and WCPC are activated, their versions need to be in-sync with the compatible versions.
		if ( ! $this->is_sensei_2() && ! $this->is_sensei_3() ) {
			return new \WP_Error( 'sensei-1', esc_html__( 'If you have WooCommerce Paid Courses (WCPC) activated, Sensei v3 must be installed WCPC v2 and Sensei v2 must be installed with WCPC v1.', 'sensei-enrollment-comparison-tool' ) );
		}

		return true;
	}

	/**
	 * Check if we are in a Sensei 3 instance.
	 *
	 * @return bool
	 */
	public function is_sensei_3() {
		if ( ! function_exists( '\Sensei' ) ) {
			return false;
		}

		if ( '3.' !== substr( \Sensei()->version, 0, 2 ) ) {
			return false;
		}

		if ( class_exists( '\Sensei_WC_Paid_Courses\Sensei_WC_Paid_Courses' ) && defined( 'SENSEI_WC_PAID_COURSES_VERSION' ) ) {
			if ( '2.' !== substr( SENSEI_WC_PAID_COURSES_VERSION, 0, 2 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if we are in a Sensei 2 instance.
	 *
	 * @return bool
	 */
	public function is_sensei_2() {
		if ( ! function_exists( '\Sensei' ) ) {
			return false;
		}

		if ( $this->is_sensei_3() || $this->is_sensei_1() ) {
			return false;
		}

		if ( class_exists( '\Sensei_WC_Paid_Courses\Sensei_WC_Paid_Courses' ) && defined( 'SENSEI_WC_PAID_COURSES_VERSION' ) ) {
			if ( '1.' !== substr( SENSEI_WC_PAID_COURSES_VERSION, 0, 2 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if we are in a Sensei 1 instance.
	 *
	 * @return bool
	 */
	public function is_sensei_1() {
		if ( ! function_exists( '\Sensei' ) ) {
			return false;
		}

		return '1.' === substr( \Sensei()->version, 0, 2 );
	}
}
