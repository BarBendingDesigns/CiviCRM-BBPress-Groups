<?php

/**
 * Deactivate and uninstall functions
 *
 */

class Civi_Bb_Groups_Deactivator {

    // Deactivate: Clear any scheduled groups syncing
	public static function deactivate() {
        wp_clear_scheduled_hook( 'civi_bbg_sync_groups' );
	}
	
	// Uninstall: Remove all plugin options from the database
	public static function uninstall(){
	    $plugin_options = array(
    	    'civi-bb-groups-login-redirect',
            'civi-bb-groups-logout-redirect', 
            'civi-bb-groups-auto-sync',
            'civi-bb-groups-sync-notify', 
            'civi-bb-groups-user-notify',
            'civi-bb-groups-syncing-map',
            'civi-bb-groups-bb-hierarchy', 
            'civi-bb-groups-account-page',
            'civi-bb-groups-profile-page',
            'civi-bb-groups-individual-profile', 
            'civi-bb-groups-organization-profile', 
            'civi-bb-groups-household-profile',
            'civi-bb-groups-restrict-tags',
	    );
	    
	    foreach($plugin_options as $o){
	        delete_option($o);
	    }
	}

}
