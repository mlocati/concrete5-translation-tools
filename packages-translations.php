<?php
/*
 * This script fetches the Transifex package resources and pushed is to the
 * git repository configured in C5TTConfiguration::$gitPackages:
 * - in the C5TTConfiguration::$gitPackagesBranchFiles branch commits the
 *   translations;
 * - in the C5TTConfiguration::$gitPackagesBranchWeb branch commits the
 *   statistical data.
 */

require_once dirname(__FILE__) . '/includes/startup.php';

// Let's include the dependencies
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'transifexer.php');
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'tempfolder.php');
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'db.php');

Package::readAll();

if(empty(Package::$all)) {
	throw new Exception('No packages loaded!');
}
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'transifexer.php');
require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'gitter.php');
$transifexer = new Transifexer(C5TTConfiguration::$transifexHost, C5TTConfiguration::$transifexUsername, C5TTConfiguration::$transifexPassword);

// Let's pull all the Transifex data
$transifexer->pull(C5TTConfiguration::$transifexPackagesProject, C5TTConfiguration::getTransifexWorkpathPackages());

// Let's list the translations
$translationHandles = getTranslationHandles();
if(empty($translationHandles)) {
	throw new Exception('No Transifex translations found');
}
foreach($translationHandles as $translationHandle) {
	if(!array_key_exists($translationHandle, Package::$all)) {
		throw new Exception("Found a resource in Transifex that is not mapped to a configured package. Its handle is '$translationHandle'");
	}
}
foreach(Package::$all as $packageHandle => $package) {
	if(!in_array($translationHandle, $translationHandles)) {
		throw new Exception("Found a configured package that is not mapped to a Transifex resource. Its handle is '$packageHandle'");
	}
}

// Let's be sure about the destination folder for .zip files
if(!@is_dir(C5TTConfiguration::getPackagesTranslationsPath())) {
	@mkdir(C5TTConfiguration::getPackagesTranslationsPath(), 0777, true);
	if(!@is_dir(C5TTConfiguration::getPackagesTranslationsPath())) {
		throw new Exception('Unable to create the directory ' . C5TTConfiguration::getPackagesTranslationsPath());
	}
}
if(!is_writable(C5TTConfiguration::getPackagesTranslationsPath())) {
	throw new Exception('Unable write to the directory ' . C5TTConfiguration::getPackagesTranslationsPath());
}
// Let's pull the latest branch version of the repository containing the translations
$gitter = new Gitter(C5TTConfiguration::$gitPackages->host, C5TTConfiguration::$gitPackages->owner, C5TTConfiguration::$gitPackages->repository, C5TTConfiguration::$gitPackagesBranchWeb, C5TTConfiguration::$gitPackages->getWorkPath(), true);
$gitter->pullOrInitialize();
$gitter->changeBranch(C5TTConfiguration::$gitPackagesBranchFiles);
$gitter->pullOrInitialize();

