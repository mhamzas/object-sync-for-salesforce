<?php
namespace Object_Sync_Salesforce;

/**
 * Main admin class.
 */
class Admin {

	protected $wpdb;
	protected $version;
	protected $slug;
	protected $login_credentials;
	protected $wordpress;
	protected $salesforce;
	protected $mappings;
	protected $push;
	protected $pull;
	protected $schedulable_classes;
	protected $queue;

	/**
	* @var string
	* Default path for the Salesforce authorize URL
	*/
	public $default_authorize_url_path;

	/**
	* @var string
	* Default path for the Salesforce token URL
	*/
	public $default_token_url_path;

	/**
	* @var string
	* What version of the Salesforce API should be the default on the settings screen.
	* Users can edit this, but they won't see a correct list of all their available versions until WordPress has
	* been authenticated with Salesforce.
	*/
	public $default_api_version;

	/**
	* @var bool
	* Default for whether to limit to triggerable items
	* Users can edit this
	*/
	public $default_triggerable;

	/**
	* @var bool
	* Default for whether to limit to updateable items
	* Users can edit this
	*/
	public $default_updateable;

	/**
	* @var int
	* Default pull throttle for how often Salesforce can pull
	* Users can edit this
	*/
	public $default_pull_throttle;

	/**
	 * @param array $values Configuration values to apply.
	 */
	public function __construct( $wpdb, $slug, $version ) {

		// default authorize url path
		$this->default_authorize_url_path = '/services/oauth2/authorize';
		// default token url path
		$this->default_token_url_path = '/services/oauth2/token';
		// what Salesforce API version to start the settings with. This is only used in the settings form
		$this->default_api_version = '42.0';
		// default pull throttle for avoiding going over api limits
		$this->default_pull_throttle = 5;
		// default setting for triggerable items
		$this->default_triggerable = true;
		// default setting for updateable items
		$this->default_updateable = true;
		// default option prefix
		$this->option_prefix = 'object_sync_for_salesforce_';
	}

