<?php
namespace Object_Sync_Salesforce;
use prospress\ActionScheduler;
/**
 * Main plugin's class.
 */
class Plugin {

	/**
	* @var object
	* Global object of `$wpdb`, the WordPress database
	*/
	private $wpdb;

	/**
	* @var string
	* The plugin's slug so we can include it when necessary
	*/
	private $slug;

	/**
	* @var array
	* Login credentials for the Salesforce API; comes from wp-config or from the plugin settings
	*/
	private $login_credentials;

	/**
	* @var array
	* Array of what classes in the plugin can be scheduled to occur with `wp_cron` events
	*/
	public $schedulable_classes;

	/**
	* @var string
	* Current version of the plugin
	*/
	private $version;

	/**
	* @var object
	*/
	private $activated;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_Logging class
	*/
	private $logging;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_Mapping class
	*/
	private $mappings;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_WordPress class
	*/
	private $wordpress;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_Salesforce class.
	* This contains the Salesforce API methods
	*/
	public $salesforce;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_Salesforce_Push class
	*/
	private $push;

	/**
	* @var object
	* Load and initialize the Object_Sync_Sf_Salesforce_Pull class
	*/
	private $pull;

	/**
	* @var object
	* Load and initialize the admin class
	*/
	private $admin;

	/**
	 * @param array $values Configuration values to apply.
	 */
	public function __construct() {

		global $wpdb;

		$this->wpdb    = $wpdb;
		$this->version = '1.3.9';
		$this->slug    = 'object-sync-for-salesforce';

		$this->login_credentials   = $this->get_login_credentials();
		$this->schedulable_classes = $this->get_schedulable_classes();

		$this->add_actions();
	}

	private function add_actions() {
		add_action( 'admin_init', array( $this, 'admin' ) );
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
	}

	/**
	* Admin interface
	*
	* @return object $admin
	*
	*/
	public function admin() {
		$this->admin = new admin( $this->wpdb, $this->slug, $this->version, $this->login_credentials );
		return $this->admin;
	}

	/**
	* Create WordPress admin options page
	*
	*/
	public function create_admin_menu() {
		$title = __( 'Salesforce', 'object-sync-for-salesforce' );
		add_options_page( $title, $title, 'configure_salesforce', 'object-sync-salesforce-admin', array( $this, 'show_admin_page' ) );
	}

	public function show_admin_page() {
		echo $this->admin->show_admin_page();
	}

	/**
	* Get the pre-login Salesforce credentials.
	* These depend on the plugin's settings or constants defined in wp-config.php.
	*
	* @return array $login_credentials
	*   Includes all settings necessary to log into the Salesforce API.
	*   Replaces settings options with wp-config.php values if they exist.
	*/
	private function get_login_credentials() {

		$consumer_key       = defined( 'OBJECT_SYNC_SF_SALESFORCE_CONSUMER_KEY' ) ? OBJECT_SYNC_SF_SALESFORCE_CONSUMER_KEY : get_option( 'object_sync_for_salesforce_consumer_key', '' );
		$consumer_secret    = defined( 'OBJECT_SYNC_SF_SALESFORCE_CONSUMER_SECRET' ) ? OBJECT_SYNC_SF_SALESFORCE_CONSUMER_SECRET : get_option( 'object_sync_for_salesforce_consumer_secret', '' );
		$callback_url       = defined( 'OBJECT_SYNC_SF_SALESFORCE_CALLBACK_URL' ) ? OBJECT_SYNC_SF_SALESFORCE_CALLBACK_URL : get_option( 'object_sync_for_salesforce_callback_url', '' );
		$login_base_url     = defined( 'OBJECT_SYNC_SF_SALESFORCE_LOGIN_BASE_URL' ) ? OBJECT_SYNC_SF_SALESFORCE_LOGIN_BASE_URL : get_option( 'object_sync_for_salesforce_login_base_url', '' );
		$authorize_url_path = defined( 'OBJECT_SYNC_SF_SALESFORCE_AUTHORIZE_URL_PATH' ) ? OBJECT_SYNC_SF_SALESFORCE_AUTHORIZE_URL_PATH : get_option( 'object_sync_for_salesforce_authorize_url_path', '' );
		$token_url_path     = defined( 'OBJECT_SYNC_SF_SALESFORCE_TOKEN_URL_PATH' ) ? OBJECT_SYNC_SF_SALESFORCE_TOKEN_URL_PATH : get_option( 'object_sync_for_salesforce_token_url_path', '' );
		$api_version        = defined( 'OBJECT_SYNC_SF_SALESFORCE_API_VERSION' ) ? OBJECT_SYNC_SF_SALESFORCE_API_VERSION : get_option( 'object_sync_for_salesforce_api_version', '' );

		$login_credentials = array(
			'consumer_key'     => $consumer_key,
			'consumer_secret'  => $consumer_secret,
			'callback_url'     => $callback_url,
			'login_url'        => $login_base_url,
			'authorize_path'   => $authorize_url_path,
			'token_path'       => $token_url_path,
			'rest_api_version' => $api_version,
		);

		return $login_credentials;

	}

	/**
	* Array of what classes in the plugin can be scheduled to occur with `wp_cron` events
	* @return array $schedulable_classes
	*/
	public function get_schedulable_classes() {
		$schedulable_classes = array(
			'salesforce_push' => array(
				'label'    => 'Push to Salesforce',
				'class'    => 'Object_Sync_Sf_Salesforce_Push',
				'callback' => 'object_sync_for_salesforce_push_record',
			),
			'salesforce_pull' => array(
				'label'       => 'Pull from Salesforce',
				'class'       => 'Object_Sync_Sf_Salesforce_Pull',
				'initializer' => 'salesforce_pull',
				'callback'    => 'salesforce_pull_process_records',
			),
			'salesforce'      => array(
				'label' => 'Salesforce Authorization',
				'class' => 'Object_Sync_Sf_Salesforce',
			),
		);

		// users can modify the list of schedulable classes
		$schedulable_classes = apply_filters( 'object_sync_for_salesforce_modify_schedulable_classes', $schedulable_classes );

		/*
		 * example to modify the array of classes by adding one and removing one
		 * add_filter( 'object_sync_for_salesforce_modify_schedulable_classes', 'modify_schedulable_classes', 10, 1 );
		 * function modify_schedulable_classes( $schedulable_classes ) {
		 * 	$schedulable_classes = array(
		 * 		'salesforce_push' => array(
		 * 		    'label' => 'Push to Salesforce',
		 * 		    'class' => 'Object_Sync_Sf_Salesforce_Push',
		 * 		    'callback' => 'salesforce_push_sync_rest',
		 * 		),
		 * 		'wordpress' => array( // WPCS: spelling ok.
		 * 		    'label' => 'WordPress',
		 * 		    'class' => 'Object_Sync_Sf_WordPress',
		 * 		),
		 * 		'salesforce' => array(
		 * 		    'label' => 'Salesforce Authorization',
		 * 		    'class' => 'Object_Sync_Sf_Salesforce',
		 * 		),
		 * 	);
		 * 	return $schedulable_classes;
		 * }
		*/
		return $schedulable_classes;
	}
}
