<?php
/**
 * Comments: list + form, minimal and accessible.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
$mr_tu_count = (int) get_comments_number();
?>
<section id="comments" class="comments" aria-labelledby="mrtu-comments-title">
	<div class="comments__inner">
		<h2 class="comments__title" id="mrtu-comments-title">
			<?php
			if ( $mr_tu_count ) {
				/* translators: %d: number of comments. */
				echo esc_html( sprintf( _n( '%d comment', '%d comments', $mr_tu_count, 'mr-tu' ), $mr_tu_count ) );
			} else {
				esc_html_e( 'Comments', 'mr-tu' );
			}
			?>
		</h2>

		<?php if ( have_comments() ) : ?>
			<ol class="comments__list">
				<?php
				wp_list_comments(
					array(
						'style'       => 'ol',
						'short_ping'  => true,
						'avatar_size' => 40,
					)
				);
				?>
			</ol>
			<?php
			the_comments_navigation(
				array(
					'prev_text' => __( 'Older comments', 'mr-tu' ),
					'next_text' => __( 'Newer comments', 'mr-tu' ),
				)
			);
			?>
		<?php endif; ?>

		<?php if ( ! comments_open() && $mr_tu_count ) : ?>
			<p class="comments__closed"><?php esc_html_e( 'Comments are closed.', 'mr-tu' ); ?></p>
		<?php endif; ?>

		<?php
		comment_form(
			array(
				'class_submit'       => 'mrtu-btn mrtu-btn--dark',
				'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
				'title_reply_after'  => '</h3>',
			)
		);
		?>
	</div>
</section>
