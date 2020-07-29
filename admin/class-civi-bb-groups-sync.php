<?php

/**
 * Handles syncing of CiviCRM groups to WP user roles
 *
 */
class Civi_Bb_Groups_Sync {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}
	
	// Add the "Manual Sync" page to the WP admin menu
	public function setup_sync_admin_page(){
	    add_submenu_page('civicrm-bbpress-groups', 'Manual Sync', 'Manual Sync', 'manage_options', 'civicrm-bbpress-manual_sync', [$this, 'do_manual_sync_page']);
	}
	
	// Output the CiviCRM BBPress Groups Manual Syncing page
	public function do_manual_sync_page(){
	    if ( ! current_user_can( 'manage_options' ) ) { return; }
	    
	    $auto_on = get_option('civi-bb-groups-auto-sync', false);
	    
	    ?>
	    
	    <div class="wrap" id="civicrm-bbpress-groups-page">
	    <h1>CiviCRM BBPress Groups Manual Syncing</h1>
	    
	    <h2>Automatic Syncing</h2>
	    <p><?php if($auto_on){
	        echo 'Automatic syncing is on. The syncing process will run once per day. ';
	        $next = wp_next_scheduled( 'civi_bbg_sync_groups' );
	        if($next){
	            $date = new DateTime('now', new DateTimeZone( get_option( 'timezone_string' ) ));
	            $date->setTimestamp($next);
	            echo "Next scheduled run is: ".$date->format('Y-m-d H:i:s').".";
	        }
	    } else {
	        echo 'Automatic syncing is off.';
	    } ?></p>
	    
	    <h2>Manual Syncing</h2>
	    <p>You can use the button below to manually run the syncing process now.</p>
	    <p>
	    <button type="button" class="button button-primary" id="civibbg-groups-sync-now">Sync Now</button>
	    <?php wp_nonce_field( 'civi_bb_do_sync', 'civi_bb_sync_nonce' ); ?>
	    </p>
	    <div id='civibbg-errors'></div>
	    <div id='civibbg-messages'></div>
	    <div id='civibbg-results'></div>
	    
	    <?php

	}

	// Schedule or unschedule daily groups syncing, when the automated syncing option is turned on or off
	// Hooked into action: update_option_civi-bb-groups-auto-sync
	public function maybe_reschedule_sync($old_value, $new_value, $option_name){
	    // If auto sync has been turned on
	    if($new_value && !wp_next_scheduled( 'civi_bbg_sync_groups' )){
	        $date = new DateTime( 'tomorrow', new DateTimeZone( get_option( 'timezone_string' ) ) );
            $midnight = $date->getTimestamp();
	        
	        wp_schedule_event( $midnight, 'daily', 'civi_bbg_sync_groups' );

	    // If auto sync has been turned off
	    } elseif(!$new_value && wp_next_scheduled( 'civi_bbg_sync_groups' )){
	        wp_clear_scheduled_hook( 'civi_bbg_sync_groups' );
	        
	    }
	    
	}
	
	// Schedule or unschedule daily groups syncing, when the automated syncing option, when automated syncing option is first added
	// Hooked into action: add_option_civi-bb-groups-auto-sync
	public function maybe_schedule_sync($option_name, $value){
	    $this->maybe_reschedule_sync(false, $value, $option_name);
	}
	
	// Handle ajax manual sync request
	public function do_manual_sync(){
	    if(!isset($_POST['civi_bb_sync_nonce']) || !wp_verify_nonce( $_POST['civi_bb_sync_nonce'], 'civi_bb_do_sync' )){
	        wp_send_json(['errors' => ['Security verification failed']], 403);
	    } elseif (!current_user_can('manage_options')){
	        wp_send_json(['errors' => ["Sorry, you're not authorised to sync groups."]], 403);
	    }
	    
	    $results = $this->sync_groups();
	    
	    $this->send_sync_report($results[0]);
	    
	    wp_send_json($results[0], $results[1]);

	}
	
	// Handle the auto sync process
	public function do_auto_sync(){
	    $results = $this->sync_groups();
	    
	    $this->send_sync_report($results[0]);
	    
	}
	
	// Send results of syncing process in an email
	private function send_sync_report($data){
	    $report_recipient = sanitize_email(get_option('civi-bb-groups-sync-notify'));
	    if($report_recipient){
            $subject = 'CiviCRM BBPress Groups syncing report';
            $parts = [
                "DATE: ".current_time('j M Y h:i a'),
                "WEBSITE: ".home_url(),
                "RESULTS: ",
                json_encode($data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_IGNORE)
            ];
            $body = implode("\n\n", $parts);
            
            wp_mail($report_recipient, $subject, $body );
	    }
	}
	

	// Run the syncing process
	public function sync_groups(){

	    $results = array(
	        'errors' => [],
	        'messages' => [],
	        'processed_users' => [],
	    );
	    
	    try{
	        $done = [];
	        
	        // Make sure Civi API3 is available
    	    if(!civibbg_civi_api3_is_on()){
    	        $results['errors'][] = 'Unable to sync CiviCRM groups to WP users: CiviCRM API is not available.';
    	        return [$results, 400];
    	    }
    	    
    	    // Make sure we have some groups to sync
    	    $groups_map = civibbg_sanitize_thing(get_option('civi-bb-groups-syncing-map', []), 'groups-map');
    	    if(empty($groups_map)){
    	        $results['messages'][] = 'No syncing required: No groups are configured to sync.';
    	        return [$results, 200];
    	    }
    	    
    	    // Fetch groups from Civi
    	    $group_ids = array_column($groups_map, 'group');
    	    $current_groups = civicrm_api3('Group', 'get', [
                'return' => ["id", "saved_search_id", "cache_date"], // cache_date may not even be set
                'is_active' => 1,
                'is_hidden' => 0,
                'id' => ['IN' => $group_ids],
            ]);

            // Make sure CiviCRM file is included.
		    require_once 'CRM/Contact/BAO/GroupContactCache.php';
            
            // Reset and repopulate from the groups we actually found (in case of deletions/deactivations)
            $group_ids = [];
            
            // Check cache status for any smart groups, and refresh if more than an hour old
            $date = new DateTime( 'now' );
            $timestamp = $date->getTimestamp();
            
            foreach($current_groups['values'] as $gx=>$g){
                $group_ids[] = $gx;
                if(!empty($g['saved_search_id'])){
                    if(empty($g['cache_date']) || $timestamp - strtotime($g['cache_date']) > 1200){
                        $results['messages'][] = "Refreshing cache for smart group ID $gx";
                        CRM_Contact_BAO_GroupContactCache::clearGroupContactCache($gx);
                    } else {
                        $results['messages'][] = "No cache refresh needed for smart group ID $gx";
                    }
	                
                }
            }
            
            if(empty($group_ids)){
                $results['messages'][] = 'No syncing required: No active groups found to sync.';
    	        return [$results, 200];
            }
            
    	    // Get BBPress roles info
    	    $default_hierarchy = ['bbp_keymaster' => 4, 'bbp_moderator' => 3, 'bbp_participant' => 2, 'bbp_spectator' => 1, 'bbp_blocked' => 0];
    	    $bb_hierarchy = get_option('civi-bb-groups-bb-hierarchy', $default_hierarchy);
    	    $bb_default = get_option('_bbp_default_role', 'bbp_blocked');
    	    $bb_roles_list = array_keys(civibbg_get_bb_roles());
	    
    	    // Figure out which groups and roles need syncing
    	    $editable_roles = civibbg_get_editable_roles();
    	    $process_removals = false;
    	    $all_removals = [];
    	    $wp_roles = [];
    	    $checked_map = [];
    	    foreach($groups_map as $g){
    	        if(empty($current_groups['values'][$g['group']])) continue;
    	        
    	        $checked_map[] = $g;
    	        if(isset($editable_roles[$g['role']])) $wp_roles[] = $g['role']; 
    	        if($g['sync_removals']){
    	            $process_removals = true;
    	            $all_removals[$g['role']] = empty( $all_removals[$g['role']]) ? [$g['bb_role']] : array_unique(array_merge($all_removals[$g['role']], $g['bb_role'])); 
    	        } 
    	    }
    	    $wp_roles = array_unique($wp_roles);
    	    
    	    // In case Civi is being used in multisite mode, we only want to sync for this site
            $domain_id = CRM_Core_Config::domainID();
            
            // Fetch all the CiviCRM contacts in the required groups
            // Have to use api3 for now, as api4 currently doesn't support a "group" IN query for Contacts. 
            $contacts = civicrm_api3('Contact', 'get', [
                'return' => ["id", "first_name", "last_name", "display_name", "organization_name", "group", "email"],
                'group' => ['IN' => $group_ids],
                'is_deleted' => 0,
                'is_deceased' => 0,
                'options' => ['limit' => 10000],
                'api.UFMatch.get' => ['domain_id' => $domain_id],
            ]);
            
            if($contacts['count'] > 0){
                
                $notify_new_users = civibbg_sanitize_thing(get_option('civi-bb-groups-user-notify', false), 'boolean');
                $default_role = get_option('default_role');
                
                // Process all the CiviCRM contacts
                foreach($contacts['values'] as $contact_id => $c){
                    
                    // Find (or create if necessary) the matching WP user
                    $wp_user = $this->get_wp_user($c, $domain_id, $notify_new_users);
                    if(empty($wp_user[0])){
                        $results['errors'][] = "Failed to sync contact ".$c['display_name']." (CiviCRM contact ID ".$c['id']."): " . $wp_user[1];
                        continue;
                    } elseif(!empty($wp_user[1])){
                        $results['messages'][] = $c['display_name']." (CiviCRM contact ID ".$c['id']."): " . $wp_user[1];
                    }
                    
                    //Figure out which roles need to be added or removed for this user
                    $add_roles = [];
                    $remove_roles = $wp_user[2] ? [$default_role] : [];
                    
                    $wp_user = $wp_user[0];
                    
                    $user_groups = explode(",",trim($c['groups'],', '));
                    $current_roles = $wp_user->roles;
                    
                    $bb_role = array_intersect($bb_roles_list, $current_roles); 
                    $bb_role = array_shift($bb_role);
                    $bb_level = 0;
                    
                    $current_bb_level = isset($bb_hierarchy[$bb_role]) ? $bb_hierarchy[$bb_role] : 0;
                    $current_bb_role = $bb_role;
                    $keep_current = false;
                    $remove_current = false;
                    
                    foreach($checked_map as $g){
                        if(in_array($g['group'], $user_groups)){
                            $add_roles[] = $g['role'];
                            if(isset($bb_hierarchy[$g['bb_role']]) && $bb_hierarchy[$g['bb_role']] >= $bb_level){
                                $bb_level = $bb_hierarchy[$g['bb_role']];
                                $bb_role = $g['bb_role']; 
                                if($g['bb_role'] === $current_bb_role) $keep_current = true;
                            } 
                        } elseif($process_removals && $g['sync_removals']){
                            $remove_roles[] = $g['role'];
                            if($g['bb_role'] === $current_bb_role) $remove_current = true;
                        }
                    }
                    
                    // If we've arrived at no bbpress role, assign the default
                    if(empty($bb_role)){
                        $bb_role = $bb_default;
                        
                    // If the current role is marked as 'keep' or not marked as 'remove', 
                    // Check whether it's higher than the bbpress role we found, and if so,
                    // keep it
                    } elseif($keep_current || !$remove_current){
                        if($current_bb_level > $bb_level) $bb_role = $current_bb_role;
                        
                    // If the current role is marked as 'remove' and the bbpress role we 
                    // found is still the current role, and if so, remove it 
                    // (and therefore, reset to the default)
                    } elseif($remove_current && $bb_role === $current_bb_role){
                        $bb_role = $bb_default;
                    }
                    
                    $add_roles = array_unique($add_roles);
                    $add_roles[] = $bb_role;
                    
                    $remove_roles = array_merge(array_unique($remove_roles), $bb_roles_list);
                    
                    $remove_roles = array_diff(array_intersect($remove_roles, $current_roles), $add_roles);
                    $add_roles = array_diff($add_roles, $current_roles);
                    
                    // Add roles as needed for this user
                    foreach($add_roles as $r){
                        $wp_user->add_role( $r );
                    }
                    
                    // Remove roles as needed for this user
                    foreach($remove_roles as $r){
                        $wp_user->remove_role( $r );
                    }
                    
                    // If the user is left with no WP role, give them the default WP role
                    if(empty(array_diff($wp_user->roles, $bb_roles_list))){
                        $wp_user->add_role($default_role);
                    }
                    
                    $done[] = $wp_user->ID;
                    $results['processed_users'][] = $c['display_name']." (CiviCRM contact ID ".$c['id'].", WP user ID $wp_user->ID) has role(s): ".implode(", ", $wp_user->roles);
                }
            }
            
            // Process any users who are not in any of the groups, but may have one of the WP roles due to previously being in the group
            if($process_removals){
                
        	    $other_users = get_users( array(
        	        'role__in' => $wp_roles,   
        	        'exclude' => $done
        	    ) );
        	    
        	    foreach($other_users as $u){
        	        $to_remove = [];
        	        $current_roles = $u->roles;
        	        
        	        // Figure out which roles need to be removed
        	        foreach($all_removals as $wp_role=>$bb_roles){
        	            if(in_array($wp_role, $current_roles)){
        	                $to_remove[] = $wp_role;
        	                $to_remove = array_merge($to_remove, $bb_roles);
        	            }
        	        }
        	        $to_remove = array_unique($to_remove);

        	        // Remove any roles as necessary
        	        foreach($to_remove as $r){
                        $u->remove_role( $r );
                    }
                    
                    // If the user is left with no WP role, give them the default WP role
                    if(empty(array_diff($u->roles, $bb_roles_list))){
                        $u->add_role($default_role);
                    }
                    
                    // If the user is left with no BB role, give them the default BB role
                    if(empty(array_intersect($bb_roles_list, $u->roles))){
                        $u->add_role($bb_default);
                    }
                    
                    $results['processed_users'][] = "$u->display_name (WP user ID $u->ID) has roles: ".implode(", ", $u->roles); 
        	    }
            }
    	    
    
	    } catch(Exception $e){
	        return [$results, 500];
	    }
	    
	    return [$results, 200];

	}
	
	// Fetch the WP user corresponding to a CiviCRM contact
	private function get_wp_user($c, $domain_id, $notify_new_users = false){
	    try {
	        
	        // Is the contact already linked to a WP user?
	        if($c['api.UFMatch.get']['count'] === 1){

                $wp_id = $c['api.UFMatch.get']['values'][0]['uf_id'];
                $wp_user = get_user_by('ID', $wp_id);
                if($wp_user) return [$wp_user, '', false];
	        } 
	        
	        // Make sure they have an email address
	        if(empty($c['email'])) return [false, "This contact does not have an email address.", false];
            
            // Make sure there are no other Civi contacts with the same primary email address
            $email_matches = civicrm_api3('Contact', 'getcount', [
                'email' => $c['email'],
                'is_deleted' => 0,
                'is_deceased' => 0,
            ]);
            
            if($email_matches > 1) return [false,  "Multiple contacts in the CiviCRM database share the same primary email address, whereas WordPress users must have unique email addresses.", false];
	        
	        // Check whether a WP user with the same email address exists 
	        $wp_user = get_user_by( 'email', $c['email'] );
	        if($wp_user){
	            // Is this WP user linked to any other Civi contact, by ID or email address?
	            $matches = civicrm_api3('UFMatch', 'getcount', [
                    'uf_name' => $c['email'],
                    'uf_id' => $wp_user->ID,
                    'domain_id' => $domain_id,
                    'options' => ['or' => [["uf_id", "uf_name"]]],
                ]);
                
                if($matches > 0){
                    // The WP user is linked to some other CiviCRM contact
                    return [false, "A WordPress account with the same email address is already linked to a different contact.", false];
                } else {
                    // The WP user isn't linked to any other CiviCRM contact, and shares the same email address, so pretty safe to assume it should be linked to this contact
                    // Link the WP user to this contact
                    $UFmatch = civicrm_api3('UFMatch', 'create', [
                        'uf_id' => $wp_user->ID,
                        'uf_name' => $c['email'],
                        'contact_id' => $c['contact_id'],
                        'domain_id' => $domain_id,
                    ]);
                    
                    return [$wp_user, '', false];
                }
	        }
	        
	        // OK, we've exhausted all possibilities, so we need to create a new WP user for this contact
	        $user_id = $this->create_wp_user($c, $domain_id, $notify_new_users);
	        if(is_wp_error($user_id)){
	            return [false, "Failed to create a new WordPress user", false];
	        } else {
	            $wp_user = get_user_by('ID', $user_id);
	            return $wp_user ? [$wp_user, 'WP user account created with WP user ID '.$user_id, true] : [false, 'Created but failed to fetch matching WP user', false];
	        }
	        
	    } catch(Exception $e){
	        return [false, 'Failed to find or create a matching WordPress user.', false];
	    }
	}
	
	// Create a new WP user based on a CiviCRM contact, and link the user to the contact
	private function create_wp_user($c, $domain_id, $notify = false){
	    
	    // Generate a unique username
	    $username = sanitize_title( sanitize_user( $c['display_name'] ) );
        if(username_exists( $username )){
            
            $count = 1;
    		$user_exists = 1;
    		$new_username = $username;
    
    		do {
    			$new_username = $username.$count;
    			$user_exists = username_exists( $new_username );
    			$count++;
    
    		} while ( $user_exists > 0 );
    		$username = $new_username;
        }
        
        $user_data = array(
            'user_login' => $username,
            'user_pass' => random_bytes(8),
            'user_email' => $c['email'],
            'first_name' => $c['first_name'],
            'last_name' => $c['last_name'],
            'display_name' => $c['display_name'],
            'nickname' => $c['display_name'],
        );
        
        // Stop Civi automatically creating another contact record! We know the right contact record already, and we'll link it
        $this->remove_filters();
        
        $user_id = wp_insert_user( $user_data ) ;
        
        
		if ( ! is_wp_error( $user_id ) AND isset( $c['contact_id'] ) ) {
            
            // Create UF Match.
            $UFmatch = civicrm_api3('UFMatch', 'create', [
                'uf_id' => $user_id,
                'uf_name' => $c['email'],
                'contact_id' => $c['contact_id'],
                'domain_id' => $domain_id,
            ]);
            
            // Notify the new user about their account and login details
            if($notify) wp_new_user_notification($user_id, null, 'user');
            
		}
        
        // Add Civi's filters back
        $this->add_filters();
        
        return $user_id;
	}
	
	// Credits to CiviCRM WordPress Member Sync plugin
	public function remove_filters() {

        if(!function_exists('civi_wp')) return;

		// Get CiviCRM instance.
		$civi = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civi, 'update_user' ) ) {

			// Remove previous CiviCRM plugin filters.
			remove_action( 'user_register', array( civi_wp(), 'update_user' ) );
			remove_action( 'profile_update', array( civi_wp(), 'update_user' ) );

		} else {

			// Remove current CiviCRM plugin filters.
			remove_action( 'user_register', array( civi_wp()->users, 'update_user' ) );
			remove_action( 'profile_update', array( civi_wp()->users, 'update_user' ) );

		}

		// Remove CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_remove();
			$civicrm_wp_profile_sync->hooks_bp_remove();
		}

	}

	// Credits to CiviCRM WordPress Member Sync plugin
	public function add_filters() {
	    
	    if(!function_exists('civi_wp')) return;

		// Get CiviCRM instance.
		$civi = civi_wp();

		// Do we have the old-style plugin structure?
		if ( method_exists( $civi, 'update_user' ) ) {

			// Re-add previous CiviCRM plugin filters.
			add_action( 'user_register', array( civi_wp(), 'update_user' ) );
			add_action( 'profile_update', array( civi_wp(), 'update_user' ) );

		} else {

			// Re-add current CiviCRM plugin filters.
			add_action( 'user_register', array( civi_wp()->users, 'update_user' ) );
			add_action( 'profile_update', array( civi_wp()->users, 'update_user' ) );

		}

		// Re-add CiviCRM WordPress Profile Sync filters.
		global $civicrm_wp_profile_sync;
		if ( is_object( $civicrm_wp_profile_sync ) ) {
			$civicrm_wp_profile_sync->hooks_wp_add();
			$civicrm_wp_profile_sync->hooks_bp_add();
		}

	}
}

