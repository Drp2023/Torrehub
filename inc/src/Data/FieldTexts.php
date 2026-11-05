<?php
/**
 * Translatable Form Builder texts. The forms (labels, option labels) live in Classified Listing's `rtcl_forms` table
 * as plain English; the theme passes the texts it knows through __() wherever it shows them (listing page, archive
 * filters, listing form). A text the site owner has changed in the Form Builder isn't in the table and shows as typed.
 *
 * @package Torrehub
 */

namespace Torrehub\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Form Builder text table.
 */
final class FieldTexts {

	/** Service form · "Mobile Service Available?" (client request 2026-11; values unchanged, see Migration U1). */
	public const MOBILE_LABEL = 'Mobile Service Available?';
	public const MOBILE_YES   = 'Yes – We Come to You';
	public const MOBILE_NO    = 'No – On-site Service Only';

	/**
	 * English source => translation.
	 *
	 * @return array<string,string>
	 */
	public static function map(): array {
		static $map = null;
		if ( null === $map || ! did_action( 'init' ) ) {
			$map = array(
				self::MOBILE_LABEL => __( 'Mobile Service Available?', 'torrehub' ),
				// The archive-filter heading keeps the old field label (Appearance › Archive filters, imported from the
				// old filter builder — a re-import brings it back), so it is mapped here instead of rewritten.
				'Mobile Service'   => __( 'Mobile Service Available?', 'torrehub' ),
				self::MOBILE_YES   => __( 'Yes – We Come to You', 'torrehub' ),
				self::MOBILE_NO    => __( 'No – On-site Service Only', 'torrehub' ),
				'Languages Spoken' => __( 'Languages Spoken', 'torrehub' ),
				'English'          => __( 'English', 'torrehub' ),
				'Spanish'          => __( 'Spanish', 'torrehub' ),
				'Swedish'          => __( 'Swedish', 'torrehub' ),
				'German'           => __( 'German', 'torrehub' ),
				'Other'            => __( 'Other', 'torrehub' ),
			);
		}
		/**
		 * Form Builder texts the theme translates: English source => translation.
		 *
		 * @param array<string,string> $map Table.
		 */
		return (array) apply_filters( 'th_field_texts', $map );
	}

	/**
	 * A Form Builder text in the site language (unknown texts unchanged).
	 *
	 * @param string $text Label as stored.
	 */
	public static function t( string $text ): string {
		$map = self::map();
		return $map[ trim( $text ) ] ?? $text;
	}
}
