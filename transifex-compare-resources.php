<?php
define('C5TT_NOTIFYERRORS', false);
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'startup.php';

if(!defined('STDIN')) {
	stopForError("Missing STDIN... Console input needed.");
}

// Check commands
Enviro::run('msgcat', '--version');
Enviro::run('msgcomm', '--version');

require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'tempfolder.php');

$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);

$resources = array();

global $argv;
switch(count($argv)) {
	case 1:
		$rList = $transifexer->getResources(C5TT_TRANSIFEX_PROJECT);
		for($i = 0; $i < 2; $i++) {
			echo "Specify the " . ($i ? "second" : "first") . " resource:\n";
			$map = array();
			foreach($rList as $r) {
				if(($i > 0) && ($r['slug'] == $resources[0]['slug'])) {
					continue;
				}
				$index = count($map);
				echo ($index + 1) . ": {$r['slug']} - {$r['name']}\n";
				$map[] = $r;
			}
			echo "X: Abort\n\n";
			for(;;) {
				echo "Your choice? ";
				$s = strtoupper(trim(fgets(STDIN)));
				if($s === 'X') {
					echo "Abort.\n";
					die(0);
				}
				$n = is_numeric($s) ? @intval($s) : 0;
				if(($n < 1) || ($n > count($map))) {
					echo "Invalid choice.\n";
				}
				else {
					$resources[] = $map[$n - 1];
					echo "\n";
					break;
				}
			}
		}
		break;
	case 3:
		$rList = $transifexer->getResources(C5TT_TRANSIFEX_PROJECT);
		foreach($argv as $argi => $arg) {
			if($argi == 0) {
				continue;
			}
			$found = false;
			foreach($rList as $r) {
				if(strcasecmp($r['slug'], $arg) === 0) {
					$found = $r;
					break;
				}
			}
			if(!$found) {
				stopForError("Invalid resource slug: $arg");
			}
			if(count($resources) && ($resources[0]['slug'] == $found['slug'])) {
				stopForError("You have to specify two different resource slugs!");
			}
			$resources[] = $found;
		}
		break;
	default:
		echo 'Syntax: ' . $argv[0] . ' with no arguments or with two arguments (the two Transifex resources to compare)';
		die(0);
}

$tempFolder = new TempFolder();
for($i = 0; $i < 2; $i++) {
	echo "Downloading '{$resources[$i]['name']}'... ";
	$source = $transifexer->getSource(C5TT_TRANSIFEX_PROJECT, $resources[$i]['slug']);
	$filename = $tempFolder->getNewFile();
	$hFile = @fopen($filename, 'wb');
	if(!$hFile) {
		throw new Exception("Unable to open $filename for writing.");
	}
	if(@fwrite($hFile, $source) === false) {
		@fclose($hFile);
		throw new Exception("Error writing to file $filename");
	}
	fclose($hFile);
	$resources[$i]['sourceFilename'] = $filename;
	echo "done.\n";
}
echo 'Merging the source files... ';
$mergedFile = $tempFolder->getNewFile();
Enviro::run(
	'msgcat',
	array(
		'--use-first',
		'--no-wrap',
		'--output-file=' . escapeshellarg($mergedFile),
		escapeshellarg($resources[0]['sourceFilename']),
		escapeshellarg($resources[1]['sourceFilename'])
	)
);
echo "done.\n";
$missingFile = $tempFolder->getNewFile();
for($resourceIndex = 0; $resourceIndex < 2; $resourceIndex++) {
	echo "Calculating missing translations in '{$resources[$resourceIndex]['name']}'... ";
	if(is_file($missingFile)) {
		@unlink($missingFile);
	}
	Enviro::run(
		'msgcomm',
		array(
			'--force-po',
			'--no-wrap',
			'--omit-header',
			'--less-than=2',
			'--output-file=' . escapeshellarg($missingFile),
			escapeshellarg($mergedFile),
			escapeshellarg($resources[$resourceIndex]['sourceFilename'])
		)
	);
	if(!is_file($missingFile)) {
		throw new Exception("Unable to find file $missingFile");
	}
	$size = @filesize($missingFile);
	if($size === false) {
		throw new Exception("Unable to determine the size of the file $missingFile");
	}
	$missings = array();
	if($size > 0) {
		$hFile = @fopen($missingFile, 'rb');
		if(!$hFile) {
			throw new Exception("Unable to open $filename for reading.");
		}
		$contents = @fread($hFile, $size);
		fclose($hFile);
		if($contents === false) {
			throw new Exception("Unable to read from file $filename");
		}
		// Merge splitted lines
		while(preg_match('/^([^\\n"]*)"([^\\n]*)"[ \\t]*\\n[ \\t]*"([^\\n]*)"[ \\t]*\n/m', $contents, $matches)) {
			$contents = str_replace($matches[0], "{$matches[1]}\"{$matches[2]}{$matches[3]}\"\n", $contents);
		}
		// Read items
		$found = @preg_match_all('/^((?:#[^\\n]*\\n+)*)(?:[ \\t]*msgctxt[ \\t]+"([^\\n]*)"[ \\t]*\\n)?[ \\t]*msgid[ \\t]+"([^\\n]*)"[ \\t]*\\n(?:[ \\t]*msgid_plural[ \\t]+"([^\\n]*)"[ \\t]*\\n)?/m', $contents, $matches);
		for($i = 0; $i < $found; $i++) {
			$missings[] = array(
				'comments' => trim($matches[1][$i]),
				'context' => $matches[2][$i],
				'msgid' => $matches[3][$i],
				'msgid_plural' => $matches[4][$i]
			);
		}
	}
	$resources[$resourceIndex]['missings'] = $missings;
	echo "done.\n";
}

for(;;) {
	echo "\n\n######## RESULTS ########\n";
	$accept = array();
	for($resourceIndex = 0; $resourceIndex < 2; $resourceIndex++) {
		$resource = $resources[$resourceIndex];
		$n = count($resource['missings']);
		echo "\n";
		if($n > 0) {
			echo "The resource \"{$resource['name']}\" [{$resource['slug']}] has $n translations less than the other resource.\n";
			echo "Enter " . ($resourceIndex + 1) . " to view them.\n";
			$accept[] = strval($resourceIndex + 1);
		}
		else {
			echo "The resource \"{$resource['name']}\" [{$resource['slug']}] does not have less translations than the other resource.\n";
		}
	}
	if(empty($accept)) {
		break;
	}
	echo "\nPress X to quit.\n\n";
	for(;;) {
		echo "Your choice? ";
		$s = strtoupper(trim(fgets(STDIN)));
		if($s === 'X') {
			echo "Abort.\n";
			die(0);
		}
		if(array_search($s, $accept, true) === false) {
			echo "Invalid choice.\n";
		}
		else {
			$resource = $resources[intval($s) - 1];
			foreach($resource['missings'] as $i => $m) {
				$s = '';
				if(false && strlen($m['comments'])) {
					$s .= "\tComments: {$m['comments']}\n";
				}
				if(strlen($m['context'])) {
					$s .= "\tContext: {$m['context']}\n";
				}
				if(strlen($m['msgid_plural'])) {
					$s .= "\tSingular: {$m['msgid']}\n";
					$s .= "\tPlural  : {$m['msgid_plural']}\n";
				}
				else {
					$s .= "\tText: {$m['msgid']}\n";
				}
				echo "Difference " . ($i + 1) . ":\n$s\n\n";
			}
			echo "Press ENTER."; fgets(STDIN);
			break;
		}
	}
}
