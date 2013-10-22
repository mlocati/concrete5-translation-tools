<?php
require_once dirname(__FILE__) . '/includes/startup.php';

// Let's include the dependencies
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'locale-name.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'tempfolder.php');

// Let's initialize some variable
$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);

// Let's determine the versions map
$versionMap = @json_decode(C5TT_TRANSIFEXRESOURCE_VMAP, true);
if(!is_array($versionMap)) {
	throw new Exception('Error decoding C5TT_TRANSIFEXRESOURCE_VMAP (value: ' . C5TT_TRANSIFEXRESOURCE_VMAP . ')');
}

// Let's list the Transifex resources
Enviro::write("Looking for local translation files... ");
$locales = array();
$txTranslations = TransifexerTranslation::getAll(C5TT_TRANSIFEX_WORKPATH);
$txResources = array();
if(empty($txTranslations)) {
	throw new Exception('No translations found');
}
foreach($txTranslations as $txTranslation) {
	if(strcasecmp($txTranslation->projectSlug, C5TT_TRANSIFEX_PROJECT) !== 0) {
		throw new Exception("The translation {$txTranslation->getName()} is not for the project " . C5TT_TRANSIFEX_PROJECT . ".");
	}
	if($txTranslation->resourceSlug != C5TT_TRANSIFEXRESOURCE_DEV) {
		if(!array_key_exists($txTranslation->resourceSlug, $versionMap)) {
			throw new Exception("Don't know which is the c5 version for the Transifex resource '{$txTranslation->resourceSlug}': update the C5TT_TRANSIFEXRESOURCE_VMAP define.");
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
foreach($versionMap as $txResource => $c5versions) {
	if($txResource == C5TT_TRANSIFEXRESOURCE_DEV) {
		throw new Exception("The Transifex resource '$txResource' is defined as used for development version and for c5 versions " . implode(', ', $c5versions));
	}
	if(!array_key_exists($txResource, $txResources)) {
		throw new Exception("The Transifex resource '$txResource' is defined in C5TT_TRANSIFEXRESOURCE_DEV but it's not used in Transifex");
	}
}
if(!array_key_exists(C5TT_TRANSIFEXRESOURCE_DEV, $txResources)) {
	throw new Exception("The Transifex resource '" . C5TT_TRANSIFEXRESOURCE_DEV . "' is defined in C5TT_TRANSIFEXRESOURCE_VMAP but it's not used in Transifex");
}
Enviro::write("done (" . count($txTranslations) . " translations found for " . count($locales) . " languages)\n");
// Let's create the shown names of versions
$shownVersions = array();
foreach($versionMap as $txResource => $c5versions) {
	$shownVersions[] = array('tx' => $txResource, 'c5' => $c5versions);
}
usort($shownVersions, 'sortByC5Versions');
Enviro::write("Removing old files (keeping the last one)... ");
if(!is_dir(C5TT_TRANSLATIONRELEASES_FOLDER)) {
	@mkdir(C5TT_TRANSLATIONRELEASES_FOLDER, 0777, true);
	if(!is_dir(C5TT_TRANSLATIONRELEASES_FOLDER)) {
		throw new Exeption('Unable to create folder ' . C5TT_TRANSLATIONRELEASES_FOLDER);
	}
}
$oldDirs = array();
if(!($hDir = @opendir(C5TT_TRANSLATIONRELEASES_FOLDER))) {
	throw new Exception('Error reading from ' . C5TT_TRANSLATIONRELEASES_FOLDER);
}
try {
	while(($item = @readdir($hDir)) !== false) {
		if(preg_match('/^\\d{9,11}$/', $item) && is_dir(Enviro::mergePath(C5TT_TRANSLATIONRELEASES_FOLDER, $item))) {
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
	Enviro::deleteFolder(Enviro::mergePath(C5TT_TRANSLATIONRELEASES_FOLDER, $oldDirs[$i]));
}
Enviro::write("done.\n");
Enviro::write("Creating temporary data\n");
$info = array();
$info['updated'] = time();
for(;;) {
	$destDataFolder = Enviro::mergePath(C5TT_TRANSLATIONRELEASES_FOLDER, strval($info['updated']));
	if(!file_exists($destDataFolder)) {
		break;
	}
	$info['updated']++;
}
$info['locales'] = $locales;
$info['dev_version'] = C5TT_TRANSIFEXRESOURCE_DEV;
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
$destDataFolder = Enviro::mergePath(C5TT_TRANSLATIONRELEASES_FOLDER, $info['updated']);
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
	if(!@rename($tempInfofile, Enviro::mergePath(C5TT_TRANSLATIONRELEASES_FOLDER, 'list.php'))) {
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
