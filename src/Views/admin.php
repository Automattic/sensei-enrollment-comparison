<?php
/**
 * Admin View: Page - Sensei Enrollment Comparison Tool
 *
 * @package sensei-enrollment-comparison-tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="wrap sensei">
	<h1><?php esc_html_e( 'Sensei LMS Enrollment Comparison Tool', 'sensei-enrollment-comparison-tool' ); ?></h1>

	<h2><?php esc_html_e( 'Generate Snapshot', 'sensei-enrollment-comparison-tool' ); ?></h2>
	<?php
	$generate = \Sensei\EnrollmentComparisonTool\Generate::instance();
	$generate->ensure_scheduled_job();

	$generate_ability = $generate->check_ability();
	$active_snapshot  = $generate->get_active_generation();

	if ( \is_wp_error( $generate_ability ) ) {
		echo '<div class="notice inline notice-error"><p>' . esc_html( $generate_ability->get_error_message() ) . '</p></div>';
	} elseif ( $active_snapshot ) {
		$percent          = $active_snapshot->get_percent_complete();
		$percent_complete = esc_html__( 'Initializing the snapshot.', 'sensei-enrollment-comparison-tool' );
		if ( false !== $percent ) {
			$percent_complete = sprintf( esc_html__( '%s%% complete.', 'sensei-enrollment-comparison-tool' ), $percent );
		}
		echo '<div class="notice inline notice-info"><p>' . esc_html__( 'Snapshot generation is currently being processed.' . ' ' . $percent_complete, 'sensei-enrollment-comparison-tool' ) . '</p></div>';
		echo '<script type="text/javascript">setTimeout( function() { window.location.reload(); }, 5000 );</script>';
	} else {
		?>
		<form id="generate-snapshot-form" method="post" class="generate-form" name="generate-snapshot">
			<input type="hidden" name="sensei-enrollment-comp-action" value="generate">
			<?php
				\wp_nonce_field( 'sensei-generate-snapshot' );
			?>
			<fieldset>
				<p>
					<label for="friendly_name"><?php esc_html_e( 'Friendly Snapshot Name', 'sensei-enrollment-comparison-tool' ); ?></label>
					<input type="text" id="friendly_name" size="100" name="friendly_name" placeholder="<?php echo \Sensei\EnrollmentComparisonTool\Snapshot::default_friendly_name(); ?>"/>
				</p>
			</fieldset>
			<p class="submit"><input type="submit" name="generate" id="generate" class="button button-primary" value="<?php esc_attr_e( 'Generate Snapshot', 'sensei-enrollment-comparison-tool' ); ?>"></p>
		</form>
		<?php
	}

	$snapshots = \Sensei\EnrollmentComparisonTool\Snapshots::get_snapshot_descriptors( true );
	if ( ! empty( $snapshots ) ) {
		?>
		<h2><?php esc_html_e( 'Compare Snapshots', 'sensei-enrollment-comparison-tool' ); ?></h2>
		<form id="compare-snapshots-form" method="post" name="compare-snapshots">
			<input type="hidden" name="sensei-enrollment-comp-action" value="compare">
			<?php
			\wp_nonce_field( 'sensei-compare-snapshots' );
			?>
			<fieldset>
				<p>
					<label for="snapshot_a"><?php esc_html_e( 'Snapshot A', 'sensei-enrollment-comparison-tool' ); ?></label>
					<select id="snapshot_a" name="snapshot_a" style="max-width: 40rem;">
						<?php
						foreach ( $snapshots as $value => $snapshot ) {
							$selected = '';
							if ( $value === array_keys( $snapshots )[0] ) {
								$selected = ' selected="selected"';
							}
							echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $snapshot ) . '</option>';
						}
						?>
					</select>
				</p>
			</fieldset>
			<fieldset>
				<p>
					<label for="snapshot_b"><?php esc_html_e( 'Snapshot B', 'sensei-enrollment-comparison-tool' ); ?></label>
					<select id="snapshot_b" name="snapshot_b" style="max-width: 40rem;">
						<?php
						$snapshot_ids     = array_keys( $snapshots );
						$last_snapshot_id = array_pop( $snapshot_ids );
						foreach ( $snapshots as $value => $snapshot ) {
							$selected = '';
							if ( $value === $last_snapshot_id ) {
								$selected = ' selected="selected"';
							}
							echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $snapshot ) . '</option>';
						}
						?>
					</select>
				</p>
			</fieldset>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
									 value="<?php esc_attr_e( 'Compare Enrollment', 'sensei-enrollment-comparison-tool' ); ?>">
			</p>
		</form>
		<h2><?php esc_html_e( 'Manage Snapshots', 'sensei-enrollment-comparison-tool' ); ?></h2>
		<?php
		echo '<table class="wp-list-table widefat fixed striped" >';
		echo '<thead>';
		echo '<th>' . esc_html__( 'Date', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '<th>' . esc_html__( 'Snapshot', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '<th>' . esc_html__( 'Sensei Version', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '<th>' . esc_html__( 'WCPC Version', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'sensei-enrollment-comparison-tool' ) . '</th>';
		echo '</thead>';
		echo '<tbody>';
		foreach ( $snapshots as $id => $snapshot_data ) {
			$snapshot = \Sensei\EnrollmentComparisonTool\Snapshots::get_snapshot( $id );
			if ( ! $snapshot ) {
				continue;
			}

			/**
			 * @var \Sensei\EnrollmentComparisonTool\Snapshot $snapshot
			 */
			echo '<tr>';
			echo '<td>' . $snapshot->get_friendly_date() . '</td>';
			echo '<td>' . $snapshot->get_friendly_name() . '</td>';
			echo '<td>' . $snapshot->get_sensei_version() . '</td>';
			echo '<td>' . $snapshot->get_wcpc_version() . '</td>';
			echo '<td>';
			if ( $snapshot->is_valid() ) {
				echo esc_html__( 'Success', 'sensei-enrollment-comparison-tool' );
			} else {
				$error = $snapshot->get_error();
				if ( empty( $error ) ) {
					$error = __( 'Unexpected state', 'sensei-enrollment-comparison-tool' );
				}
				echo '<span style="color: red">';
				echo sprintf( esc_html__( 'Error: %s', 'sensei-enrollment-comparison-tool' ), esc_html( $error ) );
				echo '</span>';
			}
			echo '</td>';

			echo '<td>';
			$delete_url = \wp_nonce_url( \admin_url( sprintf( 'admin.php?page=enrollment-comparison&sensei-enrollment-comp-action=delete&snapshot=%s', $snapshot->get_id() ) ), 'sensei-delete-snapshot' );
			echo sprintf( '<a href="%1$s">%2$s</a>', $delete_url, esc_html__( 'Delete', 'sensei-enrollment-comparison-tool' ) );
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		?>
	<?php
	}
	?>
</div>
