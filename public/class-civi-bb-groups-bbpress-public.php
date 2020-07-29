<?php

/**
 * Handles BBPress-related public/front-end functions
 *
 */

class Civi_Bb_Groups_BBPress_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}
	
	// Register the BBPress templates, used for outputting forum content
	public function register_plugin_template() {
    	bbp_register_template_stack( [$this, 'get_template_path'],  1);
    }
    
    public function get_template_path(){
        return CIVI_BB_GROUPS_PATH . 'public/templates';
    }

    // Filter whether a user is allowed to view a forum
	public function civi_bbg_user_can_view_forum($can, $forum_id, $user_id){
	    // If they're already denied for some other reason, leave it as is
	    if(!$can) return $can;
	    
	    $u = get_user_by('ID', $user_id);
	    if( $u && user_can( $u, 'edit_post', $forum_id ) ) return true; 
	    $user_roles = $u ? $u->roles : [];
	    
	    // Get any ancestor forums: We need to check them all
        $forums = get_post_ancestors($forum_id);
        array_unshift($forums, $forum_id);
        
        // Check the forum and its ancestors: If we find one that the user isn't allowed to view, return false
        foreach($forums as $f){
            $allowed = get_post_meta( $f, 'civi-bb-groups-restrict-to', true );
            $allowed = civibbg_sanitize_thing($allowed, 'array');
            
            if(!empty($allowed)){
    	        $has_roles = array_intersect($allowed, $user_roles);
    	        if(sizeof($has_roles) === 0) return false;
    	    }
        }
	    
	    // If we got this far, there are no restrictions that we need to worry about
	    return true;
	    
	}
	
	// Check whether a forum is restricted by WP roles
	private function forum_is_restricted($forum_id, $check_ancestors = true){
	    if($check_ancestors){
    	    $forums = get_post_ancestors($forum_id);
            array_unshift($forums, $forum_id);
	    } else {
	        $forums = [$forum_id];
	    }
	    
	    foreach($forums as $f){
	        $restricted_to = get_post_meta( $f, 'civi-bb-groups-restrict-to', true );
	        if(!empty($restricted_to)) return true;
	    }
	    return false;
	}
	
	// Filter topic and reply counts in forum list
	public function remove_forum_counts($args){
	    $args['show_topic_count'] = false;
        $args['show_reply_count'] = false;
        $args['count_sep'] = '';
        $args['sep'] = '';
        return $args;
	}
	
	// Filter forum breadcrumbs
	public function adjust_forum_breadcrumbs($args){
	    $args['include_home'] = false;
	    return $args;
	}

    // Only let moderators see author links
    public function suppress_author_link($retval, $author_link, $args){
        return current_user_can('moderate') ? $retval : strip_tags($retval, '<span><div>');
    }
    
    // If the user is trying to edit their own profile, and a My Account page is
    // set in plugin settings, return the URL of that page instead of the default
    // BBPress profile edit page
    public function modify_user_edit_profile_url($url, $user_id, $user_nicename){
        $current_user_id = get_current_user_id();
        if($current_user_id !== $user_id){
            return $url;
        }
        
        $p = absint(get_option('civi-bb-groups-account-page', 0));
	    return $p ? get_permalink($p) : $url;
        
    }
    
    // Fetch a link element for the top-level forums page
    public function add_back_to_forums_link(){
        $forums_url = bbp_get_forums_url();
        echo "<div class='civibbg-links'><a class='button btn btn-primary' href='".esc_url($forums_url)."'>Return to forums</a></div>";
    }
    
    // Some BBPress shortcodes will return empty output, if the user is not allowed to view the current forum:
    // Return a no-access message instead
    public function maybe_return_no_access($output, $query_name){
        switch($query_name){
            case 'bbp_single_forum':
            case 'bbp_single_topic':
            case 'bbp_topic_form':
            case 'bbp_single_reply':
                
                if(!empty($output)) return $output;
                
                $forums_home = esc_url( bbp_get_forums_url() );
                $content = "<div id='forum-private' class='bbp-forum-content'>
	<h1 class='entry-title'>Private</h1>
	<div class='entry-content'>
		<div class='alert alert-warning' role='alert'>
            Sorry, you do not have permission to view this forum. Would you like to go back to the <a href='$forums_home'>main forums page</a>?
		</div>
	</div>
</div><!-- #forum-private -->";
                return $content;
                
                break;
            default: 
                return $output;
        }
    }
	
	// Tighten up allowed tags in forum/topic/reply posts by non-Admins
	public function maybe_retrict_kses_allowed_tags($allowed){
	    $restrict = get_option('civi-bb-groups-restrict-tags', false);
	    if($restrict){
	        unset($allowed['a']);
	        unset($allowed['img']);
	    } 
	    
	    return $allowed;
	}
	
	// Override BBPress's "make clickable" functionality, if the content 
	// contains disallowed tags
	public function maybe_dont_make_things_clickable($ret, $text){
	    $restrict = get_option('civi-bb-groups-restrict-tags', false);
	    
	    if(!$restrict) return $ret;
	    
	    $allowed = array_keys(bbp_kses_allowed_tags());
	    $allowed = "<" . implode("><", $allowed) . ">";
	    
	    return strip_tags($ret, $allowed);
	}
	
	// Override BBPress's freshness link: Don't return a link if the user isn't allowed to access the link
	public function maybe_modify_freshness_link($anchor, $forum_id, $time_since, $link_url, $title, $active_id ){

	    $user_id = get_current_user_id();
	    $can_view = $this->civi_bbg_user_can_view_forum(true, $forum_id, $user_id);
	    
	    if($can_view) return $anchor;
	    
	    if ( ! empty( $time_since ) && ! empty( $link_url ) ) {
			$anchor = esc_html( $time_since );
		} else {
			$anchor = esc_html__( 'No Topics', 'bbpress' );
		}
		return $anchor;
	}
	
    // Exclude users from a forum's subscribers, if they're no longer allowed to view the forum
	public function maybe_modify_forum_subscibers($user_ids, $topic_id, $forum_id){
	    $filtered_users = [];
	    foreach($user_ids as $user_id){
	        if($this->civi_bbg_user_can_view_forum(true, $forum_id, $user_id)){
	            $filtered_users[] = $user_id;
	        }
	    }
	    return $filtered_users;
	}
	
    // Exclude users from a topic's subscribers, if they're no longer allowed to view the parent forum
	public function maybe_modify_topic_subscribers($user_ids, $reply_id, $topic_id){
	    $forum_id = bbp_get_topic_forum_id( $topic_id );
	    return $this->maybe_modify_forum_subscibers($user_ids, $topic_id, $forum_id);
	}
	

}
