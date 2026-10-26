<?php
/**
 * Minimal integration test harness for `wp eval-file` (no PHPUnit needed on the server).
 *
 * Each tests/php/test-*.php file requires this bootstrap, calls MrTu_Test::section()/ok()/same(),
 * registers fixtures through the helpers (cleaned up by MrTu_Test::finish()) and ends with
 * MrTu_Test::finish(), which prints "RESULT: <passed> passed, <failed> failed" and exits 1 on failure.
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through: wp eval-file <file>\n" );
	exit( 2 );
}

if ( ! class_exists( 'MrTu_Test' ) ) {

	/**
	 * Assertions, fixtures and request simulation.
	 */
	final class MrTu_Test {

		/**
		 * Passed assertions.
		 *
		 * @var int
		 */
		public static $passed = 0;

		/**
		 * Failed assertions.
		 *
		 * @var int
		 */
		public static $failed = 0;

		/**
		 * Posts created by the test (deleted in finish()).
		 *
		 * @var int[]
		 */
		private static $posts = array();

		/**
		 * Terms created by the test: [ term_id, taxonomy ].
		 *
		 * @var array[]
		 */
		private static $terms = array();

		/**
		 * Original option values to restore: name => value|null (null = did not exist).
		 *
		 * @var array
		 */
		private static $options = array();

		/**
		 * Unique run id (used in titles/slugs).
		 *
		 * @var string
		 */
		public static $run = '';

		/**
		 * Starts a test file.
		 *
		 * @param string $title File title.
		 */
		public static function start( $title ) {
			self::$run = strtolower( wp_generate_password( 6, false, false ) );
			echo "== {$title} (run " . self::$run . ") ==\n";
			// Keep the test process offline and deterministic.
			add_filter( 'pre_http_request', array( __CLASS__, 'offline' ) );
			wp_set_current_user( 1 );
		}

		/**
		 * Blocks outgoing HTTP requests during tests.
		 *
		 * @return WP_Error
		 */
		public static function offline() {
			return new WP_Error( 'mrtu_test_offline', 'HTTP disabled in tests' );
		}

		/**
		 * Prints a section header.
		 *
		 * @param string $name Section.
		 */
		public static function section( $name ) {
			echo "\n# {$name}\n";
		}

		/**
		 * Asserts a condition.
		 *
		 * @param bool   $condition Condition.
		 * @param string $message   Description.
		 * @param string $detail    Extra detail printed on failure.
		 * @return bool
		 */
		public static function ok( $condition, $message, $detail = '' ) {
			if ( $condition ) {
				++self::$passed;
				echo "  PASS: {$message}\n";
				return true;
			}
			++self::$failed;
			echo "  FAIL: {$message}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
			return false;
		}

		/**
		 * Asserts strict equality.
		 *
		 * @param mixed  $expected Expected.
		 * @param mixed  $actual   Actual.
		 * @param string $message  Description.
		 * @return bool
		 */
		public static function same( $expected, $actual, $message ) {
			return self::ok( $expected === $actual, $message, 'expected ' . self::export( $expected ) . ', got ' . self::export( $actual ) );
		}

		/**
		 * Compact var_export.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public static function export( $value ) {
			return str_replace( array( "\n", '  ' ), array( ' ', '' ), var_export( $value, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		/**
		 * Remembers an option so finish() restores it.
		 *
		 * @param string $name Option name.
		 */
		public static function keep_option( $name ) {
			if ( array_key_exists( $name, self::$options ) ) {
				return;
			}
			$value                  = get_option( $name, null );
			self::$options[ $name ] = $value;
		}

		/**
		 * Creates a post (registered for cleanup).
		 *
		 * @param array       $args wp_insert_post() args (defaults: published page).
		 * @param string|null $lang Language passed as `mrtu_lang`.
		 * @return int
		 */
		public static function post( array $args, $lang = null ) {
			$args = array_merge(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Test ' . self::$run,
					'post_content' => '<!-- wp:paragraph --><p>Content ' . wp_generate_password( 8, false, false ) . '</p><!-- /wp:paragraph -->',
					'post_author'  => 1,
				),
				$args
			);
			if ( null !== $lang ) {
				$args['mrtu_lang'] = $lang;
			}
			$id = wp_insert_post( wp_slash( $args ), true );
			if ( is_wp_error( $id ) ) {
				self::ok( false, 'fixture post created', $id->get_error_message() );
				return 0;
			}
			self::$posts[] = (int) $id;
			return (int) $id;
		}

		/**
		 * Registers a post created elsewhere for cleanup.
		 *
		 * @param int $id Post id.
		 */
		public static function track_post( $id ) {
			if ( $id ) {
				self::$posts[] = (int) $id;
			}
		}

		/**
		 * Creates a term (registered for cleanup).
		 *
		 * @param string $name     Name.
		 * @param string $taxonomy Taxonomy.
		 * @param string $lang     Language.
		 * @param array  $args     wp_insert_term() args.
		 * @return int
		 */
		public static function term( $name, $taxonomy, $lang, array $args = array() ) {
			$result = wp_insert_term( $name, $taxonomy, $args );
			if ( is_wp_error( $result ) ) {
				self::ok( false, 'fixture term created', $result->get_error_message() );
				return 0;
			}
			$term_id       = (int) $result['term_id'];
			self::$terms[] = array( $term_id, $taxonomy );
			mrtu_set_term_language( $term_id, $lang );
			return $term_id;
		}

		/**
		 * Simulates a frontend request (like the core test suite's go_to()).
		 *
		 * @param string $path Request URI relative to the site root, e.g. "/en/faq/".
		 */
		public static function go_to( $path ) {
			\MrTu\Multilang\Router::stop();
			\MrTu\Multilang\Switcher::flush();

			$home  = wp_parse_url( home_url() );
			$parts = wp_parse_url( $path );
			$_GET  = array();
			$_POST = array();
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $_GET );
			}
			$_SERVER['REQUEST_URI']    = $path;
			$_SERVER['PHP_SELF']       = '/index.php';
			$_SERVER['HTTP_HOST']      = $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
			$_SERVER['REQUEST_METHOD'] = 'GET';
			unset( $_SERVER['PATH_INFO'] );

			$GLOBALS['wp_the_query'] = new WP_Query();
			$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
			unset( $GLOBALS['post'] );

			// WP::parse_request() does not reset these when called again on the same object.
			$wp                   = $GLOBALS['wp'];
			$wp->query_vars       = array();
			$wp->extra_query_vars = array();
			$wp->query_string     = '';
			$wp->request          = '';
			$wp->matched_rule     = null;
			$wp->matched_query    = null;
			$wp->did_permalink    = false;

			\MrTu\Multilang\Router::start( $path );
			$GLOBALS['wp']->main( isset( $parts['query'] ) ? $parts['query'] : '' );
		}

		/**
		 * Leaves the simulated frontend (filters off, globals reset).
		 */
		public static function leave_frontend() {
			\MrTu\Multilang\Router::stop();
			\MrTu\Multilang\Switcher::flush();
			$GLOBALS['wp_the_query'] = new WP_Query();
			$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		}

		/**
		 * Deletes fixtures, restores options and prints the summary; exits 1 on failure.
		 */
		public static function finish() {
			self::leave_frontend();
			foreach ( array_reverse( array_unique( self::$posts ) ) as $id ) {
				if ( get_post( $id ) ) {
					wp_delete_post( $id, true );
				}
			}
			foreach ( self::$terms as $term ) {
				wp_delete_term( $term[0], $term[1] );
			}
			foreach ( self::$options as $name => $value ) {
				if ( null === $value ) {
					delete_option( $name );
				} else {
					update_option( $name, $value );
				}
			}
			\MrTu\Multilang\Languages::flush();
			\MrTu\Multilang\Roles::flush();

			echo "\nRESULT: " . self::$passed . ' passed, ' . self::$failed . " failed\n";
			if ( self::$failed > 0 ) {
				exit( 1 );
			}
		}
	}
}
