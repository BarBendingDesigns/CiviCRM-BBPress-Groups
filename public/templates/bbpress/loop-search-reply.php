<?php

/**
 * Search Loop - Single Reply
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

$topic_id = bbp_get_reply_topic_id();
$forum_id = bbp_get_topic_forum_id($topic_id);
$can_view = bbp_user_can_view_forum(array( 'forum_id' => $forum_id ));

?>

<div class="bbp-reply-header">
	<div class="bbp-meta">
		<span class="bbp-reply-post-date"><?php bbp_reply_post_date(); ?></span>
		<?php if($can_view): ?>
		<a href="<?php bbp_reply_url(); ?>" class="bbp-reply-permalink">#<?php bbp_reply_id(); ?></a>
		<?php else: ?>
		&nbsp;
		<?php endif; ?>
	</div><!-- .bbp-meta -->

	<div class="bbp-reply-title">
		<h3><?php esc_html_e( 'In reply to: ', 'bbpress' ); ?>
		<?php if($can_view): ?>
		<a class="bbp-topic-permalink" href="<?php bbp_topic_permalink( $topic_id ); ?>"><?php bbp_topic_title( $topic_id ); ?></a>
		<?php else: ?>
		Topic in private forum
		<?php endif; ?>
		</h3>
	</div><!-- .bbp-reply-title -->
</div><!-- .bbp-reply-header -->

<div id="post-<?php bbp_reply_id(); ?>" <?php bbp_reply_class(); ?>>
	<div class="bbp-reply-author">

		<?php do_action( 'bbp_theme_before_reply_author_details' ); ?>

		<?php bbp_reply_author_link( array( 'show_role' => true ) ); ?>

		<?php if ( bbp_is_user_keymaster() ) : ?>

			<?php do_action( 'bbp_theme_before_reply_author_admin_details' ); ?>

			<div class="bbp-reply-ip"><?php bbp_author_ip( bbp_get_reply_id() ); ?></div>

			<?php do_action( 'bbp_theme_after_reply_author_admin_details' ); ?>

		<?php endif; ?>

		<?php do_action( 'bbp_theme_after_reply_author_details' ); ?>

	</div><!-- .bbp-reply-author -->

	<div class="bbp-reply-content">

		<?php do_action( 'bbp_theme_before_reply_content' ); ?>
		
        <?php if($can_view): ?>
		<?php bbp_reply_content(); ?>
		<?php else: ?>
		<p>(Reply hidden)</p>
		<?php endif; ?>

		<?php do_action( 'bbp_theme_after_reply_content' ); ?>

	</div><!-- .bbp-reply-content -->
</div><!-- #post-<?php bbp_reply_id(); ?> -->

