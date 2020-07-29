<?php

/**
 * Single User Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php do_action( 'bbp_template_notices' ); ?>

	<?php do_action( 'bbp_template_before_user_wrapper' ); ?>

    <?php if ( current_user_can( 'moderate') || current_user_can( 'edit_user', bbp_get_displayed_user_id() ) ): ?>
    
	<div id="bbp-user-wrapper">

		<?php bbp_get_template_part( 'user', 'details' ); ?>
        
		<div id="bbp-user-body">
			<?php if ( bbp_is_favorites()               ) bbp_get_template_part( 'user', 'favorites'       ); ?>
			<?php if ( bbp_is_subscriptions()           ) bbp_get_template_part( 'user', 'subscriptions'   ); ?>
			<?php if ( bbp_is_single_user_engagements() ) bbp_get_template_part( 'user', 'engagements'     ); ?>
			<?php if ( bbp_is_single_user_topics()      ) bbp_get_template_part( 'user', 'topics-created'  ); ?>
			<?php if ( bbp_is_single_user_replies()     ) bbp_get_template_part( 'user', 'replies-created' ); ?>
			<?php if ( bbp_is_single_user_edit()        ) bbp_get_template_part( 'form', 'user-edit'       ); ?>
			<?php if ( bbp_is_single_user_profile()     ) bbp_get_template_part( 'user', 'profile'         ); ?>
		</div>
	</div>
	
	<?php else: ?>
	
	<div class='alert alert-warning'>
	    Sorry, you do not have permission to view this person's details.
	    Would you like to go back to the <a href='<?php bbp_forums_url(); ?>'>main forums page</a>?
	</div>
	
	<?php endif; ?>

	<?php do_action( 'bbp_template_after_user_wrapper' ); ?>

</div>
