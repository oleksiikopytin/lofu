<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Upgrade\CreateTable;

class CreateTermBatchMembersTable implements \IWPML_Upgrade_Command {

	const TABLE_NAME = 'icl_term_batch_members';

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$this->result = self::create_table_if_not_exists( $wpdb );

		return $this->result;
	}

	public static function create_table_if_not_exists( $wpdb ) {
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return CreateTable::create(
			$wpdb,
			$table_name,
			'
			CREATE TABLE IF NOT EXISTS `' . esc_sql( $table_name ) . '` (
			  `batch_job_id` BIGINT(20) UNSIGNED NOT NULL,
			  `term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL,
			  `term_rid` BIGINT(20) UNSIGNED NOT NULL,
			  `target_lang` VARCHAR(10) NOT NULL,
			  `words` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			  `state` VARCHAR(16) NOT NULL,
			  `reason` TEXT NULL,
			  PRIMARY KEY (`batch_job_id`, `term_rid`),
			  KEY `term_rid` (`term_rid`, `state`)
			) ENGINE=INNODB'
		);
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
