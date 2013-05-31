<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'startup.php';

require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'gitter.php');

// Let's pull the latest concrete5 core code from GitHub.
$gitter = new Gitter('github.com', C5TT_GITHUB_CORE_OWNER, C5TT_GITHUB_CORE_REPOSITORY, C5TT_GITHUB_CORE_BRANCH, C5TT_GITHUB_CORE_WORKPATH);
$gitter->reset();
$webRoot = Enviro::mergePath(C5TT_GITHUB_CORE_WORKPATH, 'web');
if(!is_dir($webRoot)) {
	throw new Exception("Unable to find the folder '$webRoot'");
}

// Let's get the repository containing the i18n.php script
$gitter = new Gitter('github.com', C5TT_GITHUB_TOOLS_OWNER, C5TT_GITHUB_TOOLS_REPOSITORY, C5TT_GITHUB_TOOLS_BRANCH, C5TT_GITHUB_TOOLS_WORKPATH);
if(!$gitter->localFolderIsGit()) {
	$gitter->initialize();
}
$i18n = Enviro::mergePath(C5TT_GITHUB_TOOLS_WORKPATH, 'i18n.php');

// Let's generate the .pot file
Enviro::write("Generating .pot file... ");
Enviro::run('php', escapeshellarg($i18n) . ' --webroot=' . escapeshellarg($webRoot) . ' --indent=no --createpot=yes --createpo=no --compile=no');
Enviro::write("done.\n");

// Let's move the .pot file to the final position 
Enviro::write("Moving the .pot file... ");
$srcFile = Enviro::mergePath($webRoot, 'languages', 'messages.pot');
if(!is_file($srcFile)) {
	throw new Exception("Unable to find the file '$srcFile'");
}
$destFolder = dirname(C5TT_POT_PATH_FOR_TRANSIFEX);
if(!is_dir($destFolder)) {
	@mkdir($destFolder, 0777, true);
	if(!is_dir($destFolder)) {
		throw new Exception("Unable to create the folder '$destFolder'");
	}
}
if(!@rename($srcFile, C5TT_POT_PATH_FOR_TRANSIFEX)) {
	throw new Exception("Unable to move the file\n$srcFile\nto\n" . C5TT_POT_PATH_FOR_TRANSIFEX);
}
Enviro::write("done.\n");

// All done
Enviro::write("POT file generated successfully: " . C5TT_POT_PATH_FOR_TRANSIFEX);
