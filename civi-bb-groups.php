<?php

/**
 * @wordpress-plugin
 * Plugin Name:       CiviCRM BBPress Groups
 * Plugin URI:        https://github.com/BarBendingDesigns/CiviCRM-BBPress-Groups
 * Description:       Synchronise selected CiviCRM group with WP users. Specify per-forum access permissions for BBPress. Shortcodes to output "My Account" and "My Profile" forms: My Profile form outputs different profile depending on current user's CiviCRM Contact ype. 
 * Version:           1.0.3
 * Author:            Jasmin Higgs
 * Author URI:        https://barbendingdesigns.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       civi-bb-groups
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Current plugin version.
define( 'CIVI_BB_GROUPS_VERSION', '1.0.3' );

// Define plugin path and URL
if ( ! defined( 'CIVI_BB_GROUPS_PATH' ) ) {
	define( 'CIVI_BB_GROUPS_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'CIVI_BB_GROUPS_URL' ) ) {
	define( 'CIVI_BB_GROUPS_URL', plugin_dir_url( __FILE__ ) );
}

// Runs during
function activate_civi_bb_groups() {
	require_once CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups-activator.php';
	Civi_Bb_Groups_Activator::activate();
}

// Runs during plugin deactivation
function deactivate_civi_bb_groups() {
	require_once CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups-deactivator.php';
	Civi_Bb_Groups_Deactivator::deactivate();
}

// Runs during plugin uninstall
function uninstall_civi_bb_groups() {
	require_once CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups-deactivator.php';
	Civi_Bb_Groups_Deactivator::uninstall();
}


register_activation_hook( __FILE__, 'activate_civi_bb_groups' );
register_deactivation_hook( __FILE__, 'deactivate_civi_bb_groups' );
register_uninstall_hook(__FILE__, 'uninstall_civi_bb_groups');

require CIVI_BB_GROUPS_PATH . 'includes/utilities.php';

// Core plugin class that is used to define internationalization,
require CIVI_BB_GROUPS_PATH . 'includes/class-civi-bb-groups.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 */
function run_civi_bb_groups() {

	$plugin = new Civi_Bb_Groups();
	$plugin->run();

}
run_civi_bb_groups();
