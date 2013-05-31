<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'startup.php';

// Some initialization
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'gitter.php');
$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);

// Let's pull all the Transifex data
$transifexer->pull(C5TT_TRANSIFEX_PROJECT, C5TT_TRANSIFEX_WORKPATH);

// Let's list all the .po files
Enviro::write("Looking for downloaded for .po files... ");
$translations = TransifexerTranslation::getAll(C5TT_TRANSIFEX_WORKPATH);
if(empty($translations)) {
	throw new Exception('No translations found');
}
foreach($translations as $translationIndex => $translation) {
	if(strcasecmp($translation->projectSlug, C5TT_TRANSIFEX_PROJECT) !== 0) {
		throw new Exception("The translation {$translation->getName()} is not for the project " . C5TT_TRANSIFEX_PROJECT . ".");
	}
}
Enviro::write("done (" . count($translations) . " translations found)\n");

// Let's generate the .mo files and collect the statistical data.
$translationStats = array();
foreach($translations as $translationIndex => $translation) {
	Enviro::write("Compiling {$translation->getName()}... ");
	$translationStats[$translationIndex] = $translation->compile();
	Enviro::write("done.\n");
}

// Let's pull the latest branch version of the repository containing the translations
$gitter = new Gitter('github.com', C5TT_GITHUB_LANGCOPY_OWNER, C5TT_GITHUB_LANGCOPY_REPOSITORY, C5TT_GITHUB_LANGCOPY_BRANCH, C5TT_GITHUB_LANGCOPY_WORKPATH, C5TT_GITHUB_LANGCOPY_USERNAME);
$gitter->pullOrInitialize();

// Let's check if some translations has changed: if so let's copy the .po and .mo files to the repository.
$someChanged = false;
foreach($translations as $translation) {
	Enviro::write("Cheking changes for {$translation->getName()}... ");
	$gitFolder = Enviro::mergePath(C5TT_GITHUB_LANGCOPY_WORKPATH, $translation->resourceSlug);
	$gitPo = Enviro::mergePath($gitFolder, $translation->languageCode . '.po');
	if($translation->detectChanges($gitPo)) {
		$translation->copyTo($gitFolder, $translation->languageCode);
		Enviro::write("CHANGED!\n");
		$someChanged = true;
	}
	else {
		Enviro::write("unchanged.\n");
	}
}
if(!$someChanged) {
	Enviro::write("No change detected: git untouched.\n");
	die(0);
}

// Let's generate the statistics file
Enviro::write("Creating statistics file...");
$resources = array();
foreach($translations as $translationIndex => $translation) {
	if(!array_key_exists($translation->resourceSlug, $resources)) {
		$resources[$translation->resourceSlug] = array();
	}
	$resources[$translation->resourceSlug][$translation->languageCode] = $translationStats[$translationIndex];
}
$xDoc = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><stats></stats>');
$xDoc->addAttribute('project', C5TT_TRANSIFEX_PROJECT);
foreach($resources as $resourceSlug => $languages) {
	$resourceNode = $xDoc->addChild('resource');
	$resourceNode->addAttribute('name', $resourceSlug);
	foreach($languages as $languageCode => $stats) {
		$languageNode = $resourceNode->addChild('language');
		$languageNode->addAttribute('name', $languageCode);
		$languageNode->addAttribute('translated', $stats['translated']);
		$languageNode->addAttribute('untranslated', $stats['untranslated']);
		$languageNode->addAttribute('fuzzy', $stats['fuzzy']);
		$languageNode->addAttribute('total', $stats['total']);
		$languageNode->addAttribute('percentual', $stats['percentual']);
	}
}
$dom = dom_import_simplexml($xDoc);
$dom->ownerDocument->formatOutput = true;
$statsFile = Enviro::mergePath(C5TT_GITHUB_LANGCOPY_WORKPATH, 'stats.xml');
if(!($hStats = @fopen($statsFile, 'wb'))) {
	throw new Exception("Error opening '$statsFile' for writing.");
}
fwrite($hStats, $dom->ownerDocument->saveXML());
fclose($hStats);
Enviro::write("done.\n");

// Let's commit and push the repository.
$gitter->commit('Transifex update');
$gitter->push();
