<?php
/**
 * File containing the class \Sensei\EnrollmentComparisonTool\Snapshots.
 *
 * @package sensei-enrollment-comparison-tool
 */

namespace Sensei\EnrollmentComparisonTool;

/**
 * Manages the snapshots.
 */
class Snapshots {
	const SNAPSHOTS_INDEX  = 'sensei-enrollment-snapshots';
	const SNAPSHOTS_PREFIX = 'sensei-enrollment-snapshot-';

	/**
	 * Store a snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public static function store( Snapshot $snapshot ) {
		$snapshot_ids                        = self::get_index();
		$snapshot_ids[ $snapshot->get_id() ] = [
			'descriptor' => $snapshot->get_descriptor(),
			'is_valid'   => $snapshot->is_valid(),
			'time'       => $snapshot->get_start_time(),
		];

		\update_option( self::SNAPSHOTS_INDEX, wp_json_encode( $snapshot_ids ), false );
		\update_option( self::SNAPSHOTS_PREFIX . $snapshot->get_id(), wp_json_encode( $snapshot ), false );
	}

	/**
	 * Delete a snapshot.
	 *
	 * @param Snapshot $snapshot
	 */
	public static function delete( Snapshot $snapshot ) {
		$snapshot_ids = self::get_index();
		unset( $snapshot_ids[ $snapshot->get_id() ] );

		\update_option( self::SNAPSHOTS_INDEX, wp_json_encode( $snapshot_ids ), false );
		\delete_option( self::SNAPSHOTS_PREFIX . $snapshot->get_id() );
	}

	/**
	 * Get the snapshot descriptors.
	 *
	 * @param bool $only_valid
	 *
	 * @return array
	 */
	public static function get_snapshot_descriptors( $only_valid = false ) {
		$descriptors = [];

		foreach ( self::get_index() as $id => $data ) {
			if ( $only_valid && empty( $data['is_valid'] ) ) {
				continue;
			}
			if ( ! get_option( self::SNAPSHOTS_PREFIX . $id ) ) {
				continue;
			}

			$descriptors[ $id ] = $data['descriptor'];
		}

		return $descriptors;
	}

	/**
	 * Get a particular snapshot.
	 *
	 * @param string $id
	 *
	 * @return bool|Snapshot
	 */
	public static function get_snapshot( $id ) {
		$snapshots = self::get_index();
		if ( ! isset( $snapshots[ $id ] ) ) {
			return false;
		}

		$snapshot_raw = get_option( self::SNAPSHOTS_PREFIX . $id );
		if ( empty( $snapshot_raw ) ) {
			return false;
		}

		$snapshot = Snapshot::from_json( $snapshot_raw );

		return $snapshot;
	}

	/**
	 * Get the snapshot catalog.
	 *
	 * @return array
	 */
	public static function get_index() {
		$snapshot_ids = json_decode( \get_option( self::SNAPSHOTS_INDEX ), true );
		if ( empty( $snapshot_ids ) ) {
			$snapshot_ids = [];
		}

		uasort(
			$snapshot_ids,
			function( $a, $b ) {
				if ( $a['time'] < $b['time'] ) {
					return -1;
				}

				return 1;
			}
		);

		return $snapshot_ids;
	}

}
