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
$gitter = new Gitter('github.com', C5TT_GITHUB_LANGCOPY_OWNER, C5TT_GITHUB_LANGCOPY_REPOSITORY, C5TT_GITHUB_LANGCOPY_BRANCH, C5TT_GITHUB_LANGCOPY_WORKPATH, true);
$gitter->pullOrInitialize();

// Let's check if some translations has changed: if so let's copy the .po and .mo files to the repository.
$allResourceSlugs = array();
$changedTranslations = array();
$changedAllTranslations = array();
$changedTranslationsCount = 0;
foreach($translations as $translation) {
	if(array_search($translation->resourceSlug, $allResourceSlugs) === false) {
		$allResourceSlugs[] = $translation->resourceSlug;
		$changedTranslations[$translation->resourceSlug] = array();
		$changedAllTranslations[$translation->resourceSlug] = true;
	}
	Enviro::write("Checking changes for {$translation->getName()}... ");
	$gitFolder = Enviro::mergePath(C5TT_GITHUB_LANGCOPY_WORKPATH, $translation->resourceSlug);
	$gitPo = Enviro::mergePath($gitFolder, $translation->languageCode . '.po');
	if($translation->detectChanges($gitPo)) {
		$translation->copyTo($gitFolder, $translation->languageCode);
		Enviro::write("CHANGED!\n");
		$changedTranslations[$translation->resourceSlug][] =  $translation->languageCode;
		$changedTranslationsCount++;
	}
	else {
		Enviro::write("unchanged.\n");
		$changedAllTranslations[$translation->resourceSlug] = false;
	}
}

// Let's check for translations under GitHub but not under Transifex (they have been removed).
$gitResources = array();
foreach(new DirectoryIterator(C5TT_GITHUB_LANGCOPY_WORKPATH) as $iResource) {
	if($iResource->isDot()) {
		continue;
	}
	if($iResource->isDir()) {
		$resourceSlug = $iResource->getFilename();
		foreach(new DirectoryIterator($iResource->getPathname()) as $iTranslation) {
			if($iTranslation->isDot()) {
				continue;
			}
			if($iTranslation->isFile() && preg_match('/^(.+)\.po$/i', $iTranslation->getFilename(), $m)) {
				if(!array_key_exists($resourceSlug, $gitResources)) {
					$gitResources[$resourceSlug] = array();
				}
				$gitResources[$resourceSlug][$m[1]] = substr($iTranslation->getPathname(), 0, -3);
			}
		}
	}
}
$removedTranslations = array();
foreach($gitResources as $gitResource => $gitLanguages) {
	foreach($gitLanguages as $gitLanguage => $gitBaseFilename) {
		$found = false;
		foreach($translations as $translation) {
			if(strcasecmp($translation->resourceSlug, $gitResource) === 0) {
				if(strcasecmp($translation->languageCode, $gitLanguage) === 0) {
					$found = true;
					break;
				}
			}
			if($found) {
				break;
			}
		}
		if(!$found) {
			$removedTranslations[] = array('resource' => $gitResource, 'language' => $gitLanguage, 'baseFilename' => $gitBaseFilename);
		}
	}
}

if(($changedTranslationsCount == 0) && (count($removedTranslations) == 0)) {
	Enviro::write("No change detected: git untouched.\n");
	die(0);
}

