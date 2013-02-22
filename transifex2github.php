<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'credentials.php';
define('TRANSIFEX_HOST', 'https://www.transifex.com');
define('TRANSIFEX_PROJECT', 'mlocati-test');

define('GITHUB_REPOSITORY', 'mlocati-potest');
define('GITHUB_BRANCH', 'master');

define('TRANSIFEX_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'transifex');
define('GITHUB_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'github');

try {
	if(!is_dir(TRANSIFEX_FOLDER)) {
		@mkdir(TRANSIFEX_FOLDER, 0777, true);
		if(!is_dir(TRANSIFEX_FOLDER)) {
			throw new Exception("Unable to create folder '" . TRANSIFEX_FOLDER . "'");
		}
	}
	chdir(TRANSIFEX_FOLDER);
	if(!is_dir('.tx')) {
		write("Initializing Transifex... ");
		run('tx', 'init --host=' . escapeshellarg(TRANSIFEX_HOST) . ' --user=' . escapeshellarg(TRANSIFEX_USER) . ' --pass=' . escapeshellarg(TRANSIFEX_PASSWORD));
		write("done.\n");
	}
	$downloadedFolder = TRANSIFEX_FOLDER . DIRECTORY_SEPARATOR . 'translations';
	if(is_dir($downloadedFolder)) {
		@rmdir($downloadedFolder);
	}
	write("Updating Transifex resource list... ");
	run('tx', 'set --auto-remote ' . escapeshellarg(TRANSIFEX_HOST . '/projects/p/' . TRANSIFEX_PROJECT . '/'));
	write("done.\n");
	write("Fetching Transifex resources... ");
	run('tx', 'pull --all --mode=developer');
	write("done.\n");
	write("Looking downloaded for .po files... ");
	$downloadedPOs = lookForTranslations($downloadedFolder, '');
	if(empty($downloadedPOs)) {
		throw new Exception('No files found');
	}
	write("done (" . count($downloadedPOs) . " files found)\n");
	$compiledMOs = array();
	$downloadedInfos = array();
	foreach($downloadedPOs as $index => $downloadedPO) {
		write("Compiling {$downloadedPO['rel']}... ");
		$compiledMO = array(
			'abs' => preg_replace('/\\.po$/i', '.mo', $downloadedPO['abs']),
			'rel' => preg_replace('/\\.po$/i', '.mo', $downloadedPO['rel'])
		);
		run('msgfmt', '--statistics --check-format --check-header --check-domain --output-file=' . escapeshellarg($compiledMO['abs']) . ' ' . escapeshellarg($downloadedPO['abs']), 0, $outputLines);
		$downloadedInfo = null;
		foreach($outputLines as $outputLine) {
			if(preg_match('/(\\d+) translated messages/', $outputLine, $matchTranslated) && preg_match('/(\\d+) untranslated messages/', $outputLine, $matchUntranslated)) {
				$downloadedInfo = array(
					'translated' => intval($matchTranslated[1]),
					'untranslated' => intval($matchUntranslated[1]),
					'fuzzy' => 0
				);
				if(preg_match('/\\d+ fuzzy translations/', $outputLine, $matchFuzzy)) {
					$downloadedInfo['fuzzy'] = intval($matchFuzzy[1]);
				}
				$downloadedInfo['total'] = $downloadedInfo['translated'] + $downloadedInfo['untranslated'] + $downloadedInfo['fuzzy'];
				$downloadedInfo['percentual'] = ($downloadedInfo['translated'] == $downloadedInfo['total']) ? 100 : ($downloadedInfo['total'] ? floor($downloadedInfo['translated'] * 100 / $downloadedInfo['total']) : 0);
				break;
			}
		}
		if(!$downloadedInfo) {
			throw new Exception("Unable to parse statistics from the output\n" . implode("\n", $outputLines));
		}
		$compiledMOs[$index] = $compiledMO;
		$downloadedInfos[$index] = $downloadedInfo;
		write("done.\n");
	}
	if(!is_dir(GITHUB_FOLDER)) {
		@mkdir(GITHUB_FOLDER, 0777, true);
		if(!is_dir(GITHUB_FOLDER)) {
			throw new Exception("Unable to create folder '" . GITHUB_FOLDER . "'");
		}
	}
	chdir(GITHUB_FOLDER);
	if(!is_dir('.git')) {
		write("Initializing git... ");
		run('git', 'clone git://github.com/' . GITHUB_USER . '/' . GITHUB_REPOSITORY . '.git .');
		run('git', 'checkout master');
		write("done.\n");
	}
	else {
		write("Updading local repository... ");
		run('git', 'checkout master');
		run('git', 'fetch origin');
		run('git', 'reset --hard origin/master');
		run('git', 'clean -f -d');
		write("done.\n");
	}
	$someChanged = false;
	foreach($downloadedPOs as $index => $downloadedPO) {
		write("Cheking changes for {$downloadedPO['rel']}... ");
		$gitPO = GITHUB_FOLDER . DIRECTORY_SEPARATOR . $downloadedPO['rel'];
		if(poChanged($downloadedPO['abs'], $gitPO)) {
			$destFolder = substr($gitPO, 0, strrpos($gitPO, DIRECTORY_SEPARATOR));
			if(!@is_dir($destFolder)) {
				@mkdir($destFolder, 0777, true);
				if(!@is_dir($destFolder)) {
					throw new Exception("Unable to create folder '$destFolder'");
				}
			}
			if(!@copy($downloadedPO['abs'], $gitPO)) {
				throw new Exception("Error copying from\n{$downloadedPO['abs']}\nto\n$gitPO");
			}
			$gitMO = GITHUB_FOLDER . DIRECTORY_SEPARATOR . $compiledMOs[$index]['rel'];
			$destFolder = substr($gitMO, 0, strrpos($gitMO, DIRECTORY_SEPARATOR));
			if(!@is_dir($destFolder)) {
				@mkdir($destFolder, 0777, true);
				if(!@is_dir($destFolder)) {
					throw new Exception("Unable to create folder '$destFolder'");
				}
			}
			if(!@copy($compiledMOs[$index]['abs'], $gitMO)) {
				throw new Exception("Error copying from\n{$compiledMOs[$index]['abs']}\nto\n$gitMO");
			}
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
		$statsFile = GITHUB_FOLDER . DIRECTORY_SEPARATOR . 'stats.txt';
		if(!($hStats = @fopen($statsFile, 'wb'))) {
			throw new Exception("Error opening '$statsFile' for writing");
		}
		foreach($downloadedPOs as $index => $downloadedPO) {
			$downloadedInfo = $downloadedInfos[$index];
			if(!@fwrite($hStats, str_replace(DIRECTORY_SEPARATOR, '/', $downloadedPO['rel']) . "\n\t{$downloadedInfo['translated']}/{$downloadedInfo['total']} translated ({$downloadedInfo['percentual']}%)\n")) {
				@fclose($hStats);
				throw new Exception("Error writing to '$statsFile'");
			}
		}
		@fclose($hStats);
		write("done.\n");
		write("Committing to git...");
		run('git', 'add --all');
		run('git', 'commit -m "Transifex update"');
		run('git', 'push origin master');
		write("done.\n");
	}
}
catch(Exception $x) {
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
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
function write($str, $isErr = false) {
	$hOut = fopen($isErr ? 'php://stderr' : 'php://stdout', 'wb');
	fwrite($hOut, $str);
	fflush($hOut);
	fclose($hOut);
}
function lookForTranslations($absFolder, $relFolder) {
	$poFiles = array();
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
					$poFiles[] = array('abs' => $absItem, 'rel' => $relItem);
				}
				break;
		}
	}
	@closedir($hDir);
	foreach($subFolders as $subFolder) {
		$poFiles = array_merge($poFiles, lookForTranslations($subFolder['abs'], $subFolder['rel']));
	}
	usort($poFiles, 'sortAbsRelArray');
	return $poFiles;
}
function sortAbsRelArray($a, $b) {
	return strcasecmp($a['rel'], $a['rel']);
}
function poChanged($tx, $gh) {
	if(!is_file($gh)) {
		return true;
	}
	$txData = @file_get_contents($tx);
	if($txData === false) {
		throw new Exception("Error reading file '$tx'");
	}
	$ghData = @file_get_contents($gh);
	if($ghData === false) {
		throw new Exception("Error reading file '$gh'");
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
