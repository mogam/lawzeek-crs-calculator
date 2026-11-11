<?php
/**
 * CRS rules for a single applicant (no accompanying spouse).
 *
 * Source: IRCC "Comprehensive Ranking System (CRS) criteria". Job-offer points were removed on 2025-03-25.
 * Kept in step with the Lawzeek scoring engine; a parity test checks both give the same scores.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The CRS points tables.
 */
class Lzcrs_Rules {
	const VERSION = 'crs-2025-03-25';

	/** @var array<int,int> Age in years => points. Ages not listed score 0. */
	const AGE = array(
		18 => 99, 19 => 105, 20 => 110, 21 => 110, 22 => 110, 23 => 110, 24 => 110, 25 => 110, 26 => 110, 27 => 110,
		28 => 110, 29 => 110, 30 => 105, 31 => 99, 32 => 94, 33 => 88, 34 => 83, 35 => 77, 36 => 72, 37 => 66,
		38 => 61, 39 => 55, 40 => 50, 41 => 39, 42 => 28, 43 => 17, 44 => 6,
	);

	/** @var array<string,array{points:int,tier:int}> */
	const EDUCATION = array(
		'none'      => array( 'points' => 0, 'tier' => 0 ),
		'secondary' => array( 'points' => 30, 'tier' => 0 ),
		'one'       => array( 'points' => 90, 'tier' => 1 ),
		'two'       => array( 'points' => 98, 'tier' => 1 ),
		'bachelor'  => array( 'points' => 120, 'tier' => 1 ),
		'twoormore' => array( 'points' => 128, 'tier' => 2 ),
		'masters'   => array( 'points' => 135, 'tier' => 2 ),
		'phd'       => array( 'points' => 150, 'tier' => 2 ),
	);

	/** @var array<string,array<int,array{0:float,1:int}>> Minimum IELTS General band => CLB, highest first. */
	const IELTS_TO_CLB = array(
		'L' => array( array( 8.5, 10 ), array( 8, 9 ), array( 7.5, 8 ), array( 6, 7 ), array( 5.5, 6 ), array( 5, 5 ), array( 4.5, 4 ) ),
		'R' => array( array( 8, 10 ), array( 7, 9 ), array( 6.5, 8 ), array( 6, 7 ), array( 5, 6 ), array( 4, 5 ), array( 3.5, 4 ) ),
		'W' => array( array( 7.5, 10 ), array( 7, 9 ), array( 6.5, 8 ), array( 6, 7 ), array( 5.5, 6 ), array( 5, 5 ), array( 4, 4 ) ),
		'S' => array( array( 7.5, 10 ), array( 7, 9 ), array( 6.5, 8 ), array( 6, 7 ), array( 5.5, 6 ), array( 5, 5 ), array( 4, 4 ) ),
	);

	/** @var array<int,array{0:int,1:int}> First official language points per ability, keyed by minimum CLB. */
	const FIRST_LANGUAGE = array( array( 10, 34 ), array( 9, 31 ), array( 8, 23 ), array( 7, 17 ), array( 6, 9 ), array( 4, 6 ) );

	/** @var array<string,array{per_ability:int,nclc:int}> */
	const FRENCH = array(
		'none' => array( 'per_ability' => 0, 'nclc' => 0 ),
		'n5'   => array( 'per_ability' => 1, 'nclc' => 5 ),
		'n7'   => array( 'per_ability' => 3, 'nclc' => 7 ),
		'n9'   => array( 'per_ability' => 6, 'nclc' => 9 ),
	);

	const SECOND_LANGUAGE_MAX = 24;

	/** @var int[] Index = years of Canadian work (capped at the last index). */
	const CANADIAN_WORK = array( 0, 40, 53, 64, 72, 80 );

	/** Transferability points for [tier 1, tier 2]. */
	const TRANSFER_HIGH      = array( 25, 50 );
	const TRANSFER_MID       = array( 13, 25 );
	const TRANSFER_GROUP_CAP = 50;
	const TRANSFER_TOTAL_CAP = 100;

	const PNP                      = 600;
	const SIBLING                  = 15;
	const FRENCH_WITH_WEAK_ENGLISH = 25;
	const FRENCH_WITH_ENGLISH      = 50;
	const CANADIAN_EDUCATION       = array( 'none' => 0, 'short' => 15, 'long' => 30 );
	const ADDITIONAL_CAP           = 600;
}
