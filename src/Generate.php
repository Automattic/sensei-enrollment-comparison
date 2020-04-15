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
	const CURRENT_SNAPSHOT_OPTION    = 'sensei-enrollment-current-snapshot';
	const SCHEDULED_SNAPSHOT_NAME    = 'sensei_enrollment_comparison_tool_generate';
	const COURSES_PER_QUERY          = 10;
	const CALCULATIONS_PER_RUN_PRE_3 = 50;
	const CALCULATIONS_PER_RUN_3     = 25;
	const CALCULATIONS_PER_RUN_CLI   = 50;

	use Singleton;

	/**
	 * Number of calculations left in this execution.
	 *
	 * @var int
	 */
	private $current_calculations_left;

	/**
	 * Active snapshot.
	 *
	 * @var Snapshot
	 */
	private $active_snapshot;

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

		if ( ! defined( 'SENSEI_IS_GENERATE_CLI' ) && $snapshot->is_locked() ) {
			return;
		}

		if ( $call_level > 5 ) {
			$snapshot->set_error( __( 'An unknown error occurred.', 'sensei-enrollment-comparison-tool' ) );
			$this->end_snapshot( $snapshot );
			return;
		}

		if ( $snapshot->is_locked() ) {
			$this->current_calculations_left = self::CALCULATIONS_PER_RUN_CLI;
		} elseif ( ! $this->is_sensei_3() ) {
			$this->current_calculations_left = self::CALCULATIONS_PER_RUN_PRE_3;
		} else {
			$this->current_calculations_left = self::CALCULATIONS_PER_RUN_3;
		}

		$call_level++;

		switch ( $snapshot->get_stage() ) {
			case 'init':
				$this->initialize_snapshot( $snapshot );
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
	 * Initialize the snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public function initialize_snapshot( Snapshot $snapshot ) {
		$total_courses = \wp_count_posts( 'course' )->publish;
		$total_users   = \count_users()['total_users'];
		$snapshot->init( $total_courses, $total_users );
		$this->update_snapshot( $snapshot );
	}

	/**
	 * Handle the snapshot processing.
	 *
	 * @param Snapshot $snapshot
	 */
	private function process_snapshot( Snapshot $snapshot ) {
		$this->current_snapshot = $snapshot;

		$query_args = [
			'post_type'      => 'course',
			'post_status'    => 'publish',
			'posts_per_page' => self::COURSES_PER_QUERY,
			'offset'         => $snapshot->get_course_offset(),
		];
		$query      = new \WP_Query( $query_args );

		foreach ( $query->get_posts() as $post ) {
			$student_results = $this->get_student_results( $snapshot, $post->ID, $snapshot->get_trust_cache() );

			if ( null === $student_results ) {
				$this->update_snapshot( $snapshot );

				return;
			}

			$snapshot->add_course_student_list( $post->ID, $student_results['students'] );
			if ( ! empty( $student_results['details'] ) ) {
				$snapshot->add_course_providing_details( $post->ID, $student_results['details'] );
			}
		}

		if ( empty( $query->post_count ) || $snapshot->get_course_offset() >= $snapshot->get_total_courses() ) {
			$this->end_snapshot( $snapshot );
			return;
		}

		$this->update_snapshot( $snapshot );
	}

	/**
	 * Get all the user IDs in the WP instance.
	 *
	 * @return int[]
	 */
	private function get_all_users() {
		static $user_ids;

		if ( ! isset( $user_ids ) ) {
			$user_ids = \get_users(
				[
					'fields' => 'ID',
				]
			);

			$user_ids = array_map( 'intval', $user_ids );
			sort( $user_ids );
		}

		return $user_ids;
	}

	/**
	 * Get all the students enrolled in a course.
	 *
	 * @param Snapshot $snapshot
	 * @param int      $course_id
	 *
	 * @return array
	 */
	private function get_student_results( $snapshot, $course_id ) {
		$state       = $snapshot->get_process_state();
		$trust_cache = $snapshot->get_trust_cache();

		if (
			empty( $state )
			|| empty( $state['course_id'] )
			|| $state['course_id'] !== $course_id
		) {
			$state = [
				'course_id'      => $course_id,
				'latest_user_id' => 0,
				'students'       => [],
				'details'        => [],
			];
		}

		foreach ( $this->get_all_users() as $user_id ) {
			if ( $user_id <= $state['latest_user_id'] ) {
				continue;
			}
			if ( $this->is_student_enrolled( $course_id, $user_id, $trust_cache ) ) {
				$state['students'][] = $user_id;

				if ( $this->is_sensei_3() ) {
					$student_details = $this->get_enrolment_providers( $course_id, $user_id );
					if ( ! empty( $student_details ) ) {
						$state['details'][ $user_id ] = $student_details;
					}
				}
			}

			$state['latest_user_id'] = $user_id;
			$snapshot->increment_calculations();
			$snapshot->set_process_state( $state );

			$this->current_calculations_left--;
			if ( $this->current_calculations_left <= 0 ) {
				return null;
			}
		}

		$snapshot->set_process_state( null );
		sort( $state['students'] );

		return [
			'students' => $state['students'],
			'details'  => $state['details'],
		];
	}

	/**
	 * Check if student is enrolled.
	 *
	 * @param int  $course_id
	 * @param int  $user_id
	 * @param bool $trust_cache
	 *
	 * @return bool
	 */
	private function is_student_enrolled( $course_id, $user_id, $trust_cache = false ) {
		if ( $this->is_sensei_3() ) {
			if ( $trust_cache ) {
				$term        = \Sensei_Learner::get_learner_term( $user_id );
				$is_enrolled = has_term( $term->term_id, \Sensei_PostTypes::LEARNER_TAXONOMY_NAME, $course_id );
			} else {
				add_filter( 'sensei_course_enrolment_store_results', [ \Sensei_Course_Enrolment::class, 'do_not_store_negative_enrolment_results' ], 10, 5 );
				$is_enrolled = \Sensei_Course::is_user_enrolled( $course_id, $user_id );
				remove_filter( 'sensei_course_enrolment_store_results', [ \Sensei_Course_Enrolment::class, 'do_not_store_negative_enrolment_results' ], 10 );
			}

			return $is_enrolled;
		}

		$current_user_id = get_current_user_id();

		// WCPC's legacy subscription checking behavior assumed we were logged in as this user.
		wp_set_current_user( $user_id );

		if ( class_exists( 'Sensei_WC_Subscriptions' ) && \Sensei_WC_Subscriptions::is_wc_subscriptions_active() ) {
			// WCPC 2.x can remove this filter and not add it back again.
			add_filter( 'sensei_user_started_course', [ 'Sensei_WC_Subscriptions', 'get_subscription_user_started_course' ], 10, 3 );
		}

		// We're dealing with legacy behavior.
		$is_student_enrolled = \Sensei_Utils::user_started_course( $course_id, $user_id );

		wp_set_current_user( $current_user_id );

		return $is_student_enrolled;
	}

	/**
	 * Get the enrolment provider IDs providing enrolment for a student.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 *
	 * @return string[]
	 */
	private function get_enrolment_providers( $course_id, $user_id ) {
		if ( ! $this->is_sensei_3() ) {
			return [];
		}

		$course_enrolment = \Sensei_Course_Enrolment::get_course_instance( $course_id );

		$provider_results = $course_enrolment->get_enrolment_check_results( $user_id );
		if ( ! $provider_results ) {
			return [];
		}

		$providers = [];

		foreach ( $provider_results->get_provider_results() as $provider_id => $result ) {
			if ( $result ) {
				$providers[] = $provider_id;
			}
		}

		return $providers;
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
		\delete_option( self::CURRENT_SNAPSHOT_OPTION );
		\wp_clear_scheduled_hook( self::SCHEDULED_SNAPSHOT_NAME );

		$this->clear_active_snapshot();
	}

	/**
	 * Store snapshot in db.
	 *
	 * @param Snapshot $snapshot
	 *
	 * @return bool
	 */
	public function update_snapshot( Snapshot $snapshot ) {
		return \update_option( self::CURRENT_SNAPSHOT_OPTION, \wp_json_encode( $snapshot ) );
	}

	/**
	 * Lock a snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public function lock( Snapshot $snapshot ) {
		$snapshot->lock();
		$this->update_snapshot( $snapshot );
	}

	/**
	 * Lock a snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public function unlock( Snapshot $snapshot ) {
		$snapshot->unlock();
		$this->update_snapshot( $snapshot );
	}

	/**
	 * Start a new snapshot.
	 *
	 * @param string|null $friendly_name
	 * @param bool        $trust_cache
	 *
	 * @return bool
	 */
	public function start_snapshot( $friendly_name = null, $trust_cache = false ) {
		if ( $this->get_active_generation() ) {
			return false;
		}

		$active_snapshot       = Snapshot::start( $friendly_name, $trust_cache );
		$result                = $this->update_snapshot( $active_snapshot );
		$this->active_snapshot = $active_snapshot;

		return $result && $this->ensure_scheduled_job();
	}

	/**
	 * Get the current active snapshot.
	 *
	 * @return false|Snapshot
	 */
	public function get_active_generation() {
		if ( ! isset( $this->active_snapshot ) ) {
			$current_snapshot = \get_option( self::CURRENT_SNAPSHOT_OPTION );

			if ( $current_snapshot ) {
				$this->active_snapshot = Snapshot::from_json( $current_snapshot );
			}
		}

		if ( empty( $this->active_snapshot ) || empty( $this->active_snapshot->get_id() ) ) {
			return false;
		}

		return $this->active_snapshot;
	}

	/**
	 * Clear the active snapshot.
	 */
	public function clear_active_snapshot() {
		$this->active_snapshot = null;
	}

	/**
	 * Makes sure the snapshot is scheduled.
	 *
	 * @return bool True if not needed or successful.
	 */
	public function ensure_scheduled_job() {
		$active_snapshot = $this->get_active_generation();
		if ( ! $active_snapshot ) {
			return true;
		}

		if ( $active_snapshot->is_locked() ) {
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

		// If Sensei and WCPC are activated, their versions need to be in-sync with the compatible versions.
		if ( ! $this->is_sensei_1() && ! $this->is_sensei_2() && ! $this->is_sensei_3() ) {
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
