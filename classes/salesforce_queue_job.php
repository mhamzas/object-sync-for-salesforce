<?php

use WP_Queue\Job;

class Salesforce_Queue_Job extends Job {

	/**
	 * @var array
	 */
	public $data;

	/**
	 * @var array
	 */
	public $job_processor;

	/**
	 * Salesforce_Queue_Job constructor.
	 */
	public function __construct( $data, $job_processor ) {
		$this->data          = $data;
		$this->job_processor = $job_processor;
	}

	/**
	 * Maybe process queue
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	/*public function maybe_handle( $already_checked = false, $ajax = false ) {

		// if we need to check for data first, run that method
		// it should call its corresponding class method that saves data to the queue
		// it should then run maybe_handle() again

		$check_for_data_first = isset( $job_processor->classes[ $job_processor->schedule_name ]['initializer'] ) ? true : false;

		if ( false === $already_checked && true === $check_for_data_first ) {
			$this->check_for_data();
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			wp_die();
		}

		if ( true === $ajax ) {
			check_ajax_referer( $this->id, 'nonce' );
		}

		$this->handle();
	}*/

	/**
	 * Check for data
	 *
	 * This method is new to the extension. It allows a scheduled method to do nothing but call the
	 * callback parameter of its calling class.
	 * This is useful for running the salesforce_pull event to check for updates in Salesforce
	 *
	 * @return $data
	 */
	protected function check_for_data() {
		if ( is_array( $job_processor->classes[ $job_processor->schedule_name ] ) ) {
			$schedule = $job_processor->classes[ $job_processor->schedule_name ];
			if ( isset( $schedule['class'] ) ) {
				$class  = new $schedule['class']( $job_processor->version, $job_processor->login_credentials, $job_processor->slug, $job_processor->wordpress, $job_processor->salesforce, $job_processor->mappings, $job_processor->logging, $job_processor->classes );
				$method = $schedule['initializer'];
				$task   = $class->$method();
			}
		}
		// we have checked for data and it's in the queue if it exists
		// now run maybe_handle again to see if it nees to be processed
		$this->maybe_handle( true );
	}

	/**
	 * Handle job logic.
	 */
	public function handle() {

		$job_processor = $this->job_processor;
		$data          = $this->data;

		if ( is_array( $job_processor['classes'][ $job_processor['schedule_name'] ] ) ) {
			$schedule = $job_processor['classes'][ $job_processor['schedule_name'] ];
			if ( isset( $schedule['class'] ) ) {
				$class  = new $schedule['class']( $job_processor['version'], $job_processor['login_credentials'], $job_processor['slug'], $job_processor['wordpress'], $job_processor['salesforce'], $job_processor['mappings'], $job_processor['logging'], $job_processor['classes'], $job_processor['queue'] );
				$method = $schedule['callback'];
				$task   = $class->$method( $data['object_type'], $data['object'], $data['mapping'], $data['sf_sync_trigger'] );

			}
		}
	}

}
