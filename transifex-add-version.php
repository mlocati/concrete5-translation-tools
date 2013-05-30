<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'startup.php';

$args = parseArguments();

require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'tempfolder.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'gettext.php');

$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);

Enviro::write("Retrieving info on the source resource '{$args['source']}'... ");
$sourceInfo = $transifexer->getResourceInfo(C5TT_TRANSIFEX_PROJECT, $args['source'], true);
Enviro::write("done.\n");

Enviro::write("Retrieving info on the destination resource '{$args['destination']}'... ");
try {
	$destinationInfo = $transifexer->getResourceInfo(C5TT_TRANSIFEX_PROJECT, $args['destination']);
}
catch(TransifexerException $x) {
	if($x->getCode() == TransifexerException::TRANSIFEX_BAD_COMMAND) {
		$destinationInfo = null;
	}
	else {
		throw $x;
	}
}
Enviro::write("done.\n");
if($destinationInfo) {
	if(array_key_exists('pot', $args)) {
		throw new Exception("The .pot file can't be specified, since the resource {$args['destination']} already exists.");
	}
}
else {
	if(!array_key_exists('pot', $args)) {
		throw new Exception("The .pot file must be specified, since the resource {$args['destination']} does not exist.");
	}
}

$transifexer->pull(C5TT_TRANSIFEX_PROJECT, C5TT_TRANSIFEX_WORKPATH, true);

Enviro::write("Listing translations to clone... ");
$translationsToClone = array();
$otherProjects = array();
$otherResources = array();
foreach(TransifexerTranslation::getAll(C5TT_TRANSIFEX_WORKPATH) as $translation) {
	if(strcasecmp($translation->projectSlug, C5TT_TRANSIFEX_PROJECT) === 0) {
		if(strcasecmp($translation->resourceSlug, $args['source']) === 0) {
			$translationsToClone[] = $translation;
		}
		else {
			$otherResources[$translation->resourceSlug] = true;
		}
	}
	else {
		$otherProjects[$translation->projectSlug] = true;
	}
}
if(empty($translationsToClone)) {
	if(!empty($otherResources)) {
		$error = "No current translations found with the slug '{$args['source']}'.\nAvailable slugs:\n" . implode("\n", array_keys($otherResources));
	}
	elseif(!empty($otherProjects)) {
		$error = "No resources found for project '" . C5TT_TRANSIFEX_PROJECT . "'.\nAvailable projects::\n" . implode("\n", array_keys($otherProjects));
	}
	else {
		$error = 'No translations found.';
	}
	throw new Exception($error);
}
Enviro::write("done (" . count($translationsToClone) . " .po files found).\n");

if($destinationInfo) {
	$tempFolder = new TempFolder();
	$tempTransifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);
	$tempTransifexer->pull(C5TT_TRANSIFEX_PROJECT, $tempFolder->getName(), false, $args['destination'], true);
	$potFile = '';
	foreach(TransifexerTranslation::getAll($tempFolder->getName()) as $tempTranslation) {
		if(strcasecmp($tempTranslation->projectSlug, C5TT_TRANSIFEX_PROJECT) === 0) {
			if(strcasecmp($tempTranslation->resourceSlug, $args['destination']) === 0) {
				if(strcasecmp($tempTranslation->languageCode, $destinationInfo['source_language_code']) === 0) {
					$potFile = $tempTranslation->poPath;
					break;
				}
			}
		}
	}
	if(!strlen($potFile)) {
		throw new Exception("Unable to find the source .po file for the resource '{$args['destination']}' in language '{$destinationInfo['source_language_code']}'.");
	}
}
else {
	Enviro::write("Creating resource '{$args['destination']}'... ");
	$options = array();
	$options['slug'] = $args['destination'];
	$options['name'] = $args['destination'];
	$options['accept_translations'] = $sourceInfo['accept_translations'] ? true : false;
	$options['i18n_type'] = 'PO';
	$options['category'] = $sourceInfo['category'];
	$options['content'] = @file_get_contents($args['pot']);
	if($options['content'] === false) {
		throw new Exception("Unable to read content of file '{$args['pot']}'.");
	}
	$transifexer->pull(C5TT_TRANSIFEX_PROJECT, C5TT_TRANSIFEX_WORKPATH);
	$destinationInfo = $transifexer->createResource(C5TT_TRANSIFEX_PROJECT, $options);
	$potFile = $args['pot'];
	Enviro::write("done.\n");
}

