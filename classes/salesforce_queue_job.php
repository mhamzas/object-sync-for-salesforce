<?php
/**
 * Class file for the Object_Sync_Sf_Queue_Job class. Extend the WP_Queue\Job class for the purposes of Object Sync for Salesforce.
 *
 * @file
 */

if ( ! class_exists( 'Object_Sync_Salesforce' ) ) {
	die();
}

use WP_Queue\Job;

/**
 * Schedule events
 */
class Object_Sync_Sf_Queue_Job extends Job {

	/**
	 * @var string
	 */
	public $schedule_name;

	/**
	 * @var array
	 */
	public $schedulable_classes;

	/**
	 * Object_Sync_Sf_Queue_Job constructor.
	 *
	 * @param string $schedule_name
	 * @param array $schedulable_classes
	 */
	public function __construct( $schedule_name, $schedulable_classes ) {
		$this->schedule_name       = $schedule_name;
		$this->schedulable_classes = $schedulable_classes;
	}

	/**
	 * Handle job logic.
	 */
	public function handle() {
		if ( is_array( $this->schedulable_classes[ $this->schedule_name ] ) ) {
			$schedule = $this->schedulable_classes[ $this->schedule_name ];
			if ( isset( $schedule['class'] ) ) {
				$class  = new $schedule['class']( $this->wpdb, $this->version, $this->login_credentials, $this->slug, $this->wordpress, $this->salesforce, $this->mappings, $this->logging, $this->schedulable_classes );
				$method = $schedule['callback'];
				$task   = $class->$method( $data['object_type'], $data['object'], $data['mapping'], $data['sf_sync_trigger'] );
			}
		}
		return false;
	}

}
