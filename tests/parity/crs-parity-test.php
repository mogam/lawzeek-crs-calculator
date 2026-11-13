<?php
/**
 * Checks the free plugin's PHP CRS engine against the TypeScript engine (scripts/fixtures/crs-parity.json).
 * Runs without WordPress. Run: php tests/parity/crs-parity-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function __( $s, $d = null ) { return $s; }
$dir = __DIR__ . '/../../includes/';
require $dir . 'class-lzcrs-rules.php';
require $dir . 'class-lzcrs-scoring.php';

$cases  = json_decode( file_get_contents( __DIR__ . '/fixtures/crs-parity.json' ), true );
$fail   = 0;
$fields = array( 'minClb', 'age', 'education', 'firstLanguage', 'secondLanguage', 'canadianWork', 'core', 'educationTransferability', 'foreignWorkTransferability', 'skillTransferability', 'pnp', 'sibling', 'frenchBonus', 'canadianEducation', 'additional', 'total' );
foreach ( $cases as $i => $c ) {
	$a = Lzcrs_Scoring::validate( $c['answers'] );
	if ( null === $a ) {
		echo "case $i: valid answers were rejected\n";
		$fail++;
		continue;
	}
	$b = Lzcrs_Scoring::calculate( $a );
	foreach ( $fields as $f ) {
		if ( $b[ $f ] !== $c['breakdown'][ $f ] ) {
			echo "case $i: $f php={$b[$f]} ts={$c['breakdown'][$f]}\n";
			$fail++;
		}
	}
	if ( $b['clb'] !== $c['breakdown']['clb'] ) { echo "case $i: clb differs\n"; $fail++; }
	$g = array_map( function ( $x ) { return array( 'id' => $x['id'], 'delta' => $x['delta'], 'scoreAfter' => $x['scoreAfter'], 'reachesCutoff' => $x['reachesCutoff'] ); }, Lzcrs_Scoring::gap_plan( $a, $c['cutoff'] ) );
	if ( $g !== $c['gaps'] ) { echo "case $i: gap plan differs\n  php " . json_encode( $g ) . "\n  ts  " . json_encode( $c['gaps'] ) . "\n"; $fail++; }
	if ( Lzcrs_Scoring::lead_band( $b['total'], $a, $c['cutoff'] ) !== $c['band'] ) { echo "case $i: band differs\n"; $fail++; }
	if ( Lzcrs_Scoring::likely_pathway( $a, $b ) !== $c['pathway'] ) { echo "case $i: pathway differs\n"; $fail++; }
}

// Strict validation: anything malformed is rejected.
$base = $cases[0]['answers'];
$bad  = array(
	'missing field'   => array_diff_key( $base, array( 'pnp' => 1 ) ),
	'unknown field'   => $base + array( 'job' => 50 ),
	'age as string'   => array_merge( $base, array( 'age' => '29' ) ),
	'age too high'    => array_merge( $base, array( 'age' => 81 ) ),
	'band not half'   => array_merge( $base, array( 'L' => 6.3 ) ),
	'band too high'   => array_merge( $base, array( 'R' => 9.5 ) ),
	'unknown edu'     => array_merge( $base, array( 'edu' => 'diploma' ) ),
	'pnp as string'   => array_merge( $base, array( 'pnp' => 'true' ) ),
	'caExp too high'  => array_merge( $base, array( 'caExp' => 6 ) ),
	'not an array'    => 'hello',
);
foreach ( $bad as $name => $in ) {
	if ( null !== Lzcrs_Scoring::validate( $in ) ) { echo "validation: '$name' was accepted\n"; $fail++; }
}

$n = count( $cases );
echo $fail ? "FAILED: $fail differences in $n cases\n" : "Parity OK: $n cases identical to the TypeScript engine; " . count( $bad ) . " bad inputs rejected\n";
exit( $fail ? 1 : 0 );
