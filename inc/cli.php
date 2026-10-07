<?php
/**
 * WP-CLI commands shipped with the theme (loaded only under WP-CLI). The go-live steps are the same code as
 * Tools › Torrehub migration (Modules\Migration\Steps); a run from here shows up in that page's log too.
 *
 * Commands: wp torrehub fix-option-values · migrate-pages · import-chat · import-search-alerts · trash-demo ·
 * purge-nie · purge-old-verification-docs (each a dry run without --apply) · data-check · maintenance
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use Torrehub\Modules\Migration\Module as Migration;
use Torrehub\Modules\Migration\Steps;

/**
 * Run a migration step and print its report.
 *
 * @param string               $key        Step key.
 * @param array<string,string> $assoc_args Flags.
 */
function th_cli_step( string $key, array $assoc_args ): void {
	$mode   = ! empty( $assoc_args['rollback'] ) ? 'rollback' : ( ! empty( $assoc_args['apply'] ) ? 'apply' : 'dry' );
	$report = Steps::run( $key, $mode );
	Migration::log( $key, $mode, $report, 'wp-cli' );
	foreach ( $report->lines as $line ) {
		if ( 'warning' === $line[0] ) {
			WP_CLI::warning( $line[1] );
		} else {
			WP_CLI::line( $line[1] );
		}
	}
	if ( $report->failed ) {
		WP_CLI::error( $report->summary );
	}
	if ( 'dry' === $mode ) {
		WP_CLI::log( $report->summary . ' Dry run — add --apply to write.' );
	} else {
		WP_CLI::success( $report->summary );
	}
}

// The step titles are translatable: register once the theme's text domain may load.
add_action(
	'init',
	static function () {
		$apply = array(
			'type'        => 'flag',
			'name'        => 'apply',
			'optional'    => true,
			'description' => 'Write the changes (default: dry run, report only).',
		);
		foreach ( Steps::all() as $key => $step ) {
			$synopsis = array( $apply );
			if ( $step['rollback'] ) {
				$synopsis[] = array(
					'type'        => 'flag',
					'name'        => 'rollback',
					'optional'    => true,
					'description' => 'Undo an earlier --apply.',
				);
			}
			WP_CLI::add_command(
				'torrehub ' . $key,
				static function ( $args, $assoc_args ) use ( $key ) {
					th_cli_step( $key, (array) $assoc_args );
				},
				array(
					'shortdesc' => $step['code'] . ' — ' . $step['title'],
					'longdesc'  => $step['help'],
					'synopsis'  => $synopsis,
				)
			);
		}
	},
	99
);

WP_CLI::add_command(
	'torrehub data-check',
	static function ( $args, $assoc_args ) {
		$baseline = Migration::baseline();
		$before   = (array) ( $baseline['counts'] ?? array() );
		$rows     = array();
		foreach ( Steps::data_counts() as $label => $now ) {
			$rows[] = array(
				'what'     => $label,
				'baseline' => isset( $before[ $label ] ) ? (string) $before[ $label ] : '—',
				'now'      => (string) $now,
				'changed'  => isset( $before[ $label ] ) && $before[ $label ] !== $now ? '*' : '',
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'what', 'baseline', 'now', 'changed' ) );
		if ( ! empty( $assoc_args['save-baseline'] ) ) {
			Migration::save_baseline();
			WP_CLI::success( 'Baseline saved.' );
		} elseif ( ! $before ) {
			WP_CLI::log( 'No baseline yet — run with --save-baseline before the first step.' );
		}
	},
	array(
		'shortdesc' => 'A6/G1 — aggregate counts before / after the migration (no personal data).',
		'synopsis'  => array(
			array(
				'type'        => 'flag',
				'name'        => 'save-baseline',
				'optional'    => true,
				'description' => 'Store the current counts as the "before" baseline.',
			),
		),
	)
);

WP_CLI::add_command(
	'torrehub maintenance',
	static function ( $args ) {
		$what = (string) ( $args[0] ?? 'status' );
		if ( in_array( $what, array( 'on', 'off' ), true ) ) {
			Migration::set_maintenance( 'on' === $what );
		}
		WP_CLI::success( 'Maintenance mode: ' . ( Migration::maintenance() ? 'on (visitors see "Back soon"; administrators work as usual)' : 'off' ) );
	},
	array(
		'shortdesc' => 'B1 — maintenance mode that keeps wp-admin usable for administrators.',
		'synopsis'  => array(
			array(
				'type'     => 'positional',
				'name'     => 'state',
				'optional' => true,
				'options'  => array( 'on', 'off', 'status' ),
			),
		),
	)
);
