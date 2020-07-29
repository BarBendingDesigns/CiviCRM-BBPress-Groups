<?php

/**
 * Search Loop - Single Forum
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

$forum_id = bbp_get_forum_id();
$can_view = bbp_user_can_view_forum(array( 'forum_id' => $forum_id ));

?>

<div class="bbp-forum-header">
	<div class="bbp-meta">
		<span class="bbp-forum-post-date"><?php printf( esc_html__( 'Last updated %s', 'bbpress' ), bbp_get_forum_last_active_time() ); ?></span>
		<?php if($can_view): ?>
		<a href="<?php bbp_forum_permalink(); ?>" class="bbp-forum-permalink">#<?php bbp_forum_id(); ?></a>
		<?php else: ?>
		&nbsp;
		<?php endif; ?>
	</div><!-- .bbp-meta -->

	<div class="bbp-forum-title">

		<?php do_action( 'bbp_theme_before_forum_title' ); ?>
        
        <?php if($can_view): ?>
		<h3><?php esc_html_e( 'Forum:', 'bbpress' ); ?>
		<a href="<?php bbp_forum_permalink(); ?>"><?php bbp_forum_title(); ?></a></h3>
		<?php else: ?>
		<h3><?php esc_html_e( 'Forum:', 'bbpress' ); ?>
		<?php bbp_forum_title(); ?> (private)</h3>
		<?php endif; ?>

		<?php do_action( 'bbp_theme_after_forum_title' ); ?>

	</div><!-- .bbp-forum-title -->
</div><!-- .bbp-forum-header -->

<div id="post-<?php bbp_forum_id(); ?>" <?php bbp_forum_class(); ?>>
	<div class="bbp-forum-content">

		<?php do_action( 'bbp_theme_before_forum_content' ); ?>
        <?php if($can_view): ?>
		<?php bbp_forum_content(); ?>
        <?php else: ?>
		<p>(Private forum)</p>
		<?php endif; ?>
		<?php do_action( 'bbp_theme_after_forum_content' ); ?>

	</div><!-- .bbp-forum-content -->
</div><!-- #post-<?php bbp_forum_id(); ?> -->
