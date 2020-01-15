<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Main.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

use Sensei\EnrollmentComparisonTool\Traits\Singleton;

/**
 * Main plugin class.
 */
class Main {

	use Singleton;

	/**
	 * Initializes the hooks.
	 */
	public function init() {
		Generate::instance()->init();

		if ( ! is_admin() ) {
			// Admin only tool.
			return;
		}

		Admin::instance()->init();
	}
}
