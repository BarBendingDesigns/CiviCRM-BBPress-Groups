<?php

/**
 * Handles BBPress-related admin functions
 *
 */

class Civi_Bb_Groups_BBPress_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}
	
	/** Output role restriction checkboxes
	 *  Used to extend the bbp_forum_metabox (Forum Attributes) displayed on the Forum edit page
	 */
	public function output_role_restrict_meta_section($post){
	    $wp_roles = civibbg_get_editable_roles();
	    
	    $allowed = [];
	    $current_roles = get_post_meta( $post->ID, 'civi-bb-groups-restrict-to', true );
	    if(!empty($current_roles)){
	        foreach($current_roles as $c){
	            $c = sanitize_key($c);
	            $allowed[$c] = true;
	        }
	    } 
	    
	    ?>
	    <div class='civibbg-extra-meta'>
	    <p><strong>Restrict to WP Roles</strong></p>
        <?php
        foreach($wp_roles as $k=>$v){
            $checked = empty($allowed[$k]) ? '' : "checked='checked'";
            echo "<label><input type='checkbox' $checked name='civibbg_restrict[$k]' /> $v</label>";
        }
        ?>
	    <p><em>Only the selected WP Roles above will be able to view this forum. 
	    If no roles are ticked, the forum will not be restricted by WP role. 
	    Administrators can always view any forum.</em></p>
	    </div>
	    <?php
	    
	}
	
	// Stop other plugins trying to manage permissions on forum post types
	// E.g. hooked into filter: members_enable_forum_content_permissions
	public function override_other_restrict_content_plugins($enabled){
	    return false;
	}
	
	/**
	 * Save the role restriction settings when saving a Forum in WP admin
	 * Used when saving input from the bbp_forum_metabox (Forum Attributes) displayed on the Forum edit page
	 * 
	 * This is hooked into the bbp_forum_attributes_metabox_save action, 
	 * after nonce checks etc. have already run
	 */
	public function save_role_restrict_meta($forum_id){
	    $meta = [];
	    if(!empty($_POST['civibbg_restrict'])){
	        foreach($_POST['civibbg_restrict'] as $r=>$v){
	            $role = sanitize_key($r);
	            if(civibbg_sanitize_thing($v, 'bool')){
	                $meta[] = $r;
	            }
	        }
	    }
	    update_post_meta( $forum_id, 'civi-bb-groups-restrict-to', $meta);
	}
	
	// Add the WP Role Access column (for the Forums list table in WP admin)
	public function add_forum_role_restrictions_column($columns){
	    $added = false;
	    $updated = [];
	    foreach($columns as $k=>$v){
	        if($k === 'author'){
	            $updated['civibbg_restrict'] = 'WP Role Access';
	            $added = true;
	        }
	        $updated[$k] = $v;
	    }
	    if(!$added) $updated['civibbg_restrict'] = 'WP Role Access';
	    return $updated;
	}
	
	// Output the WP Role Access column content
	public function output_forum_role_restrictions_column($column, $forum_id){
	    if($column !== 'civibbg_restrict') return;
	    
	    $wp_roles = civibbg_get_editable_roles();

	    $current_roles = get_post_meta( $forum_id, 'civi-bb-groups-restrict-to', true );
	    $current_roles = civibbg_sanitize_thing($current_roles, 'array');
	    $role_names = [];
	    foreach($current_roles as $r){
	        if(!empty($wp_roles[$r])) $role_names[] = $wp_roles[$r];
	    }
	    
	    $parent_role_names = [];
	    $ancestors = get_post_ancestors($forum_id);
	    foreach($ancestors as $a){
	        $ar = get_post_meta( $a, 'civi-bb-groups-restrict-to', true );
	        $ar = civibbg_sanitize_thing($ar, 'array');
	        foreach($ar as $r){
	            if(!empty($wp_roles[$r])) $parent_role_names[] = $wp_roles[$r];
	        }
	    }
	    
	    if(empty($role_names) && empty($parent_role_names)){
	        echo '(All WP roles)';
	    } else {
	        if(!empty($role_names)) echo "<p>".implode(", ", $role_names)."</p>";
	        if(!empty($parent_role_names)) echo "<p>Access based on parent forums: ".implode(", ", $parent_role_names)."</p>";
	    }
	    
	}
	
}

