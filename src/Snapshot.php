<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Main.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

/**
 * Snapshot of enrollment results..
 */
class Snapshot implements \JsonSerializable {
	/**
	 * Unique ID for snapshot.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Current point in the process of generating snapshot.
	 *
	 * @var string
	 */
	private $stage;

	/**
	 * Whether or not we trusted the cache when generating the snapshot.
	 *
	 * @var bool
	 */
	private $trust_cache = false;

	/**
	 * Friendly name given during creation.
	 *
	 * @var string
	 */
	private $friendly_name;

	/**
	 * Time snapshot generation process started.
	 *
	 * @var float
	 */
	private $start_time;

	/**
	 * Time snapshot generation process ended.
	 *
	 * @var float
	 */
	private $end_time;

	/**
	 * Total number of courses in snapshot.
	 *
	 * @var int
	 */
	private $total_courses;

	/**
	 * Sensei version.
	 *
	 * @var string
	 */
	private $sensei_version;

	/**
	 * WCPC version.
	 *
	 * @var string
	 */
	private $wcpc_version;

	/**
	 * Results.
	 *
	 * @var array
	 */
	private $results = [];

	/**
	 * Provider details.
	 *
	 * @var array
	 */
	private $providers = [];

	/**
	 * Error message.
	 *
	 * @var string|bool
	 */
	private $error = false;

