<?php

class DB {
	/**
	* @throws Exception
	* @return mysqli
	*/
	private static function getConnection() {
		static $cn;
		if(!isset($cn)) {
			if(!C5TTConfiguration::$database) {
				throw new Exception('C5TTConfiguration::$database not set');
			}
			$c = @new mysqli(C5TTConfiguration::$database->host, C5TTConfiguration::$database->username, C5TTConfiguration::$database->password, C5TTConfiguration::$database->name);
			if($c->connect_errno) {
				throw new Exception('Database connection error: ' . $c->connect_error);
			}
			try {
				if(@$c->set_charset('utf8') === false) {
					throw new Exception('Error setting charset: ' . $c->connect_error);
				}
				$cn = $c;
			}
			catch(Exception $x) {
				try {
					@$c->close();
				}
				catch(Exception $foo) {
				}
				throw $x;
			}
		}
		return $cn;
	}
	/**
	* @param bool $mode
	* @throws Exception
	*/
	public static function setAutocommit($mode) {
		$cn = self::getConnection();
		if(@$cn->autocommit($mode ? true : false) === false) {
			throw new Exception('Setting autocommit failed: ' . $cn->error);
		}
	}
	/**
	* @throws Exception
	*/
	public static function commit() {
		$cn = self::getConnection();
		if(@$cn->commit() === false) {
			throw new Exception('Commit failed: ' . $cn->error);
		}
	}
	/**
	* @throws Exception
	*/
	public static function rollback() {
		$cn = self::getConnection();
		if(@$cn->rollback() === false) {
			throw new Exception('Rollback failed: ' . $cn->error);
		}
	}
	/**
	* @param string $arg
	* @param bool $emptyToNull
	* @throws Exception
	* @return string
	*/
	public static function escape($arg, $emptyToNull = false) {
		$arg = is_null($arg) ? '' : strval($arg);
		if($emptyToNull && (!strlen($arg))) {
			return 'NULL';
		}
		else {
			return "'" . self::getConnection()->real_escape_string($arg) . "'";
		}
	}
	/**
	* @param string $sql
	* @throws Exception
	* @return mysqli_result|true
	*/
	public static function query($sql) {
		$cn = self::getConnection();
		$rs = @$cn->query($sql);
		if($rs === false) {
			throw new Exception('Query failed: ' . $cn->error);
		}
		return $rs;
	}
}
