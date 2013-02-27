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

/** Pulls data from Transifex into the TRANSIFEX_LOCALFOLDER folder.
* @param bool $reset [default: false] Force reload of all Translations (useful to clean dirty local .po files).
* @throws Exception
*/
function pullTransifex($reset = false) {
	if(!is_dir(TRANSIFEX_LOCALFOLDER)) {
		@mkdir(TRANSIFEX_LOCALFOLDER, 0777, true);
		if(!is_dir(TRANSIFEX_LOCALFOLDER)) {
			throw new Exception("Unable to create folder '" . TRANSIFEX_LOCALFOLDER . "'");
		}
	}
	chdir(TRANSIFEX_LOCALFOLDER);
	if($reset) {
		deleteFolder(TRANSIFEX_LOCALFOLDER . DIRECTORY_SEPARATOR . '.tx');
		deleteFolder(TRANSIFEX_LOCALFOLDER . DIRECTORY_SEPARATOR . 'translations');
	}
	if(!is_dir('.tx')) {
		write("Initializing Transifex... ");
		run('tx', 'init --host=' . escapeshellarg(TRANSIFEX_HOST) . ' --user=' . escapeshellarg(TRANSIFEX_USERNAME) . ' --pass=' . escapeshellarg(TRANSIFEX_PASSWORD));
		write("done.\n");
	}
	write("Updating Transifex resource list... ");
	run('tx', 'set --auto-remote ' . escapeshellarg(TRANSIFEX_HOST . '/projects/p/' . TRANSIFEX_PROJECT . '/'));
	write("done.\n");
	write("Fetching Transifex resources... ");
	run('tx', 'pull --all --mode=developer');
	write("done.\n");
}

