<?php
/*
* You need a file called cretentials.php that has the following defines:
* define('TRANSIFEX_USERNAME', 'Transifex user name');
* define('TRANSIFEX_PASSWORD', 'Transifex password');
*/
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'credentials.php';

/** The Transifex host name.
* @var string
*/
define('TRANSIFEX_HOST', 'https://www.transifex.com');
/** The local copy of the Transifex data.
* @var string
*/
define('TRANSIFEX_LOCALFOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'transifex');
/** The Transifex project name.
* @var string
*/
define('TRANSIFEX_PROJECT', 'mlocati-test');

/** GitHub user owning the repository with the concrete5 core.
* @var string
*/
define('CORE_GITHUB_OWNER', 'concrete5');
/** GitHub repository for the concrete5 core.
* @var string
*/
define('CORE_GITHUB_REPOSITORY', 'concrete5');
/** The master branch of the concrete5 core.
* @var string
*/
define('CORE_GITHUB_BRANCH', 'master');
/** The master branch of the concrete5 core.
* @var string
*/
define('CORE_LOCALFOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'github-concrete5-core');

/** GitHub user owning the repository with the .po/.mo files taken from Transifex.
* @var string
*/
define('LANGCOPY_GITHUB_OWNER', 'mlocati');
/** The GitHub repository name that contains the .po/.mo files taken from Transifex.
* @var string
*/
define('LANGCOPY_GITHUB_REPOSIORY', 'mlocati-potest');
/** The branch of the GitHub repository name that contains the .po/.mo files taken from Transifex.
* @var string
*/
define('LANGCOPY_GITHUB_BRANCH', 'master');
/** The master branch of the concrete5 core.
* @var string
*/
define('LANGCOPY_LOCALFOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'github-langcopy');

/** GitHub user owning the repository with the tools scripts.
* @var string
*/
define('TOOLS_GITHUB_OWNER', 'mlocati');
/** GitHub repository for the tools scripts.
* @var string
*/
define('TOOLS_GITHUB_REPOSITORY', 'concrete5-build');
/** The master branch of the tools scripts.
* @var string
*/
define('TOOLS_GITHUB_BRANCH', 'master');
/** Folder containing a clone of https://github.com/mlocati/concrete5-build.
* @var string
*/
define('TOOLS_LOCALFOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'github-tools');

/** The location of the .pot file to generate (will be fetched by Transifex).
* @var string
*/
define('POT_FILE_FOR_TRANSIFEX', '/var/www/website/core-dev.pot');
