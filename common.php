<?php
@ini_set('error_reporting', E_ALL & ~E_DEPRECATED);
$ddtz = @date_default_timezone_get();
if((!is_string($ddtz)) || (!strlen($ddtz))) {
	$ddtz = 'UTC';
}
@date_default_timezone_set($ddtz);
@ini_set('track_errors', true);
@ini_set('html_errors', false);
@ini_set('display_errors', 'stderr');
@ini_set('display_startup_errors', true);
@ini_set('log_errors', false);
set_error_handler('errorCatcher');

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'configuration.php';

/** Catches a php error/warning and raises an exception.
* @param int $errNo The level of the error raised.
* @param string $errstr the error message.
* @param string $errfile The filename that the error was raised in.
* @param int $errline The line number the error was raised at.
* @throws Exception Throws an Exception when an error is detected during the script execution.
*/
function errorCatcher($errno, $errstr, $errfile, $errline) {
	throw new Exception("$errstr in $errfile on line $errline", $errno);
}

/** Execute a command.
 * @param string $command The command to execute.
 * @param string|array $arguments The argument(s) of the program.
 * @param int|array $goodResult Valid return code(s) of the command (default: 0).
 * @param out array $output The output from stdout/stderr of the command.
 * @return int Return the command result code.
 * @throws Exception Throws an exception in case of errors.
 */
function run($command, $arguments = '', $goodResult = 0, &$output = null) {
	if(stripos(PHP_OS, 'Win') !== 0) {
		$hereCommand = dirname(__FILE__) . DIRECTORY_SEPARATOR . $command;
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
	$output = array();
	exec($line . ' 2>&1', $output, $rc);
	if(!@is_int($rc)) {
		$rc = -1;
	}
	if(!is_array($output)) {
		$output = array();
	}
	if(is_array($goodResult)) {
		if(array_search($rc, $goodResult) === false) {
			throw new Exception("$command failed: " . implode("\n", $output));
		}
	}
	elseif($rc != $goodResult) {
		throw new Exception("$command failed: " . implode("\n", $output));
	}
	return $rc;
}

/** Writes out something
* @param string $str
* @param bool $isErr Should we write to standard error? If not we'll write to standard output.
*/
function write($str, $isErr = false) {
	$hOut = fopen($isErr ? 'php://stderr' : 'php://stdout', 'wb');
	fwrite($hOut, $str);
	fflush($hOut);
	fclose($hOut);
}
