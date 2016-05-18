<?php
/*
 * For each of the entries of the C5TTConfiguration::$devBranches array, this
 * script fetches the latest commits and generates a .pot file that can be
 * fetched by external tools like Transifex. 
 */

require_once dirname(__FILE__) . '/includes/startup.php';

require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'gitter.php');

function getCoreVersion($webRoot) {
	$versionSchema = null;
	$configFile = Enviro::mergePath($webRoot, 'concrete', 'config', 'version.php');
	if (is_file($configFile)) {
		$versionSchema = 1;
	} else {
		$configFile = Enviro::mergePath($webRoot, 'concrete', 'config', 'concrete.php');
		if (is_file($configFile)) {
			$versionSchema = 2;
		}
	}
	switch ($versionSchema) {
		case 1:
			if(!defined('C5_EXECUTE')) {
				define('C5_EXECUTE', true);
			}
			$APP_VERSION = null;
			@include $configFile;
			if (empty($APP_VERSION) || !is_string($APP_VERSION) || ($APP_VERSION === '')) {
				throw new Exception('Failed to retrieve concrete5 version from file '.$configFile);
			}
			$version = $APP_VERSION;
			break;
		case 2:
			$oldErrorLevel = error_reporting(0);
			$config = @include $configFile;
			error_reporting($oldErrorLevel);
			if (!(isset($config) && is_array($config) && isset($config['version']) && isset($config['version']) && is_string($config['version']) && $config['version'] !== '')) {
				throw new Exception('Failed to retrieve concrete5 version from file '.$configFile);
			}
			$version = $config['version'];
			break;
		default:
			throw new Exception($webRoot . ' is not the valid concrete5 web root directory (the version file does not exist).');
	}
	return $version;
}
	
function buildCorePotFile($webRoot) {
	$version = getCoreVersion($webRoot);
}
// Let's get the repository containing the i18n.php script

foreach(C5TTConfiguration::$devBranches as $devBranch) {
	Enviro::write('WORKING ON CORE v' . $devBranch->version . "\n");

	Enviro::write("- Updating local files... ");
	$gitter = new Gitter($devBranch->host, $devBranch->owner, $devBranch->repository, $devBranch->branch, $devBranch->getWorkPath());
	$gitter->pullOrInitialize();
	if (version_compare(str_replace('x', '0', $devBranch->version), '8') >= 0) {
		$webRoot = $devBranch->getWorkPath();
	} else {
		$webRoot = Enviro::mergePath($devBranch->getWorkPath(), 'web');
	}
	if(!is_dir($webRoot)) {
		throw new Exception("Unable to find the folder '$webRoot'");
	}
	Enviro::write("done.\n");

	Enviro::write("- Detecting version... ");
	$coreVersion = getCoreVersion($webRoot);
	if(version_compare($coreVersion, '5.7') < 0) {
		$directoryToPotify = 'concrete';
		$potfile2root = '..';
	}
	else {
		$directoryToPotify = 'concrete';
		$potfile2root = '../..';
	}
	Enviro::write("$coreVersion\n");

	// Let's generate the .pot file
	Enviro::write("- Generating .pot file:\n");
	$translations = new Gettext\Translations();
	$translations->setLanguage('en_US');
	$translations->setHeader('Project-Id-Version', "concrete5 $coreVersion");
	$translations->setHeader('Report-Msgid-Bugs-To', 'http://www.concrete5.org/developers/bugs/');
	$translations->setHeader('X-Poedit-Basepath', $potfile2root);
	$translations->setHeader('X-Poedit-SourceCharset', 'UTF-8');
	
	foreach(C5TL\Parser::getAllParsers() as $parser) {
		if($parser->canParseDirectory()) {
			Enviro::write('  > parser "' . $parser->getParserName() . '"... ');
			$parser->parseDirectory(
				Enviro::MergePath($webRoot, $directoryToPotify),
				$directoryToPotify,
				$translations,
				false,
				true
			);
			Enviro::write("done.\n");
		}
	}

	Enviro::write("- Saving .pot file... ");
	$destFile = $devBranch->getPotPath();
	$destFolder = dirname($destFile);
	if(!is_dir($destFolder)) {
		@mkdir($destFolder, 0777, true);
		if(!is_dir($destFolder)) {
			throw new Exception("Unable to create the folder '$destFolder'");
		}
	}
	$translations->toPoFile($destFile);
	@chmod($destFile, 0666);
	Enviro::write("done ($destFile).\n");
}
