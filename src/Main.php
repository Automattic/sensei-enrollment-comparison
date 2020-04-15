<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Main.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

use Sensei\EnrollmentComparisonTool\Commands\Process;
use Sensei\EnrollmentComparisonTool\Traits\Singleton;
use TLF\Migrator\Commands\Export;
use TLF\Migrator\Commands\Import;
use TLF\Migrator\Commands\PreImport;
use TLF\Migrator\Commands\SetupSite;

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

		add_action(
			'plugins_loaded',
			function() {
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					\WP_CLI::add_command( 'sensei-snapshot', new Process() );
				}
			},
			100
		);

		if ( ! is_admin() ) {
			// Admin only tool.
			return;
		}

		Admin::instance()->init();
	}
}
