<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

try {
	if(!is_dir(TRANSIFEX_LOCALFOLDER)) {
		@mkdir(TRANSIFEX_LOCALFOLDER, 0777, true);
		if(!is_dir(TRANSIFEX_LOCALFOLDER)) {
			throw new Exception("Unable to create folder '" . TRANSIFEX_LOCALFOLDER . "'");
		}
	}
	chdir(TRANSIFEX_LOCALFOLDER);
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
	write("Looking for downloaded for .po files... ");
	$translations = Translation::getAll();
	if(empty($translations)) {
		throw new Exception('No translations found');
	}
	write("done (" . count($translations) . " files found)\n");
	foreach($translations as $translation) {
		write("Compiling {$translation->poRelative}... ");
		$translation->compile();
		write("done.\n");
	}
	if(!is_dir(LANGCOPY_LOCALFOLDER)) {
		@mkdir(LANGCOPY_LOCALFOLDER, 0777, true);
		if(!is_dir(LANGCOPY_LOCALFOLDER)) {
			throw new Exception("Unable to create folder '" . LANGCOPY_LOCALFOLDER . "'");
		}
	}
	chdir(LANGCOPY_LOCALFOLDER);
	if(!is_dir('.git')) {
		write("Initializing git... ");
		run('git', 'clone git://github.com/' . LANGCOPY_GITHUB_OWNER . '/' . LANGCOPY_GITHUB_REPOSIORY . '.git .');
		run('git', 'checkout ' . LANGCOPY_GITHUB_BRANCH);
		write("done.\n");
	}
	else {
		write("Updading local repository... ");
		run('git', 'checkout ' . LANGCOPY_GITHUB_BRANCH);
		run('git', 'fetch origin');
		run('git', 'reset --hard origin/' . LANGCOPY_GITHUB_BRANCH);
		run('git', 'clean -f -d');
		write("done.\n");
	}
	$someChanged = false;
	foreach($translations as $translation) {
		write("Cheking changes for {$translation->poRelative}... ");
		if($translation->detectChanges()) {
			$translation->copyToGit();
			write("CHANGED!\n");
			$someChanged = true;
		}
		else {
			write("unchanged.\n");
		}
	}
	if(!$someChanged) {
		write("No change detected: git untouched.\n");
	}
	else {
		write("Creating statistics file...");
		$statsFile = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . 'stats.txt';
		if(!($hStats = @fopen($statsFile, 'wb'))) {
			throw new Exception("Error opening '$statsFile' for writing");
		}
		try {
			foreach($translations as $translation) {
				$translation->writeStats($hStats);
			}
		}
		catch(Exception $x) {
			@fclose($hStats);
			throw $x;
		}
		@fclose($hStats);
		write("done.\n");
		write("Committing to git...");
		run('git', 'add --all');
		run('git', 'commit -m "Transifex update"');
		run('git', 'push origin ' . LANGCOPY_GITHUB_BRANCH);
		write("done.\n");
	}
}
catch(Exception $x) {
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
}


class Translation {
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
		$this->poAbsolute = $poAbsolute;
		$this->poRelative = $poRelative;
		$this->moAbsolute = preg_replace('/\\.po$/i', '.mo', $this->poAbsolute);
		$this->moRelative = preg_replace('/\\.po$/i', '.mo', $this->poRelative);
		$this->poAbsoluteGit = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . $this->poRelative;
		$this->moAbsoluteGit = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . $this->poRelative;
		$this->stats = null;
	}
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
		while($item = @readdir($hDir)) {
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
						if(preg_match('/^' . preg_quote(TRANSIFEX_PROJECT, '/') . '\\.(.+)$/i', $relItem, $m)) {
							$relItem = $m[1];
						}
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
}
