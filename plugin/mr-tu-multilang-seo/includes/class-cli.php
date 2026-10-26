<?php
/**
 * WP-CLI commands (contract §2.13): `wp mrtu seed|health|translations|export|import|sitemap`.
 *
 * Registered by Plugin::boot() only when WP-CLI runs. Public instance methods are the
 * subcommands; the static init() is ignored by WP-CLI.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Mr Tu multilingual tools: seed content, health check, translation matrix, configuration export/import, sitemap cache.
 */
final class Cli {

	/**
	 * Registers the `mrtu` command.
	 */
	public static function init() {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'mrtu', __CLASS__ );
	}

	/**
	 * Seeds the core pages, blog posts and categories of every language from the bundled content.
	 *
	 * Idempotent: existing seeded posts are skipped. With --update, posts whose content is unchanged since
	 * seeding are refreshed from the files; posts edited on the website are reported as "customized" and kept.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Compute the report without writing anything.
	 *
	 * [--update]
	 * : Refresh seeded posts whose content is still identical to the seeded version.
	 *
	 * [--set-front]
	 * : Set the Reading settings (static front page = default-language home, posts page = default-language blog),
	 * the timezone (when empty) and the date format.
	 *
	 * [--lang=<code>]
	 * : Only seed these languages (comma-separated codes). Default: every registered language.
	 *
	 * [--source=<dir>]
	 * : Alternative source directory holding {lang}/pages.json.
	 *
	 * [--format=<format>]
	 * : Output format of the per-language table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu seed --dry-run
	 *     wp mrtu seed --set-front
	 *     wp mrtu seed --update --lang=en,de
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function seed( $args, $assoc_args ) {
		$report = Seeder::run(
			array(
				'dry_run'   => \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ),
				'update'    => \WP_CLI\Utils\get_flag_value( $assoc_args, 'update', false ),
				'set_front' => \WP_CLI\Utils\get_flag_value( $assoc_args, 'set-front', false ),
				'lang'      => (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'lang', '' ),
				'source'    => (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'source', '' ),
			)
		);
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( $report['dry_run'] ) {
			\WP_CLI::log( 'Dry run: nothing was written. Source: ' . $report['source'] );
		} else {
			\WP_CLI::log( 'Source: ' . $report['source'] );
		}

		$rows = array();
		foreach ( $report['languages'] as $code => $lang ) {
			if ( ! $lang['requested'] ) {
				continue;
			}
			$rows[] = array(
				'lang'       => $code,
				'created'    => $lang['created'],
				'updated'    => $lang['updated'],
				'skipped'    => $lang['skipped'],
				'customized' => $lang['customized'],
				'errors'     => $lang['errors'],
				'note'       => $lang['missing'] ? 'missing: ' . $lang['message'] : '',
			);
		}
		\WP_CLI\Utils\format_items( $format, $rows, array( 'lang', 'created', 'updated', 'skipped', 'customized', 'errors', 'note' ) );

		if ( 'table' === $format ) {
			foreach ( $report['languages'] as $code => $lang ) {
				foreach ( $lang['items'] as $item ) {
					if ( in_array( $item['action'], array( 'errors', 'customized', 'updated' ), true ) ) {
						$line = sprintf( '%s %s %s #%d %s: %s', strtoupper( $code ), $item['action'], $item['type'], $item['id'], $item['seed_id'], $item['message'] );
						if ( 'errors' === $item['action'] ) {
							\WP_CLI::warning( $line );
						} else {
							\WP_CLI::log( '  ' . $line );
						}
					}
				}
			}
			foreach ( $report['front']['messages'] as $message ) {
				\WP_CLI::log( '  ' . $message );
			}
			foreach ( $report['messages'] as $message ) {
				\WP_CLI::warning( $message );
			}
		}

		$totals  = $report['totals'];
		$summary = sprintf( '%d created, %d updated, %d skipped, %d customized, %d errors', $totals['created'], $totals['updated'], $totals['skipped'], $totals['customized'], $totals['errors'] );
		if ( $report['front']['applied'] ) {
			$summary .= sprintf( '; reading settings applied (front page #%d, posts page #%d)', $report['front']['page_on_front'], $report['front']['page_for_posts'] );
		}
		if ( $totals['errors'] > 0 ) {
			\WP_CLI::warning( 'Seeding finished with errors: ' . $summary );
			\WP_CLI::halt( 1 );
		}
		\WP_CLI::success( ( $report['dry_run'] ? 'Would seed: ' : 'Seeded: ' ) . $summary );
	}

	/**
	 * Runs the health checks (translations, SEO metadata, links, business data, front pages, sitemap, head audit).
	 *
	 * Exits with status 1 when any check reports an error.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--skip-loopback]
	 * : Do not fetch the sitemap and the language home pages over HTTP.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu health
	 *     wp mrtu health --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function health( $args, $assoc_args ) {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$report = Health::get(
			array(
				'fresh'    => true,
				'loopback' => ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-loopback', false ),
			)
		);
		$rows   = array();
		foreach ( $report['checks'] as $check ) {
			$rows[] = array(
				'severity' => $check['severity'],
				'id'       => $check['id'],
				'label'    => $check['label'],
				'detail'   => $check['detail'],
			);
		}
		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		} else {
			\WP_CLI\Utils\format_items( $format, $rows, array( 'severity', 'id', 'label', 'detail' ) );
		}
		$summary = $report['summary'];
		$line    = sprintf( '%d ok, %d warnings, %d errors', $summary['ok'], $summary['warning'], $summary['error'] );
		if ( $summary['error'] > 0 ) {
			if ( 'json' !== $format ) {
				\WP_CLI::warning( 'Health: ' . $line );
			}
			\WP_CLI::halt( 1 );
		}
		if ( 'json' !== $format ) {
			\WP_CLI::success( 'Health: ' . $line );
		}
	}

	/**
	 * Shows the translation matrix of the core page roles × enabled languages.
	 *
	 * Cells: "published #id", "draft #id" (any other status) or "missing".
	 *
	 * ## OPTIONS
	 *
	 * [--missing]
	 * : Only roles with a missing or unpublished language version.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu translations
	 *     wp mrtu translations --missing
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function translations( $args, $assoc_args ) {
		$only_missing = \WP_CLI\Utils\get_flag_value( $assoc_args, 'missing', false );
		$format       = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$data         = Health::completeness();
		$rows         = array();
		foreach ( $data['matrix'] as $role => $cells ) {
			$row      = array( 'role' => $role );
			$complete = true;
			foreach ( $data['languages'] as $code ) {
				$cell = $cells[ $code ];
				if ( 'published' === $cell['status'] ) {
					$row[ $code ] = 'published #' . $cell['id'];
				} elseif ( 'draft' === $cell['status'] ) {
					$row[ $code ] = get_post_status( $cell['id'] ) . ' #' . $cell['id'];
					$complete     = false;
				} else {
					$row[ $code ] = 'missing';
					$complete     = false;
				}
			}
			if ( $only_missing && $complete ) {
				continue;
			}
			$rows[] = $row;
		}
		\WP_CLI\Utils\format_items( $format, $rows, array_merge( array( 'role' ), $data['languages'] ) );
		if ( 'table' === $format ) {
			\WP_CLI::log( sprintf( '%d%% complete (%d of %d language versions published).', $data['percent'], $data['published'], $data['total'] ) );
		}
	}

	/**
	 * Exports the configuration (store information, prices, language settings) as JSON.
	 *
	 * Without --file the JSON is printed, so `wp mrtu export > backup.json` works.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write the JSON to this file instead of printing it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu export > mr-tu-config.json
	 *     wp mrtu export --file=/tmp/mr-tu-config.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function export( $args, $assoc_args ) {
		$data             = Business::export_data();
		$settings         = Languages::settings();
		$data['settings'] = array(
			'languages' => $settings['languages'],
			'x_default' => $settings['x_default'],
		);
		$json             = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$file             = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );
		if ( '' === $file ) {
			\WP_CLI::line( $json );
			return;
		}
		$written = file_put_contents( $file, $json . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI export to a path chosen by the operator.
		if ( false === $written ) {
			\WP_CLI::error( 'Could not write ' . $file );
		}
		\WP_CLI::success( sprintf( 'Wrote %s (%d bytes).', $file, $written ) );
	}

	/**
	 * Imports a configuration file (store information + prices, optional language settings).
	 *
	 * Validated through the same normalizers as the admin forms; the current data is snapshotted first
	 * (Store information → Backup & restore).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a JSON file produced by `wp mrtu export` or the admin backup download.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu import mr-tu-config.json --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	public function import( $args, $assoc_args ) {
		$file = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
			\WP_CLI::error( 'File not found: ' . $file );
		}
		$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI import from a local file.
		if ( ! is_string( $json ) ) {
			\WP_CLI::error( 'Could not read ' . $file );
		}
		\WP_CLI::confirm( 'Replace the current store information and prices with the file contents? (A snapshot of the current data is taken first.)', $assoc_args );

		$result = Business::import_json( $json );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['errors'] ) ) {
				foreach ( (array) $data['errors'] as $key => $message ) {
					\WP_CLI::warning( $key . ': ' . $message );
				}
			}
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( 'Store information and prices imported.' );

		$decoded = json_decode( $json, true );
		if ( is_array( $decoded ) && isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) {
			$validated = Languages::validate(
				array(
					'languages' => isset( $decoded['settings']['languages'] ) && is_array( $decoded['settings']['languages'] ) ? $decoded['settings']['languages'] : array(),
					'x_default' => isset( $decoded['settings']['x_default'] ) ? (string) $decoded['settings']['x_default'] : Languages::setting( 'x_default', 'en' ),
				)
			);
			foreach ( $validated['errors'] as $message ) {
				\WP_CLI::warning( $message );
			}
			Languages::update_settings( $validated['settings'] );
			Health::flush();
			\WP_CLI::success( 'Language settings imported.' );
		}
	}

	/**
	 * Sitemap maintenance.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - flush
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mrtu sitemap flush
	 *
	 * @param array $args Positional arguments.
	 */
	public function sitemap( $args ) {
		$action = isset( $args[0] ) ? (string) $args[0] : '';
		if ( 'flush' !== $action ) {
			\WP_CLI::error( 'Usage: wp mrtu sitemap flush' );
		}
		if ( ! Health::flush_sitemap() ) {
			\WP_CLI::warning( 'The sitemap module is not available; nothing to flush.' );
			return;
		}
		\WP_CLI::success( 'Sitemap cache flushed.' );
	}
}
