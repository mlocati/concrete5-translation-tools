<?php
// Let's setup the PHP enviromnent
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
set_exception_handler('exceptionCatcher');

/** Catches a php error/warning and raises an exception.
* @param int $errNo The level of the error raised.
* @param string $errstr the error message.
* @param string $errfile The filename that the error was raised in.
* @param int $errline The line number the error was raised at.
* @throws Exception Throws an Exception when an error is detected during the script execution.
*/
function errorCatcher($errno, $errstr, $errfile, $errline) {
	$stderr = fopen('php://stderr', 'wb');
	fwrite($stderr, "ERROR: " . $errstr . "\n");
	fwrite($stderr, "CODE: " . $errno . "\n");
	fwrite($stderr, "FILE: " . $errfile . "\n");
	fwrite($stderr, "LINE: " . $errline . "\n");
	fflush($stderr);
	fclose($stderr);
	die($errno ? $errno : 1);
}

/** Catches a php Exception and die.
* @param Exception $exception
*/
function exceptionCatcher($exception) {
	$stderr = fopen('php://stderr', 'wb');
	fwrite($stderr, "EXCEPTION: " . $exception->getMessage() . "\n");
	fwrite($stderr, "CODE: " . $exception->getCode() . "\n");
	fwrite($stderr, "FILE: " . $exception->getFile() . "\n");
	fwrite($stderr, "LINE: " . $exception->getLine() . "\n");
	fwrite($stderr, "TRACE:\n" . $exception->getTraceAsString() . "\n");
	fflush($stderr);
	fclose($stderr);
	die($exception->getCode() ? $exception->getCode() : 1);
}

// Let's include
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'configuration.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'enviro.php';
