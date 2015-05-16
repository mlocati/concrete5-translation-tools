<?php
/** Class holding the global configuration variables */
class C5TTConfiguration {
	/** About the currently running environment: 'shell', 'ajax'
	* @var string
	*/
	public static $runningEnviro = 'shell';
	/** The sender email address of outgoing emails
	* @var string
	*/
	public static $emailSenderAddress = 'c5tt@localhost';
	/** Shall we send notification emails on errors?
	* @var bool
	*/
	public static $notifyErrors = true;
	/** A comma-separated list of email addresses of the recipients of exception notifications
	* @var string
	*/
	public static $notifyErrorsTo = '';
	/** The root folder of the concrete5 translation tools
	* @var string
	*/
	public static $rootPath;
	/** The folder containing the include files
	* @var string
	*/
	public static $includesPath;
	/** The folder containing the working files/directories
	* @var string
	*/
	public static $workPath;
	/** Returns the path to the local copy of the Transifex data for core
	* @var string
	*/
	public static function getTransifexWorkpathCore() {
		return Enviro::mergePath(self::$workPath, 'transifex-core');
	}
	/** Returns the path to the local copy of the Transifex data for packages
	* @var string
	*/
	public static function getTransifexWorkpathPackages() {
		return Enviro::mergePath(self::$workPath, 'transifex-packages');
	}
	/** The folder containing the root of the web files
	* @var string
	*/
	public static $webrootPath = '/var/www/website';
	/** The Transifex host name
	* @var string
	*/
	public static $transifexHost = 'https://www.transifex.com';
	/** The Transifex username
	* @var string
	*/
	public static $transifexUsername = '';
	/** The Transifex password
	* @var string
	*/
	public static $transifexPassword = '';
	/** The database connection info
	* @var C5TTConfigurationDB
	*/
	public static $database = null;
	/** Info about development versions of concrete5
	* @var array[C5TTConfigurationGitC5Dev]
	*/
	public static $devBranches = array();
	/** The Transifex project handle for core translations
	* @var string
	*/
	public static $transifexCoreProject = 'concrete5';
	/** The Transifex project handle for packages translations
	* @var string
	*/
	public static $transifexPackagesProject = 'concrete5-packages';
	/** Info for the the repository with the packages translations
	* @var C5TTConfigurationGit
	*/
	public static $gitPackages;
	/** The branch of the GitHub repository that contains the packages translations
	* @var string
	*/
	public static $gitPackagesBranchFiles = 'master';
	/** The branch of the GitHub repository that contains the web page for packages translations
	* @var string
	*/
	public static $gitPackagesBranchWeb = 'gh-pages';
	/** The relative path to the folder where we'll save the package translations
	* @var string
	*/
	public static $packagesTranslationsRelPath = 'packages-translations';
	/** Returns the absolute path to the folder where we'll save JavaScript translations info and of the zip files to be downloaded
	* @var string
	*/
	public static function getPackagesTranslationsPath() {
		return Enviro::mergePath(self::$webrootPath, self::$packagesTranslationsRelPath);
	}
	/** The relative path to the folder where we'll save the core translations
	* @var string
	*/
	public static $coreTranslationsRelPath = 'translations';
	/** Returns the absolute path to the folder where we'll save the core translations
	* @var string
	*/
	public static function getCoreTranslationsPath() {
		return Enviro::mergePath(self::$webrootPath, self::$coreTranslationsRelPath);
	}
	/** Info about Transifex resources for released concrete5 versions
	* @var array[array] Keys: transifex resources, values: list of concrete5 versions
	*/
	public static $transifexReleased;
	/** The repository with the .po/.mo files taken from Transifex
	* @var C5TTConfigurationGitOneBranch
	*/
	public static $langcopyBranch;
	/** Authors that'll taken randomly when committing to the repository with the .po/.mo files taken from Transifex
	* @var array[string]
	*/
	public static $langcopyAuthors = array();
	/** The repository with the build scripts
	* @var C5TTConfigurationGitOneBranch
	*/
	public static $buildtoolsBranch;
	/** Returns the full path to the lock file
	* @return string
	*/
	public static function getLockFileName() {
		return Enviro::mergePath(self::$workPath, 'c5tt-lockfile');
	}
	/** The relative path to the folder where we'll save JavaScript translations info and of the zip files to be downloaded
	* @var string
	*/
	public static $translationreleasesRelPath = 'translation-releases';
	/** Returns the absolute path to the folder where we'll save JavaScript translations info and of the zip files to be downloaded
	* @var string
	*/
	public static function getTranslationreleasesPath() {
		return Enviro::mergePath(self::$webrootPath, self::$translationreleasesRelPath);
	}
}
/** Class holding configuratin about a database connection */
class C5TTConfigurationDB {
	/** The database server
	* @var string
	*/
	public $host;
	/** The database name
	* @var string
	*/
	public $name;
	/** The database username
	* @var string
	*/
	public $username;
	/** The database password
	* @var string
	*/
	public $password;
	/** Initializes the instance
	* @param string $host The database server
	* @param string $name The database name
	* @param string $username The database username
	* @param string $password The database password
	*/
	public function __construct($host, $name, $username, $password) {
		$this->host = $host;
		$this->name = $name;
		$this->username = $username;
		$this->password = $password;
	}
}
/** Class holding configuratin about a git repository */
class C5TTConfigurationGit {
	/** User owning the repository
	* @var string
	*/
	public $owner;
	/** Repository
	* @var string
	*/
	public $repository;
	/** Host for the repository
	* @var string
	*/
	public $host;
	/** Initializes the instance
	* @param string $owner User owning the repository
	* @param string $repository Repository
	* @param string $host Host for the repository (defaults to 'github.com')
	*/
	public function __construct($owner, $repository, $host = 'github.com') {
		$this->owner = $owner;
		$this->repository = $repository;
		$this->host = $host;
	}
	/** Returns the full path to the local copy of the repository/branch
	* @return string
	*/
	public function getWorkPath() {
		return Enviro::mergePath(C5TTConfiguration::$workPath, "git-{$this->owner}-{$this->repository}");
	}
}
/** Class holding configuratin about a git repository/branch */
class C5TTConfigurationGitOneBranch extends C5TTConfigurationGit {
	/** Branch of the repository
	* @var string
	*/
	public $branch;
	/** Initializes the instance
	* @param string $owner User owning the repository
	* @param string $repository Repository
	* @param string $branch Branch of the repository
	* @param string $host Host for the repository (defaults to 'github.com')
	*/
	public function __construct($owner, $repository, $branch, $host = 'github.com') {
		parent::__construct($owner, $repository, $host);
		$this->branch = $branch;
	}
	/** Returns the full path to the local copy of the repository/branch
	* @return string
	*/
	public function getWorkPath() {
		return Enviro::mergePath(C5TTConfiguration::$workPath, "git-{$this->owner}-{$this->repository}-{$this->branch}");
	}
}
/** Represent a git branch of a concrete5 development version */
class C5TTConfigurationGitC5Dev extends C5TTConfigurationGitOneBranch {
	/** The Transifex resource handle
	* @var string
	*/
	public $transifexResource;
	/** The develpment version
	* @var string
	*/
	public $version;
	/** The path name of the .pot file to generate for this version, relative to C5TTConfiguration::$webrootPath
	* @var string
	*/
	public $potRelPath;
	/** Initializes the instance
	* @param string $transifexResource The Transifex resource handle
	* @param string $version The develpment version
	* @param string $potPath The full path name of the .pot file to generate for this version
	* @param string $owner User owning the repository
	* @param string $repository Repository
	* @param string $branch Branch of the repository
	* @param string $host Host for the repository (defaults to 'github.com')
	*/
	public function __construct($transifexResource, $version, $potRelPath, $owner, $repository, $branch, $host = 'github.com') {
		parent::__construct($owner, $repository, $branch, $host);
		$this->transifexResource = $transifexResource;
		$this->version = $version;
		$this->potRelPath = $potRelPath;
	}
	/** Retrieves the full path name of the .pot file to generate for this version
	* @return string
	*/
	public function getPotPath() {
		return Enviro::mergePath(C5TTConfiguration::$webrootPath, $this->potRelPath);
	}
}

