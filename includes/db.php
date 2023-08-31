<?php
namespace JET_AI_Search;

/**
 * Database manager class
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define Base DB class
 */
class DB {

	/**
	 * Check if booking DB table already exists
	 *
	 * @var bool
	 */
	public $table_exists = null;

	/**
	 * Stores latest queried result to use it
	 *
	 * @var null
	 */
	public $latest_result = null;

	public $defaults = array();

	public $queried_ids = null;

	/**
	 * Constructor for the class
	 */
	public function __construct() {

		if ( ! empty( $_GET['ai_embeddings_install_table'] ) ) {
			add_action( 'init', array( $this, 'install_table' ) );
		}

	}

	/**
	 * Returns table name
	 * @return [type] [description]
	 */
	public function table() {
		return $this->wpdb()->prefix . 'jet_ai_embeddings_index';
	}

	/**
	 * Returns columns schema
	 * @return [type] [description]
	 */
	public function schema() {
		return array(
			'ID' => array(
				'sql' => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
				'default' => 0,
			),
			'post_id' => array(
				'sql' => 'BIGINT(20)',
				'default' => '',
			),
			'post_title' => array(
				'sql' => 'TEXT',
				'default' => '',
			),
			'post_url' => array(
				'sql' => 'TEXT',
				'default' => '',
			),
			'fragment' => array(
				'sql' => 'LONGTEXT',
				'default' => '',
			),
			'embedding' => array(
				'sql' => 'LONGTEXT', // 'sql', 'meta' etc
				'default' => null,
			),
			'source' => array(
				'sql' => 'TEXT',
				'default' => '',
			),
		);
	}