/** Represents a .po (and .mo) translation. */
class Translation {
	/** The resource slug. 
	* @var string
	*/
	public $resourceSlug;
	/** The language code.
	 * @var string
	 */
	public $languageCode;
	/** Absolute location of the .po file (in the Transifex folder).
	* @var string
	*/
	public $poAbsolute;
	/** Relative location of the .po file.
	* @var string
	*/
	public $poRelative;
	/** Absolute location of the .mo file (in the Transifex folder).
	* @var string
	*/
	public $moAbsolute;
	/** Relative location of the .mo file.
	* @var string
	*/
	public $moRelative;
	/** Absolute location of the .po file (in the GitHub folder).
	* @var string
	*/
	public $poAbsoluteGit;
	/** Absolute location of the .mo file (in the GitHub folder).
	* @var string
	*/
	public $moAbsoluteGit;
	/** Statistics (available only after compilation).
	* @var null|array If compiled, it's an array with:<ul>
	*	<li>int <b>translated</b></li>
	*	<li>int <b>untranslated</b></li>
	*	<li>int <b>fuzzy</b></li>
	*	<li>int <b>total</b></li>
	*	<li>int <b>percentual</b></li>
	* </ul>
	*/
	public $stats;
	/** Initializes the instance.
	* @param string $poAbsolute
	* @param string $poRelative
	*/
	private function __construct($poAbsolute, $poRelative) {
		if(!preg_match('/^' . preg_quote(TRANSIFEX_PROJECT, '/') . '\\.([^' . preg_quote(DIRECTORY_SEPARATOR, '/') . ']+)' . preg_quote(DIRECTORY_SEPARATOR, '/') . '(([^' . preg_quote(DIRECTORY_SEPARATOR, '/') . ']+)\\.po)$/i', $poRelative, $m)) {
			throw new Exception("Invalid relative po file name: '$poRelative'");
		}
		$this->resourceSlug = $m[1];
		$this->languageCode = $m[3];
		$this->poRelative = $this->resourceSlug . DIRECTORY_SEPARATOR . $m[2];
		$this->poAbsolute = $poAbsolute;
		$this->moAbsolute = preg_replace('/\\.po$/i', '.mo', $this->poAbsolute);
		$this->moRelative = preg_replace('/\\.po$/i', '.mo', $this->poRelative);
		$this->poAbsoluteGit = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . $this->poRelative;
		$this->moAbsoluteGit = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . $this->poRelative;
		$this->stats = null;
	}
	/** Compiles the .po file into the .mo file (and retrieve stats).
	* @throws Exception
	*/
	public function compile() {
		run('msgfmt', '--statistics --check-format --check-header --check-domain --output-file=' . escapeshellarg($this->moAbsolute) . ' ' . escapeshellarg($this->poAbsolute), 0, $outputLines);
		$stats = null;
		foreach($outputLines as $outputLine) {
			if(preg_match('/(\\d+) translated messages/', $outputLine, $matchTranslated) && preg_match('/(\\d+) untranslated messages/', $outputLine, $matchUntranslated)) {
				$stats = array(
						'translated' => intval($matchTranslated[1]),
						'untranslated' => intval($matchUntranslated[1]),
						'fuzzy' => 0
				);
				if(preg_match('/(\\d+) fuzzy translations/', $outputLine, $matchFuzzy)) {
					$stats['fuzzy'] = intval($matchFuzzy[1]);
				}
				$stats['total'] = $stats['translated'] + $stats['untranslated'] + $stats['fuzzy'];
				$stats['percentual'] = ($stats['translated'] == $stats['total']) ? 100 : ($stats['total'] ? floor($stats['translated'] * 100 / $stats['total']) : 0);
				break;
			}
		}
		if(!$stats) {
			throw new Exception("Unable to parse statistics from the output\n" . implode("\n", $outputLines));
		}
		$this->stats = $stats;
	}
	/** Returns all the translations found in the Transifex local folder.
	* @return Translation[]
	* @throws Exception
	*/
	public static function getAll() {
		return self::lookForTranslations(TRANSIFEX_LOCALFOLDER . DIRECTORY_SEPARATOR . 'translations', '');
	}
	/** Scans a folder for translations.
	* @param string $absFolder
	* @param string $relFolder
	* @return Translation[]
	* @throws Exception
	*/
	private static function lookForTranslations($absFolder, $relFolder) {
		$class = __CLASS__;
		$translations = array();
		$subFolders = array();
		if(!($hDir = @opendir($absFolder))) {
			throw new Exception("Unable to open folder '$relFolder'");
		}
		while(($item = @readdir($hDir)) !== false) {
			switch($item) {
				case '.':
				case '..':
					break;
				default:
					$absItem = $absFolder . DIRECTORY_SEPARATOR . $item;
					$relItem = ltrim($relFolder . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR) . $item;
					if(is_dir($absItem)) {
						$subFolders[] = array('abs' => $absItem, 'rel' => $relItem);
					}
					elseif(preg_match('/.\\.po$/i', $item)) {
						$translations[] = new $class($absItem, $relItem);
					}
					break;
			}
		}
		@closedir($hDir);
		foreach($subFolders as $subFolder) {
			$translations = array_merge($translations, self::lookForTranslations($subFolder['abs'], $subFolder['rel']));
		}
		usort($translations, array($class, 'sort'));
		return $translations;
	}
	/** Translations sorter
	* @param Translation $a
	* @param Translation $b
	* @return int
	*/
	private static function sort($a, $b) {
		return strcasecmp($a->poRelative, $b->poRelative);
	}
	/** Checks if the Transifex and GitHub .po files are different.
	* @return boolean
	* @throws Exception
	*/
	public function detectChanges() {
		if(!is_file($this->poAbsoluteGit)) {
			return true;
		}
		$txData = @file_get_contents($this->poAbsolute);
		if($txData === false) {
			throw new Exception("Error reading file '{$this->poAbsolute}'");
		}
		$ghData = @file_get_contents($this->poAbsoluteGit);
		if($ghData === false) {
			throw new Exception("Error reading file '{$this->poAbsoluteGit}'");
		}
		if(strcmp($txData, $ghData) === 0) {
			return false;
		}
		$txData = preg_replace('/(POT-Creation-Date|PO-Revision-Date): [0-9:\\-+ ]+/', '', $txData);
		$ghData = preg_replace('/(POT-Creation-Date|PO-Revision-Date): [0-9:\\-+ ]+/', '', $ghData);
		if(strcmp($txData, $ghData) === 0) {
			return false;
		}
		return true;
	}
	/** Copy the .po and .mo files from the local Transifex folder to the local GitHub folder.
	* @throws Exception
	*/
	public function copyToGit() {
		$destFolder = substr($this->poAbsoluteGit, 0, strrpos($this->poAbsoluteGit, DIRECTORY_SEPARATOR));
		if(!@is_dir($destFolder)) {
			@mkdir($destFolder, 0777, true);
			if(!@is_dir($destFolder)) {
				throw new Exception("Unable to create folder '$destFolder'");
			}
		}
		if(!@copy($this->poAbsolute, $this->poAbsoluteGit)) {
			throw new Exception("Error copying from\n{$this->poAbsolute}\nto\n{$this->poAbsoluteGit}");
		}
		$destFolder = substr($this->moAbsoluteGit, 0, strrpos($this->moAbsoluteGit, DIRECTORY_SEPARATOR));
		if(!@is_dir($destFolder)) {
			@mkdir($destFolder, 0777, true);
			if(!@is_dir($destFolder)) {
				throw new Exception("Unable to create folder '$destFolder'");
			}
		}
		if(!@copy($this->moAbsolute, $this->moAbsoluteGit)) {
			throw new Exception("Error copying from\n{$this->moAbsolute}\nto\n{$this->moAbsoluteGit}");
		}
	}
	/** Writes the statistical info to an open file.
	* @param resource $hFile
	* @throws Exception
	*/
	public function writeStats($hFile) {
		if(!@fwrite($hStats, str_replace(DIRECTORY_SEPARATOR, '/', $this->poRelative) . "\t{$this->stats['translated']}/{$$this->stats['total']} translated ({$$this->stats['percentual']}%)\n")) {
			throw new Exception("Error writing statistics to file");
		}
	}
	/** Copy the .po file in a new Transifex resource.
	* @param string $newResouceSlug The slug of the new resource.
	* @throws Exception
	*/
	public function cloneIntoResource($newResouceSlug) {
		$destFolder = TRANSIFEX_LOCALFOLDER . DIRECTORY_SEPARATOR . 'translations' . DIRECTORY_SEPARATOR . TRANSIFEX_PROJECT . '.' . $newResouceSlug;
		if(!is_dir($destFolder)) {
			@mkdir($destFolder, 0777, true);
			if(!is_dir($destFolder)) {
				throw new Exception("Unable to create folder '" . $destFolder . "'");
			}
		}
		$cloneName = $destFolder . DIRECTORY_SEPARATOR . $this->languageCode . '.po';
		if(is_file($cloneName)) {
			throw new Exception("The file '$cloneName' already exists");
		}
		if(!@copy($this->poAbsolute, $cloneName)) {
			throw new Exception("Error copying from\n{$this->poAbsolute}\nto\n$cloneName");
		}
	}
}

/** Deletes a folder, if exists.
* @param string $folderName The folder to delete.
* @throws Exception Throws an Exception if $folderName is not a deletable folder. 
*/
function deleteFolder($folder) {
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
					$absItem = $folder . DIRECTORY_SEPARATOR . $item;
					if(is_dir($absItem)) {
						$subFolders[] = $absItem;
					}
					else {
						if(!@unlink($absItem)) {
							throw new Exception("Error deleting file '$absItem'");
						}
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
		deleteFolder($subFolder);
	}
	if(!@rmdir($folder)) {
		throw new Exception("rmdir() failed on '$folder'");
	}
}
