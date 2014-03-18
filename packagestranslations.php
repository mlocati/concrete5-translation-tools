<?php
define('C5TT_NOTIFYERRORS', false);
require_once dirname(__FILE__) . '/includes/startup.php';

// Let's include the dependencies
require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'transifexer.php');

// Let's include the dependencies
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
	if(!array_key_exists($translationHandle, Package::$all)) {
		throw new Exception("Found a resource in Transifex that is not mapped to a configured package. Its handle is '$translationHandle'");
	}
}
foreach(Package::$all as $packageHandle => $package) {
	if(!in_array($packageHandle, $translationHandles)) {
		throw new Exception("Found a configured package that is not mapped to a Transifex resource. Its handle is '$packageHandle'");
	}
}

// Let's pull the latest branch version of the repository containing the translations
$gitter = new Gitter('github.com', C5TT_GITHUB_PACKAGES_OWNER, C5TT_GITHUB_PACKAGES_REPOSITORY, C5TT_GITHUB_PACKAGES_BRANCH_WEB, C5TT_GITHUB_PACKAGES_WORKPATH, true);
////////////////////////////// $gitter->pullOrInitialize();
$gitter->changeBranch(C5TT_GITHUB_PACKAGES_BRANCH_FILES);
//////////////////////////// $gitter->pullOrInitialize();

foreach(Package::$all as /* @var $package Package */$package) {
	$package->process();
}
die(0);

class Package {
	public static $all = array();
	public $handle;
	public $name;
	public $sourceURL;
	public $poDirectory;
	public $allLocales;
	public function __construct($handle, $name, $sourceURL) {
		$this->handle = is_string($handle) ? str_replace('_','-', strtolower(trim($handle))) : '';
		$this->name = is_string($name) ? trim($name) : '';
		$this->sourceURL = is_string($sourceURL) ? trim($sourceURL) : '';
		if(!strlen($this->handle)) {
			throw new Exception('Field required for packages: $handle');
		}
		if(!strlen($this->name)) {
			throw new Exception('Field required for package ' . $this->handle . ': $name');
		}
		if(!strlen($this->sourceURL)) {
			throw new Exception('Field required for package ' . $this->handle . ': $sourceURL');
		}
		if(array_key_exists($this->handle, self::$all)) {
			throw new Exception('Duplicated package handle: ' . $this->handle);
		}
		$this->poDirectory = Enviro::mergePath(C5TT_TRANSIFEX_PACKAGES_WORKPATH, 'translations', C5TT_TRANSIFEX_PACKAGES_PROJECT . '.' . $this->handle);
		self::$all[$this->handle] = $this;
	}
	public function process() {
		Enviro::write("Processing package {$this->handle}... ");
		$this->allLocales = array();
		$hDir = @opendir($this->poDirectory);
		if($hDir === false) {
			throw new Exception("Failed to open the translations directory: {$this->poDirectory}");
		}
		while(($item = @readdir($hDir)) !== false) {
			if(is_file(Enviro::mergePath($this->poDirectory, $item)) && preg_match('/(\\w.*)\\.po/i', $item, $m)) {
				$this->allLocales[] = $m[1];
			} 
		}
		closedir($hDir);
		if(empty($this->allLocales)) {
			throw new Exception('no locales found');
		}
		print_r($this->allLocales);
		die();
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
