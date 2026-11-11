<?php
/**
 * Answer validation, CRS calculation, gap plan, lead band and likely pathway.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure scoring functions. No WordPress calls except for translating labels.
 */
class Lzcrs_Scoring {

	const SKILLS = array( 'L', 'R', 'W', 'S' );

	/**
	 * Validates raw answers strictly. Returns the clean answers, or null if anything is wrong or unknown.
	 *
	 * @param mixed $in Decoded JSON.
	 * @return array|null
	 */
	public static function validate( $in ) {
		if ( ! is_array( $in ) ) {
			return null;
		}
		$keys = array( 'age', 'edu', 'caEdu', 'L', 'R', 'W', 'S', 'fr', 'caExp', 'frExp', 'sibling', 'pnp' );
		if ( array_diff( array_keys( $in ), $keys ) || array_diff( $keys, array_keys( $in ) ) ) {
			return null; // Missing or unknown fields.
		}
		$int = function ( $v, $min, $max ) {
			return is_int( $v ) && $v >= $min && $v <= $max;
		};
		if ( ! $int( $in['age'], 16, 80 ) || ! $int( $in['caExp'], 0, 5 ) || ! $int( $in['frExp'], 0, 3 ) ) {
			return null;
		}
		if ( ! is_string( $in['edu'] ) || ! isset( Lzcrs_Rules::EDUCATION[ $in['edu'] ] ) ) {
			return null;
		}
		if ( ! is_string( $in['caEdu'] ) || ! isset( Lzcrs_Rules::CANADIAN_EDUCATION[ $in['caEdu'] ] ) ) {
			return null;
		}
		if ( ! is_string( $in['fr'] ) || ! isset( Lzcrs_Rules::FRENCH[ $in['fr'] ] ) ) {
			return null;
		}
		foreach ( self::SKILLS as $s ) {
			$b = $in[ $s ];
			if ( ! ( is_int( $b ) || is_float( $b ) ) || $b < 0 || $b > 9 || fmod( $b * 2, 1.0 ) !== 0.0 ) {
				return null; // IELTS bands go in half steps.
			}
		}
		if ( ! is_bool( $in['sibling'] ) || ! is_bool( $in['pnp'] ) ) {
			return null;
		}
		$out = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = in_array( $k, self::SKILLS, true ) ? (float) $in[ $k ] : $in[ $k ];
		}
		return $out;
	}

	/** @return int CLB level for one IELTS skill (3 means below CLB 4). */
	public static function clb_from_ielts( $skill, $band ) {
		foreach ( Lzcrs_Rules::IELTS_TO_CLB[ $skill ] as $row ) {
			if ( $band >= $row[0] ) {
				return $row[1];
			}
		}
		return 3;
	}

	private static function first_language_points( $clb ) {
		foreach ( Lzcrs_Rules::FIRST_LANGUAGE as $row ) {
			if ( $clb >= $row[0] ) {
				return $row[1];
			}
		}
		return 0;
	}

	private static function pair_points( $tier, $high, $mid ) {
		if ( 0 === $tier ) {
			return 0;
		}
		if ( $high ) {
			return Lzcrs_Rules::TRANSFER_HIGH[ $tier - 1 ];
		}
		if ( $mid ) {
			return Lzcrs_Rules::TRANSFER_MID[ $tier - 1 ];
		}
		return 0;
	}

	/**
	 * Full CRS breakdown for validated answers.
	 *
	 * @param array $a Answers from validate().
	 * @return array
	 */
	public static function calculate( array $a ) {
		$clb = array();
		foreach ( self::SKILLS as $s ) {
			$clb[ $s ] = self::clb_from_ielts( $s, $a[ $s ] );
		}
		$min_clb = min( $clb );

		$age            = isset( Lzcrs_Rules::AGE[ $a['age'] ] ) ? Lzcrs_Rules::AGE[ $a['age'] ] : 0;
		$education      = Lzcrs_Rules::EDUCATION[ $a['edu'] ]['points'];
		$first_language = 0;
		foreach ( self::SKILLS as $s ) {
			$first_language += self::first_language_points( $clb[ $s ] );
		}
		$french          = Lzcrs_Rules::FRENCH[ $a['fr'] ];
		$second_language = min( Lzcrs_Rules::SECOND_LANGUAGE_MAX, $french['per_ability'] * 4 );
		$canadian_work   = Lzcrs_Rules::CANADIAN_WORK[ min( $a['caExp'], count( Lzcrs_Rules::CANADIAN_WORK ) - 1 ) ];
		$core            = $age + $education + $first_language + $second_language + $canadian_work;

		$edu_tier     = Lzcrs_Rules::EDUCATION[ $a['edu'] ]['tier'];
		$foreign_tier = $a['frExp'] >= 3 ? 2 : ( $a['frExp'] >= 1 ? 1 : 0 );
		$lang_high    = $min_clb >= 9;
		$lang_mid     = $min_clb >= 7;
		$ca_high      = $a['caExp'] >= 2;
		$ca_mid       = 1 === $a['caExp'];

		$edu_transfer     = min( Lzcrs_Rules::TRANSFER_GROUP_CAP, self::pair_points( $edu_tier, $lang_high, $lang_mid ) + self::pair_points( $edu_tier, $ca_high, $ca_mid ) );
		$foreign_transfer = min( Lzcrs_Rules::TRANSFER_GROUP_CAP, self::pair_points( $foreign_tier, $lang_high, $lang_mid ) + self::pair_points( $foreign_tier, $ca_high, $ca_mid ) );
		$transfer         = min( Lzcrs_Rules::TRANSFER_TOTAL_CAP, $edu_transfer + $foreign_transfer );

		$pnp          = $a['pnp'] ? Lzcrs_Rules::PNP : 0;
		$sibling      = $a['sibling'] ? Lzcrs_Rules::SIBLING : 0;
		$french_bonus = $french['nclc'] >= 7 ? ( $min_clb >= 5 ? Lzcrs_Rules::FRENCH_WITH_ENGLISH : Lzcrs_Rules::FRENCH_WITH_WEAK_ENGLISH ) : 0;
		$ca_education = Lzcrs_Rules::CANADIAN_EDUCATION[ $a['caEdu'] ];
		$additional   = min( Lzcrs_Rules::ADDITIONAL_CAP, $pnp + $sibling + $french_bonus + $ca_education );

		return array(
			'rulesVersion'               => Lzcrs_Rules::VERSION,
			'clb'                        => $clb,
			'minClb'                     => $min_clb,
			'age'                        => $age,
			'education'                  => $education,
			'firstLanguage'              => $first_language,
			'secondLanguage'             => $second_language,
			'canadianWork'               => $canadian_work,
			'core'                       => $core,
			'educationTransferability'   => $edu_transfer,
			'foreignWorkTransferability' => $foreign_transfer,
			'skillTransferability'       => $transfer,
			'pnp'                        => $pnp,
			'sibling'                    => $sibling,
			'frenchBonus'                => $french_bonus,
			'canadianEducation'          => $ca_education,
			'additional'                 => $additional,
			'total'                      => $core + $transfer + $additional,
		);
	}

	/**
	 * "How to raise your score": up to 4 changes, biggest gain first, then PNP last.
	 *
	 * @param array $a      Answers.
	 * @param int   $cutoff Recent cutoff.
	 * @return array<int,array{id:string,label:string,detail:string,delta:int,scoreAfter:int,reachesCutoff:bool}>
	 */
	public static function gap_plan( array $a, $cutoff ) {
		$base  = self::calculate( $a );
		$steps = array();
		$try   = function ( $id, $label, $detail, $change ) use ( $a, $base, $cutoff, &$steps ) {
			$b     = $change( $a );
			$after = self::calculate( $b )['total'];
			$delta = $after - $base['total'];
			if ( $delta > 0 ) {
				$steps[] = array(
					'id'            => $id,
					'label'         => $label,
					'detail'        => $detail,
					'delta'         => $delta,
					'scoreAfter'    => $after,
					'reachesCutoff' => $base['total'] < $cutoff && $after >= $cutoff,
				);
			}
		};

		if ( $base['minClb'] < 9 ) {
			$try(
				'clb9',
				__( 'Score CLB 9 in every IELTS skill', 'lawzeek-crs-calculator' ),
				__( 'Listening 8, Reading 7, Writing 7, Speaking 7', 'lawzeek-crs-calculator' ),
				function ( $b ) {
					$b['L'] = max( $b['L'], 8.0 );
					$b['R'] = max( $b['R'], 7.0 );
					$b['W'] = max( $b['W'], 7.0 );
					$b['S'] = max( $b['S'], 7.0 );
					return $b;
				}
			);
		}
		if ( $base['minClb'] < 10 ) {
			$try(
				'clb10',
				__( 'Score CLB 10 in every IELTS skill', 'lawzeek-crs-calculator' ),
				__( 'Listening 8.5, Reading 8, Writing 7.5, Speaking 7.5', 'lawzeek-crs-calculator' ),
				function ( $b ) {
					$b['L'] = max( $b['L'], 8.5 );
					$b['R'] = max( $b['R'], 8.0 );
					$b['W'] = max( $b['W'], 7.5 );
					$b['S'] = max( $b['S'], 7.5 );
					return $b;
				}
			);
		}
		if ( Lzcrs_Rules::FRENCH[ $a['fr'] ]['nclc'] < 7 ) {
			$try(
				'french',
				__( 'Pass a French test at NCLC 7', 'lawzeek-crs-calculator' ),
				__( 'TEF Canada or TCF Canada, all four skills', 'lawzeek-crs-calculator' ),
				function ( $b ) {
					$b['fr'] = 'n7';
					return $b;
				}
			);
		}
		if ( $a['caExp'] < 5 ) {
			$try(
				'caExp',
				__( 'Add one more year of Canadian work', 'lawzeek-crs-calculator' ),
				__( 'Skilled work (TEER 0–3) in Canada', 'lawzeek-crs-calculator' ),
				function ( $b ) {
					$b['caExp'] = $b['caExp'] + 1;
					return $b;
				}
			);
		}
		if ( Lzcrs_Rules::EDUCATION[ $a['edu'] ]['tier'] < 2 ) {
			$try(
				'masters',
				__( "Complete a master's degree", 'lawzeek-crs-calculator' ),
				__( 'Needs an ECA for credentials earned abroad', 'lawzeek-crs-calculator' ),
				function ( $b ) {
					$b['edu'] = 'masters';
					return $b;
				}
			);
		}

		// Stable sort by gain, biggest first (same order as the TypeScript engine for ties).
		$indexed = array();
		foreach ( $steps as $i => $s ) {
			$indexed[] = array( $i, $s );
		}
		usort(
			$indexed,
			function ( $x, $y ) {
				return $y[1]['delta'] - $x[1]['delta'] ?: $x[0] - $y[0];
			}
		);
		$top = array_slice( array_map( function ( $p ) { return $p[1]; }, $indexed ), 0, 4 );

		if ( ! $a['pnp'] ) {
			$b          = $a;
			$b['pnp']   = true;
			$after      = self::calculate( $b )['total'];
			$top[]      = array(
				'id'            => 'pnp',
				'label'         => __( 'Get a provincial nomination (PNP)', 'lawzeek-crs-calculator' ),
				'detail'        => __( 'Adds 600 points; usually leads to an invitation', 'lawzeek-crs-calculator' ),
				'delta'         => $after - $base['total'],
				'scoreAfter'    => $after,
				'reachesCutoff' => $base['total'] < $cutoff && $after >= $cutoff,
			);
		}
		return $top;
	}

	/** Hot: PNP or within 15 of the cutoff. Warm: within 60. Cold: the rest. */
	public static function lead_band( $total, array $a, $cutoff ) {
		if ( $a['pnp'] || $total >= $cutoff - 15 ) {
			return 'hot';
		}
		if ( $total >= $cutoff - 60 ) {
			return 'warm';
		}
		return 'cold';
	}

	/** Most likely Express Entry route for these answers. */
	public static function likely_pathway( array $a, array $b ) {
		if ( $a['pnp'] ) {
			return 'Express Entry with PNP';
		}
		if ( $a['caExp'] >= 1 && $b['minClb'] >= 7 ) {
			return 'Canadian Experience Class';
		}
		if ( $a['frExp'] >= 1 && $b['minClb'] >= 7 && Lzcrs_Rules::EDUCATION[ $a['edu'] ]['tier'] >= 1 ) {
			return 'Federal Skilled Worker';
		}
		return 'PNP or study pathway';
	}
}
