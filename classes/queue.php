<?php
/**
 * Class file for the Object_Sync_Sf_Queue class. Extend the WP_Queue\Job class for the purposes of Object Sync for Salesforce.
 *
 * @file
 */

if ( ! class_exists( 'Object_Sync_Salesforce' ) ) {
	die();
}

class Object_Sync_Sf_Queue {

	/**
	 * Singleton
	 *
	 * @var Queue|null
	 */
	protected static $instance = null;

	protected $version;
	protected $slug;
	protected $schedulable_classes;

	public $attempts;
	public $interval;

	/**
	 * Singleton
	 *
	 * @return Queue|null
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Queue constructor.
	 */
	public function __construct( $version, $slug, $schedulable_classes ) {
		//add_filter( 'update_post_metadata', array( $this, 'filter_update_post_metadata' ), 10, 5 );
		$this->version             = $version;
		$this->slug                = $slug;
		$this->schedulable_classes = $schedulable_classes;

		$this->attempts = apply_filters( 'object_sync_for_salesforce_job_attempts', 3 );
		$this->interval = apply_filters( 'object_sync_for_salesforce_cron_interval', 1 );

		add_action( 'plugins_loaded', array( $this, 'add_actions' ) );

	}

	/**
	 * Add actions
	 */
	public function add_actions() {
		add_filter( 'wp_queue_default_connection', array( $this, 'default_connection' ) );
		if ( ! class_exists( 'Salesforce_Queue_Job' ) && file_exists( plugin_dir_path( __FILE__ ) . '../vendor/autoload.php' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../vendor/autoload.php';
			require_once plugin_dir_path( __FILE__ ) . '../classes/salesforce_queue_job.php';
		}
		wp_queue()->cron( $this->attempts, $this->interval );
		$this->set_schedule_frequency();
	}

	/**
	 * Set default connection
	 * @param string $connection
	 * @return string $connection
	 */
	function default_connection( $connection ) {
		$connection = get_option( 'object_sync_for_salesforce_default_connection', 'database' ); // the default for wp-queue is database
		return $connection;
	}

	/**
	 * Set frequency for schedules
	 */
	public function set_schedule_frequency( $schedules = array() ) {
		// create an option in the core schedules array for each one the plugin defines
		foreach ( $this->schedulable_classes as $key => $value ) {
			$schedule_number = absint( get_option( 'object_sync_for_salesforce_' . $key . '_schedule_number', '' ) );
			$schedule_unit   = get_option( 'object_sync_for_salesforce_' . $key . '_schedule_unit', '' );

			switch ( $schedule_unit ) {
				case 'minutes':
					$seconds = 60;
					break;
				case 'hours':
					$seconds = 3600;
					break;
				case 'days':
					$seconds = 86400;
					break;
				default:
					$seconds = 0;
			}

			$key = $schedule_unit . '_' . $schedule_number;

			$schedules[ $key ] = array(
				'interval' => $seconds * $schedule_number,
				'display'  => 'Every ' . $schedule_number . ' ' . $schedule_unit,
			);

			$this->schedule_frequency = $key;

		}

		return $schedules;
	}

	/**
	 * Get frequency for a single schedule
	 */
	public function get_schedule_frequency_key( $name = '' ) {

		$schedule_number = get_option( 'object_sync_for_salesforce_' . $name . '_schedule_number', '' );
		$schedule_unit   = get_option( 'object_sync_for_salesforce_' . $name . '_schedule_unit', '' );

		switch ( $schedule_unit ) {
			case 'minutes':
				$seconds = 60;
				break;
			case 'hours':
				$seconds = 3600;
				break;
			case 'days':
				$seconds = 86400;
				break;
			default:
				$seconds = 0;
		}

		$key = $schedule_unit . '_' . $schedule_number;

		return $key;

	}

	/**
	* Convert the schedule frequency from the admin settings into seconds
	*
	*/
	public function get_schedule_frequency_seconds( $name = '' ) {

		$schedule_number = get_option( 'object_sync_for_salesforce_' . $name . '_schedule_number', '' );
		$schedule_unit   = get_option( 'object_sync_for_salesforce_' . $name . '_schedule_unit', '' );

		switch ( $schedule_unit ) {
			case 'minutes':
				$seconds = 60;
				break;
			case 'hours':
				$seconds = 3600;
				break;
			case 'days':
				$seconds = 86400;
				break;
			default:
				$seconds = 0;
		}

		$total = $seconds * $schedule_number;

		return $total;

	}

	/**
	 * Push data to a queue, and save the parameters needed to process it
	 *
	 * @param array $data
	 * @param array $job_processor
	 *
	 */
	public function save_to_queue( $data, $job_processor ) {
		wp_queue()->push( new Salesforce_Queue_Job( $data, $job_processor ) );
	}

}
