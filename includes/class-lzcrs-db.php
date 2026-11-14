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
 * All database access for leads. Every query uses $wpdb->prepare, with %i for the table name.
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

	/*
	 * Leads live in the plugin's own table, so WordPress has no API for them and direct queries are needed.
	 * Every query is prepared, with the table name passed as an identifier (%i). Reads are kept in the object
	 * cache and invalidated whenever a lead is added, changed or deleted.
	 */

	const CACHE_GROUP = 'lzcrs_leads';

	/** Cache key for a read, tied to the last time any lead changed. */
	private static function cache_key( $name, array $args ) {
		return $name . ':' . md5( (string) wp_json_encode( $args ) ) . ':' . wp_cache_get_last_changed( self::CACHE_GROUP );
	}

	/** Marks every cached read as out of date. */
	public static function flush_cache() {
		wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	/**
	 * @param array $lead Clean lead data.
	 * @return int|false New id.
	 */
	public static function insert( array $lead ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table; reads are cached and flushed below.
		$ok = $wpdb->insert(
			self::table(),
			$lead,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		self::flush_cache();
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function get( $id ) {
		global $wpdb;
		$key = self::cache_key( 'get', array( (int) $id ) );
		$row = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table; cached above.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );
			wp_cache_set( $key, $row, self::CACHE_GROUP );
		}
		return $row;
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
		$orderby = in_array( $orderby, array( 'created_at', 'total' ), true ) ? $orderby : 'created_at';
		$order   = 'asc' === strtolower( (string) $order ) ? 'ASC' : 'DESC';
		$key     = self::cache_key( 'query', array( $f, $orderby, $order, (int) $limit, (int) $offset ) );
		$rows    = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$where = self::where( $f );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table, cached above; $where is built with prepare() and $orderby/$order are whitelisted.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i $where ORDER BY $orderby $order, id $order LIMIT %d OFFSET %d", self::table(), $limit, $offset ), ARRAY_A );
			wp_cache_set( $key, $rows, self::CACHE_GROUP );
		}
		return $rows;
	}

	public static function count( array $f = array() ) {
		global $wpdb;
		$key = self::cache_key( 'count', array( $f ) );
		$n   = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			$where = self::where( $f );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table, cached above; $where is built with prepare().
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i $where", self::table() ) );
			wp_cache_set( $key, $n, self::CACHE_GROUP );
		}
		return (int) $n;
	}

	/** Numbers for the dashboard: all leads, leads since a UTC time, hot leads, average score. */
	public static function stats( $since_utc ) {
		global $wpdb;
		$key = self::cache_key( 'stats', array( (string) $since_utc ) );
		$row = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table; cached above.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(created_at >= %s) AS recent, SUM(band = 'hot') AS hot, AVG(total) AS avg_score FROM %i", $since_utc, self::table() ), ARRAY_A );
			wp_cache_set( $key, $row, self::CACHE_GROUP );
		}
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
		$key   = self::cache_key( 'daily', array( $since ) );
		$rows  = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table; cached above.
			$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT created_at FROM %i WHERE created_at >= %s', self::table(), $since ) );
			wp_cache_set( $key, $rows, self::CACHE_GROUP );
		}
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table; cached reads are flushed below.
		$ok = false !== $wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
		self::flush_cache();
		return $ok;
	}

	public static function delete( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the plugin's own table, cached reads flushed below; $in is only "%d" placeholders, one per id.
		$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ($in)", array_merge( array( self::table() ), $ids ) ) );
		self::flush_cache();
		return $n;
	}

	public static function by_email( $email ) {
		global $wpdb;
		$key  = self::cache_key( 'by_email', array( strtolower( $email ) ) );
		$rows = wp_cache_get( $key, self::CACHE_GROUP, false, $found );
		if ( ! $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the plugin's own table; cached above.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s ORDER BY id', self::table(), strtolower( $email ) ), ARRAY_A );
			wp_cache_set( $key, $rows, self::CACHE_GROUP );
		}
		return $rows;
	}

	public static function drop() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the plugin's own table on request.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table() ) );
		self::flush_cache();
	}
}
