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

if(!defined('C5TT_TRANSLATIONRELEASES_FOLDER')) {
	/** The location of the JavaScript translations info and of the zip files to be downloaded.
	* @var string
	*/
	define('C5TT_TRANSLATIONRELEASES_FOLDER', '/var/www/website/translation-releases');
}

if(!defined('C5TT_TRANSIFEXRESOURCE_DEV')) {
	/** The Transifex resource handle of the latest (development) concrete5 version.
	* @var string
	*/
	define('C5TT_TRANSIFEXRESOURCE_DEV', 'core');
}

if(!defined('C5TT_TRANSIFEXRESOURCE_VMAP')) {
	/** The map from Transifex resource to the concrete5 versions (in JSON format).
	* @var string
	*/
	define('C5TT_TRANSIFEXRESOURCE_VMAP', '{"core-562": ["5.6.2"], "core-5621": ["5.6.2.1"] }');
}

/** The lock file name.
* @var string
*/
define('C5TT_LOCKFILE', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'c5tt-lockfile');

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

if(!defined('C5TT_TRANSIFEX_PACKAGES_PROJECT')) {
	/** The Transifex project name for packages translations.
	 * @var string
	 */
	define('C5TT_TRANSIFEX_PACKAGES_PROJECT', 'concrete5-packages');
}

/** The local copy of the Transifex data for packages translations.
 * @var string
 */
define('C5TT_TRANSIFEX_PACKAGES_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'transifex-packages');
/** The local folder where the master branch of the concrete5 core is stored.
 * @var string
 */
define('C5TT_GITHUB_PACKAGES_WORKPATH', C5TT_WORKPATH . DIRECTORY_SEPARATOR . 'github-packages');

if(!defined('C5TT_GITHUB_PACKAGES_OWNER')) {
	/** GitHub user owning the repository with the packages translations.
	 * @var string
	 */
	define('C5TT_GITHUB_PACKAGES_OWNER', 'concrete5');
}

if(!defined('C5TT_GITHUB_PACKAGES_REPOSITORY')) {
	/** GitHub repository for the packages translations.
	 * @var string
	 */
	define('C5TT_GITHUB_PACKAGES_REPOSITORY', 'package-translations');
}

if(!defined('C5TT_GITHUB_PACKAGES_BRANCH_FILES')) {
	/** The branch of the GitHub repository that contains the packages translations.
	 * @var string
	 */
	define('C5TT_GITHUB_PACKAGES_BRANCH_FILES', 'master');
}
if(!defined('C5TT_GITHUB_PACKAGES_BRANCH_WEB')) {
	/** The branch of the GitHub repository that contains the web page for packages translations.
	 * @var string
	 */
	define('C5TT_GITHUB_PACKAGES_BRANCH_WEB', 'gh-pages');
}

if(!defined('C5TT_PATH_PACKAGES_TRANSLATIONS')) {
	/** The location where the package translations files will be saved.
	 * @var string
	 */
	define('C5TT_PATH_PACKAGES_TRANSLATIONS', '/var/www/website/packages-translations');
}
