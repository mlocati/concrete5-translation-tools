<?php
define('C5TT_NOTIFYERRORS', false);
require_once dirname(__FILE__) . '/includes/startup.php';

// Let's include the dependencies
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'tempfolder.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'packages.php');

if(empty(Package::$all)) {
	throw new Exception('No packages loaded!');
}

require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'gitter.php');
////////////////////////////////////////////////////////////////////////$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);

// Let's pull all the Transifex data
////////////////////////////////////////////////////////////////////////$transifexer->pull(C5TT_TRANSIFEX_PACKAGES_PROJECT, C5TT_TRANSIFEX_PACKAGES_WORKPATH);


// Let's list the translations
$translationHandles = getTranslationHandles();
if(empty($translationHandles)) {
	throw new Exception('No Transifex translations found');
}
foreach($translationHandles as $translationHandle) {
	if(!array_key_exists(str_replace('-', '_', $translationHandle), Package::$all)) {
		throw new Exception("Found a resource in Transifex that is not mapped to a configured package. Its handle is '$translationHandle'");
	}
}
foreach(Package::$all as $packageHandle => $package) {
	if(!in_array(str_replace('_', '-', $translationHandle), $translationHandles)) {
		throw new Exception("Found a configured package that is not mapped to a Transifex resource. Its handle is '$packageHandle'");
	}
}

// Let's pull the latest branch version of the repository containing the translations
$gitter = new Gitter('github.com', C5TT_GITHUB_PACKAGES_OWNER, C5TT_GITHUB_PACKAGES_REPOSITORY, C5TT_GITHUB_PACKAGES_BRANCH_WEB, C5TT_GITHUB_PACKAGES_WORKPATH, true);
////////////////////////////// $gitter->pullOrInitialize();
$gitter->changeBranch(C5TT_GITHUB_PACKAGES_BRANCH_FILES);
//////////////////////////// $gitter->pullOrInitialize();

$moTempFolder = new TempFolder();
$newMoFiles = array();
$jsData = array();
foreach(Package::$all as /* @var $package Package */$package) {
	if($package->process($moTempFolder)) {
		$jsData[] = array(
			'handle' => $package->concrete5Handle,
			'name' => $package->name,
			'locales' => $package->translatedLocales
		);
		$newMoFiles[] = $package->moFile;
	}
}
$gitter->changeBranch(C5TT_GITHUB_PACKAGES_BRANCH_WEB);
$ghZipFolder = Enviro::mergePath(C5TT_GITHUB_PACKAGES_WORKPATH, 'translations');
if(file_exists($ghZipFolder)) {
	Enviro::deleteFolder($ghZipFolder, true);
}
else {
	@mkdir($ghZipFolder);
}
if(!is_dir($ghZipFolder)) {
	throw new Exception('Unable to create the directory ' . $ghZipFolder);
}
foreach($newMoFiles as $newMoFile) {
	if(@rename($newMoFile, Enviro::mergePath($ghZipFolder, basename($newMoFile))) === false) {
		throw new Exception('Unable to move the zip file to the filan directory ' . newMoFile);
	}
}
print_r($jsData);
die(0);

class Package {
	public static $all = array();
	public $transifexHandle;
	public $concrete5Handle;
	public $name;
	public $sourceURL;
	public $poDirectory;
	public $allLocales;
	public $translatedLocales;
	public $moFile;
	public function __construct($handle, $name, $sourceURL) {
		$this->transifexHandle = is_string($handle) ? str_replace('_','-', strtolower(trim($handle))) : '';
		$this->concrete5Handle = str_replace('-','_', $this->transifexHandle);
		$this->name = is_string($name) ? trim($name) : '';
		$this->sourceURL = is_string($sourceURL) ? trim($sourceURL) : '';
		if(!strlen($this->concrete5Handle)) {
			throw new Exception('Field required for packages: $handle');
		}
		if(!strlen($this->name)) {
			throw new Exception('Field required for package ' . $this->concrete5Handle . ': $name');
		}
		if(!strlen($this->sourceURL)) {
			throw new Exception('Field required for package ' . $this->concrete5Handle . ': $sourceURL');
		}
		if(array_key_exists($this->concrete5Handle, self::$all)) {
			throw new Exception('Duplicated package handle: ' . $this->concrete5Handle);
		}
		$this->poDirectory = Enviro::mergePath(C5TT_TRANSIFEX_PACKAGES_WORKPATH, 'translations', C5TT_TRANSIFEX_PACKAGES_PROJECT . '.' . $this->transifexHandle);
		self::$all[$this->concrete5Handle] = $this;
	}
	public function process($moTempFolder) {
		Enviro::write("Processing package {$this->concrete5Handle}...\n");
		$this->allLocales = array();
		$this->translatedLocales = array();
		$hDir = @opendir($this->poDirectory);
		if($hDir === false) {
			throw new Exception("Failed to open the translations directory: {$this->poDirectory}");
		}
		while(($item = @readdir($hDir)) !== false) {
			$fullPath = Enviro::mergePath($this->poDirectory, $item);
			if(is_file($fullPath) && preg_match('/(\\w.*)\\.po/i', $item, $m)) {
				$this->allLocales[$m[1]] = array('poFile' => $fullPath);
			} 
		}
		closedir($hDir);
		if(empty($this->allLocales)) {
			throw new Exception('no locales found');
		}
		
		$tempFolder = new TempFolder();
		$commonFolder = Enviro::mergePath($tempFolder->getName(), 'packages', str_replace('-', '_', $this->concrete5Handle), 'languages');
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
					$someStats =  true;
				}
				if(preg_match('/(\\d+) untranslated message/', $outputLine, $match)) {
					$stats['untranslated'] = intval($match[1]);
					$someStats =  true;
				}
				if(preg_match('/(\\d+) fuzzy translation/', $outputLine, $match)) {
					$stats['fuzzy'] = intval($match[1]);
					$someStats =  true;
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
			Enviro::write("  - No languages found: skipping package.\n");
			return false;
		}
		Enviro::write("  - Zipping languages (" . implode(', ', array_keys($this->translatedLocales)) . ")... ");
		$prevDir = getcwd();
		chdir($tempFolder->getName());
		try {
			Enviro::run('zip', '-r ' . escapeshellarg($this->concrete5Handle . '.zip') . ' packages');
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
		chdir($prevDir);
		$this->moFile = Enviro::mergePath($moTempFolder->getName(), $this->concrete5Handle . '.zip');
		if(@rename(Enviro::mergePath($tempFolder->getName(), $this->concrete5Handle . '.zip'), $this->moFile) === 0) {
			throw new Exception("Error moving .zip file!");
		}
		unset($tempFolder);
		Enviro::write("done.\n");
		return true;
	}
}

function getTranslationHandles() {
	$result = array();
	$hDir = @opendir(Enviro::mergePath(C5TT_TRANSIFEX_PACKAGES_WORKPATH, 'translations'));
	if($hDir === false) {
		throw new Exception('Failed to list the Transifex translations');
	}
	while(($item = @readdir($hDir)) !== false) {
		if(is_dir(Enviro::mergePath(C5TT_TRANSIFEX_PACKAGES_WORKPATH, 'translations', $item))) {
			if(preg_match('/^' . C5TT_TRANSIFEX_PACKAGES_PROJECT . '\\.(.+)$/', $item, $m)) {
				$result[] = $m[1];
			}
		}
	}
	@closedir($hDir);
	return $result;
}
