<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Admin.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

use Sensei\EnrollmentComparisonTool\Traits\Singleton;

/**
 * Frontend for generating and comparing enrolment snapshots.
 */
class Admin {

	use Singleton;

	/**
	 * Initializes the hooks.
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_menu_pages' ], 100 );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
	}

	/**
	 * Adds admin menu pages.
	 */
	public function add_menu_pages() {
		$title = \esc_html__( 'Enrollment Comparison Tool', 'sensei-enrollment-comparison-tool' );
		\add_submenu_page( 'sensei', $title, $title, 'manage_sensei', 'enrollment-comparison', [ $this, 'output_admin_page' ] );
	}

	/**
	 * Handle the admin page actions.
	 */
	public function handle_actions() {
		if ( isset( $_REQUEST['sensei-enrollment-comp-action'] ) ) {
			switch ( $_REQUEST['sensei-enrollment-comp-action'] ) {
				case 'generate':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Don't touch the nonce.
					if ( empty( $_POST['_wpnonce'] ) || ! \wp_verify_nonce( \wp_unslash( $_POST['_wpnonce'] ), 'sensei-generate-snapshot' ) ) {
						die( 'Invalid nonce.' );
					}

					$friendly_name = ! empty( $_POST['friendly_name'] ) ? sanitize_text_field( wp_unslash( $_POST['friendly_name'] ) ) : null;
					$trust_cache   = false;
					if ( Generate::instance()->is_sensei_3() && ! empty( $_POST['trust_cache'] ) ) {
						$trust_cache = true;
					}
					if ( Generate::instance()->start_snapshot( $friendly_name, $trust_cache ) ) {
						\wp_safe_redirect( \admin_url( 'admin.php?page=enrollment-comparison' ) );
					} else {
						echo '<div class="notice notice-error"><p>' . \esc_html__( 'Unable to start generation snapshot. A snapshot may have already been started.', 'sensei-enrollment-comparison-tool' ) . '</p></div>';
					}
					break;

				case 'delete':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Don't touch the nonce.
					if ( ! isset( $_GET['_wpnonce'] ) || ! \wp_verify_nonce( \wp_unslash( $_GET['_wpnonce'] ), 'sensei-delete-snapshot' ) ) {
						die( 'Invalid nonce.' );
					}

					if ( ! empty( $_GET['snapshot'] ) ) {
						$snapshot = Snapshots::get_snapshot( sanitize_text_field( wp_unslash( $_GET['snapshot'] ) ) );
					}

					if ( empty( $snapshot ) ) {
						echo '<div class="notice notice-error"><p>' . \esc_html__( 'Snapshot may already have been deleted.', 'sensei-enrollment-comparison-tool' ) . '</p></div>';
					} else {
						Snapshots::delete( $snapshot );
						\wp_safe_redirect( \admin_url( 'admin.php?page=enrollment-comparison' ) );

						return;
					}
					break;
			}
		}
	}

	/**
	 * Outputs the main admin page.
	 */
	public function output_admin_page() {
		if ( isset( $_REQUEST['sensei-enrollment-comp-action'] ) ) {
			switch ( $_REQUEST['sensei-enrollment-comp-action'] ) {
				case 'compare':
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Don't touch the nonce.
					if ( ! isset( $_GET['_wpnonce'] ) || ! \wp_verify_nonce( \wp_unslash( $_GET['_wpnonce'] ), 'sensei-compare-snapshots' ) ) {
						die( 'Invalid nonce.' );
					}

					if ( ! empty( $_GET['snapshot_a'] ) && ! empty( $_GET['snapshot_b'] ) ) {
						$snapshot_a = Snapshots::get_snapshot( sanitize_text_field( wp_unslash( $_GET['snapshot_a'] ) ) );
						$snapshot_b = Snapshots::get_snapshot( sanitize_text_field( wp_unslash( $_GET['snapshot_b'] ) ) );
					}

					$diff_only = ! empty( $_GET['diff_only'] );

					if ( empty( $snapshot_a ) || empty( $snapshot_b ) ) {
						echo '<div class="notice inline notice-error"><p>' . \esc_html__( 'Unable to find at least one of the selected snapshots.', 'sensei-enrollment-comparison-tool' ) . '</p></div>';
					} else {
						$diff = new Diff( $snapshot_a, $snapshot_b, $diff_only );
						include __DIR__ . '/Views/diff.php';

						return;
					}
					break;
			}
		}

		include __DIR__ . '/Views/admin.php';
	}
}
