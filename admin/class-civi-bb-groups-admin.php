<?php
/**
 * The admin-specific functionality of the plugin.
 *
 */
class Civi_Bb_Groups_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

    // Enqueue required CSS for admin pages
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/civi-bb-groups-admin.css', array(), $this->version, 'all' );

	}

    // Enqueue required JavaScript for admin pages
	public function enqueue_scripts() {
	    
        $screen = get_current_screen();
        if($screen){
            switch($screen->id){
                case 'toplevel_page_civicrm-bbpress-groups':
                    wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/civi-bb-groups-admin.js', array( 'jquery', 'lodash' ), $this->version, false );
            
                    $localize_data = array(
                        'civi_groups' => $this->get_civi_groups(),
                        'wp_roles' => civibbg_get_editable_roles(),
                        'bb_roles' => civibbg_get_bb_roles(),
                        'bb_on' => civibbg_bbpress_is_on(),
                    );
                
                    wp_localize_script( $this->plugin_name, 'civibbg_data', $localize_data );
                    
                    break;
                case 'civicrm-bbpress-groups_page_civicrm-bbpress-manual_sync':
                    wp_enqueue_script( 'civi-bb-groups-sync', plugin_dir_url( __FILE__ ) . 'js/civi-bb-groups-sync.js', array( 'jquery', 'lodash' ), $this->version, false );
                    
                    break;
                default:
                    return;
            }
        }
	}
	
	// Add pages to WP admin, to handle CiviCRM BBPress Groups settings, shortcode instructions and manual syncing
	public function setup_admin_page(){

	    add_menu_page('CiviCRM BBPress Groups', 'CiviCRM BBPress Groups', 'manage_options','civicrm-bbpress-groups', [$this, 'do_admin_page'], '', 59);
	    add_submenu_page('civicrm-bbpress-groups', 'Settings', 'Settings', 'manage_options','civicrm-bbpress-groups');
	    
	    add_settings_section( 'shortcodes', 'Login and Logout Shortcodes', null, 'civicrm-bbpress-groups' );
	    add_settings_section( 'syncing', 'CiviCRM Groups to WP User Roles Syncing', null, 'civicrm-bbpress-groups' );
	    add_settings_section( 'profiles', 'User Account Pages & Profiles', null, 'civicrm-bbpress-groups' );
	    add_settings_section( 'bbpress', 'BBPress Forums', null, 'civicrm-bbpress-groups' );
	    
	    $settings_fields = $this->get_settings_fields();
	    foreach($settings_fields as $id=>$field){
	        if($field['type'] !== 'readonly') register_setting('civicrm-bbpress-groups', $id, $field['sanitize_callback']);
	        
	        $field['id'] = $id;
	        add_settings_field($id, $field['name'], [$this, 'render_settings_field'], 'civicrm-bbpress-groups', $field['section'], $field);
	    }
	    
	}
	
	public function setup_shortcodes_admin_page(){
	    add_submenu_page('civicrm-bbpress-groups', 'Shortcodes', 'Shortcodes', 'manage_options','civicrm-bbpress-groups-shortcodes', [$this, 'do_shortcodes_page']);
	}

    // Output a settings field
	public function render_settings_field($field){
	    if($field['type'] === 'readonly'){
	        $val = $field['output'];
	    } else {
	        $val = civibbg_sanitize_thing(get_option($field['id'], $field['default']), $field['type']);
	    }
	    
	    switch($field['type']){
	        case 'url':
	        case 'email':
	        case 'text':
	            echo "<input type='".$field['type']."' value='$val' name='".$field['id']."' />";
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            break;
	        case 'dropdown':
	            wp_dropdown_pages(array(
	                'name' => $field['id'],
	                'selected' => $val,
	                'show_option_none' => 'Select...',
	                'option_none_value' => 0,
	            ));
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            break;
	        case 'shortcode':
	            echo "<input type='text' value='$val' name='".$field['id']."' />";
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            break;
	        case 'boolean':
	            echo "<input type='checkbox' ".checked(true, $val, false)." name='".$field['id']."' />";
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            break;
	        case 'groups-map':
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            $this->render_groups_map($val);
	            break;
	        case 'role-hierarchy':
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            $this->render_role_hierarchy($val);
	            break;
	        case 'readonly':
	            echo $val;
	            if(!empty($field['description'])){
	                echo "<p class='description'>".$field['description']."</p>";
	            }
	            break;
	        default:
	            echo 'Unhandled field type';
	    }
	}
	
	// Fetch all currently active CiviCRM groups
	private function get_civi_groups(){
        if(!civibbg_civi_api_is_on()){
           return []; 
        }
	    
	    try{
    	    $groups = \Civi\Api4\Group::get()
              ->addSelect('id', 'title', 'description')
              ->addWhere('is_active', '=', TRUE)
              ->addWhere('is_hidden', '=', FALSE)
              ->execute();
            return $groups;
	    } catch(Exception $e){
	        return [];
	    }
	}
	
	// Fetch CiviCRM groups, based on group IDs
	private function get_civi_groups_in($in){
	    if(!civibbg_civi_api_is_on()){
           return []; 
        }
        
        $ids = array_unique(array_column($in, 'group'));
        
        try{
    	    $groups = \Civi\Api4\Group::get()
              ->addSelect('id', 'title')
              ->addWhere('is_active', '=', TRUE)
              ->addWhere('is_hidden', '=', FALSE)
              ->addWhere('id', 'IN', $ids)
              ->execute();
              
            $result = [];
            foreach($groups as $g){
                $result[$g['id']] = $g['title'];
            }
              
            return $result;
	    } catch(Exception $e){
	        return [];
	    }
	}
	
	// Output the BBPress roles hierarchy table, for the plugin settings page
	private function render_role_hierarchy($current){
	    $bb_roles = civibbg_get_bb_roles();
	    ?>
	    <table class='civibbg-roles-hierarchy widefat fixed striped'>
	        <thead><tr><th>BBPress Role</th><th>Hierarchy</th></tr>
	        <tbody>
	    <?php
	    foreach($bb_roles as $r=>$name){
	        $value = isset($current[$r]) ? $current[$r] : 0;
	        echo "<tr><td>".$name."</td><td><input type='number' name='civi-bb-groups-bb-hierarchy[$r]' value='$value' required /></td></tr>";
	    }
	    ?>
	    </tbody></table>
	    <?php
	}
	
	// Output the CiviCRM groups to WordPress roles syncing table, for the plugin settings page
	private function render_groups_map($value){
	    $header = "<tr><th>CiviCRM Group</th><th>WP User Role</th><th>BBPress Role</th><th>Sync Removals?</td><th><button class='button button-primary civibbg-add-group' type='button'>Add Row</button></th></tr>";

        $roles = civibbg_get_editable_roles();
        $bb_roles = civibbg_get_bb_roles();
        $groups = $this->get_civi_groups_in($value);
        
        $default_role = sanitize_key(get_option('default_role'));
        $bb_default = get_option('_bbp_default_role', 'bbp_blocked');
	    ?>
	    <ul>
	        <li><strong>One row per CiviCRM group</strong> is allowed. Any additional rows for the same group will be ignored.</li>
	        <li>If <strong>Sync Removals</strong> is ticked, 
	        the syncing will <em>remove</em> the appropriate roles if the a contact is removed from the CiviCRM group.<br/>
	        If it is not ticked, the syncing will only add roles, and not remove them.<br/>
	        If role removal results in a WP user with no roles, the default WP role (<?php echo $default_role; ?>) and default BBPress role (<?php echo $bb_default; ?>) will be assigned to them.</li>
	        <li>WP Roles determine what a user can access on the website.<br/>
	        BBPress roles determine what an user can do in the forums: For example, a 'Participant' can post in a forum, whereas a 'Spectator' can only read the forum.<br/>
	        WP Roles can be used to control access to specific forums.</li>
	        <li>Only one BBPress role is allowed. If syncing rules mean that a user would otherwise be assigned multiple BBPress roles, they will be assigned the highest one.</li>
	        <li>Syncing will add WP users if required, but will not delete WP users.</li>
	    </ul>
	    <table class='civibbg-groups-map widefat fixed striped'>
	        <thead>
	            <?php echo $header; ?>
	        </thead>
	        <tbody>
	            
	            <?php foreach($value as $k=>$g){
	                $role_id = $g['role'];
	                $role_name = isset($roles[$role_id]) ? $roles[$role_id] : '(unknown role)';
	                $group_id = $g['group'];
	                $group_name = isset($groups[$group_id]) ? $groups[$group_id] : '(unknown group)';
	                $bb_id = $g['bb_role'];
	                $bb_name = isset($bb_roles[$bb_id]) ? $bb_roles[$bb_id] : '(unknown role)';
	                $remove = empty($g['sync_removals']) ? 0 : 1;
	                $remove_name = $remove ? 'Yes' : 'No';
	                
	                echo "<tr id='civibbg_group_$k'>
	                        <td><label class='civibbg_hideSm'>CiviCRM Group</label><p>$group_name</p><input type='hidden' name='civi-bb-groups-syncing-map[group-$k][group]' class='civibbg_group_field' value='$group_id' /></td>
	                        <td><label class='civibbg_hideSm'>WP Role</label><p>$role_name</p><input type='hidden' name='civi-bb-groups-syncing-map[group-$k][role]' class='civibbg_role_field' value='$role_id' /></td>
	                        <td><label class='civibbg_hideSm'>BBPress Role</label><p>$bb_name</p><input type='hidden' name='civi-bb-groups-syncing-map[group-$k][bb_role]' class='civibbg_bb_role_field' value='$bb_id' /></td>
	                        <td><label class='civibbg_hideSm'>Sync Removals?</label><p>$remove_name</p><input type='hidden' name='civi-bb-groups-syncing-map[group-$k][sync_removals]' class='civibbg_removals_field' value='$remove' /></td>
	                        <td><button type='button' class='civibbg-groups-edit button button-default'>Edit</button>
	                            <button type='button' class='civibbg-groups-delete button button-default'>Delete</button></td>
	                    </tr>";
	            } ?>
	        </tbody>
	        <tfoot>
	            <?php echo $header; ?>
	        </tfoot>
	    </table>
	    
	    <?php
	    
	}
	
	// Return details of all the settings fields required for the plugin
	private function get_settings_fields(){
	    return array(
	        'civi-bb-groups-login-redirect' => array(
	            'type' => 'url', 
	            'name' => 'Redirect after login', 
	            'description' => 'If set, the login form will redirect to this URL after successful login.<br/>Leave blank to stay on the same page after login.<br/>Used for the [civi-bbg-login-form] and [civi-bbg-login-logout-form] shortcodes.',
	            'sanitize_callback' => 'esc_url_raw',
	            'default' => '', 
	            'section' => 'shortcodes',
	        ),
	        'civi-bb-groups-logout-redirect' => array(
	            'type' => 'url', 
	            'name' => 'Redirect after logout',
	            'description' => 'If set, the logout button will redirect to this URL after logout.<br/>Leave blank to stay on the same page after logout.<br/>Used for the [civi-bbg-logout-link] and [civi-bbg-login-logout-form] shortcodes.',
	            'section' => 'shortcodes',
	            'sanitize_callback' => 'esc_url_raw',
	            'default' => '', 
	        ),
	        'civi-bb-groups-auto-sync' => array(
	            'type' => 'boolean', 
	            'name' => 'Automatically sync groups?',
	            'description' => 'Tick to automatically sync the selected groups with the corresponding WordPress user roles. The automatic process will run once per day.',
	            'section' => 'syncing',
	            'sanitize_callback' => 'civibbg_sanitize_checkbox',
	            'default' => false, 
	        ),
	        'civi-bb-groups-sync-notify' => array(
	            'type' => 'email', 
	            'name' => 'Email syncing report to',
	            'description' => 'Enter an email address to receive a report each time the syncing process runs. Leave blank to send no report.',
	            'section' => 'syncing',
	            'sanitize_callback' => 'sanitize_email',
	            'default' => '', 
	        ),
	        'civi-bb-groups-user-notify' => array(
	            'type' => 'boolean', 
	            'name' => 'Notify new WP users?',
	            'description' => 'If ticked: When a new WP user is created as part of the syncing a process, an email will be sent to them containing their username and a link to set their password.<br/>Note: If the number of new users to add at once is large, this may result in a large number of emails being sent in a short space of time. Consequently, it is recommended to check whether your web host imposes any rate limits on sending emails before turning this option on.',
	            'section' => 'syncing',
	            'sanitize_callback' => 'civibbg_sanitize_checkbox',
	            'default' => false, 
	        ),
	        'civi-bb-groups-syncing-map' => array(
	            'type' => 'groups-map',
	            'name' => 'CiviCRM groups to WordPress roles syncing',
	            'description' => 'Select CiviCRM groups, and the corresponding WordPress user roles that they should sync to.',
	            'section' => 'syncing',
	            'sanitize_callback' => 'civibbg_sanitize_groups_map',
	            'default' => [], 
	        ),
	        'civi-bb-groups-bb-hierarchy' => array(
	            'type' => 'role-hierarchy',
	            'name' => 'BBPress roles hierarchy',
	            'description' => 'Specify the hierarchy of BBPress roles: A higher number overrides a lower number. When syncing users, this will be used to determine which BBPress role they should be assigned.',
	            'section' => 'syncing',
	            'sanitize_callback' => 'civibbg_sanitize_role_hierarchy',
	            'default' => array(
	                'bbp_keymaster' => 4,
	                'bbp_moderator' => 3,
	                'bbp_participant' => 2,
	                'bbp_spectator' => 1,
	                'bbp_blocked' => 0,
	            ), 
	        ),
	        'civi-bb-groups-account-page' => array(
	        	'type' => 'dropdown',
	            'name' => '"My Account" Page',
	            'description' => 'A page allowing the the user to view their login details and change their password. Recommended: Add [civi-bbg-user-account] shortcode to this page.',
	            'section' => 'profiles',
	            'sanitize_callback' => 'absint',
	            'default' => 0, 
	        ),
	        'civi-bb-groups-profile-page' => array(
	        	'type' => 'dropdown',
	            'name' => '"My Profile" Page',
	            'description' => 'A page allowing the user to view and edit their details, such name and address. Recommended: Add the [civi-bbg-user-profile] shortcode to this page.',
	            'section' => 'profiles',
	            'sanitize_callback' => 'absint',
	            'default' => 0, 
	        ),
	        'civi-bb-groups-individual-profile' => array(
	            'type' => 'shortcode',
	            'name' => 'User Profile: Shortcode for Individuals',
	            'description' => 'Enter a shortcode to output the "My Profile" form for users who are Individual contacts in CiviCRM:<br/>This will be used to determine the output of the [civi-bbg-user-profile] shortcode.',
	            'section' => 'profiles',
	            'sanitize_callback' => 'civibbg_sanitize_shortcode',
	            'default' => '', 
	        ),
	        'civi-bb-groups-organization-profile' => array(
	            'type' => 'shortcode',
	            'name' => 'User Profile: Shortcode for Organisations',
	            'description' => 'Enter a shortcode to output the "My Profile" form for users who are Organisation contacts in CiviCRM:<br/>This will be used to determine the output of the [civi-bbg-user-profile] shortcode.',
	            'section' => 'profiles',
	            'sanitize_callback' => 'civibbg_sanitize_shortcode',
	            'default' => '', 
	        ),
	        'civi-bb-groups-household-profile' => array(
	            'type' => 'shortcode',
	            'name' => 'User Profile: Shortcode for Households',
	            'description' => 'Enter a shortcode to output the "My Profile" form for users who are Household contacts in CiviCRM:<br/>This will be used to determine the output of the [civi-bbg-user-profile] shortcode.',
	            'section' => 'profiles',
	            'sanitize_callback' => 'civibbg_sanitize_shortcode',
	            'default' => '', 
	        ),
	        'civi-bb-groups-restrict-tags' => array(
	            'type' => 'boolean', 
	            'name' => 'Restrict HTML in Forum Posts?',
	            'description' => 'Tick to block non-Admin users from posting HTML links and images in forums, topics and replies.',
	            'section' => 'bbpress',
	            'sanitize_callback' => 'civibbg_sanitize_checkbox',
	            'default' => false, 
	        ),
	        'civi-bb-groups-forum-instructions-page' => array(
	        	'type' => 'dropdown',
	            'name' => 'Forums "Instructions" Page',
	            'description' => 'A page containing instructions for using the forums.',
	            'section' => 'bbpress',
	            'sanitize_callback' => 'absint',
	            'default' => 0, 
	        ),
	        'civi-bb-groups-forum-tandcs-page' => array(
	        	'type' => 'dropdown',
	            'name' => 'Forums "Terms & Conditions" Page',
	            'description' => 'A page containing terms & conditions for using the forums.',
	            'section' => 'bbpress',
	            'sanitize_callback' => 'absint',
	            'default' => 0, 
	        ),
	        
	    );
	}
	
	// Turn off HTML editor and autoembed feature (BBPress options), if "Restrict HTML in Forum Posts" is on.
	public function maybe_turn_off_bbp_wp_editor($old_value, $value, $option){
	    if($value && $value != $old_value){
	        update_option('_bbp_use_wp_editor', 0);
	        update_option('_bbp_use_autoembed', 0);
	    }
	}
	
	// Output the main plugin settings page
	public function do_admin_page(){

        if ( ! current_user_can( 'manage_options' ) ) { return; }
        
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( 'civicrm-bbpress-groups_messages', 'civicrm-bbpress-groups_message', 'Settings Saved', 'updated' );
        }
        
        settings_errors( 'civicrm-bbpress-groups_messages' );
	    
	    ?>
	    <div class="wrap" id="civicrm-bbpress-groups-page">
	    <h1>CiviCRM BBPress Groups Settings</h1>
	    <form action="options.php" method="post">
	    <?php 
	        submit_button('Save Settings');
	        settings_fields( 'civicrm-bbpress-groups' );
	        do_settings_sections( 'civicrm-bbpress-groups' ); 
	        submit_button('Save Settings');
	    ?>
	    </form>
	    </div>
	    <?php
	}
	
	// Output the plugin shortcodes page
	public function do_shortcodes_page(){
	    $login_url = esc_url(get_option('civi-bb-groups-login-redirect', ''));
	    $logout_url = esc_url(get_option('civi-bb-groups-logout-redirect', ''));
	    $settings_url = menu_page_url( 'civicrm-bbpress-groups', false );
	    
	    ?>
	    <div class="wrap" id="civicrm-bbpress-shortcodes-page">
	    <h1>CiviCRM BBPress Groups Shortcodes</h1>
	    <p>Shortcode settings are available on the <a href='<?php echo $settings_url; ?>'>CiviCRM BBPress Groups settings page</a>.</p>
	    <h2>Available shortcodes</h2>
	    <table class='civibbg-admin-table widefat fixed striped'>
	        <thead><th>Shortcode</th><th>Description</th><th>Options</th><th>Example Use</th></thead>
	        <tbody>
	            <tr>
	                <td><label class='civibbg_hideSm'>Shortcode</label>
	                    <code>[civi-bbg-login-form no_redirect={0|1}]</code></td>
	                <td><label class='civibbg_hideSm'>Description</label>
	                <p>Output a login form, including a "lost" your password link.<br/>
	                If the user is already logged in, there is no output.</p></td>
	                <td><label class='civibbg_hideSm'>Options</label>
	                    <p><strong>no_redirect</strong>: If set to 1, the login form will redirect to the current page.<br/>
	                If set to 0, or not set at all, the login form will redirect to the URL from CiviCRM BBPress Groups settings (currently: <?php echo $login_url; ?>).</p>
	                </td>
	                <td><label class='civibbg_hideSm'>Example Use</label>
	                    <code>[civi-bbg-login-form no_redirect=1]</code></td>
	            </tr>
	            <tr>
	                <td><label class='civibbg_hideSm'>Shortcode</label>
	                    <code>[civi-bbg-logout-link no_redirect={0|1} as_button={1|0}]</code></td>
	                <td><label class='civibbg_hideSm'>Description</label>
	                <p>Output a logout link or button.<br/>
	                If the user is not logged in, there is no output.</p></td>
	                <td><label class='civibbg_hideSm'>Options</label>
	                    <p><strong>no_redirect</strong>: If set to 1, the login form will redirect to the current page.<br/>
	                If set to 0, or not set at all, the logout link/button will redirect to the URL from CiviCRM BBPress Groups settings (currently: <?php echo $logout_url; ?>).</p>
	                    <p><strong>as_button</strong>: If set to 1, or not set at all, the logout link will be displayed as a button.<br/>
	                    If set to 0, the logout link will be displayed as a hyperlink.</p>
	                </td>
	                <td><label class='civibbg_hideSm'>Example Use</label>
	                    <code>[civi-bbg-logout-link as_button=1]</code></td>
	            </tr>
	            <tr>
	                <td><label class='civibbg_hideSm'>Shortcode</label>
	                    <code>[civi-bbg-login-logout-form no_redirect={0|1}]</code></td>
	                <td><label class='civibbg_hideSm'>Description</label>
	                <p>Output a login form or logout button, depending on whether or not the user is logged in.</p></td>
	                <td><label class='civibbg_hideSm'>Options</label>
	                    <p><strong>no_redirect</strong>: If set to 1, the login form will redirect to the current page.<br/>
	                If set to 0, or not set at all, the login form or logout button will redirect to the URL from CiviCRM BBPress Groups settings (currently login: <?php echo $login_url .", logout: ".$logout_url; ?>).</p>
	                </td>
	                <td><label class='civibbg_hideSm'>Example Use</label>
	                    <code>[civi-bbg-login-logout-form no_redirect=0]</code></td>
	            </tr>
	            <tr>
	                <td><label class='civibbg_hideSm'>Shortcode</label>
	                    <code>[civi-bbg-user-account]</code></td>
	                <td><label class='civibbg_hideSm'>Description</label>
	                <p>Output a "My Account" form, for the current user, allowing them to view their login details and change their password.</p></td>
	                <td><label class='civibbg_hideSm'>Options</label>
	                <p>(None.)</p></td>
	                <td><label class='civibbg_hideSm'>Example Use</label>
	                <code>[civi-bbg-user-account]</code></td>
	            </tr>
	            <tr>
	                <td><label class='civibbg_hideSm'>Shortcode</label>
	                    <code>[civi-bbg-user-profile]</code></td>
	                <td><label class='civibbg_hideSm'>Description</label>
	                <p>Output a "My Profile" form, for the current user, allowing them to view and edit details such as name and address.<br/>
	                The form shown will depend on the user's CiviCRM contact type (Individual, Organisation, or Household).</p></td>
	                <td><label class='civibbg_hideSm'>Options</label>
	                <p>(None.)</p></td>
	                <td><label class='civibbg_hideSm'>Example Use</label>
	                <code>[civi-bbg-user-profile]</code></td>
	            </tr>
	        </tbody>
	   </table>
	    
	    </div>
	    <?php
	}

}
