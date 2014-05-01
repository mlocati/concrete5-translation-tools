<?php
require_once dirname(__FILE__) . '/includes/startup.php';

require_once Enviro::mergePath(C5TTConfiguration::$includesPath, 'gitter.php');

// Let's get the repository containing the i18n.php script
$gitter = new Gitter(C5TTConfiguration::$buildtoolsBranch->host, C5TTConfiguration::$buildtoolsBranch->owner, C5TTConfiguration::$buildtoolsBranch->repository, C5TTConfiguration::$buildtoolsBranch->branch, C5TTConfiguration::$buildtoolsBranch->getWorkPath());
if(!$gitter->localFolderIsGit()) {
	$gitter->initialize();
}
$i18n = Enviro::mergePath(C5TTConfiguration::$buildtoolsBranch->getWorkPath(), 'i18n.php');

foreach(C5TTConfiguration::$devBranches as $devBranch) {
	Enviro::write('WORKING ON CORE v' . $devBranch->version . "\n");
	// Let's pull the latest concrete5 core code from GitHub.
	$gitter = new Gitter($devBranch->host, $devBranch->owner, $devBranch->repository, $devBranch->branch, $devBranch->getWorkPath());
	$gitter->pullOrInitialize();
	$webRoot = Enviro::mergePath($devBranch->getWorkPath(), 'web');
	if(!is_dir($webRoot)) {
		throw new Exception("Unable to find the folder '$webRoot'");
	}
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
	$destFolder = dirname($devBranch->getPotPath());
	if(!is_dir($destFolder)) {
		@mkdir($destFolder, 0777, true);
		if(!is_dir($destFolder)) {
			throw new Exception("Unable to create the folder '$destFolder'");
		}
	}
	if(!@rename($srcFile, $devBranch->getPotPath())) {
		throw new Exception("Unable to move the file\n$srcFile\nto\n" . $devBranch->getPotPath());
	}
	@chmod($devBranch->getPotPath(), 0777);
	Enviro::write("done.\n");
	// All done
	Enviro::write("POT file generated successfully:\n" . $devBranch->getPotPath() . "\n");
}
