<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

$resetTransifex = false;
try {
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
			case '--name':
				if(strlen($value)) {
					$args['name'] = $value;
				}
				break;
			case '--cloneof':
				if(strlen($value)) {
					$args['cloneof'] = $value;
				}
				break;
			default:
				showHelp();
				die(1);
		}
	}
	if(!(isset($args['cloneof']) && isset($args['name']))) {
		showHelp();
		die(1);
	}
	pullTransifex(true);
	write("Listing translations to clone... ");
	$translationsToClone = array();
	$otherSlugs = array();
	foreach(Translation::getAll() as $translation) {
		if(strcasecmp($translation->resourceSlug, $args['name']) === 0) {
			throw new Exception("There's already a translation for '{$args['name']}': '{$translation->poRelative}'");
		}
		if(strcasecmp($translation->resourceSlug, $args['cloneof']) === 0) {
			$translationsToClone[] = $translation;
		}
		else {
			$otherSlugs[$translation->resourceSlug] = true;
		}
	}
	if(empty($translationsToClone)) {
		if(empty($otherSlugs)) {
			$error = 'No translations found.';
		}
		else {
			$error = "No current translations found with the slug '{$args['cloneof']}'.\nAvailable slugs:\n" . implode("\n", array_keys($otherSlugs));
		}
		throw new Exception($error);
	}
	write("done ( " . count($translationsToClone) . " .po files found).\n");
	write("Check existance of resource '{$args['name']}'... ");
	run('tx', 'status --resource=' . escapeshellarg(TRANSIFEX_PROJECT . '.' . $args['name']));
	write("passed.\n");
	write("Check existance of resource '{$args['cloneof']}'... ");
	run('tx', 'status --resource=' . escapeshellarg(TRANSIFEX_PROJECT . '.' . $args['cloneof']));
	write("passed.\n");
	write("Copying " . count($translationsToClone) . " .po files... ");
	foreach($translationsToClone as $translationToClone) {
		$translationToClone->cloneIntoResource($args['name']);
		$resetTransifex = true;
	}
	write("done.\n");
	write("Pushing new translations for '{$args['name']}'... ");
	run('tx', 'push -t -r ' . escapeshellarg(TRANSIFEX_PROJECT . '.' . $args['name']));
	write("done.\n");
}
catch(Exception $x) {
	if($resetTransifex) {
		try {
			deleteFolder(TRANSIFEX_LOCALFOLDER);
		}
		catch(Exception $x) {
		}
	}
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
}

function showHelp() {
	global $argv;
	write("Syntax: php {$argv[0]} --name=<NewResourceSlug> --cloneof=<OldResourceSlug>\n", true);
}
