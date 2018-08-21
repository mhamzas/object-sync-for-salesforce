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