C5TTConfiguration::$rootPath = dirname(dirname(__FILE__));
C5TTConfiguration::$includesPath = dirname(__FILE__);
C5TTConfiguration::$workPath = Enviro::mergePath(C5TTConfiguration::$rootPath, 'work');
C5TTConfiguration::$langcopyBranch = new C5TTConfigurationGitOneBranch('concrete5', 'concrete5-translations', 'master');
C5TTConfiguration::$devBranches[] = new C5TTConfigurationGitC5Dev('core-dev-56', '5.6.x', 'transifex/core-dev-5.6.pot', 'concrete5', 'concrete5-legacy', 'master');
C5TTConfiguration::$devBranches[] = new C5TTConfigurationGitC5Dev('core-dev-57', '5.7.x', 'transifex/core-dev-5.7.pot', 'concrete5', 'concrete5', 'develop');
C5TTConfiguration::$buildtoolsBranch = new C5TTConfigurationGitOneBranch('mlocati', 'concrete5-build', 'master');

C5TTConfiguration::$transifexReleased['core-562'] = array('5.6.2');
C5TTConfiguration::$transifexReleased['core-5621'] = array('5.6.2.1');
C5TTConfiguration::$transifexReleased['core-563'] = array('5.6.3', '5.6.3.1');

C5TTConfiguration::$gitPackages = new C5TTConfigurationGit('concrete5', 'package-translations');
if(is_file(Enviro::mergePath(C5TTConfiguration::$rootPath . '/configuration/customize.php'))) {
	require Enviro::mergePath(C5TTConfiguration::$rootPath . '/configuration/customize.php');
}
