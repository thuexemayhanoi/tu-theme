<?php
/**
 * Theme settings ("Giao diện"): hero/story images, motion, view transitions, default colour mode.
 *
 * Stored in the `mrtu_theme_settings` option. The screen joins the plugin's "Mr Tu" admin menu
 * when its registry exists (filter `mrtu_admin_pages`), otherwise it lives under Appearance.
 * Saved through admin-post.php with a nonce and the `edit_theme_options` capability.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

const MR_TU_SETTINGS_OPTION = 'mrtu_theme_settings';
const MR_TU_SETTINGS_SLUG   = 'mrtu-theme';
const MR_TU_SETTINGS_ACTION = 'mr_tu_save_theme_settings';

/**
 * Default settings.
 *
 * @return array
 */
function mr_tu_settings_defaults() {
	return array(
		'hero_image'       => 0,
		'story_image'      => 0,
		'reveal'           => true,
		'view_transitions' => true,
		'color_mode'       => 'system',
	);
}

/**
 * All settings merged with defaults.
 *
 * @return array
 */
function mr_tu_settings() {
	$saved = get_option( MR_TU_SETTINGS_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), mr_tu_settings_defaults() );
}

/**
 * One setting value.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function mr_tu_setting( $key ) {
	$settings = mr_tu_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/**
 * Sanitizes raw settings input.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function mr_tu_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = mr_tu_settings_defaults();
	$clean    = array();

	foreach ( array( 'hero_image', 'story_image' ) as $key ) {
		$id            = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		$clean[ $key ] = ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
	}
	foreach ( array( 'reveal', 'view_transitions' ) as $key ) {
		$clean[ $key ] = ! empty( $input[ $key ] ) && '0' !== $input[ $key ];
	}
	$mode                = isset( $input['color_mode'] ) ? sanitize_key( $input['color_mode'] ) : $defaults['color_mode'];
	$clean['color_mode'] = in_array( $mode, array( 'system', 'light', 'dark' ), true ) ? $mode : $defaults['color_mode'];

	return $clean;
}

/**
 * Registers the settings screen in the right place.
 */
function mr_tu_settings_bootstrap() {
	if ( ! is_admin() ) {
		return;
	}
	if ( class_exists( '\MrTu\Multilang\Admin\Admin' ) ) {
		add_filter( 'mrtu_admin_pages', 'mr_tu_settings_admin_page' );
	} else {
		add_action( 'admin_menu', 'mr_tu_settings_theme_page' );
	}
	add_action( 'admin_post_' . MR_TU_SETTINGS_ACTION, 'mr_tu_settings_save' );
	add_action( 'admin_enqueue_scripts', 'mr_tu_settings_enqueue' );
}
add_action( 'after_setup_theme', 'mr_tu_settings_bootstrap' );

/**
 * Adds the screen to the plugin's "Mr Tu" admin menu registry.
 *
 * @param array $pages Registered pages.
 * @return array
 */
function mr_tu_settings_admin_page( $pages ) {
	$pages   = is_array( $pages ) ? $pages : array();
	$pages[] = array(
		'slug'       => MR_TU_SETTINGS_SLUG,
		'title'      => __( 'Theme appearance', 'mr-tu' ),
		'menu_title' => __( 'Appearance', 'mr-tu' ),
		'capability' => 'edit_theme_options',
		'callback'   => 'mr_tu_settings_render',
		'position'   => 80,
	);
	return $pages;
}

/**
 * Fallback: screen under Appearance when the plugin is inactive.
 */
function mr_tu_settings_theme_page() {
	add_theme_page(
		__( 'Mr Tu theme', 'mr-tu' ),
		__( 'Mr Tu theme', 'mr-tu' ),
		'edit_theme_options',
		MR_TU_SETTINGS_SLUG,
		'mr_tu_settings_render'
	);
}

/**
 * Loads the media modal and the small picker script on the settings screen only.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function mr_tu_settings_enqueue( $hook_suffix ) {
	if ( '_page_' . MR_TU_SETTINGS_SLUG !== substr( (string) $hook_suffix, -strlen( '_page_' . MR_TU_SETTINGS_SLUG ) ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'mr-tu-admin-settings', MR_TU_URI . '/assets/css/admin-settings.css', array(), MR_TU_VERSION );
	wp_enqueue_script( 'mr-tu-admin-settings', MR_TU_URI . '/assets/js/admin-settings.js', array( 'media-editor' ), MR_TU_VERSION, true );
	wp_localize_script(
		'mr-tu-admin-settings',
		'mrTuSettingsL10n',
		array(
			'title'  => __( 'Choose an image', 'mr-tu' ),
			'button' => __( 'Use this image', 'mr-tu' ),
		)
	);
}

/**
 * Renders one image picker row.
 *
 * @param string $key   Setting key.
 * @param string $label Field label.
 * @param string $help  Help text.
 * @param int    $value Attachment ID.
 */
function mr_tu_settings_image_field( $key, $label, $help, $value ) {
	$field_id = 'mr-tu-' . str_replace( '_', '-', $key );
	$preview  = $value ? wp_get_attachment_image( $value, 'medium', false, array( 'class' => 'mr-tu-media__img' ) ) : '';
	?>
	<div class="mr-tu-field mr-tu-media" data-mr-tu-media>
		<p class="mr-tu-field__label" id="<?php echo esc_attr( $field_id ); ?>-label"><?php echo esc_html( $label ); ?></p>
		<div class="mr-tu-media__preview" data-mr-tu-media-preview aria-live="polite">
			<?php
			if ( $preview ) {
				echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
			} else {
				echo '<span class="mr-tu-media__empty">' . esc_html__( 'No image — a designed graphic is shown instead.', 'mr-tu' ) . '</span>';
			}
			?>
		</div>
		<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" data-mr-tu-media-input>
		<div class="mr-tu-media__actions">
			<button type="button" class="button" data-mr-tu-media-choose aria-describedby="<?php echo esc_attr( $field_id ); ?>-label"><?php esc_html_e( 'Choose image', 'mr-tu' ); ?></button>
			<button type="button" class="button-link mr-tu-media__remove" data-mr-tu-media-remove<?php echo $value ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'mr-tu' ); ?></button>
		</div>
		<p class="description"><?php echo esc_html( $help ); ?></p>
	</div>
	<?php
}

