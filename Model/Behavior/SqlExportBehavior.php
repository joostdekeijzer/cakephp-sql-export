<?php
/**
 * Copyright 2014, Joost de Keijzer (http://dekeijzer.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2014, Joost de Keijzer (http://dekeijzer.org)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('ModelBehavior', 'Model');

/**
 * Searchable behavior
 *
 */
class SqlExportBehavior extends ModelBehavior {
	const VERSION = '1.0';
	const MAXCHAR = 100000;

	public function dump( Model $model, $options = array() ) {
		$db = $model->getDataSource();

		$rows = Hash::extract( $db->fetchAll( sprintf('SELECT * FROM `%s`;', $model->table) ), sprintf('{n}.%s', $model->table) );

		$sql = self::dumpHeader($db->description, $db->getVersion(), $model->schemaName);

		$sql .= sprintf("--\n-- Dumping data for table `%s`\n--\n\n", $model->table);
		$sql .= self::dumpSql( $db, $model->table );

		// dump relevant translatable data
		if( $model->Behaviors->loaded('Translate') ) {
			$i18nTable = 'i18n'; // the default
			// TODO: handle http://book.cakephp.org/2.0/en/core-libraries/behaviors/translate.html#multiple-translation-tables

			$sql .= sprintf("\n\n--\n-- Dumping relevant data for model '%s' from `%s`\n--\n\n", $model->name, $i18nTable);
			$sql .= self::dumpSql( $db, $i18nTable, sprintf( '`model` = "%s"', $model->name ) );
		}

		$sql .= self::dumpFooter();

		return $sql;
	}

	public static function dumpDatabase( $model = null, $sourceName = 'default' ) {
		//$connectionObjectNames = array_keys( ConnectionManager::enumConnectionObjects() );

		if( is_string( $model ) && !empty( $model ) ) {
			$sourceName = $model;
		}
		$dataSource = ConnectionManager::getDataSource( $sourceName );
		$tableNames = $dataSource->listSources();

		$sql = self::dumpHeader($dataSource->description, $dataSource->getVersion(), $dataSource->config['database'] );
		foreach( $tableNames as $table ) {
			$sql .= sprintf("--\n-- Dumping data for table `%s`\n--\n\n", $table);
			$sql .= self::dumpSql( $dataSource, $table );
		}

		$sql .= self::dumpFooter();

		return $sql;
	}

	protected static function dumpSql( $dataSource, $table, $select = '' ) {
		$where = '';
		if( strlen($select) > 0 ) {
			$where = ' WHERE ' . $select;
		}
		$rows = Hash::extract( $dataSource->fetchAll( sprintf('SELECT * FROM `%s`%s;', $table, $where) ), sprintf('{n}.%s', $table) );

		$sql = '';
		$line = array();
		$charCount = 0;
		foreach( $rows as $row ) {
			$values = array();
			foreach( $row as $value ) {
				$values[] = $dataSource->value($value);
			}
			$line[] = '(' . implode(',', $values) . ')';
			$charCount += strlen(end($line));
			if( $charCount > SqlExportBehavior::MAXCHAR ) {
				$sql .= sprintf( "INSERT INTO `%s` VALUES %s;\n", $table, implode(',', $line) );
				$line = array();
				$charCount = 0;
			}
		}
		if( $charCount > 0 ) {
			$sql .= sprintf( "INSERT INTO `%s` VALUES %s;\n", $table, implode(',', $line) );
		}

		return $sql;
	}

	protected static function dumpHeader( $dbType, $dbVersion, $schema ) {
		return sprintf( "-- CakePHP SqlExport plugin\n-- Version %s\n--\n-- Host: %s\n-- Generation Time: %s\n-- Server Type: %s\n-- Server Version: %s\n\n/*!40101 SET NAMES utf8 */;\n\n--\n-- Database: `%s`\n--\n\n", SqlExportBehavior::VERSION, $_SERVER['SERVER_ADDR'], date('r'), $dbType, $dbVersion, $schema );
	}

	protected static function dumpFooter() {
		return sprintf("\n--\n-- Done\n-- %s\n--\n", date('r') );
	}
}
