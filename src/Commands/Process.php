<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Commands\Process.
 *
 * @package tlf-migrator
 */

namespace Sensei\EnrollmentComparisonTool\Commands;

use Sensei\EnrollmentComparisonTool\Generate;
use WP_CLI;

/**
 * Class Process.
 */
class Process {
	/**
	 * Invoke the generate command.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		define( 'SENSEI_IS_GENERATE_CLI', true );

		$generate = Generate::instance();
		$snapshot = $generate->get_active_generation();
		if ( ! $snapshot ) {
			$friendly_name = null;
			$trust_cache   = false;

			if ( ! empty( $args[0] ) ) {
				$friendly_name = sanitize_text_field( $args[0] );
			}

			if ( ! empty( $args[1] ) ) {
				$trust_cache = boolval( $args[1] );
			}

			$trust_cache_desc = $trust_cache ? 'Yes' : 'No';
			\WP_CLI::confirm( "Start a new generation? (Friendly: {$friendly_name}; Trust Cache: $trust_cache_desc)" );

			$generate->start_snapshot( $friendly_name, $trust_cache );
			$snapshot = $generate->get_active_generation();
		} else {
			\WP_CLI::line( 'Resuming current snapshot...' );
		}

		$generate->lock( $snapshot );

		if ( 'init' === $snapshot->get_stage() ) {
			$generate->initialize_snapshot( $snapshot );
		}

		$progress  = \WP_CLI\Utils\make_progress_bar( 'Generating snapshot', $snapshot->get_total_calculations() );
		$last_tick = $snapshot->get_done_calculations();

		$progress->tick( $last_tick );
		while ( ! in_array( $snapshot->get_stage(), [ 'end', 'error' ], true ) ) {
			$generate->process();
			$next_tick = $snapshot->get_done_calculations();
			$diff_tick = $next_tick - $last_tick;
			$progress->tick( $diff_tick );
			$last_tick = $next_tick;
		}

		$progress->finish();
		\WP_CLI::line( 'Done!' );
	}

}