$timestamp = time();
$moTempFolder = new TempFolder();
$newMoFiles = array();
$jsPackages = array();
$newPackages = array();
$deletedPackages = array();
$updatedPackages = array();
$packagesWithoutTranslations = array();
foreach(Package::$all as /* @var $package Package */$package) {
	$jsPackage = array(
		'handle' => $package->handle,
		'name' => $package->name,
		'sourceURL' => $package->sourceURL
	);
	if($package->process($moTempFolder)) {
		$jsPackage['locales'] = $package->translatedLocales;
		$newMoFiles[] = $package->moFile;
		$ghFolder = Enviro::mergePath(C5TTConfiguration::$gitPackages->getWorkPath(), $package->handle);
		if(!is_dir($ghFolder)) {
			@mkdir($ghFolder);
			if(!is_dir($ghFolder)) {
				throw new Exception('Unable to create folder ' . $ghFolder);
			}
			foreach($package->allLocales as $localeInfo) {
				$toFile = Enviro::mergePath($ghFolder, basename($localeInfo['poFile']));
				if(@copy($localeInfo['poFile'], $toFile) === false) {
					throw new Exception("Unable to copy from '{$localeInfo['poFile']}' to '$toFile'");
				}
			}
			$newPackages[] = $package->handle;
		}
		else {
			$updated = array();
			$ghFiles = array();
			$hDir = @opendir($ghFolder);
			while(($item = readdir($hDir)) !== false) {
				if(strpos($item, '.') !== 0) {
					$ghFiles[] = $item;
				}
			}
			foreach($ghFiles as $ghFile) {
				if(preg_match('/^(.+)\\.po$/', $ghFile, $m) && (!array_key_exists($m[1], $package->allLocales))) {
					$f = Enviro::mergePath($ghFolder, $ghFile);
					if(@unlink($f) === false) {
						throw new Exception('Unable to delete file ' . $f);
					}
					if(!array_key_exists('removed', $updated)) {
						$updated['removed'] = array();
					}
					$updated['removed'][] = $m[1];
				}
			}
			closedir($hDir);
			foreach($package->allLocales as $locale => $localeInfo) {
				$ghFile = Enviro::mergePath($ghFolder, "$locale.po");
				if(is_file($ghFile)) {
					if(TransifexerTranslation::arePoDifferent($localeInfo['poFile'], $ghFile)) {
						if(@unlink($ghFile) === false) {
							throw new Exception('Unable to delete file ' . $ghFile);
						}
						if(@copy($localeInfo['poFile'], $ghFile) === false) {
							throw new Exception("Unable to copy from '{$localeInfo['poFile']}' to '$ghFile'");
						}
						if(!array_key_exists('updated', $updated)) {
							$updated['updated'] = array();
						}
						$updated['updated'][] = $locale;
					}
				}
				else {
					if(@copy($localeInfo['poFile'], $ghFile) === false) {
						throw new Exception("Unable to copy from '{$localeInfo['poFile']}' to '$ghFile'");
					}
					if(!array_key_exists('added', $updated)) {
						$updated['added'] = array();
					}
					$updated['added'][] = $locale;
				}
			}
			if(!empty($updated)) {
				$updatedPackages[$package->handle] = $updated;
			}
		}
	}
	else {
		$packagesWithoutTranslations[] = $package->handle;
	}
	$jsPackages[] = $jsPackage;
}
$hDir = @opendir(C5TTConfiguration::$gitPackages->getWorkPath());
while(($item = readdir($hDir)) !== false) {
	if(strpos($item, '.') !== 0) {
		$fullPath = Enviro::mergePath(C5TTConfiguration::$gitPackages->getWorkPath(), $item);
		if(is_dir($fullPath)) {
			if((!array_key_exists($item, Package::$all)) || in_array($item, $packagesWithoutTranslations)) {
				Enviro::deleteFolder($fullPath);
				$deletedPackages[] = $item;
			}
		}
	}
}
closedir($hDir);
$zipFolder = Enviro::mergePath(C5TTConfiguration::getPackagesTranslationsPath(), $timestamp);
@mkdir($zipFolder);
if(!is_dir($zipFolder)) {
	throw new Exception('Unable to create the directory ' . $zipFolder);
}
foreach($newMoFiles as $newMoFile) {
	if(@rename($newMoFile, Enviro::mergePath($zipFolder, basename($newMoFile))) === false) {
		throw new Exception('Unable to move the zip file to the directory ' . $zipFolder);
	}
}
$jsData = array(
	'updated' => $timestamp,
	'packages' => $jsPackages
);

$msgTitle = array();
$msgBody = array();
$n = count($newPackages);
if($n > 0) {
	$msgTitle[] = ($n === 1) ? '1 new package' : "$n new packages";
	$body = 'New packages:';
	foreach($newPackages as $newPackage) {
		$body .= "\n# " . Package::$all[$newPackage]->name;
	}
	$msgBody[] = $body;
}
$n = count($updatedPackages);
if($n > 0) {
	$msgTitle[] = ($n === 1) ? '1 package updated' : "$n packages updated";
	$body = 'Updated packages:';
	foreach($updatedPackages as $handle => $info) {
		$body .= "\n# " . Package::$all[$handle]->name;
		if(array_key_exists('added', $info)) {
			$body .= "\n  - new languages: " . implode(', ', $info['added']);
		}
		if(array_key_exists('updated', $info)) {
			$body .= "\n  - updated languages: " . implode(', ', $info['updated']);
		}
		if(array_key_exists('removed', $info)) {
			$body .= "\n  - removed languages: " . implode(', ', $info['removed']);
		}
	}
	$msgBody[] = $body;
}
$n = count($deletedPackages);
if($n > 0) {
	$msgTitle[] = ($n === 1) ? '1 package removed' : "$n packages removed";
	$body = 'Removed packages:';
	foreach($deletedPackages as $deletedPackage) {
		$body .= "\n# " . (array_key_exists($deletedPackage, Package::$all) ? Package::$all[$deletedPackage]->name : $deletedPackage);
	}
	$msgBody[] = $body;
}

