<?php
/**
 * File containing the trait \Sensei\EnrollmentComparisonTool\Traits\Singleton.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool\Traits;

trait Singleton {

	/**
	 * Stores the instances.
	 *
	 * @var array
	 */
	private static $instance = [];

	/**
	 * Retrieves instance of singleton.
	 *
	 * @return static
	 */
	public static function instance() {
		if ( ! isset( self::$instance[ static::class ] ) ) {
			self::$instance[ static::class ] = new static();
		}

		return self::$instance[ static::class ];
	}
}
