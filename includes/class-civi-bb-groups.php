<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 */

class Civi_Bb_Groups {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'CIVI_BB_GROUPS_VERSION' ) ) {
			$this->version = CIVI_BB_GROUPS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'civi-bb-groups';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 */
	private function load_dependencies() {

		require_once CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups-loader.php';

		require_once CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups-i18n.php';

		require_once CIVI_BB_GROUPS_PATH . 'admin/class-civi-bb-groups-admin.php';
		require_once CIVI_BB_GROUPS_PATH . 'admin/class-civi-bb-groups-sync.php';
		require_once CIVI_BB_GROUPS_PATH . 'admin/class-civi-bb-groups-bbpress-admin.php';
		
		require_once CIVI_BB_GROUPS_PATH . 'public/class-civi-bb-groups-public.php';
		require_once CIVI_BB_GROUPS_PATH . 'public/class-civi-bb-groups-bbpress-public.php';

		$this->loader = new Civi_Bb_Groups_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {

		$plugin_i18n = new Civi_Bb_Groups_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Civi_Bb_Groups_Admin( $this->get_plugin_name(), $this->get_version() );
		$sync_manager = new Civi_Bb_Groups_Sync( $this->get_plugin_name(), $this->get_version() );
		$bb_manager = new Civi_Bb_Groups_BBPress_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'setup_admin_page' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'setup_shortcodes_admin_page' );
		$this->loader->add_action( 'update_option_civi-bb-groups-restrict-tags', $plugin_admin, 'maybe_turn_off_bbp_wp_editor', 10, 3);
		
		$this->loader->add_action( 'admin_menu', $sync_manager, 'setup_sync_admin_page' );
        $this->loader->add_action( 'wp_ajax_civi_bb_do_sync', $sync_manager, 'do_manual_sync' );
		$this->loader->add_action( 'civi_bbg_sync_groups', $sync_manager, 'do_auto_sync');
		$this->loader->add_action( 'update_option_civi-bb-groups-auto-sync', $sync_manager, 'maybe_reschedule_sync', 10, 3 );
		$this->loader->add_action( 'add_option_civi-bb-groups-auto-sync', $sync_manager, 'maybe_schedule_sync', 10, 2 );
		$this->loader->add_action( 'civibbg_remove_civi_filters', $sync_manager, 'remove_filters');
		$this->loader->add_action( 'civibbg_add_civi_filters', $sync_manager, 'add_filters');
		
		$this->loader->add_action( 'bbp_forum_metabox', $bb_manager, 'output_role_restrict_meta_section' );
        $this->loader->add_action( 'bbp_forum_attributes_metabox_save', $bb_manager, 'save_role_restrict_meta' );
        $this->loader->add_filter( 'bbp_admin_forums_column_headers', $bb_manager, 'add_forum_role_restrictions_column');
        $this->loader->add_action( 'bbp_admin_forums_column_data', $bb_manager, 'output_forum_role_restrictions_column', 10, 2 );
        $this->loader->add_filter( 'members_enable_forum_content_permissions', $bb_manager, 'override_other_restrict_content_plugins' );
        $this->loader->add_filter( 'members_enable_topic_content_permissions', $bb_manager, 'override_other_restrict_content_plugins' );
        $this->loader->add_filter( 'members_enable_reply_content_permissions', $bb_manager, 'override_other_restrict_content_plugins' );
        
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 */
	private function define_public_hooks() {

		$plugin_public = new Civi_Bb_Groups_Public( $this->get_plugin_name(), $this->get_version() );
		$bb_public = new Civi_Bb_Groups_BBPress_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
        $this->loader->add_action( 'the_post', $plugin_public, 'maybe_enqueue_civi' );
        //$this->loader->add_action( 'the_post', $plugin_public, 'maybe_process_profile_shortcode');
        $this->loader->add_action( 'the_post', $plugin_public, 'maybe_enqueue_account_script' );
        $this->loader->add_filter( 'the_content', $plugin_public, 'maybe_add_user_links');
        
        $this->loader->add_filter( 'bbp_user_can_view_forum', $bb_public, 'civi_bbg_user_can_view_forum', 10, 3 );
        $this->loader->add_action( 'bbp_register_theme_packages', $bb_public, 'register_plugin_template' );
        $this->loader->add_filter( 'bbp_before_list_forums_parse_args', $bb_public, 'remove_forum_counts' );
        $this->loader->add_filter( 'bbp_before_get_breadcrumb_parse_args', $bb_public, 'adjust_forum_breadcrumbs' );
        $this->loader->add_filter( 'bbp_suppress_private_author_link', $bb_public, 'suppress_author_link', 10, 3);
        $this->loader->add_filter( 'bbp_display_shortcode', $bb_public, 'maybe_return_no_access', 10, 2);
        $this->loader->add_filter( 'bbp_get_user_edit_profile_url', $bb_public, 'modify_user_edit_profile_url', 10, 3);
        $this->loader->add_action( 'bbp_template_after_user_details_menu_items', $bb_public, 'add_back_to_forums_link' );
        $this->loader->add_filter( 'bbp_kses_allowed_tags', $bb_public, 'maybe_retrict_kses_allowed_tags');
        $this->loader->add_filter( 'bbp_make_clickable', $bb_public, 'maybe_dont_make_things_clickable', 10, 2);
        $this->loader->add_filter( 'bbp_get_forum_freshness_link', $bb_public, 'maybe_modify_freshness_link', 10, 6);
        $this->loader->add_filter( 'bbp_forum_subscription_user_ids', $bb_public, 'maybe_modify_forum_subscibers', 10, 3);
        $this->loader->add_filter( 'bbp_topic_subscription_user_ids', $bb_public, 'maybe_modify_topic_subscribers', 10, 3);
        
        
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}

}
