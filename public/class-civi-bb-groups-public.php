<?php

/**
 * The public-facing/front end functionality of the plugin.
 *
 */

class Civi_Bb_Groups_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

    // Enqueue required CSS
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, CIVI_BB_GROUPS_URL . 'public/css/civi-bb-groups-public.css', array(), $this->version );
		wp_enqueue_style( 'civi-bbpress', CIVI_BB_GROUPS_URL . 'public/css/civi-bbpress.css', array(), $this->version );

	}

    // Enqueue required JavaScript
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, CIVI_BB_GROUPS_URL . 'public/js/civi-bb-groups-public.js', array( 'jquery' ), $this->version, false );

	}
	
	// Register shortcodes
	public function register_shortcodes(){
	    add_shortcode('civi-bbg-login-form', [$this, 'login_form']);
	    add_shortcode('civi-bbg-logout-link', [$this, 'logout_link']);
	    add_shortcode('civi-bbg-login-logout-form', [$this, 'login_logout_form']);
	    add_shortcode('civi-bbg-user-profile', [$this, 'user_profile']);
	    add_shortcode('civi-bbg-user-account', [$this, 'user_account']);
	}
	
	// Register and localize the script for the [civi-bbg-user-account] shortcode,
	// And handle user account update if requested
	public function register_user_account_script_and_localize_result(){
	    wp_register_script( 'civibbg_account_shortcode', CIVI_BB_GROUPS_URL . 'public/js/civi-bb-groups-account-shortcode.js', array( 'jquery' ), $this->version, false );
	    
	    if(isset($_GET['civibbg_updated'])){
	        $result = array(
    	        'submitted' => true,
    	        'success' => true,
    	        'message' => 'Account details saved',
    	    );
    	    
	    } elseif(isset($_POST['civibbg'])){
            $data = $this->frontend_update_user_account();
            
            // We need to redirect if the password has been updated, so that nonces will be generated correctly
            if(!empty($data[0]) && !empty($data[2])){
                $args = array( 'civibbg_updated' => 'true' );
        		$current_url = $this->get_current_url();
        		$redirect = add_query_arg( $args, $current_url );
                wp_safe_redirect( $redirect, 302 );
                
            } else {
                $result = array(
                    'submitted' => true,
                    'success' => $data[0],
                    'message' => $data[1],
                    
                );
            }
	    } else {
	        $result = array(
    	        'submitted' => false,
    	        'success' => false,
    	        'message' => '',
    	    );
	    }
	    wp_localize_script( 'civibbg_account_shortcode', 'civibbg_account_result', $result );
	    
	}
	
	// Enqueue the script for the [civi-bbg-user-account] shortcode
	public function enqueue_user_account_script(){
	    wp_enqueue_script( 'civibbg_account_shortcode' );
	}
	
	// If post content contains the [civi-bbg-user-account] shortcode, enqueue the required script
	public function maybe_enqueue_account_script($p){
	    if(has_shortcode($p->post_content, 'civi-bbg-user-account')){
            add_action( 'wp_enqueue_scripts', [$this, 'enqueue_user_account_script'] );
	    }
	}
	
	/**
	 * Alternative to $this->maybe_process_profile_shortcode()
	 * If this option is used, we're not substituting shortcodes within the post content,
	 * However it only works if there are no other CiviCRM shortcodes on the page,
	 * and we may be enqueueing civi's front_end_page_load needlessly if non-civi shortcodes have been used
	 */
	public function maybe_enqueue_civi($p){
	    if(!is_admin() && civibbg_civi_api3_is_on() && has_shortcode($p->post_content, 'civi-bbg-user-profile')){
	        
	        // Only display the user account if the page/post has no CiviCRM shortcodes
	        if(has_shortcode($p->post_content, 'civicrm')){
	            $p->post_content = preg_replace('/(\[civi-bbg-user-profile[^\]]*\])/i', '', $p->post_content);
	            
	        } else {
	            add_action( 'wp_enqueue_scripts', array( civi_wp(), 'front_end_page_load' ), 100 );
	        }
	    }
	}
	
	/**
	 * Preg_replace [civi-bbg-user-profile] shortcode in post content with appropriate shortcode 
	 * according to whether the user is logged in and what CiviCRM contact type they are.
	 * This function has to be hooked into the_post action, because CiviCRM preprocesses shortcodes quite early 
	 */
	public function maybe_process_profile_shortcode($p){
	    if(!is_admin() && civibbg_civi_api3_is_on() && has_shortcode($p->post_content, 'civi-bbg-user-profile')){
	        $output = $this->user_profile(['unapplied' => true]);
	        $p->post_content = preg_replace('/(\[civi-bbg-user-profile[^\]]*\])/i', $output, $p->post_content);
	    }
	}
	
	// Handle a user account update request, from the [civi-bbg-user-account] shortcode form
	public function frontend_update_user_account(){
	    
	    if(!isset($_POST['civibbg_user_account_nonce_field']) || !wp_verify_nonce($_POST['civibbg_user_account_nonce_field'], 'civibbg_user_account_action')){
	        return [false, 'Security verification failed', false];
	    }
	    
	    $user_data = [];
	    
	    $data = empty($_POST['civibbg']) ? [] : $_POST['civibbg'];
	    
        $fields = array(
            'ID' => 'int',
            'display_name' => 'text',
            'user_email' => 'email'
        );
        foreach($fields as $k=>$t){
            $f = isset($data[$k]) ? civibbg_sanitize_thing($data[$k], $t) : false;
            if(empty($f)){
                return [false, 'Required field is invalid or empty', false];
            } else {
                $user_data[$k] = $f;
            }
        }

	    if(empty($user_data['ID']) || !current_user_can('edit_user', $data['ID'])){
	        return [false, "Sorry, you're not permitted to update this user account", false];
	    } else {
	        $u = get_user_by( 'ID', $user_data['ID'] );
	        if(empty($u)) return [false, "You seem to be trying to update a user who doesn't exist", false];
	    }
	    
	    $e = email_exists($user_data['user_email']);
	    if($e && $e !== $u->ID) return [false, 'Email "'.$user_data['user_email'].'" is already in use by another user', false];
	    
	    $password_change = false;
	    if(!empty($data['new_password'])){
	        if(empty($data['old_password']) || !wp_check_password( $data['old_password'], $u->data->user_pass, $u->ID )){
	            return [false, "Old password is incorrect", false];
	        } elseif(false !== strpos( wp_unslash( $data['new_password'] ), '\\' ) ){
	            return [false, 'Passwords may not contain the character "\\"', false];
	        } else {
	            $user_data['user_pass'] = $data['new_password'];
	            $password_change = true;
	        }

	    }
	    
        do_action('civibbg_remove_civi_filters');
	    $updated = wp_update_user($user_data);
	    do_action('civibbg_add_civi_filters');
	    
	    if(is_wp_error($updated)){
	        return [false, "Failed to update user details", false];
	    } else {
	        $success = true;
	        $message = "Account details saved";
	        
	        if($u->data->user_email !== $user_data['user_email'] && civibbg_civi_api3_is_on()){
	            
	            try{
	                
	                $domain_id = CRM_Core_Config::domainID();
	                $uf_match = civicrm_api3('UFMatch', 'get', [
                      'sequential' => 1,
                      'return' => ["id", "contact_id"],
                      'uf_id' => $user_data['ID'],
                      'domain_id' => $domain_id,
                      'api.Contact.get' => [],
                    ]);
                    
                    if(empty($uf_match['values']) || empty($uf_match['values'][0]['api.Contact.get']) || empty($uf_match['values'][0]['api.Contact.get']['values']) ){
                        $success = false;
                        $message = 'Updated user account but failed to update details in CiviCRM';
                    } else {
                        $uf_update = civicrm_api3('UFMatch', 'create', [
                            'uf_id' => $user_data['ID'],
                            'uf_name' => $user_data['user_email'],
                            'contact_id' => $uf_match['values'][0]['contact_id'],
                            'id' => $uf_match['values'][0]['id'], // Must specify record ID to update instead of create
                        ]);
                        
                        $email_update = civicrm_api3('Email', 'create', [
                          'contact_id' => $uf_match['values'][0]['contact_id'],
                          'email' => $user_data['user_email'],
                          'id' => $uf_match['values'][0]['api.Contact.get']['values'][0]['email_id'],
                          'is_primary' => 1,
                        ]);
                        
                    }

                } catch(Exception $e){
                    $success = false;
                    $message = 'Updated user account but failed to update details in CiviCRM';
                }
	           
	            
	        }
	        
	        return [$success, $message, $password_change];
	    }

	}
	

	// Return the user account form (for [civi-bbg-user-account] shortcode)
	public function user_account($atts){
	    $atts = shortcode_atts( array(
    
        ), $atts, 'civi-bbg-user-account' );
        
        if(!is_user_logged_in()){
            return '<h2>Login</h2>' . (empty($atts['unapplied']) ?  $this->get_login_form(1) : '[civi-bbg-login-form no-redirect=1]');
        }
        
        $user_id = get_current_user_id();
        $u = get_user_by('id', $user_id);
        
        $form_fields = array(
            'user_login' => array(
                'name' => 'Username', 
                'type' => 'text',
                'readonly' => true,
            ),
            'user_email' => array(
                'name' => 'Email', 
                'type' => 'email',
                'readonly' => false,
                'description' => 'You can log in using either your username or this email address.',
            ),
            'display_name' => array(
                'name' => 'Display name', 
                'type' => 'text',
                'readonly' => false,
                'description' => 'If you post in the forums on this site, this is how your name will be displayed.',
            ),
        );
        
        foreach($form_fields as $k=>$field){
            $form_fields[$k]['value'] = civibbg_sanitize_thing($u->$k, $field['type']);
        }
        
        $password_fields = array(
            'old_password' => 'Old password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm new password',
        );
        
        $form = "<form id='civibbg-account-form' method='post' action='".esc_url($this->get_current_url())."' ><div id='civibbg-account-result'></div>";
        
        foreach($form_fields as $k=>$field){
            $extra = $field['readonly'] ? "readonly" : "name='civibbg[$k]' required";
            
            $description = empty($field['description']) ? '' : "<p class='help-block form-text text-muted'>".$field['description']."</p>";
            $form .= "<div class='form-group'>
                <label for='civibbg[$k]'>".$field['name']."</label>
                <input class='form-control' type='".$field['type']."' value='".$field['value']."' $extra/>
                $description
                </div>";
        }
        
        $form .= "<p><em>Fill in the fields below to reset your password. Leave blank if you don't want to change your password.</em></p>";
        
        foreach($password_fields as $k=>$name){
            $form .= "<div class='form-group'>
                <label for='civibbg[$k]'>$name</label>
                <input class='form-control' type='password' name='civibbg[$k]' autocomplete='new-password'/>
                <p class='civibbg_valid'></p>
                </div>";
        }
        
        $form .= "<input type='hidden' name='civibbg[ID]' value='$u->ID'/>";
        $form .= wp_nonce_field( 'civibbg_user_account_action', 'civibbg_user_account_nonce_field', true, false );
        $form .= "<input type='button' class='button btn btn-primary civibbg-button' value='Save' id='civibbg-account-submit' name='civibbg[submitted]' />";
        $form .= "</form>";
        
        return $form;
	}

	/**
	 * Return the user profile form (for [civi-bbg-user-profile] shortcode)
	 */
	public function user_profile($atts){
	    if(!is_user_logged_in()){ 
	        return '<h2>Login</h2>' . (empty($atts['unapplied']) ?  $this->get_login_form(1) : '[civi-bbg-login-form no-redirect=1]');
	    }
	    
	    if(!civibbg_civi_api3_is_on()) return '';
	    
	    $user_id = get_current_user_id();
	    
	    try{
	        $domain_id = CRM_Core_Config::domainID();
	        
            $user_data = civicrm_api3('UFMatch', 'get', [
              'sequential' => 1,
              'return' => ["contact_id"],
              'uf_id' => $user_id,
              'domain_id' => $domain_id,
              'api.Contact.get' => ['return' => ["contact_type"]],
            ]);
            
            if($user_data['count'] === 0) return '';
            
            $contact_type = $user_data['values'][0]['api.Contact.get']['values'][0]['contact_type'];
            
            switch($contact_type){
                case 'Individual':
                case 'Organization':
                case 'Household':
                    $key = 'civi-bb-groups-'.strtolower($contact_type).'-profile';
                    $shortcode = civibbg_sanitize_thing(get_option($key, ''), 'shortcode');
                    
                    $user_profile = "<div id='civibbg-user-profile'>"
                        . (empty($atts['unapplied']) ? apply_shortcodes($shortcode) : $shortcode) 
                        . "</div>";
                    
                    return $user_profile;

                    break;
                default:
                    return '';
            }

	    } catch(Exception $e){
	        return 'Unable to load form: An error occurred.';
	    }

	}
	
	// Return links to the My Account and My Profile pages
	public function user_links($current = 0){
	    if(!is_user_logged_in()) return;
	    
	    $links = [];
	    
	    $pages = array(
	        'My Account' => 'civi-bb-groups-account-page',
	        'My Profile' => 'civi-bb-groups-profile-page'
	    );
	    
	    foreach($pages as $n=>$o){
	        $p = absint(get_option($o, 0));
	        if($p){
	            $link = get_permalink($p);
	            if($link){
	                $class = $current === $p ? 'button btn btn-default' : 'button btn btn-primary';
	                $links[] = "<a href='".esc_url($link)."' class='$class'>$n</a>";
	            }
	        }
	    }

	    $links[] = $this->get_logout_link();
	    
	    return "<p class='civibbg-links'>" . implode("", $links) . "</p>";
	   
	}
	
	// If the current page is the My Account or My Profile page, add 
	// My Account, My Profile and Logout links to the top of the page content
	public function maybe_add_user_links($content){
	    $account = absint(get_option('civi-bb-groups-account-page', 0));
	    if($account && is_page($account)){
	        $content = $this->user_links($account) . $content;
	    } else {
	        $profile = absint(get_option('civi-bb-groups-profile-page', 0));
	        if($profile && is_page($profile)) $content = $this->user_links($profile) . $content;
	    }
	    
	    return $content;
	}
	
	// Get the URL of the current page
	private function get_current_url(){
	    global $wp;
        return home_url( $wp->request );
	}
	
	// Get the URL that the logout form should redirect to
	private function get_logout_redirect(){
	    $logout_redirect = esc_url_raw(get_option('civi-bb-groups-logout-redirect', ''));
	    if(empty($logout_redirect)){
            $logout_redirect = $this->get_current_url();
	    }
	    return $logout_redirect;
	}
	
	// Get the URL that the login form should redirect to
	private function get_login_redirect(){
	    $login_redirect = esc_url_raw(get_option('civi-bb-groups-login-redirect', ''));
	    if(empty($login_redirect)){
            $login_redirect = $this->get_current_url();
	    }
	    return $login_redirect;
	}
	
	// Return the login form HTML
	private function get_login_form($no_redirect = false){
	    $login_redirect = $no_redirect ? $this->get_current_url() : $this->get_login_redirect();
	    $login_form = wp_login_form( [ 'echo' => false, 'redirect' => $login_redirect, 'form_id' => 'civibbg-login-form', 'remember' => true ] );
	    $login_form .= "<p class='civibbg-lost-password-link'>Lost your password? You can <a href='" . wp_lostpassword_url( $login_redirect ) . "'>reset it here</a></p>";
	    return $login_form;
	}
	
	// Return the logout link HTML
	private function get_logout_link($button = true, $paragraph = false, $no_redirect = false){
	    $logout_redirect = $no_redirect ? $this->get_current_url() : $this->get_logout_redirect();
	    $logout_url = wp_logout_url( $logout_redirect );
	    
	    $link_class = $button ? 'button btn btn-primary civibbg-button civibbg-logout' : 'civibbg-link civibbg-logout';
	    
	    $link = "<a class='$link_class' href='$logout_url'>Logout</a>";
	    if($paragraph) $link = "<p class='civibbg-logout-link'>$link</p>";
	    return $link;

	}
	
	// Return the login form (for [civi-bbg-login-form] shortcode)
	public function login_form($atts){
	    if(is_user_logged_in()) return;
	    
	    $atts = shortcode_atts( array(
            'no_redirect' => 0
        ), $atts, 'civi-bbg-login-form' );
        
	    return $this->get_login_form($atts['no_redirect']);
	}
	
	// Return the logout link (for [civi-bbg-logout-link] shortcode)
	public function logout_link($atts){
	    if(!is_user_logged_in()) return;
	    
	    $atts = shortcode_atts( array(
	        'as_button' => 1,
            'in_paragraph' => 0,
            'no_redirect' => 0
        ), $atts, 'civi-bbg-logout-link' );
	    
	    return $this->get_logout_link($atts['as_button'], $atts['in_paragraph'], $atts['no_redirect']);
	    
	}
	
	// Return the login form or logout link as appropriate (for [[civi-bbg-login-logout-form] shortcode)
    public function login_logout_form($atts){
        $atts = shortcode_atts( array(
            'no_redirect' => 0
        ), $atts, 'civi-bbg-login-logout-form' );
        
        if(is_user_logged_in()){
            return $this->get_logout_link(true, true, $atts['no_redirect']);
        } else {
            return $this->get_login_form($atts['no_redirect']);
        }

    }
    
}