	/**
	* Render full admin pages in WordPress
	* This allows other plugins to add tabs to the Salesforce settings screen
	*
	* todo: better front end: html, organization of html into templates, css, js
	*
	*/
	public function show_admin_page() {
		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		$allowed = $this->check_wordpress_admin_permissions();
		if ( false === $allowed ) {
			return;
		}
		$tabs = array(
			'settings'      => __( 'Settings', 'object-sync-for-salesforce' ),
			'authorize'     => __( 'Authorize', 'object-sync-for-salesforce' ),
			'fieldmaps'     => __( 'Fieldmaps', 'object-sync-for-salesforce' ),
			'schedule'      => __( 'Scheduling', 'object-sync-for-salesforce' ),
			'import-export' => __( 'Import &amp; Export', 'object-sync-for-salesforce' ),
		); // this creates the tabs for the admin

		// optionally make tab(s) for logging and log settings
		$logging_enabled      = get_option( $this->option_prefix . 'enable_logging', false );
		$tabs['log_settings'] = __( 'Log Settings', 'object-sync-for-salesforce' );

		$mapping_errors = $this->mappings->get_failed_object_maps();
		if ( ! empty( $mapping_errors ) ) {
			$tabs['mapping_errors'] = __( 'Mapping Errors', 'object-sync-for-salesforce' );
		}

		// filter for extending the tabs available on the page
		// currently it will go into the default switch case for $tab
		$tabs = apply_filters( 'object_sync_for_salesforce_settings_tabs', $tabs );

		$tab = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'settings';
		$this->tabs( $tabs, $tab );

		$consumer_key    = $this->login_credentials['consumer_key'];
		$consumer_secret = $this->login_credentials['consumer_secret'];
		$callback_url    = $this->login_credentials['callback_url'];

		if ( true !== $this->salesforce['is_authorized'] ) {
			$url     = esc_url( $callback_url );
			$anchor  = esc_html__( 'Authorize tab', 'object-sync-for-salesforce' );
			$message = sprintf( 'Salesforce needs to be authorized to connect to this website. Use the <a href="%s">%s</a> to connect.', $url, $anchor );
			require( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
		}

		if ( 0 === count( $this->mappings->get_fieldmaps() ) ) {
			$url     = esc_url( get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=fieldmaps' ) );
			$anchor  = esc_html__( 'Fieldmaps tab', 'object-sync-for-salesforce' );
			$message = sprintf( 'No fieldmaps exist yet. Use the <a href="%s">%s</a> to map WordPress and Salesforce objects to each other.', $url, $anchor );
			require( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
		}

		$push_schedule_number = get_option( $this->option_prefix . 'salesforce_push_schedule_number', '' );
		$push_schedule_unit   = get_option( $this->option_prefix . 'salesforce_push_schedule_unit', '' );
		$pull_schedule_number = get_option( $this->option_prefix . 'salesforce_pull_schedule_number', '' );
		$pull_schedule_unit   = get_option( $this->option_prefix . 'salesforce_pull_schedule_unit', '' );

		if ( '' === $push_schedule_number && '' === $push_schedule_unit && '' === $pull_schedule_number && '' === $pull_schedule_unit ) {
			$url     = esc_url( get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=schedule' ) );
			$anchor  = esc_html__( 'Scheduling tab', 'object-sync-for-salesforce' );
			$message = sprintf( 'Because the plugin schedule has not been saved, the plugin cannot run automatic operations. Use the <a href="%s">%s</a> to create schedules to run.', $url, $anchor );
			require( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
		}

		try {
			switch ( $tab ) {
				case 'authorize':
					if ( isset( $get_data['code'] ) ) {
						// this string is an oauth token
						$data          = esc_html( wp_unslash( $get_data['code'] ) );
						$is_authorized = $this->salesforce['sfapi']->request_token( $data );
						?>
						<script>window.location = '<?php echo esc_url_raw( $callback_url ); ?>'</script>
						<?php
					} elseif ( true === $this->salesforce['is_authorized'] ) {
							require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/authorized.php' );
							$this->status( $this->salesforce['sfapi'] );
					} elseif ( true === is_object( $this->salesforce['sfapi'] ) && isset( $consumer_key ) && isset( $consumer_secret ) ) {
						?>
						<p><a class="button button-primary" href="<?php echo esc_url( $this->salesforce['sfapi']->get_authorization_code() ); ?>"><?php echo esc_html__( 'Connect to Salesforce', 'object-sync-for-salesforce' ); ?></a></p>
						<?php
					} else {
						$url    = esc_url( get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=settings' ) );
						$anchor = esc_html__( 'Settings', 'object-sync-for-salesforce' );
						// translators: placeholders are for the settings tab link: 1) the url, and 2) the anchor text
						$message = sprintf( esc_html__( 'Salesforce needs to be authorized to connect to this website but the credentials are missing. Use the <a href="%1$s">%2$s</a> tab to add them.', 'object-sync-salesforce' ), $url, $anchor );
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/error.php' );
					}
					break;
				case 'fieldmaps':
					if ( isset( $get_data['method'] ) ) {

						$method      = sanitize_key( $get_data['method'] );
						$error_url   = get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=fieldmaps&method=' . $method );
						$success_url = get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=fieldmaps' );

						if ( isset( $get_data['transient'] ) ) {
							$transient = sanitize_key( $get_data['transient'] );
							$posted    = $this->sfwp_transients->get( $transient );
						}

						if ( isset( $posted ) && is_array( $posted ) ) {
							$map = $posted;
						} elseif ( 'edit' === $method || 'clone' === $method || 'delete' === $method ) {
							$map = $this->mappings->get_fieldmaps( isset( $get_data['id'] ) ? sanitize_key( $get_data['id'] ) : '' );
						}

						if ( isset( $map ) && is_array( $map ) ) {
							$label                           = $map['label'];
							$salesforce_object               = $map['salesforce_object'];
							$salesforce_record_types_allowed = maybe_unserialize( $map['salesforce_record_types_allowed'] );
							$salesforce_record_type_default  = $map['salesforce_record_type_default'];
							$wordpress_object                = $map['wordpress_object'];
							$pull_trigger_field              = $map['pull_trigger_field'];
							$fieldmap_fields                 = $map['fields'];
							$sync_triggers                   = $map['sync_triggers'];
							$push_async                      = $map['push_async'];
							$push_drafts                     = $map['push_drafts'];
							$weight                          = $map['weight'];
						}

						if ( 'add' === $method || 'edit' === $method || 'clone' === $method ) {
							require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/fieldmaps-add-edit-clone.php' );
						} elseif ( 'delete' === $method ) {
							require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/fieldmaps-delete.php' );
						}
					} else {
						$fieldmaps = $this->mappings->get_fieldmaps();
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/fieldmaps-list.php' );
					} // End if().
					break;
				case 'logout':
					$this->logout();
					break;
				case 'clear_cache':
					$this->clear_cache();
					break;
				case 'clear_schedule':
					if ( isset( $get_data['schedule_name'] ) ) {
						$schedule_name = sanitize_key( $get_data['schedule_name'] );
					}
					$this->clear_schedule( $schedule_name );
					break;
				case 'settings':
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
					break;
				case 'mapping_errors':
					if ( isset( $get_data['method'] ) ) {

						$method      = sanitize_key( $get_data['method'] );
						$error_url   = get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=mapping_errors&method=' . $method );
						$success_url = get_admin_url( null, 'options-general.php?page=object-sync-salesforce-admin&tab=mapping_errors' );

						if ( isset( $get_data['map_transient'] ) ) {
							$transient = sanitize_key( $get_data['map_transient'] );
							$posted    = $this->sfwp_transients->get( $transient );
						}

						if ( isset( $posted ) && is_array( $posted ) ) {
							$map_row = $posted;
						} elseif ( 'edit' === $method || 'delete' === $method ) {
							$map_row = $this->mappings->get_failed_object_map( isset( $get_data['id'] ) ? sanitize_key( $get_data['id'] ) : '' );
						}

						if ( isset( $map_row ) && is_array( $map_row ) ) {
							$salesforce_id = $map_row['salesforce_id'];
							$wordpress_id  = $map_row['wordpress_id'];
						}

						if ( 'edit' === $method ) {
							require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/mapping-errors-edit.php' );
						} elseif ( 'delete' === $method ) {
							require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/mapping-errors-delete.php' );
						}
					} else {
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/mapping-errors.php' );
					}
					break;
				case 'import-export':
					require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/import-export.php' );
					break;
				default:
					$include_settings = apply_filters( 'object_sync_for_salesforce_settings_tab_include_settings', true, $tab );
					$content_before   = apply_filters( 'object_sync_for_salesforce_settings_tab_content_before', null, $tab );
					$content_after    = apply_filters( 'object_sync_for_salesforce_settings_tab_content_after', null, $tab );
					if ( null !== $content_before ) {
						echo esc_html( $content_before );
					}
					if ( true === $include_settings ) {
						require_once( plugin_dir_path( __FILE__ ) . '/../templates/admin/settings.php' );
					}
					if ( null !== $content_after ) {
						echo esc_html( $content_after );
					}
					break;
			} // End switch().
		} catch ( SalesforceApiException $ex ) {
			echo sprintf( '<p>Error <strong>%1$s</strong>: %2$s</p>',
				absint( $ex->getCode() ),
				esc_html( $ex->getMessage() )
			);
		} catch ( Exception $ex ) {
			echo sprintf( '<p>Error <strong>%1$s</strong>: %2$s</p>',
				absint( $ex->getCode() ),
				esc_html( $ex->getMessage() )
			);
		} // End try().
		echo '</div>';
	}

}
