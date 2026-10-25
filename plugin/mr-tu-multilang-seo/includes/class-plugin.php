<?php
/**
 * Plugin bootstrap: wires every module that exists.
 *
 * Each module is a class with a static init() (and optionally activate()/deactivate()).
 * Modules are owned by separate files so they can evolve independently; a missing
 * module is skipped, never fatal.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Front/back modules in boot order. Core i18n first, then data, SEO, content tools.
	 *
	 * @return string[]
	 */
	public static function modules() {
		return array(
			'Languages',
			'Translations',
			'Roles',
			'Router',
			'Links',
			'Query',
			'Switcher',
			'Breadcrumbs',
			'Business',
			'Pricing',
			'Blocks',
			'Quick_Contact',
			'Seo',
			'Seo_Head',
			'Schema',
			'Sitemaps',
			'Yoast_Compat',
			'Redirects',
			'Toc',
			'Health',
			'Seeder',
		);
	}

	/**
	 * Admin-only modules (each registers its screens through the `mrtu_admin_pages` filter).
	 *
	 * @return string[]
	 */
	public static function admin_modules() {
		return array(
			'Admin\\Admin',
			'Admin\\Admin_Dashboard',
			'Admin\\Admin_Business',
			'Admin\\Admin_Pricing',
			'Admin\\Admin_Vehicles',
			'Admin\\Admin_Languages',
			'Admin\\Admin_Translations',
			'Admin\\Admin_Seo',
			'Admin\\Admin_Quick_Contact',
			'Admin\\Admin_Health',
		);
	}

	/**
	 * Returns the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boots all available modules once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( self::modules() as $module ) {
			self::call( $module, 'init' );
		}

		if ( is_admin() ) {
			foreach ( self::admin_modules() as $module ) {
				self::call( $module, 'init' );
			}
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( __NAMESPACE__ . '\\Cli' ) ) {
			Cli::init();
		}

		add_action( 'init', array( __CLASS__, 'load_textdomain' ), 0 );
	}

	/**
	 * Loads bundled translations (JIT loading would also find them; explicit keeps older WP happy).
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'mr-tu-multilang-seo', false, dirname( plugin_basename( MRTU_ML_FILE ) ) . '/languages' );
	}

	/**
	 * Activation: every module may prepare its own options/terms. Never touches content.
	 */
	public static function activate() {
		foreach ( array_merge( self::modules(), array( 'Cli' ) ) as $module ) {
			self::call( $module, 'activate' );
		}
		update_option( 'mrtu_ml_version', MRTU_ML_VERSION, false );
	}

	/**
	 * Deactivation: modules clean transient state only. Data is kept.
	 */
	public static function deactivate() {
		foreach ( self::modules() as $module ) {
			self::call( $module, 'deactivate' );
		}
	}

	/**
	 * Calls a static method on a module class when both exist.
	 *
	 * @param string $module Class name relative to the plugin namespace.
	 * @param string $method Static method name.
	 */
	private static function call( $module, $method ) {
		$class = __NAMESPACE__ . '\\' . $module;
		if ( class_exists( $class ) && method_exists( $class, $method ) ) {
			call_user_func( array( $class, $method ) );
		}
	}
}
