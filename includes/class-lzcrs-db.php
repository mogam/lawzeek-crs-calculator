<?php
/**
 * The leads table: install, upgrade and queries.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All database access for leads. Every query uses $wpdb->prepare or fixed SQL.
 */
class Lzcrs_Db {
	const DB_VERSION = '1';
	const STATUSES   = array( 'new', 'contacted', 'booked', 'retained', 'lost' );
	const BANDS      = array( 'hot', 'warm', 'cold' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'lzcrs_leads';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			name varchar(120) NOT NULL,
			email varchar(200) NOT NULL,
			phone varchar(30) DEFAULT NULL,
			answers longtext NOT NULL,
			total smallint(5) unsigned NOT NULL,
			band varchar(8) NOT NULL,
			pathway varchar(60) NOT NULL,
			rules_version varchar(20) NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'new',
			consent_privacy tinyint(1) NOT NULL DEFAULT 1,
			consent_marketing tinyint(1) NOT NULL DEFAULT 0,
			consent_at datetime NOT NULL,
			page_url varchar(255) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY email (email(100)),
			KEY total (total)
			) $charset;"
		);
		update_option( 'lzcrs_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'lzcrs_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array $lead Clean lead data.
	 * @return int|false New id.
	 */
	public static function insert( array $lead ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			$lead,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed.
	}

	/**
	 * Builds the WHERE clause for list filters.
	 *
	 * @param array $f band, search.
	 * @return string Prepared SQL fragment.
	 */
	private static function where( array $f ) {
		global $wpdb;
		$sql = 'WHERE 1=1';
		if ( ! empty( $f['band'] ) && in_array( $f['band'], self::BANDS, true ) ) {
			$sql .= $wpdb->prepare( ' AND band = %s', $f['band'] );
		}
		if ( ! empty( $f['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $f['search'] ) . '%';
			$sql .= $wpdb->prepare( ' AND (name LIKE %s OR email LIKE %s)', $like, $like );
		}
		return $sql;
	}

	/**
	 * @param array $f       band, search.
	 * @param string $orderby created_at|total.
	 * @param string $order   asc|desc.
	 * @param int    $limit   Rows.
	 * @param int    $offset  Offset.
	 */
	public static function query( array $f, $orderby = 'created_at', $order = 'desc', $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table   = self::table();
		$orderby = in_array( $orderby, array( 'created_at', 'total' ), true ) ? $orderby : 'created_at';
		$order   = 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
		$where   = self::where( $f );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table, where (prepared) and order are fixed/whitelisted.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table $where ORDER BY $orderby $order, id $order LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
	}

	public static function count( array $f = array() ) {
		global $wpdb;
		$table = self::table();
		$where = self::where( $f );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where is prepared.
	}

	/** Numbers for the dashboard: all leads, leads since a UTC time, hot leads, average score. */
	public static function stats( $since_utc ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(created_at >= %s) AS recent, SUM(band = 'hot') AS hot, AVG(total) AS avg_score FROM $table", $since_utc ), ARRAY_A );
		return array(
			'total'  => (int) $row['total'],
			'recent' => (int) $row['recent'],
			'hot'    => (int) $row['hot'],
			'avg'    => null === $row['avg_score'] ? null : (int) round( (float) $row['avg_score'] ),
		);
	}

	/**
	 * Leads per day for the last $days days, keyed by site-timezone date (Y-m-d), oldest first.
	 *
	 * @param int $days Number of days, today included.
	 * @return array<string,int>
	 */
	public static function daily( $days = 30 ) {
		global $wpdb;
		$days  = max( 1, (int) $days );
		$tz    = wp_timezone();
		$utc   = new DateTimeZone( 'UTC' );
		$today = new DateTimeImmutable( 'today', $tz );
		$out   = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ $today->modify( "-$i days" )->format( 'Y-m-d' ) ] = 0;
		}
		$since = $today->modify( '-' . ( $days - 1 ) . ' days' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed.
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT created_at FROM $table WHERE created_at >= %s", $since ) );
		foreach ( (array) $rows as $when ) {
			$day = ( new DateTimeImmutable( $when, $utc ) )->setTimezone( $tz )->format( 'Y-m-d' );
			if ( isset( $out[ $day ] ) ) {
				++$out[ $day ];
			}
		}
		return $out;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		return false !== $wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$table = self::table();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed table name; $in is only "%d" placeholders, one per id.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE id IN ($in)", $ids ) );
	}

	public static function by_email( $email ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s ORDER BY id", strtolower( $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function drop() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}
