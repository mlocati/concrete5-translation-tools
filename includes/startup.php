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
register_shutdown_function('executionDone');

/** Ends the execution because of the specified error.
* @param string $description The error description.
* @param int|null $code The error code.
* @param string $file The file where the error occurred.
* @param int|null $line The line where the error occurred.
* @param string $trace The call stack to the error location.
 */
function stopForError($description, $code = null, $file = '', $line = null, $trace = '') {
	if(defined('C5TT_IS_WEB') && C5TT_IS_WEB) {
		$level = @ob_get_level();
		while(is_int($level) && ($level > 0)) {
			@ob_end_clean();
			$newLevel = @ob_get_level();
			if((!is_int($newLevel)) || ($newLevel >= $level)) {
				break;
			}
			$level = $newLevel;
		}
		header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
		header('Content-Type: text/plain; charset=UTF-8', true);
		echo $description;
		die();
	}
	$text = '';
	$text .= "$description\n";
	if(!empty($code)) {
		$text .= "CODE : $code\n";
	}
	if(strlen($file)) {
		$text .= "FILE : $file\n";
	}
	if(!empty($line)) {
		$text .= "LINE : $line\n";
	}
	if(strlen($trace)) {
		$text .= "TRACE:\n$trace\n";
	}
	$stderr = fopen('php://stderr', 'wb');
	fwrite($stderr, $text);
	fflush($stderr);
	fclose($stderr);
	if(((!defined('C5TT_NOTIFYERRORS')) || C5TT_NOTIFYERRORS) && defined('C5TT_NOTIFYERRORS_TO') && strlen(C5TT_NOTIFYERRORS_TO)) {
		$headers = array();
		$headers[] = 'From: ' . C5TT_EMAILSENDERADDRESS;
		$subject = 'concrete5 translation tools error';
		global $argv;
		if(isset($argv) && is_array($argv) && count($argv)) {
			$subject .= " ({$argv[0]})";
		}
		mail(C5TT_NOTIFYERRORS_TO, $subject, $text, implode("\r\n", $headers));
	}
	if(class_exists('Locker')) {
		Locker::releaseAll();
	}
	die(empty($code) ? 1 : $code);
}

/** Catches a php error/warning and raises an exception.
* @param int $errNo The level of the error raised.
* @param string $errstr the error message.
* @param string $errfile The filename that the error was raised in.
* @param int $errline The line number the error was raised at.
* @throws Exception Throws an Exception when an error is detected during the script execution.
*/
function errorCatcher($errno, $errstr, $errfile, $errline) {
	stopForError($errstr, $errno, $errfile, $errline);
}

/** Catches a php Exception and dies.
* @param Exception $exception
*/
function exceptionCatcher($exception) {
	stopForError($exception->getMessage(), $exception->getCode(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString());
}

/** Shutdown function that catches errors not intercepted by errorCatcher (for instance, a call to an undefined function). */
function executionDone() {
	if($err = @error_get_last()) {
		switch($err['type']) {
			default:
				stopForError($err['message'], $err['type'], $err['file'], $err['line']);
				break;
		}
	}
	if(class_exists('Locker')) {
		Locker::releaseAll();
	}
}

// Let's include
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'configuration.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'enviro.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'locker.php';

// Let's ensure that we run just one concrete5-translation-tools script at a time.
new Locker(C5TT_LOCKFILE);