if($changedTranslationsCount > 0) {
	// Let's generate the statistics file
	$now = gmdate('c');
	Enviro::write("Creating current statistic files...");
	$resources = array();
	foreach($translations as $translationIndex => $translation) {
		if(!array_key_exists($translation->resourceSlug, $resources)) {
			$resources[$translation->resourceSlug] = array();
		}
		$resources[$translation->resourceSlug][$translation->languageCode] = $translationStats[$translationIndex];
	}
	$xDoc = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><stats></stats>');
	$xDoc->addAttribute('project', C5TT_TRANSIFEX_PROJECT);
	$xDoc->addAttribute('updated', $now);
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
	$statsFile = Enviro::mergePath(C5TT_GITHUB_LANGCOPY_WORKPATH, 'stats-current.xml');
	if(!($hStats = @fopen($statsFile, 'wb'))) {
		throw new Exception("Error opening '$statsFile' for writing.");
	}
	fwrite($hStats, $dom->ownerDocument->saveXML());
	fclose($hStats);
	Enviro::write("done.\n");
	
	Enviro::write("Creating/updating historical statistic files...");
	$historyFile = Enviro::mergePath(C5TT_GITHUB_LANGCOPY_WORKPATH, 'stats-history.xml');
	$xDoc = null;
	if(is_file($historyFile)) {
		$xDoc = @simplexml_load_string(@preg_replace('/>\s+/', '>', @file_get_contents($historyFile)));
		if($xDoc) {
			if($xDoc->getName() != 'projects') {
				$xDoc = null;
			}
		}
	}
	if(!$xDoc) {
		$xDoc = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><projects></projects>');
	}
	$xProject = null;
	foreach($xDoc->children() as $x) {
		if(($x->getName() == 'project') && isset($x['name']) && (C5TT_TRANSIFEX_PROJECT === (string)$x['name'])) {
			$xProject = $x;
			break;
		}
	}
	if(!$xProject) {
		$xProject = $xDoc->addChild('project');
		$xProject->addAttribute('name', C5TT_TRANSIFEX_PROJECT);
	}
	$latest = array();
	foreach($xProject->xpath('./stats') as $xStats) {
		if(isset($xStats['timestamp'])) {
			$timestamp = (string)$xStats['timestamp'];
			foreach($xStats->xpath('./resource') as $xResource) {
				if(isset($xResource['name'])) {
					$resourceSlug = (string)$xResource['name'];
					foreach($xResource->xpath('./language') as $xLanguage) {
						if(isset($xLanguage['name'])) {
							$languageCode = (string)$xLanguage['name'];
							if(!isset($latest[$resourceSlug])) {
								$latest[$resourceSlug] = array();
							}
							if((!isset($latest[$resourceSlug][$languageCode])) || ($latest[$resourceSlug][$languageCode]['timestamp'] < $timestamp)) {
								$latest[$resourceSlug][$languageCode] = array(
									'timestamp' => $timestamp,
									'state' => array(
										'translated' => @intval((string)@$xLanguage['translated']),
										'untranslated' => @intval((string)@$xLanguage['untranslated']),
										'fuzzy' => @intval((string)@$xLanguage['fuzzy']),
										'total' => @intval((string)@$xLanguage['total']),
										'percentual' => @intval((string)@$xLanguage['percentual'])
									)
								);
							}
						}
					}
				}
			}
		}
	}
	$someWritten = false;
	$xStats = null;
	foreach($resources as $resourceSlug => $languages) {
		$xResource = null;
		foreach($languages as $languageCode => $stats) {
			$write = true;
			if(isset($latest[$resourceSlug])) {
				if(isset($latest[$resourceSlug][$languageCode])) {
					$prev = $latest[$resourceSlug][$languageCode]['state'];
					$write = false;
					foreach($stats as $name => $num) {
						if($prev[$name] != $num) {
							$write = true;
							break;
						}
					}
				}
			}
			if($write) {
				$someWritten = true;
				if(!$xStats) {
					$xStats = $xProject->addChild('stats');
					$xStats->addAttribute('timestamp', $now);
				}
				if(!$xResource) {
					$xResource = $xStats->addChild('resource');
					$xResource->addAttribute('name', $resourceSlug);
				}
				$xLanguage = $xResource->addChild('language');
				$xLanguage->addAttribute('name', $languageCode);
				$xLanguage->addAttribute('translated', $stats['translated']);
				$xLanguage->addAttribute('untranslated', $stats['untranslated']);
				$xLanguage->addAttribute('fuzzy', $stats['fuzzy']);
				$xLanguage->addAttribute('total', $stats['total']);
				$xLanguage->addAttribute('percentual', $stats['percentual']);
			}
		}
	}
	if($someWritten) {
		$dom = dom_import_simplexml($xDoc);
		$dom->ownerDocument->formatOutput = true;
		if(!($hStats = @fopen($historyFile, 'wb'))) {
			throw new Exception("Error opening '$historyFile' for writing.");
		}
		fwrite($hStats, $dom->ownerDocument->saveXML());
		fclose($hStats);
		Enviro::write("done (updated).\n");
	}
	else {
		Enviro::write("done (no change).\n");
	}
	
	// Let's commit and push the repository.
	$resourceMessages = array();
	foreach($allResourceSlugs as $resourceSlug) {
		if($changedAllTranslations[$resourceSlug]) {
			$resourceMessage = 'all';
		}
		else {
			$resourceMessage = implode(', ', $changedTranslations[$resourceSlug]);
		}
		$resourceMessages[] = "$resourceSlug ($resourceMessage)";
	}
	$commitMessage = 'Updated: ' . implode('; ', $resourceMessages);
	$gitter->commit($commitMessage, C5TT_GITHUB_LANGCOPY_AUTHORS);
}

if(count($removedTranslations) > 0) {
	$commitNames = array();
	foreach($removedTranslations as $removedTranslation) {
		unlink($removedTranslation['baseFilename'] . '.po');
		$fn = $removedTranslation['baseFilename'] . '.mo';
		if(is_file($fn)) {
			unlink($fn);
		}
		$commitNames[] = $removedTranslation['resource'] . '/' . $removedTranslation['language'];
	}
	$gitter->commit('Removed languages: ' . implode(', ', $commitNames), C5TT_GITHUB_LANGCOPY_AUTHORS);
}
$gitter->push();
