<?php
/**
 * Plugin Name: Sensei LMS - Enrollment Comparison Tool
 * Plugin URI: https://senseilms.org
 * Description: Allows generation and comparison of two different enrollment snapshots.
 * Version: 1.0.0
 * License: GPLv3
 * Requires at least: 5.1
 * Tested up to: 5.3
 * Requires PHP: 5.6
 * Author: Automattic
 * Author URI: https://automattic.com
 *
 * @package sensei-enrollment-comparison-tool
 */

define( 'SENSEI_ENROLLMENT_COMPARISON_TOOL_VERSION', '1.0.0' );
define( 'SENSEI_ENROLLMENT_COMPARISON_TOOL_PLUGIN_FILE', __FILE__ );
define( 'SENSEI_ENROLLMENT_COMPARISON_TOOL_PLUGIN_DIR', __DIR__ );
define( 'SENSEI_ENROLLMENT_COMPARISON_TOOL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require __DIR__ . '/vendor/autoload.php';

\Sensei\EnrollmentComparisonTool\Main::instance()->init();