	/**
	 * Snapshot constructor.
	 *
	 * @param array $values
	 */
	private function __construct( $values ) {
		foreach ( $values as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * Start the snapshot.
	 *
	 * @param null|string $friendly_name
	 * @param bool        $trust_cache
	 *
	 * @return Snapshot
	 */
	public static function start( $friendly_name = null, $trust_cache = false ) {
		if ( empty( $friendly_name ) ) {
			$friendly_name = self::default_friendly_name();
		}

		$initial_values = [
			'id'            => md5( uniqid() ),
			'friendly_name' => $friendly_name,
			'start_time'    => microtime( true ),
			'stage'         => 'init',
			'trust_cache'   => $trust_cache,
		];

		return new self( $initial_values );
	}

	/**
	 * Restore snapshot from JSON string.
	 *
	 * @param string $json_string
	 *
	 * @return Snapshot
	 */
	public static function from_json( $json_string ) {
		$values_raw = json_decode( $json_string, true );

		$values = [
			'id'             => isset( $values_raw['id'] ) ? \sanitize_text_field( $values_raw['id'] ) : null,
			'start_time'     => isset( $values_raw['start_time'] ) ? floatval( $values_raw['start_time'] ) : null,
			'end_time'       => isset( $values_raw['end_time'] ) ? floatval( $values_raw['end_time'] ) : null,
			'stage'          => isset( $values_raw['stage'] ) ? \sanitize_text_field( $values_raw['stage'] ) : null,
			'results'        => isset( $values_raw['results'] ) ? $values_raw['results'] : [],
			'providers'      => isset( $values_raw['providers'] ) ? $values_raw['providers'] : [],
			'error'          => isset( $values_raw['error'] ) ? \sanitize_text_field( $values_raw['error'] ) : false,
			'trust_cache'    => isset( $values_raw['trust_cache'] ) ? boolval( $values_raw['trust_cache'] ) : false,
			'friendly_name'  => isset( $values_raw['friendly_name'] ) ? \sanitize_text_field( $values_raw['friendly_name'] ) : null,
			'sensei_version' => isset( $values_raw['sensei_version'] ) ? \sanitize_text_field( $values_raw['sensei_version'] ) : null,
			'wcpc_version'   => isset( $values_raw['wcpc_version'] ) ? \sanitize_text_field( $values_raw['wcpc_version'] ) : null,
			'total_courses'  => isset( $values_raw['total_courses'] ) ? intval( $values_raw['total_courses'] ) : null,
		];

		return new self( $values );
	}

	/**
	 * Check if student is enrolled in a course.
	 *
	 * @param int $course_id
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function is_enrolled( $course_id, $user_id ) {
		if ( ! isset( $this->results[ $course_id ] ) ) {
			return false;
		}

		return in_array( $user_id, $this->results[ $course_id ], true );
	}

	/**
	 * Get the providers the student is enrolled with (if any).
	 *
	 * @param int $course_id
	 * @param int $user_id
	 *
	 * @return string[]
	 */
	public function get_enrolling_providers( $course_id, $user_id ) {
		if ( ! isset( $this->providers[ $course_id ] ) || ! isset( $this->providers[ $course_id ][ $user_id ] ) ) {
			return [];
		}

		$provider_ids = $this->providers[ $course_id ][ $user_id ];
		$providers    = [];
		foreach ( $provider_ids as $provider_id ) {
			$provider_label            = ucwords( str_replace( 'wc-', 'WooCommerce ', $provider_id ) );
			if ( class_exists( 'Sensei_Course_Enrolment_Manager' ) ) {
				$provider = \Sensei_Course_Enrolment_Manager::instance()->get_enrolment_provider_by_id( $provider_id );
				if ( $provider ) {
					$provider_label = $provider->get_name();
				}
			}
			$providers[ $provider_id ] = $provider_label;
		}

		return $providers;
	}

	/**
	 * Serialize object into array for JSON.
	 *
	 * @return array|mixed
	 */
	public function jsonSerialize() {
		$attributes = [
			'id',
			'start_time',
			'end_time',
			'stage',
			'results',
			'providers',
			'error',
			'sensei_version',
			'wcpc_version',
			'total_courses',
			'friendly_name',
			'trust_cache',
		];

		$arr = [];
		foreach ( $attributes as $key ) {
			$arr[ $key ] = $this->{$key};
		}

		return $arr;
	}

	/**
	 * Initialize snapshot.
	 *
	 * @param int $total_courses
	 */
	public function init( $total_courses = 0 ) {
		$this->sensei_version = \Sensei()->version;
		$this->wcpc_version   = defined( 'SENSEI_WC_PAID_COURSES_VERSION' ) ? SENSEI_WC_PAID_COURSES_VERSION : null;
		$this->stage          = 'process';
		$this->total_courses  = intval( $total_courses );
		$this->results        = [];
	}

	/**
	 * Generate a default friendly name for snapshot.
	 *
	 * @return string
	 */
	public static function default_friendly_name() {
		if ( function_exists( '\Sensei' ) && '3.' === substr( \Sensei()->version, 0, 2 ) ) {
			return \esc_html__( 'After Sensei v3.0 Migration', 'sensei-enrollment-comparison-tool' );
		}

		return \esc_html__( 'Before Sensei v3.0 Migration', 'sensei-enrollment-comparison-tool' );
	}

	/**
	 * End the snapshot.
	 */
	public function end() {
		$this->stage    = 'end';
		$this->end_time = microtime( true );
	}

	/**
	 * Get the ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the stage.
	 *
	 * @return string
	 */
	public function get_stage() {
		return $this->stage;
	}

	/**
	 * Get the error (if any).
	 *
	 * @return string|false
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Get the start time.
	 *
	 * @return float
	 */
	public function get_start_time() {
		return $this->start_time;
	}

	/**
	 * Get the end time.
	 *
	 * @return float
	 */
	public function get_end_time() {
		return $this->end_time;
	}

	/**
	 * Get if we should trust the cache on this build.
	 *
	 * @return bool
	 */
	public function get_trust_cache() {
		return (bool) $this->trust_cache;
	}

	/**
	 * Get the percentage complete.
	 *
	 * @return false|float
	 */
	public function get_percent_complete() {
		if ( empty( $this->get_total_courses() ) ) {
			return false;
		}
		if ( 'end' === $this->stage ) {
			return 100;
		}

		return round( ( $this->get_course_offset() / $this->get_total_courses() ) * 100, 1 );
	}

	/**
	 * Add the enrollment result.
	 *
	 * @param int   $course_id
	 * @param int[] $student_ids
	 */
	public function add_course_student_list( $course_id, $student_ids ) {
		$this->results[ $course_id ] = $student_ids;
	}

	/**
	 * Add the enrollment providers providing enrolment.
	 *
	 * @param int        $course_id
	 * @param string[][] $providers
	 */
	public function add_course_providing_details( $course_id, $providers ) {
		$this->providers[ $course_id ] = $providers;
	}

	/**
	 * Set the error message.
	 *
	 * @param string $error Error message.
	 */
	public function set_error( $error ) {
		$this->error = $error;
	}

	/**
	 * Check if the comparison is valid.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return 'end' === $this->stage && ! $this->error;
	}

	/**
	 * Get the number of courses currently processed.
	 *
	 * @return int
	 */
	public function get_course_offset() {
		return count( $this->results );
	}

	/**
	 * Get the friendly date.
	 *
	 * @return string
	 */
	public function get_friendly_date() {
		$date_format = \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' );

		if ( function_exists( 'wp_date' ) ) {
			return \wp_date( $date_format, round( $this->get_start_time() ) );
		}

		return gmdate( $date_format, round( $this->get_start_time() ) );
	}

	/**
	 * Get the descriptor for the snapshot.
	 *
	 * @return string
	 */
	public function get_descriptor() {
		$wcpc_version = $this->get_wcpc_version();
		if ( ! $wcpc_version ) {
			$wcpc_version = 'Absent';
		}

		return sprintf( '%s (%s; Sensei: %s; WCPC: %s)', $this->get_friendly_name(), $this->get_friendly_date(), $this->get_sensei_version(), $wcpc_version );
	}

	/**
	 * Get the friendly name for the snapshot.
	 *
	 * @return string
	 */
	public function get_friendly_name() {
		if ( empty( $this->friendly_name ) ) {
			return __( 'Snapshot', 'sensei-enrollment-comparison-tool' );
		}
		return $this->friendly_name;
	}

	/**
	 * Get the Sensei version.
	 *
	 * @return string
	 */
	public function get_sensei_version() {
		return $this->sensei_version;
	}

	/**
	 * Get the WCPC version.
	 *
	 * @return string
	 */
	public function get_wcpc_version() {
		return $this->wcpc_version;
	}

	/**
	 * Get the total courses.
	 *
	 * @return int
	 */
	public function get_total_courses() {
		return $this->total_courses;
	}

	/**
	 * Get the results.
	 *
	 * @return array
	 */
	public function get_results() {
		return $this->results;
	}

}
