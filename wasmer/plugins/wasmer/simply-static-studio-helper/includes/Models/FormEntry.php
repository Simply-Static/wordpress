<?php

namespace Simply_Static_Studio\Models;

class FormEntry extends Model {
	/**
	 * Database table name.
	 *
	 * @var string
	 */
	protected static $table_name = 'form_entries';

	/**
	 * Table columns.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'          => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
		'title'       => 'VARCHAR(255) NULL',
		'form_id'     => 'BIGINT(20) UNSIGNED NULL',
		'form_plugin' => 'VARCHAR(255) NULL',
		'posted'      => 'TEXT NOT NULL',
		'created_at'  => "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'",
		'updated_at'  => "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'"
	);

	/**
	 * Indexes for columns.
	 *
	 * @var array
	 */
	protected static $indexes = array(
		'PRIMARY KEY  (id)',
		'KEY form_id (form_id)',
	);

	/**
	 * Primary key.
	 *
	 * @var string
	 */
	protected static $primary_key = 'id';

}