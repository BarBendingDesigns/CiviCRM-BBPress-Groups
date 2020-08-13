<?php

/**
 * Search
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( bbp_allow_search() ) : ?>

	<div class="bbp-search-form">
		<form role="search" method="get" id="bbp-topic-search-form" class="form-inline">
			<div class="form-group">
				<label class="screen-reader-text hidden" for="ts"><?php esc_html_e( 'Search topics:', 'bbpress' ); ?></label>
				<input type="text" class="form-control" value="<?php bbp_search_terms(); ?>" name="ts" id="ts" />
				<input class="button btn btn-primary" type="submit" id="bbp_search_submit" value="<?php esc_attr_e( 'Search', 'bbpress' ); ?>" />
			</div>
		</form>
	</div>

<?php endif;
