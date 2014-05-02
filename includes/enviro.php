<?php

/** Environment-related functions. */
class Enviro {
	/** Merges OS paths.
	* @param {string} Any number of paths to be merged.
	* @return string
	* @throws Exception Throws an Exception if no arguments is given.
	*/
	public static function mergePath() {
		$args = func_get_args();
		switch(count($args)) {
			case 0:
				throw new Exception(__CLASS__ . '::' . __METHOD__ . ': missing arguments');
			case 1:
				return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $args[0]);
			default:
				$path = '';
				foreach($args as $arg) {
					if(strlen($arg)) {
						$arg = str_replace('\\', '/', $arg);
						if(!strlen($path)) {
							$path = $arg;
						}
						else {
							$path = rtrim($path, '/') . '/' . ltrim($arg, '/');
						}
					}
				}
				return str_replace('/', DIRECTORY_SEPARATOR, $path);
		}
	}
	/** Execute a command.
	* @param string $command The command to execute.
	* @param string|array $arguments The argument(s) of the program.
	* @param int|array $goodResult Valid return code(s) of the command (default: 0).
	* @param out array $output The output lines from stdout/stderr of the command.
	* @return int Return the command result code.
	* @throws Exception Throws an exception in case of errors.
	*/
	public static function run($command, $arguments = '', $goodResult = 0, &$output = null) {
		if(stripos(PHP_OS, 'Win') === 0) {
			$hereCommand = self::mergePath(dirname(__FILE__), $command . '.exe');
			if(is_file($hereCommand)) {
				$command = $hereCommand;
			}
		}
		else {
			$hereCommand = self::mergePath(dirname(__FILE__), $command);
			if(is_file($hereCommand)) {
				$command = $hereCommand;
			}
		}
		$line = escapeshellarg($command);
		if(is_array($arguments)) {
			if(count($arguments)) {
				$line .= ' ' . implode(' ', $arguments);
			}
		}
		else {
			$arguments = (string)$arguments;
			if(strlen($arguments)) {
				$line .= ' ' . $arguments;
			}
		}
		if(@ob_start() === false) {
			throw new Exception('ob_start failed');
		}
		@system($line . ' 2>&1', $rc);
		$ob = @ob_get_contents();
		@ob_end_clean();
		if(!@is_int($rc)) {
			$rc = -1;
		}
		if(!is_string($ob)) {
			$ob = '';
		}
		$output = explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $ob)));
		if(is_array($goodResult)) {
			if(array_search($rc, $goodResult) === false) {
				throw new Exception("$command failed: " . $ob);
			}
		}
		elseif($rc != $goodResult) {
			throw new Exception("$command failed: " . $ob);
		}
		return $rc;
	}
	/** Writes out something to standard output/standard error.
	* @param string $str
	* @param bool $isErr Should we write to standard error? If not we'll write to standard output.
	*/
	public static function write($str, $isErr = false) {
		$hOut = fopen($isErr ? 'php://stderr' : 'php://stdout', 'wb');
		fwrite($hOut, $str);
		fflush($hOut);
		fclose($hOut);
	}
	/** Deletes a folder, if it exists.
	* @param string $folder The folder to delete.
	* @param bool $justEmptyIt [default: false] Set to true to just empty the folder, false to delete it.
	* @throws Exception Throws an Exception if $folder is not a deletable folder.
	*/
	public static function deleteFolder($folder, $justEmptyIt = false) {
		if(is_file($folder)) {
			throw new Exception("'$folder' is a file, not a folder");
		}
		if(!is_dir($folder)) {
			return;
		}
		$s = realpath($folder);
		if($s === false) {
			throw new Exception("realpath() failed on '$folder'");
		}
		$folder = $s;
		if(!is_writable($folder)) {
			throw new Exception("'$folder' is not a writable folder");
		}
		@clearstatcache();
		$subFolders = array();
		if(!($hDir = @opendir($folder))) {
			throw new Exception("Error while opening '$folder'");
		}
		try {
			while(($item = @readdir($hDir)) !== false) {
				switch($item) {
					case '.':
					case '..':
						break;
					default:
						$absItem = self::mergePath($folder, $item);
						if(is_dir($absItem)) {
							$subFolders[] = $absItem;
						}
						else {
							if(!@unlink($absItem)) {
								throw new Exception("Error deleting file '$absItem'");
							}
							@clearstatcache();
						}
						break;
				}
			}
		}
		catch(Exception $x) {
			@closedir($hDir);
			throw $x;
		}
		@closedir($hDir);
		foreach($subFolders as $subFolder) {
			self::deleteFolder($subFolder, false);
		}
		if(!$justEmptyIt) {
			if(!@rmdir($folder)) {
				throw new Exception("rmdir() failed on '$folder'");
			}
			@clearstatcache();
		}
	}
}
