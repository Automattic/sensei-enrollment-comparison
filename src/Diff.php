<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Generate.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

/**
 * Comparing the snapshot files.
 */
class Diff {
	/**
	 * Notices found during diff.
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * First snapshot.
	 *
	 * @var Snapshot
	 */
	private $a;

	/**
	 * Second snapshot.
	 *
	 * @var Snapshot
	 */
	private $b;

	/**
	 * All courses in two snapshots.
	 *
	 * @var WP_Post[]
	 */
	private $courses;

	/**
	 * All users in two snapshots.
	 *
	 * @var WP_User[]
	 */
	private $users;

	/**
	 * Diff constructor.
	 *
	 * @param Snapshot $a
	 * @param Snapshot $b
	 */
	public function __construct( Snapshot $a, Snapshot $b ) {
		$this->a = $a;
		$this->b = $b;

		$this->get_courses();

		$this->get_all_users();
	}

	/**
	 * Get snapshot A.
	 *
	 * @return Snapshot
	 */
	public function get_a() {
		return $this->a;
	}

	/**
	 * Get snapshot B.
	 *
	 * @return Snapshot
	 */
	public function get_b() {
		return $this->b;
	}

	/**
	 * Get diff for a specific course.
	 *
	 * @param int $course_id
	 *
	 * @return array
	 */
	public function get_enrollment_diff( $course_id ) {
		$course_users = $this->get_course_users( $course_id );
		$enrollment   = [];
		$courses      = $this->get_courses();
		if ( ! isset( $courses[ $course_id ] ) ) {
			$course_label = 'Unknown ' . $course_id;
			$course_link  = false;
		} else {
			$course_label = $courses[ $course_id ]->post_title;
			$course_link  = get_permalink( $course_id );
		}
		$same = true;
		foreach ( $course_users as $user_id => $user_label ) {
			$enrollment[ $user_id ]         = [
				'label' => $user_label,
				'a'     => $this->a->is_enrolled( $course_id, $user_id ),
				'b'     => $this->b->is_enrolled( $course_id, $user_id ),
			];
			$enrollment[ $user_id ]['same'] = $enrollment[ $user_id ]['a'] === $enrollment[ $user_id ]['b'];
			if ( ! $enrollment[ $user_id ]['same'] ) {
				$same = false;
			}
		}

		return [
			'course_label' => $course_label,
			'course_link'  => $course_link,
			'same'         => $same,
			'enrollment'   => $enrollment,
		];
	}

	/**
	 * Get users for both snapshots in a particular course.
	 *
	 * @param int $course_id
	 *
	 * @return array
	 */
	public function get_course_users( $course_id ) {
		$a_results       = $this->a->get_results();
		$b_results       = $this->b->get_results();
		$users           = $this->get_all_users();
		$course_user_ids = [];

		if ( isset( $a_results[ $course_id ] ) ) {
			$course_user_ids = array_merge( $course_user_ids, $a_results[ $course_id ] );
		}

		if ( isset( $b_results[ $course_id ] ) ) {
			$course_user_ids = array_merge( $course_user_ids, $b_results[ $course_id ] );
		}

		$course_users    = [];
		$course_user_ids = array_unique( $course_user_ids );
		foreach ( $course_user_ids as $user_id ) {
			if ( ! isset( $users[ $user_id ] ) ) {
				$course_users[ $user_id ] = 'Unknown ' . $user_id;
				continue;
			}
			$course_users[ $user_id ] = $users[ $user_id ]->display_name;
		}

		return $course_users;
	}

	/**
	 * Get all users between both snapshots.
	 *
	 * @return \WP_User[]
	 */
	public function get_all_users() {
		if ( ! isset( $this->users ) ) {
			$user_ids = [];
			foreach ( $this->a->get_results() as $course_id => $users ) {
				$user_ids = array_merge( $user_ids, $users );
			}
			foreach ( $this->b->get_results() as $course_id => $users ) {
				$user_ids = array_merge( $user_ids, $users );
			}

			$user_ids = array_unique( $user_ids );
			sort( $user_ids );

			$missing     = [];
			$this->users = [];
			foreach ( $user_ids as $user_id ) {
				$user = \get_user_by( 'ID', $user_id );
				if ( ! $user ) {
					$missing[] = $user_id;
				}

				$this->users[ $user_id ] = $user;
			}

			if ( ! empty( $missing ) ) {
				// translators: %s placeholder is list of user IDs.
				$this->notices['missing_users'] = sprintf( __( 'The following user IDs were included in snapshots but can no longer by found: %s', 'sensei-enrollment-comparison-tool' ), implode( ', ', $missing ) );
			}
		}

		return $this->users;
	}

	/**
	 * Get all courses between two snapshots.
	 *
	 * @return WP_Post[]
	 */
	public function get_courses() {
		if ( ! isset( $this->courses ) ) {
			$course_ids = array_unique( array_merge( array_keys( $this->a->get_results() ), array_keys( $this->a->get_results() ) ) );
			sort( $course_ids );

			$missing       = [];
			$this->courses = [];
			foreach ( $course_ids as $course_id ) {
				$course = \get_post( $course_id );
				if ( ! $course || 'course' !== get_post_type( $course ) ) {
					$missing[] = $course_id;
				}

				$this->courses[ $course_id ] = $course;
			}

			if ( ! empty( $missing ) ) {
				// translators: %s placeholder is list of course IDs.
				$this->notices['missing_courses'] = sprintf( __( 'The following course IDs were included in snapshots but can no longer by found: %s', 'sensei-enrollment-comparison-tool' ), implode( ', ', $missing ) );
			}
		}

		return $this->courses;
	}

	/**
	 * Get all notices.
	 *
	 * @return array
	 */
	public function get_notices() {
		return $this->notices;
	}
}