/**
 * Renders the settings screen.
 */
function mr_tu_settings_render() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the theme settings.', 'mr-tu' ), 403 );
	}
	$settings = mr_tu_settings();
	$flash    = get_transient( 'mr_tu_settings_saved_' . get_current_user_id() );
	if ( $flash ) {
		delete_transient( 'mr_tu_settings_saved_' . get_current_user_id() );
	}
	$modes = array(
		'system' => __( 'Follow the device (System)', 'mr-tu' ),
		'light'  => __( 'Light', 'mr-tu' ),
		'dark'   => __( 'Dark', 'mr-tu' ),
	);
	?>
	<div class="wrap mrtu-admin mr-tu-settings">
		<h1><?php esc_html_e( 'Theme appearance', 'mr-tu' ); ?></h1>
		<p class="mr-tu-settings__intro"><?php esc_html_e( 'Photos and motion for the Mr Tu theme. Business details, prices and languages are edited in the Mr Tu plugin screens.', 'mr-tu' ); ?></p>
		<?php if ( $flash ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'mr-tu' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( MR_TU_SETTINGS_ACTION, '_mr_tu_nonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( MR_TU_SETTINGS_ACTION ); ?>">

			<section class="mrtu-card mr-tu-card">
				<h2 class="mrtu-card__title"><?php esc_html_e( 'Photos', 'mr-tu' ); ?></h2>
				<?php
				mr_tu_settings_image_field(
					'hero_image',
					__( 'Home hero photo', 'mr-tu' ),
					__( 'Landscape, at least 2400 px wide: a real bike against an Old Quarter wall works best. Loaded with high priority, never lazy.', 'mr-tu' ),
					(int) $settings['hero_image']
				);
				mr_tu_settings_image_field(
					'story_image',
					__( 'Story photo', 'mr-tu' ),
					__( 'Shown in the "story" section of the home page, e.g. the hand-over moment. Without a photo, an abstract Old Quarter map is shown.', 'mr-tu' ),
					(int) $settings['story_image']
				);
				?>
			</section>

			<section class="mrtu-card mr-tu-card">
				<h2 class="mrtu-card__title"><?php esc_html_e( 'Motion', 'mr-tu' ); ?></h2>
				<p class="mr-tu-check">
					<input type="hidden" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION ); ?>[reveal]" value="0">
					<input type="checkbox" id="mr-tu-reveal" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION ); ?>[reveal]" value="1" <?php checked( (bool) $settings['reveal'] ); ?>>
					<label for="mr-tu-reveal"><?php esc_html_e( 'Reveal sections softly while scrolling', 'mr-tu' ); ?></label>
				</p>
				<p class="mr-tu-check">
					<input type="hidden" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION ); ?>[view_transitions]" value="0">
					<input type="checkbox" id="mr-tu-vt" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION ); ?>[view_transitions]" value="1" <?php checked( (bool) $settings['view_transitions'] ); ?>>
					<label for="mr-tu-vt"><?php esc_html_e( 'Cross-fade between pages (supported browsers only)', 'mr-tu' ); ?></label>
				</p>
				<p class="description"><?php esc_html_e( 'Visitors who ask their device for reduced motion never see these effects.', 'mr-tu' ); ?></p>
			</section>

			<section class="mrtu-card mr-tu-card">
				<h2 class="mrtu-card__title" id="mr-tu-mode-label"><?php esc_html_e( 'Default colour mode', 'mr-tu' ); ?></h2>
				<div role="radiogroup" aria-labelledby="mr-tu-mode-label">
					<?php foreach ( $modes as $mode => $label ) : ?>
						<p class="mr-tu-check">
							<input type="radio" id="mr-tu-mode-<?php echo esc_attr( $mode ); ?>" name="<?php echo esc_attr( MR_TU_SETTINGS_OPTION ); ?>[color_mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $settings['color_mode'], $mode ); ?>>
							<label for="mr-tu-mode-<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></label>
						</p>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Visitors can still switch between System, Light and Dark; their choice is remembered on their device.', 'mr-tu' ); ?></p>
			</section>

			<p class="submit"><button type="submit" class="button button-primary mrtu-button"><?php esc_html_e( 'Save changes', 'mr-tu' ); ?></button></p>
		</form>
	</div>
	<?php
}

/**
 * Handles the admin-post submission.
 */
function mr_tu_settings_save() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change the theme settings.', 'mr-tu' ), 403 );
	}
	check_admin_referer( MR_TU_SETTINGS_ACTION, '_mr_tu_nonce' );

	$raw = isset( $_POST[ MR_TU_SETTINGS_OPTION ] ) ? wp_unslash( $_POST[ MR_TU_SETTINGS_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field by field below.
	update_option( MR_TU_SETTINGS_OPTION, mr_tu_sanitize_settings( $raw ) );
	set_transient( 'mr_tu_settings_saved_' . get_current_user_id(), 1, 60 );

	$parent = class_exists( '\MrTu\Multilang\Admin\Admin' ) ? 'admin.php' : 'themes.php';
	wp_safe_redirect( add_query_arg( 'page', MR_TU_SETTINGS_SLUG, admin_url( $parent ) ) );
	exit;
}
