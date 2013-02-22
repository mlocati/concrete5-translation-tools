<?php
/** Execute a command.
 * @param string $command The command to execute.
 * @param string|array $arguments The argument(s) of the program.
 * @param int|array $goodResult Valid return code(s) of the command (default: 0).
 * @param out array $output The output from stdout/stderr of the command.
 * @return int Return the command result code.
 * @throws Exception Throws an exception in case of errors.
 */
function run($command, $arguments = '', $goodResult = 0, &$output = null) {
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