if(count($msgTitle)) {
	$gitter->commit(implode(', ', $msgTitle) . "\n\n" . implode("\n", $msgBody));
	$filesChanged = true;
}
else {
	$filesChanged = false;
}

$gitter->changeBranch(C5TTConfiguration::$gitPackagesBranchWeb);

$jsFolder = Enviro::mergePath(C5TTConfiguration::$gitPackages->getWorkPath(), 'js');
if(!is_dir($jsFolder)) {
	if(@mkdir($jsFolder))
	if(!is_dir($jsFolder)) {
		throw new Exception('Unable to create folder ' . $jsFolder);
	}
}
if(@file_put_contents(
	Enviro::mergePath($jsFolder, 'data.js'),
	json_encode(
		$jsData,
		0
		| (version_compare(PHP_VERSION, '5.4.0', '>=') ? JSON_PRETTY_PRINT : 0)
		| (version_compare(PHP_VERSION, '5.4.0', '>=') ? JSON_UNESCAPED_SLASHES : 0)
	)
) === false) {
	throw new Exception('Unable to write data.js');
}
$gitter->commit('Refresh data');
$gitter->push($filesChanged ? true : false);
try {
	deleteOldZipFolders();
}
catch(Exception $x) {
}
die(0);

class Package {
	public static $all = array();
	public $handle;
	public $name;
	public $sourceURL;
	public $txDirectory;
	public $allLocales;
	public $translatedLocales;
	public $moFile;
	public function __construct($handle, $name, $sourceURL) {
		$this->handle = is_string($handle) ? str_replace('_', '-', strtolower(trim($handle))) : '';
		$this->name = is_string($name) ? trim($name) : '';
		$this->sourceURL = is_string($sourceURL) ? trim($sourceURL) : '';
		if(!strlen($this->handle)) {
			throw new Exception('Field required for packages: $handle');
		}
		if(!strlen($this->name)) {
			throw new Exception('Field required for package ' . $this->handle . ': $name');
		}
		if(array_key_exists($this->handle, self::$all)) {
			throw new Exception('Duplicated package handle: ' . $this->handle);
		}
		$this->txDirectory = Enviro::mergePath(C5TTConfiguration::getTransifexWorkpathPackages(), 'translations', C5TTConfiguration::$transifexPackagesProject . '.' . $this->handle);
		self::$all[$this->handle] = $this;
	}
	/** Process package translations
	* @param TempFolder $moTempFolder The temporary folder where to save the .zip files
	* @return boolean Returns false if package has no translations, false otherwise
	* @throws Exception Throws an exception in case of errors
	*/
	public function process($moTempFolder) {
		Enviro::write("Processing package {$this->handle}...\n");
		$this->allLocales = array();
		$this->translatedLocales = array();
		$hDir = @opendir($this->txDirectory);
		if($hDir === false) {
			throw new Exception("Failed to open the translations directory: {$this->txDirectory}");
		}
		while(($item = @readdir($hDir)) !== false) {
			$fullPath = Enviro::mergePath($this->txDirectory, $item);
			if(is_file($fullPath) && preg_match('/(\\w.*)\\.po/i', $item, $m)) {
				$this->allLocales[$m[1]] = array('poFile' => $fullPath);
			}
		}
		closedir($hDir);
		if(empty($this->allLocales)) {
			throw new Exception('no locales found');
		}
		$concrete5Handle = str_replace('-', '_', $this->handle);
		$tempFolder = new TempFolder();
		$commonFolder = Enviro::mergePath($tempFolder->getName(), 'packages', $concrete5Handle, 'languages');
		if(@mkdir($commonFolder, 0777, true) === false) {
			throw new Exception('Failed to create temporary folder: ' . $commonFolder);
		}
		foreach(array_keys($this->allLocales) as $locale) {
			Enviro::write("  - processing $locale... ");
			$localeFolder = Enviro::mergePath($commonFolder, $locale);
			$moFolder = Enviro::mergePath($localeFolder, 'LC_MESSAGES');
			if(@mkdir($moFolder, 0777, true) === false) {
				throw new Exception('Failed to create temporary folder: ' . $moFolder);
			}
			$moFile = Enviro::mergePath($moFolder, 'messages.mo');
			Enviro::run('msgfmt', '--statistics ' . (false ? ' --check-format' : '') . ' --check-header --check-domain --output-file=' . escapeshellarg($moFile) . ' ' . escapeshellarg($this->allLocales[$locale]['poFile']), 0, $outputLines);
			$stats = array(
				'translated' => 0,
				'untranslated' => 0,
				'fuzzy' => 0
			);
			$someStats = false;
			foreach($outputLines as $outputLine) {
				if(preg_match('/(\\d+) translated message/', $outputLine, $match)) {
					$stats['translated'] = intval($match[1]);
					$someStats = true;
				}
				if(preg_match('/(\\d+) untranslated message/', $outputLine, $match)) {
					$stats['untranslated'] = intval($match[1]);
					$someStats = true;
				}
				if(preg_match('/(\\d+) fuzzy translation/', $outputLine, $match)) {
					$stats['fuzzy'] = intval($match[1]);
					$someStats = true;
				}
			}
			if(!$someStats) {
				throw new Exception("Unable to parse statistics from the output\n" . implode("\n", $outputLines));
			}
			$stats['total'] = $stats['translated'] + $stats['untranslated'] + $stats['fuzzy'];
			$stats['perc'] = ($stats['translated'] == $stats['total']) ? 100 : ($stats['total'] ? floor($stats['translated'] * 100 / $stats['total']) : 0);
			if($stats['translated'] === 0) {
				Enviro::deleteFolder($localeFolder);
				if(is_dir($localeFolder)) {
					throw new Exception('Unable to delete temporary folder: ' . $localeFolder);
				}
				Enviro::write("no translated strings: skipping.\n");
				continue;
			}
			Enviro::write("included (progress: {$stats['perc']}%)\n");
			$this->translatedLocales[$locale] = $stats;
		}
		if(empty($this->translatedLocales)) {
			unset($tempFolder);
			Enviro::write("  - No locales found: skipping package.\n");
			return false;
		}
		Enviro::write("  - Zipping locales (" . implode(', ', array_keys($this->translatedLocales)) . ")... ");
		$prevDir = getcwd();
		chdir($tempFolder->getName());
		try {
			Enviro::run('zip', '-r ' . escapeshellarg($concrete5Handle . '.zip') . ' packages');
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
		chdir($prevDir);
		$this->moFile = Enviro::mergePath($moTempFolder->getName(), "$concrete5Handle.zip");
		if(@rename(Enviro::mergePath($tempFolder->getName(), "$concrete5Handle.zip"), $this->moFile) === 0) {
			throw new Exception("Error moving .zip file!");
		}
		unset($tempFolder);
		Enviro::write("done.\n");
		return true;
	}
	public static function readAll() {
		$all = array();
		$rs = DB::query('select pHandle, pName, pSourceUrl from C5TTPackage where pDisabled = 0');
		while($row = $rs->fetch_assoc()) {
			$all[$row['pHandle']] = new self($row['pHandle'], $row['pName'], $row['pSourceUrl']);
		}
		$rs->close();
		Package::$all = $all;
	}
}

function getTranslationHandles() {
	$result = array();
	$hDir = @opendir(Enviro::mergePath(C5TTConfiguration::getTransifexWorkpathPackages(), 'translations'));
	if($hDir === false) {
		throw new Exception('Failed to list the Transifex translations');
	}
	while(($item = @readdir($hDir)) !== false) {
		if(is_dir(Enviro::mergePath(C5TTConfiguration::getTransifexWorkpathPackages(), 'translations', $item))) {
			if(preg_match('/^' . C5TTConfiguration::$transifexPackagesProject . '\\.(.+)$/', $item, $m)) {
				$result[] = $m[1];
			}
		}
	}
	@closedir($hDir);
	return $result;
}

function deleteOldZipFolders() {
	$oldDirs = array();
	if(!($hDir = @opendir(C5TTConfiguration::getPackagesTranslationsPath()))) {
		throw new Exception('Error reading from ' . C5TTConfiguration::getPackagesTranslationsPath());
	}
	try {
		while(($item = @readdir($hDir)) !== false) {
			if(preg_match('/^\\d{9,11}$/', $item) && is_dir(Enviro::mergePath(C5TTConfiguration::getPackagesTranslationsPath(), $item))) {
				$oldDirs[] = $item;
			}
		}
	}
	catch(Exception $x) {
		@closedir($hDir);
		throw $x;
	}
	@closedir($hDir);
	usort($oldDirs, 'sortOldZipFolders');
	for($i = 0; $i < count($oldDirs) - 2; $i++) {
		Enviro::deleteFolder(Enviro::mergePath(C5TTConfiguration::getPackagesTranslationsPath(), $oldDirs[$i]));
	}
}
function sortOldZipFolders($a, $b) {
	return @intval($a) - @intval($b);
}
