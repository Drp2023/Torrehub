<?php
/**
 * Output of one migration step run: detail lines, warnings, a summary and the number of changes. The same report
 * is printed by WP-CLI (`wp torrehub …`) and shown on Tools › Torrehub migration.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Step report.
 */
final class Report {

	/**
	 * Detail lines: [ 'line'|'warning', text ].
	 *
	 * @var array<int,array{0:string,1:string}>
	 */
	public array $lines = array();

	/**
	 * One-line result.
	 *
	 * @var string
	 */
	public string $summary = '';

	/**
	 * Rows that were (apply) or would be (dry run) changed.
	 *
	 * @var int
	 */
	public int $changes = 0;

	/**
	 * Stopped with an error; nothing was written.
	 *
	 * @var bool
	 */
	public bool $failed = false;

	/**
	 * Add a detail line.
	 *
	 * @param string $text Text.
	 */
	public function line( string $text ): void {
		$this->lines[] = array( 'line', $text );
	}

	/**
	 * Add a warning.
	 *
	 * @param string $text Text.
	 */
	public function warning( string $text ): void {
		$this->lines[] = array( 'warning', $text );
	}

	/**
	 * Finish with a summary.
	 *
	 * @param string $summary Summary.
	 * @param int    $changes Changed rows.
	 */
	public function done( string $summary, int $changes = 0 ): void {
		$this->summary = $summary;
		$this->changes = $changes;
	}

	/**
	 * Stop with an error (nothing was written).
	 *
	 * @param string $message Error.
	 */
	public function fail( string $message ): void {
		$this->failed  = true;
		$this->summary = $message;
	}

	/**
	 * Plain array (stored between the request that ran the step and the page that shows it).
	 *
	 * @return array{lines:array,summary:string,changes:int,failed:bool}
	 */
	public function to_array(): array {
		return array(
			'lines'   => $this->lines,
			'summary' => $this->summary,
			'changes' => $this->changes,
			'failed'  => $this->failed,
		);
	}
}
