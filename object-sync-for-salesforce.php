<?php
/*
Plugin Name: Object Sync for Salesforce
Description: Object Sync for Salesforce maps and syncs data between Salesforce objects and WordPress objects.
Version: 1.3.9
Author: MinnPost
Author URI: https://code.minnpost.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: object-sync-for-salesforce
*/

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}
$object_sync_salesforce = new \Object_Sync_Salesforce\Plugin();
