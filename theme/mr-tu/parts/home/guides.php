<?php
/**
 * Language-filtered editorial links on every home page.
 *
 * @package MrTu\\Theme
 */

defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'mrtu_current_language' ) ) {
	return;
}
$mr_tu_lang = mrtu_current_language();
$mr_tu_labels = array(
	'vi' => array( 'Cẩm nang đi lại Hà Nội', 'Danh mục hướng dẫn', 'Bài viết hữu ích', 'Xem cẩm nang' ),
	'en' => array( 'Hanoi transport guides', 'Guide categories', 'Useful reading', 'View the guides' ),
	'de' => array( 'Ratgeber für Hanoi', 'Themen', 'Praktische Artikel', 'Alle Ratgeber' ),
	'fr' => array( 'Guides pour Hanoï', 'Rubriques pratiques', 'Articles utiles', 'Tous les guides' ),
	'es' => array( 'Guías para Hanói', 'Categorías prácticas', 'Lecturas útiles', 'Ver las guías' ),
);
if ( ! isset( $mr_tu_labels[ $mr_tu_lang ] ) ) {
	return;
}
$mr_tu_words = $mr_tu_labels[ $mr_tu_lang ];
$mr_tu_terms = get_terms(
	array(
		'taxonomy' => 'category',
		'hide_empty' => true,
		'meta_key' => '_mrtu_lang',
		'meta_value' => $mr_tu_lang,
		'number' => 12,
	)
);
$mr_tu_posts = get_posts(
	array(
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => 3,
		'orderby' => 'date',
		'order' => 'DESC',
		'suppress_filters' => false,
		'tax_query' => array(
			array(
				'taxonomy' => 'mrtu_language',
				'field' => 'slug',
				'terms' => $mr_tu_lang,
			),
		),
	)
);
?>
<section class="mrtu-section mrtu-home-guides" aria-labelledby="mrtu-home-guides-title">
	<div class="mrtu-home-guides__inner">
		<h2 id="mrtu-home-guides-title"><?php echo esc_html( $mr_tu_words[0] ); ?></h2>
		<?php if ( ! is_wp_error( $mr_tu_terms ) && $mr_tu_terms ) : ?>
			<nav class="mrtu-home-categories" aria-label="<?php echo esc_attr( $mr_tu_words[1] ); ?>">
				<ul>
					<?php foreach ( $mr_tu_terms as $mr_tu_term ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $mr_tu_term ) ); ?>"><?php echo esc_html( $mr_tu_term->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
		<?php if ( $mr_tu_posts ) : ?>
			<div class="card-grid" aria-label="<?php echo esc_attr( $mr_tu_words[2] ); ?>">
				<?php
				foreach ( $mr_tu_posts as $mr_tu_item ) {
					get_template_part( 'parts/components/post-card', null, array(
							'post' => $mr_tu_item,
							'heading' => 'h3',
						) );
				}
				?>
			</div>
		<?php endif; ?>
		<p><a class="mrtu-btn mrtu-btn--secondary" href="<?php echo esc_url( mrtu_role_url( 'blog', $mr_tu_lang ) ); ?>"><?php echo esc_html( $mr_tu_words[3] ); ?></a></p>
	</div>
</section>
