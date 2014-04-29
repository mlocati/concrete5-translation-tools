<?php
require_once dirname(__FILE__) . '/includes/startup.php';

// Let's include the dependencies
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'transifexer.php');
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'locale-name.php');
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'tempfolder.php');

// Let's initialize some variable
$transifexer = new Transifexer(C5TTConfiguration::$transifexHost, C5TTConfiguration::$transifexUsername, C5TTConfiguration::$transifexPassword);
$devResources = array();
foreach(C5TTConfiguration::$devBranches as $devBranch) {
	$devResources[] = $devBranch->transifexResource;
}

// Let's list the Transifex resources
Enviro::write("Looking for local translation files... ");
$locales = array();
$txTranslations = TransifexerTranslation::getAll(C5TTConfiguration::getTransifexWorkpath());
$txResources = array();
if(empty($txTranslations)) {
	throw new Exception('No translations found');
}
foreach($txTranslations as $txTranslation) {
	if(strcasecmp($txTranslation->projectSlug, C5TTConfiguration::$transifexProject) !== 0) {
		throw new Exception("The translation {$txTranslation->getName()} is not for the project " . C5TTConfiguration::$transifexProject . ".");
	}
	if(!in_array($txTranslation->resourceSlug, $devResources, true)) {
		if(!array_key_exists($txTranslation->resourceSlug, C5TTConfiguration::$transifexReleased)) {
			throw new Exception('Don\'t know which is the c5 version for the Transifex resource \'' . $txTranslation->resourceSlug . '\': you have to update C5TTConfiguration::$transifexReleased.');
		}
	}
	if(!array_key_exists($txTranslation->resourceSlug, $txResources)) {
		$txResources[$txTranslation->resourceSlug] = array();
	}
	$txResources[$txTranslation->resourceSlug][$txTranslation->languageCode] = $txTranslation;
	if(!array_key_exists($txTranslation->languageCode, $locales)) {
		$locales[$txTranslation->languageCode] = LocaleName::decode($txTranslation->languageCode);
	}
}
// Some consistency check
foreach(C5TTConfiguration::$transifexReleased as $txResource => $c5versions) {
	if(!in_array($txResource, $devResources)) {
		throw new Exception("The Transifex resource '$txResource' is defined as a development version and for c5 versions " . implode(', ', $c5versions));
	}
	if(!array_key_exists($txResource, $txResources)) {
		throw new Exception('The Transifex resource \'' . $txResource .'\' is defined in C5TTConfiguration::$devBranches but it\'s not used in Transifex');
	}
}
foreach($devResources as $dev) {
	if(!array_key_exists($dev, $txResources)) {
		throw new Exception('The Transifex resource \'' . $dev . '\' is defined in C5TTConfiguration::$transifexReleased but it\'s not used in Transifex');
	}
}
Enviro::write("done (" . count($txTranslations) . " translations found for " . count($locales) . " languages)\n");
// Let's create the shown names of versions
$shownVersions = array();
foreach(C5TTConfiguration::$transifexReleased as $txResource => $c5versions) {
	$shownVersions[] = array('tx' => $txResource, 'c5' => $c5versions);
}
usort($shownVersions, 'sortByC5Versions');
Enviro::write("Removing old files (keeping the last one)... ");
if(!is_dir(C5TTConfiguration::getCoreTranslationsPath())) {
	@mkdir(C5TTConfiguration::getCoreTranslationsPath(), 0777, true);
	if(!is_dir(C5TTConfiguration::getCoreTranslationsPath())) {
		throw new Exeption('Unable to create folder ' . C5TTConfiguration::getCoreTranslationsPath());
	}
}
$oldDirs = array();
if(!($hDir = @opendir(C5TTConfiguration::getCoreTranslationsPath()))) {
	throw new Exception('Error reading from ' . C5TTConfiguration::getCoreTranslationsPath());
}
try {
	while(($item = @readdir($hDir)) !== false) {
		if(preg_match('/^\\d{9,11}$/', $item) && is_dir(Enviro::mergePath(C5TTConfiguration::getCoreTranslationsPath(), $item))) {
			$oldDirs[] = $item;
		}
	}
}
catch(Exception $x) {
	@closedir($hDir);
	throw $x;
}
@closedir($hDir);
usort($oldDirs, 'sortOldFolders');
for($i = 0; $i < count($oldDirs) - 1; $i++) {
	Enviro::deleteFolder(Enviro::mergePath(C5TTConfiguration::getCoreTranslationsPath(), $oldDirs[$i]));
}
Enviro::write("done.\n");
Enviro::write("Creating temporary data\n");
$info = array();
$info['updated'] = time();
for(;;) {
	$destDataFolder = Enviro::mergePath(C5TTConfiguration::getCoreTranslationsPath(), strval($info['updated']));
	if(!file_exists($destDataFolder)) {
		break;
	}
	$info['updated']++;
}
$info['locales'] = $locales;
$info['dev_versions'] = array();
foreach(C5TTConfiguration::$devBranches as $devBranch) {
	$info['dev_versions'][$devBranch->transifexResource] = 'Development (' . $devBranch->version . ')';
}
$info['prod_versions'] = $shownVersions;
$info['stats'] = array();
$tempFolder = new TempFolder();
$zipFileNames = array();
foreach($txResources as $txResourceId => $txFiles) {
	$info['stats'][$txResourceId] = array();
	foreach(array_keys($locales) as $localeId) {
		Enviro::write("\t$txResourceId.$localeId... ");
		if(!array_key_exists($localeId, $txFiles)) {
			throw new Exception("Locale '$localeId' not found for resource '$txResourceId'.");
		}
		$info['stats'][$txResourceId][$localeId] = $txFiles[$localeId]->compile();
		$poDate = false;
		$poFileData = @file_get_contents($txFiles[$localeId]->poPath);
		if($poFileData === false) {
			throw new Exception('Error reading file ' . $txFiles[$localeId]->poPath);
		}
		$poFileData = "\n" . str_replace("\r", "\n", str_replace("\r\n", "\n", $poFileData));
		if(preg_match('/\\nmsgid[ \\t]+"[^"]/', $poFileData, $m)) {
			$poFileData = substr($poFileData, 0, strpos($poFileData, $m[0]));
		}
		if(preg_match('/\\n[ \\t]*"PO-Revision-Date[ \\t]*:[ \\t]*(\\d\\d\\d\\d-\\d\\d-\\d\\d \\d\\d:\\d\\d[+\\-]\\d\\d\\d\\d)[^\\d]/', $poFileData, $m)) {
			$poDate = @strtotime($m[1]);
		}
		$info['stats'][$txResourceId][$localeId]['updated'] = $poDate ? $poDate : null;
		$zipFileName = Enviro::mergePath($tempFolder->getName(), "$txResourceId-$localeId.zip");
		$zip = new ZipArchive();
		$ec = $zip->open($zipFileName, ZIPARCHIVE::CREATE);
		if($ec !== true) {
			throw new Exception("Error creating zip file: $ec");
		}
		if(!$zip->addFile($txFiles[$localeId]->moPath, "languages/$localeId/LC_MESSAGES/messages.mo")) {
			throw new Exception("Error adding file to zip file");
		}
		if(!$zip->close()) {
			throw new Exception("Error closing the zip file");
		}
		$zipFileNames[] = $zipFileName;
		Enviro::write("done.\n");
	}
}
$tempInfofile = $tempFolder->getNewFile(true);
file_put_contents($tempInfofile, str_replace("\r\n", "\n", "<?php
header('Last-Modified: " . gmdate('D, d M Y H:i:s') . " GMT');
header('Expires: " . gmdate('D, d M Y H:i:s', strtotime('+1 day')) . " GMT');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
?>" . json_encode($info)));
Enviro::write("Publishing files... ");
$destDataFolder = Enviro::mergePath(C5TTConfiguration::getCoreTranslationsPath(), $info['updated']);
@mkdir($destDataFolder, 0777, true);
if(!is_dir($destDataFolder)) {
	throw new Exception('Unable to create folder ' . $destDataFolder);
}
try {
	foreach($zipFileNames as $zipFileName) {
		if(!@rename($zipFileName, Enviro::mergePath($destDataFolder, basename($zipFileName)))) {
			throw new Exception('Error publishing ' . $zipFileName);
		}
	}
	if(!@rename($tempInfofile, Enviro::mergePath(C5TTConfiguration::getCoreTranslationsPath(), 'list.php'))) {
		throw new Exception('Error publishing ' . $zipFileName);
	}
}
catch(Exception $x) {
	try {
		Enviro::deleteFolder($destDataFolder);
	}
	catch(Exception $x) {
	}
	throw $x;
}
Enviro::write("done.\n");
unset($tempFolder);
die(0);

function sortOldFolders($a, $b) {
	return @intval($a) - @intval($b);
}

function sortByC5Versions($a, $b) {
	$vMinA = $a['c5'][0];
	$vMinB = $a['c5'][0];
	foreach($a['c5'] as $c5v) {
		if(version_compare($vMinA, $c5v) > 0) {
			$vMinA = $c5v;
		}
	}
	foreach($b['c5'] as $c5v) {
		if(version_compare($vMinB, $c5v) > 0) {
			$vMinB = $c5v;
		}
	}
	return version_compare($vMinA, $vMinB);
}
