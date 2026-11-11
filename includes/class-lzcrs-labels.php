<?php
/**
 * Human-readable, translatable labels for answers, bands, statuses and pathways.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All visible labels live here so they are translated once.
 */
class Lzcrs_Labels {
	public static function education() {
		return array(
			'none'      => __( 'Less than high school', 'lawzeek-crs-calculator' ),
			'secondary' => __( 'High school', 'lawzeek-crs-calculator' ),
			'one'       => __( 'One-year diploma or certificate', 'lawzeek-crs-calculator' ),
			'two'       => __( 'Two-year diploma', 'lawzeek-crs-calculator' ),
			'bachelor'  => __( "Bachelor's or 3+ year program", 'lawzeek-crs-calculator' ),
			'twoormore' => __( 'Two or more credentials (one 3+ years)', 'lawzeek-crs-calculator' ),
			'masters'   => __( "Master's or professional degree", 'lawzeek-crs-calculator' ),
			'phd'       => __( 'PhD', 'lawzeek-crs-calculator' ),
		);
	}

	public static function canadian_education() {
		return array(
			'none'  => __( 'None', 'lawzeek-crs-calculator' ),
			'short' => __( '1–2 year Canadian credential', 'lawzeek-crs-calculator' ),
			'long'  => __( '3+ year Canadian credential', 'lawzeek-crs-calculator' ),
		);
	}

	public static function french() {
		return array(
			'none' => __( 'No French test', 'lawzeek-crs-calculator' ),
			'n5'   => __( 'NCLC 5–6', 'lawzeek-crs-calculator' ),
			'n7'   => __( 'NCLC 7–8', 'lawzeek-crs-calculator' ),
			'n9'   => __( 'NCLC 9 or higher', 'lawzeek-crs-calculator' ),
		);
	}

	public static function band( $band ) {
		$l = array(
			'hot'  => __( 'Hot', 'lawzeek-crs-calculator' ),
			'warm' => __( 'Warm', 'lawzeek-crs-calculator' ),
			'cold' => __( 'Cold', 'lawzeek-crs-calculator' ),
		);
		return isset( $l[ $band ] ) ? $l[ $band ] : $band;
	}

	public static function statuses() {
		return array(
			'new'       => __( 'New', 'lawzeek-crs-calculator' ),
			'contacted' => __( 'Contacted', 'lawzeek-crs-calculator' ),
			'booked'    => __( 'Booked', 'lawzeek-crs-calculator' ),
			'retained'  => __( 'Retained', 'lawzeek-crs-calculator' ),
			'lost'      => __( 'Lost', 'lawzeek-crs-calculator' ),
		);
	}

	public static function pathway( $p ) {
		$l = array(
			'Express Entry with PNP'    => __( 'Express Entry with PNP', 'lawzeek-crs-calculator' ),
			'Canadian Experience Class' => __( 'Canadian Experience Class', 'lawzeek-crs-calculator' ),
			'Federal Skilled Worker'    => __( 'Federal Skilled Worker', 'lawzeek-crs-calculator' ),
			'PNP or study pathway'      => __( 'PNP or study pathway', 'lawzeek-crs-calculator' ),
		);
		return isset( $l[ $p ] ) ? $l[ $p ] : $p;
	}

	/** "7.5" or "Below 4" for an IELTS band. */
	public static function ielts( $b ) {
		return $b < 4 ? __( 'Below 4', 'lawzeek-crs-calculator' ) : number_format_i18n( (float) $b, 1 );
	}
}
