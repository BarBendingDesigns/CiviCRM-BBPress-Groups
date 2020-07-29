<?php

/**
 * No Access Feedback Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="forum-private" class="bbp-forum-content">
	<h1 class="entry-title"><?php esc_html_e( 'Private', 'bbpress' ); ?></h1>
	<div class="entry-content">
		<div class="alert alert-warning" role="alert">
            Sorry, you do not have permission to view this forum. Would you like to go back to the <a href='<?php bbp_forums_url(); ?>'>main forums page</a>?
		</div>
	</div>
</div><!-- #forum-private -->
