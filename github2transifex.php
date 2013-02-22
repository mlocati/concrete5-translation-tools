<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

define('GITHUB_USER', 'concrete5');
define('GITHUB_REPOSITORY', 'concrete5');
define('GITHUB_BRANCH', 'master');
define('WEBROOT', 'web');
define('POT_DESTINATION_FILENAME', '/var/www/website/core-dev.pot');

define('SOURCECODE_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'src');
define('TOOLS_FOLDER', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tools');

try {
	if(!is_dir(SOURCECODE_FOLDER)) {
		@mkdir(SOURCECODE_FOLDER, 0777, true);
		if(!is_dir(SOURCECODE_FOLDER)) {
			throw new Exception("Unable to create folder '" . SOURCECODE_FOLDER . "'");
		}
	}
	chdir(SOURCECODE_FOLDER);
	if(!is_dir('.git')) {
		write("Initializing git... ");
		run('git', 'clone git://github.com/' . GITHUB_USER . '/' . GITHUB_REPOSITORY . '.git .');
		run('git', 'checkout ' . GITHUB_BRANCH);
		write("done.\n");
	}
	else {
		write("Updading local repository... ");
		run('git', 'checkout ' . GITHUB_BRANCH);
		run('git', 'fetch origin');
		run('git', 'reset --hard origin/' . GITHUB_BRANCH);
		run('git', 'clean -f -d');
		write("done.\n");
	}
	write("Generating .pot file... ");
	$webRoot = SOURCECODE_FOLDER . DIRECTORY_SEPARATOR . trim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, WEBROOT), DIRECTORY_SEPARATOR);
	if(!is_dir($webRoot)) {
		throw new Exception("Unable to find the folder '$webRoot'");
	}
	$i18n = TOOLS_FOLDER . DIRECTORY_SEPARATOR . 'i18n.php';
	if(!is_file($i18n)) {
		throw new Exception("Unable to find the file '$i18n'");
	}
	run('php', escapeshellarg($i18n) . ' --webroot=' . escapeshellarg($webRoot) . ' --indent=no --createpot=yes --createpo=no --compile=no');
	write("done.\n");
	write("Moving the .pot file... ");
	$srcFile = $webRoot . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'messages.pot';
	if(!is_file($srcFile)) {
		throw new Exception("Unable to find the file '$srcFile'");
	}
	$destFile = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, POT_DESTINATION_FILENAME);
	$destFolder = substr($destFile, 0, strrpos($destFile, DIRECTORY_SEPARATOR));
	if(!is_dir($destFolder)) {
		@mkdir($destFolder, 0777, true);
		if(!is_dir($destFolder)) {
			throw new Exception("Unable to create folder '$destFolder'");
		}
	}
	if(!@rename($srcFile, $destFile)) {
		throw new Exception("Unable to move the file\n$srcFile\nto\n$destFile");
	}
	write("done.\n");
}
catch(Exception $x) {
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
}
		