	/**
	 * Returns table schema
	 *
	 * @return [type] [description]
	 */
	public function get_table_schema() {

		$charset_collate = $this->wpdb()->get_charset_collate();
		$table           = $this->table();
		$columns_schema  = '';

		foreach ( $this->schema() as $col => $data ) {
			$columns_schema .= $col . ' ' . $data['sql'] . ',';
		}

		return "CREATE TABLE $table (
			$columns_schema
			PRIMARY KEY (ID)
		) $charset_collate;";

	}

	/**
	 * Returns table default values
	 *
	 * @return [type] [description]
	 */
	public function get_defaults() {

		if ( empty( $this->defaults ) ) {
			foreach ( $this->schema() as $col => $data ) {
				$this->defaults[ $col ] = $data['default'];
			}
		}

		return $this->defaults;

	}

	/**
	 * Insert booking
	 *
	 * @param  array  $booking [description]
	 * @return [type]          [description]
	 */
	public function insert( $data = array() ) {

		foreach ( $this->get_defaults() as $default_key => $default_value ) {
			if ( ! isset( $data[ $default_key ] ) ) {
				$data[ $default_key ] = $default_value;
			}
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = maybe_serialize( $value );
			}
		}

		$inserted = $this->wpdb()->insert( $this->table(), $data );

		if ( $inserted ) {
			return $this->wpdb()->insert_id;
		} else {
			return false;
		}

	}

	/**
	 * Insert booking
	 *
	 * @param  array  $booking [description]
	 * @return [type]          [description]
	 */
	public function insert_or_update( $data = array() ) {

		$table = $this->table();

		$keys = array_keys( $data );
		$values = array_values( $data );
		$keys = implode( ', ', $keys );
		$values = array_map( function( $item ) {
			return sprintf( '\'%s\'', $item );
		}, $values );
		$values = implode( ', ', $values );

		$update_clause = array();

		foreach ( $data as $key => $value ) {
			$update_clause[] = $key . '=\'' . $value . '\'';
		}

		$update_clause = implode( ', ', $update_clause );

		$inserted = $this->wpdb()->query( "INSERT INTO {$table} ({$keys}) VALUES ({$values}) ON DUPLICATE KEY UPDATE $update_clause" );
		
	}

	/**
	 * Update appointment info
	 *
	 * @param  array  $new_data [description]
	 * @param  array  $where    [description]
	 * @return [type]           [description]
	 */
	public function update( $new_data = array(), $where = array() ) {

		foreach ( $this->defaults as $default_key => $default_value ) {
			if ( ! isset( $data[ $default_key ] ) ) {
				$data[ $default_key ] = $default_value;
			}
		}

		foreach ( $new_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$new_data[ $key ] = maybe_serialize( $value );
			}
		}

		$this->wpdb()->update( $this->table(), $new_data, $where );

	}

	/**
	 * Delete column
	 * @return [type] [description]
	 */
	public function delete( $where = array() ) {
		$this->wpdb()->delete( $this->table(), $where );
	}

	/**
	 * Delete column
	 * @return [type] [description]
	 */
	public function truncate() {
		$table = $this->table();
		$this->wpdb()->query( "TRUNCATE TABLE $table;" );
	}

	/**
	 * Check if booking table alredy exists
	 *
	 * @return boolean [description]
	 */
	public function is_table_exists() {

		if ( null !== $this->table_exists ) {
			return $this->table_exists;
		}

		$table = $this->table();

		if ( $table === $this->wpdb()->get_var( "SHOW TABLES LIKE '$table'" ) ) {
			$this->table_exists = true;
		} else {
			$this->table_exists = false;
		}

		return $this->table_exists;
	}

	/**
	 * Try to recreate DB table by request
	 *
	 * @return void
	 */
	public function install_table() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->create_table();

	}

	/**
	 * Returns WPDB instance
	 * @return [type] [description]
	 */
	public function wpdb() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Create DB table for apartment units
	 *
	 * @return [type] [description]
	 */
	public function create_table( $delete_if_exists = false ) {

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		}

		if ( $delete_if_exists && $this->is_table_exists() ) {
			$table = $this->table();
			$this->wpdb()->query( "DROP TABLE $table;" );
		}

		if ( $this->is_table_exists() ) {
			return;
		}

		$sql = $this->get_table_schema();

		dbDelta( $sql );

	}

	/**
	 * Insert new columns into existing bookings table
	 *
	 * @param  [type] $columns [description]
	 * @return [type]          [description]
	 */
	public function insert_table_columns( $columns = array() ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table          = $this->table();
		$columns_schema = '';

		foreach ( $columns as $column ) {
			$columns_schema .= $column . ' text,';
		}

		$columns_schema = rtrim( $columns_schema, ',' );

		$sql = "ALTER TABLE $table
			ADD $columns_schema;";

		$this->wpdb()->query( $sql );

	}

	/**
	 * Check if booking DB column is exists
	 *
	 * @return [type] [description]
	 */
	public function column_exists( $column ) {

		$table = $this->table();
		return $this->wpdb()->query( "SHOW COLUMNS FROM `$table` LIKE '$column'" );

	}

	/**
	 * Delete columns into existing bookings table
	 *
	 * @param  [type] $columns [description]
	 * @return [type]          [description]
	 */
	public function delete_table_columns( $columns ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table          = $this->table();
		$columns_schema = '';

		foreach ( $columns as $column ) {
			$columns_schema .= $column . ',';
		}

		$columns_schema = rtrim( $columns_schema, ',' );

		$sql = "ALTER TABLE $table
			DROP COLUMN $columns_schema;";

		$this->wpdb()->query( $sql );

	}

	/**
	 * Add nested query arguments
	 *
	 * @param  [type]  $key    [description]
	 * @param  [type]  $value  [description]
	 * @param  boolean $format [description]
	 * @return [type]          [description]
	 */
	public function get_sub_query( $key, $value, $format = false ) {

		$query = '';
		$glue  = '';

		if ( ! $format ) {

			if ( false !== strpos( $key, '!' ) ) {
				$format = '`%1$s` != \'%2$s\'';
				$key    = ltrim( $key, '!' );
			} else {
				$format = '`%1$s` = \'%2$s\'';
			}

		}

		foreach ( $value as $child ) {
			$query .= $glue;
			$query .= sprintf( $format, esc_sql( $key ), esc_sql( $child ) );
			$glue   = ' OR ';
		}

		return $query;

	}

	/**
	 * Return count of queried items
	 *
	 * @return [type] [description]
	 */
	public function count( $args = array(), $rel = 'AND' ) {

		$table = $this->table();

		$query = "SELECT count(*) FROM $table";

		if ( ! $rel ) {
			$rel = 'AND';
		}

		$query .= $this->add_where_args( $args, $rel );

		return $this->wpdb()->get_var( $query );

	}

	/**
	 * Add where arguments to query
	 *
	 * @param array  $args [description]
	 * @param string $rel  [description]
	 */
	public function add_where_args( $args = array(), $rel = 'AND' ) {

		$query      = '';
		$multi_args = false;

		if ( ! empty( $args ) ) {

			$query  .= ' WHERE ';
			$glue    = '';
			$search  = array();
			$props   = array();

			if ( count( $args ) > 1 ) {
				$multi_args = true;
			}

			foreach ( $args as $key => $value ) {

				$format = '`%1$s` = \'%2$s\'';

				$query .= $glue;

				if ( false !== strpos( $key, '!' ) ) {
					$key    = ltrim( $key, '!' );
					$format = '`%1$s` != \'%2$s\'';
				} elseif ( false !== strpos( $key, '>=' ) ) {
					$key    = rtrim( $key, '>=' );
					$format = '`%1$s` >= %2$d';
				} elseif ( false !== strpos( $key, '>' ) ) {
					$key    = rtrim( $key, '>' );
					$format = '`%1$s` > %2$d';
				} elseif ( false !== strpos( $key, '<=' ) ) {
					$key    = rtrim( $key, '<=' );
					$format = '`%1$s` <= %2$d';
				} elseif ( false !== strpos( $key, '<' ) ) {
					$key    = rtrim( $key, '<' );
					$format = '`%1$s` < %2$d';
				}

				if ( is_array( $value ) ) {
					$query .= sprintf( '( %s )', $this->get_sub_query( $key, $value, $format ) );
				} else {
					$query .= sprintf( $format, esc_sql( $key ), esc_sql( $value ) );
				}

				$glue = ' ' . $rel . ' ';

			}

		}

		return $query;

	}

	/**
	 * Add order arguments to query
	 *
	 * @param array $args [description]
	 */
	public function add_order_args( $order = array() ) {

		$query = '';

		if ( ! empty( $order['orderby'] ) ) {

			$orderby = $order['orderby'];
			$order   = ! empty( $order['order'] ) ? $order['order'] : 'desc';
			$order   = strtoupper( $order );
			$query  .= " ORDER BY $orderby $order";

		}

		return $query;

	}

	/**
	 * Clear table data
	 * @return [type] [description]
	 */
	public function clear() {
		$table = $this->table();
		$this->wpdb()->query( "TRUNCATE `$table`;" );
	}

	/**
	 * [find_code description]
	 * @return [type] [description]
	 */
	public function get_item( $args = array() ) {

		$res = $this->query( $args );

		if ( ! $res || empty( $res ) ) {
			return false;
		} else {
			return $res[0];
		}

	}

	/**
	 * Query data from db table
	 *
	 * @return [type] [description]
	 */
	public function query( $args = array(), $limit = 0, $offset = 0, $order = array(), $rel = 'AND' ) {

		$table = $this->table();

		$query = "SELECT * FROM $table";

		if ( ! $rel ) {
			$rel = 'AND';
		}

		$query .= $this->add_where_args( $args, $rel );
		$query .= $this->add_order_args( $order );

		if ( intval( $limit ) > 0 ) {
			$limit  = absint( $limit );
			$offset = absint( $offset );
			$query .= " LIMIT $offset, $limit";
		}

		$raw = $this->wpdb()->get_results( $query, ARRAY_A );

		return $raw;

	}

	/**
	 * Query posts to return posts list to use in JS options lists
	 *
	 * @param  [type] $post_type [description]
	 * @return [type]            [description]
	 */
	public function query_posts_for_js( $post_type = 'post' ) {

		$table = $this->wpdb()->posts;
		$query = "SELECT ID AS value, post_title AS label FROM $table WHERE `post_type` = '$post_type' AND `post_status` = 'publish'";

		$raw = $this->wpdb()->get_results( $query, ARRAY_A );

		return $raw;

	}

}