Enviro::write('Determining .po properties... ');
$potProperties = array();
$props = Gettext::getPoProperties($potFile);
foreach(array('Project-Id-Version', 'POT-Creation-Date') as $copy) {
	if(isset($props[$copy])) {
		$potProperties[$copy] = $props[$copy];
	}
}
Enviro::write("done.\n");
$resetTransifex = false;
$poTempFolder = TempFolder::getDefault();
try {
	Enviro::write("Copying " . count($translationsToClone) . " .po files... ");
	foreach($translationsToClone as $translationToClone) {
		$destinationPO = TransifexerTranslation::getFilePath(C5TT_TRANSIFEX_WORKPATH, C5TT_TRANSIFEX_PROJECT, $args['destination'], $translationToClone->languageCode);
		if(is_file($destinationPO)) {
			$poProperties = Gettext::getPoProperties($destinationPO);
			$mergedPO = $poTempFolder->getNewFile();
			Enviro::run('msgcat', array(
				'--use-first', // Use first available translation for each message. Don't merge several translations into one.
				'--force-po', // Always write an output file even if it contains no message. 
				'--no-location', // Do not write ‘#: filename:line’ lines.
				'--no-wrap', // Do not break long message lines.
				'--output-file=' . escapeshellarg($mergedPO), // Write output to specified file.
				escapeshellarg($destinationPO),
				escapeshellarg($translationToClone->poPath)
			));
			$finalPO = $poTempFolder->getNewFile();
			Enviro::run('msgmerge', array(
				'--no-fuzzy-matching', // Do not use fuzzy matching when an exact match is not found.
				'--previous', // Keep the previous msgids of translated messages, marked with '#|', when adding the fuzzy marker to such messages.
				'--lang=' . $translationToClone->languageCode. // Specify the 'Language' field to be used in the header entry
				'--force-po', // Always write an output file even if it contains no message.
				'--add-location', // Generate '#: filename:line' lines.
				'--no-wrap', // Do not break long message lines
				'--output-file=' . escapeshellarg($finalPO), // Write output to specified file.
				escapeshellarg($mergedPO),
				escapeshellarg($potFile)
			));
			$finalProperties = array_merge($poProperties, $potProperties);
			Gettext::setPoProperties($finalProperties, $finalPO, true, $destinationPO);
		}
		else {
			Gettext::setPoProperties($potProperties, $translationToClone->poPath, true, $destinationPO);
		}
		$resetTransifex = true;
	}
	Enviro::write("done.\n");
	Enviro::write("Pushing new translations for '{$args['destination']}'... ");
	$transifexer->push(C5TT_TRANSIFEX_WORKPATH, C5TT_TRANSIFEX_PROJECT, $args['destination']);
	Enviro::write("done.\n");
}
catch(Exception $x) {
	if($resetTransifex) {
		try {
			Enviro::deleteFolder(C5TT_TRANSIFEX_WORKPATH);
		}
		catch(Exception $x) {
		}
	}
	throw $x;
}

function parseArguments() {
	global $argv;
	$args = array();
	foreach($argv as $argi => $arg) {
		if($argi == 0) {
			continue;
		}
		$p = strpos($arg, '=');
		$name = strtolower(($p === false) ? $arg : substr($arg, 0, $p));
		$value = ($p === false) ? '' : substr($arg, $p + 1);
		switch($name) {
			case '--source':
				if(strlen($value)) {
					$args['source'] = $value;
				}
				break;
			case '--destination':
				if(strlen($value)) {
					$args['destination'] = $value;
				}
				break;
			case '--pot':
				if(strlen($value)) {
					if(!is_file($value)) {
						throw new Exception("Unable to find the file '$value'.");
					}
					$args['pot'] = $value;
				}
				break;
			default:
				showHelp();
				die(1);
		}
	}
	if(!(isset($args['source']) && isset($args['destination']))) {
		showHelp();
		die(1);
	}
	return $args;
}
function showHelp() {
	global $argv;
	Enviro::write(<<<EOT
Syntax: php {$argv[0]} --source=<OldResourceSlug> --destination=<NewResourceSlug> [--pot=<PathToPotFile>]
Where PathToPotFile is a local .pot file, to be specified if and only if the new resource is to be created.
EOT
	, true);
}
