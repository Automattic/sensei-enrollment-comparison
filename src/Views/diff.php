<?php
/**
 * Admin View: Page - Sensei Enrollment View Diff
 *
 * @package sensei-enrollment-comparison-tool
 *
 * @var \Sensei\EnrollmentComparisonTool\Diff $diff
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

foreach ( $diff->get_notices() as $notice ) {
	echo '<div class="notice inline notice-warning"><p>' . esc_html( $notice ) . '</p></div>';
}
?>
<div class="wrap sensei">
<h1><?php esc_html_e( 'Sensei LMS - Compare Enrollment', 'sensei-enrollment-comparison-tool' ); ?></h1>
	<?php
	foreach ( $diff->get_courses() as $course ) {
		$course_diff = $diff->get_enrollment_diff( $course->ID );
		echo '<h2>' . $course->post_title . '</h2>';
		if ( empty( $course_diff['enrollment'] ) ) {
			echo '<div class="notice inline notice-info"><p>' . esc_html__( 'No students enrolled in either snapshot.', 'sensei-enrollment-comparison-tool' ) . '</p></div>';
		} else {
			if ( ! $course_diff['same'] ) {
				echo '<div class="notice inline error"><p><strong>' . esc_html__( 'Course enrollment differs between the two snapshots.', 'sensei-enrollment-comparison-tool' ) . '</strong></p></div>';
			}
			echo '<table class="wp-list-table widefat fixed striped" style="max-width: 40rem;">';
			echo '<thead>';
			echo '<th>' . esc_html__( 'Student', 'sensei-enrollment-comparison-tool' ) . '</th>';
			echo '<th title="' . esc_attr( $diff->get_a()->get_descriptor() ) . '">' . esc_html__( 'Snapshot A', 'sensei-enrollment-comparison-tool' ) . '</th>';
			echo '<th title="' . esc_attr( $diff->get_b()->get_descriptor() ) . '">' . esc_html__( 'Snapshot B', 'sensei-enrollment-comparison-tool' ) . '</th>';
			echo '</thead>';

			echo '<tbody>';
			foreach ( $course_diff['enrollment'] as $user_id => $data ) {
				$row_style = '';
				if ( ! $data['same'] ) {
					$row_style = 'color: red; font-weight: bold;';
				}
				echo '<tr>';
				echo '<th>';
				echo '<strong>' . esc_html( $data['label'] ) . '</strong>';
				echo '</th>';
				echo '<td>';
				if ( $data['a'] ) {
					echo esc_html__( 'Enrolled', 'sensei-enrollment-comparison-tool' );
				} else {
					echo esc_html__( 'Not Enrolled', 'sensei-enrollment-comparison-tool' );
				}
				echo '</td>';

				echo '<td style="' . esc_attr( $row_style ) . '">';
				if ( $data['b'] ) {
					echo esc_html__( 'Enrolled', 'sensei-enrollment-comparison-tool' );
				} else {
					echo esc_html__( 'Not Enrolled', 'sensei-enrollment-comparison-tool' );
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}
	}
	?>
</div>
