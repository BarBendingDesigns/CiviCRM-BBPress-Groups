<?php

/**
 * Fired during plugin activation
 *
 */
class Civi_Bb_Groups_Activator {

    // If the civi-bb-groups-auto-sync option is already present and set to true,
    // setup daily syncing of CiviCRM groups to WP roles
	public static function activate() {
        $auto_on = get_option('civi-bb-groups-auto-sync', false);
        if($auto_on && !wp_next_scheduled ( 'civi_bbg_sync_groups' )) {
                
            $date = new DateTime( 'tomorrow', new DateTimeZone( get_option( 'timezone_string' ) ) );
            $midnight = $date->getTimestamp();
    
            wp_schedule_event( $midnight, 'daily', 'civi_bbg_sync_groups' );
            
        }
	}

}
