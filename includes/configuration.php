<?php
/** The root folder of the concrete5 translation tools.
* @var string
*/
define('C5TT_ROOTPATH', dirname(dirname(__FILE__)));

/** The folder containing the include files.
* @var string
*/
define('C5TT_INCLUDESPATH', dirname(__FILE__));

/** The folder containing the include files.
* @var string
*/
define('C5TT_CONFIGPATH', C5TT_ROOTPATH . DIRECTORY_SEPARATOR . 'configuration');

if(!is_file(C5TT_CONFIGPATH . DIRECTORY_SEPARATOR . 'credentials.php')) {
	throw new Exception('Missing credentials.php file.');
}
require_once C5TT_CONFIGPATH . DIRECTORY_SEPARATOR . 'credentials.php';

if(is_file(C5TT_CONFIGPATH . DIRECTORY_SEPARATOR . 'customize.php')) {
	require_once C5TT_CONFIGPATH . DIRECTORY_SEPARATOR . 'customize.php';
}

if(!defined('C5TT_WORKPATH')) {
	/** The folder containing the working files/directories.
	* @var string
	*/
	define('C5TT_WORKPATH', C5TT_ROOTPATH . DIRECTORY_SEPARATOR . 'work');
}

if(!defined('C5TT_TRANSIFEX_HOST')) {
	/** The Transifex host name.
	* @var string
	*/
	define('C5TT_TRANSIFEX_HOST', 'https://www.transifex.com');
}

if(!defined('C5TT_TRANSIFEX_PROJECT')) {
	/** The Transifex project name.
	* @var string
	*/
	define('C5TT_TRANSIFEX_PROJECT', 'concrete5');
}

if(!defined('C5TT_GITHUB_CORE_OWNER')) {
	/** GitHub user owning the repository with the concrete5 core.
	* @var string
	*/
	define('C5TT_GITHUB_CORE_OWNER', 'concrete5');
}

if(!defined('C5TT_GITHUB_CORE_REPOSITORY')) {
	/** GitHub repository for the concrete5 core.
	* @var string
	*/
	define('C5TT_GITHUB_CORE_REPOSITORY', 'concrete5');
}

if(!defined('C5TT_GITHUB_CORE_BRANCH')) {
	/** The master branch of the concrete5 core.
	* @var string
	*/
	define('C5TT_GITHUB_CORE_BRANCH', 'master');
}

if(!defined('C5TT_GITHUB_LANGCOPY_OWNER')) {
	/** GitHub user owning the repository with the .po/.mo files taken from Transifex.
	* @var string
	*/
	define('C5TT_GITHUB_LANGCOPY_OWNER', 'concrete5');
}

if(!defined('C5TT_GITHUB_LANGCOPY_REPOSITORY')) {
	/** The GitHub repository name that contains the .po/.mo files taken from Transifex.
	* @var string
	*/
	define('C5TT_GITHUB_LANGCOPY_REPOSITORY', 'concrete5-translations');
}

if(!defined('C5TT_GITHUB_LANGCOPY_BRANCH')) {
	/** The branch of the GitHub repository that contains the .po/.mo files taken from Transifex.
	* @var string
	*/
	define('C5TT_GITHUB_LANGCOPY_BRANCH', 'master');
}

if(!defined('C5TT_GITHUB_LANGCOPY_AUTHORS')) {
	/** A pipe-separated list of authors that'll taken randomly when committing.
	* @var string
	*/
	define('C5TT_GITHUB_LANGCOPY_AUTHORS', '');
}


if(!defined('C5TT_GITHUB_TOOLS_OWNER')) {
	/** GitHub user owning the repository with the tools scripts.
	* @var string
	*/
	define('C5TT_GITHUB_TOOLS_OWNER', 'mlocati');
}

if(!defined('C5TT_GITHUB_TOOLS_REPOSITORY')) {
	/** GitHub repository for the tools scripts.
	* @var string
	*/
	define('C5TT_GITHUB_TOOLS_REPOSITORY', 'concrete5-build');
}

if(!defined('C5TT_GITHUB_TOOLS_BRANCH')) {
	/** The master branch of the tools scripts.
	* @var string
	*/
	define('C5TT_GITHUB_TOOLS_BRANCH', 'master');
}

if(!defined('C5TT_POT_PATH_FOR_TRANSIFEX')) {
	/** The location of the .pot file to generate (will be fetched by Transifex).
	* @var string
	*/
	define('C5TT_POT_PATH_FOR_TRANSIFEX', '/var/www/website/core-dev.pot');
}

if(!defined('C5TT_EMAILSENDERADDRESS')) {
	/** The sender email address of outgoing emails.
	 * @var string
	 */
	define('C5TT_EMAILSENDERADDRESS', 'c5tt@localhost');
}

if(!defined('C5TT_NOTIFYERRORS_TO')) {
	/** A comma-separated list of email addresses of the recipients of exception notifications.
	 * @var string
	 */
	define('C5TT_NOTIFYERRORS_TO', '');
}


/** The local copy of the Transifex data.
* @var string
*/
define('C5TT_TRANSIFEX_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'transifex');

/** The local folder where the master branch of the concrete5 core is stored.
* @var string
*/
define('C5TT_GITHUB_CORE_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'github-concrete5');

/** The local folder where the GitHub repository that contains the .po/.mo files taken from Transifex is stored.
* @var string
*/
define('C5TT_GITHUB_LANGCOPY_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'github-langcopy');

/** Folder containing a clone of https://github.com/mlocati/concrete5-build.
* @var string
*/
define('C5TT_GITHUB_TOOLS_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'github-tools');
