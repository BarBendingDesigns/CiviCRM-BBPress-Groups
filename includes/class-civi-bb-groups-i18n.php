<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 */
class Civi_Bb_Groups_i18n {

	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'civi-bb-groups',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
