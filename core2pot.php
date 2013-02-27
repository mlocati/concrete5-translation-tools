<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

try {
	if(!is_dir(CORE_LOCALFOLDER)) {
		@mkdir(CORE_LOCALFOLDER, 0777, true);
		if(!is_dir(CORE_LOCALFOLDER)) {
			throw new Exception("Unable to create folder '" . CORE_LOCALFOLDER . "'");
		}
	}
	chdir(CORE_LOCALFOLDER);
	if(!is_dir('.git')) {
		write("Initializing git... ");
		run('git', 'clone git://github.com/' . CORE_GITHUB_OWNER . '/' . CORE_GITHUB_REPOSITORY . '.git .');
		run('git', 'checkout ' . CORE_GITHUB_BRANCH);
		write("done.\n");
	}
	else {
		write("Updading local repository... ");
		run('git', 'checkout ' . CORE_GITHUB_BRANCH);
		run('git', 'fetch origin');
		run('git', 'reset --hard origin/' . CORE_GITHUB_BRANCH);
		run('git', 'clean -f -d');
		write("done.\n");
	}
	$webRoot = CORE_LOCALFOLDER . DIRECTORY_SEPARATOR . 'web';
	if(!is_dir($webRoot)) {
		throw new Exception("Unable to find the folder '$webRoot'");
	}
	$i18n = TOOLS_LOCALFOLDER . DIRECTORY_SEPARATOR . 'i18n.php';
	if(!is_file($i18n)) {
		if(!is_dir(TOOLS_LOCALFOLDER)) {
			@mkdir(TOOLS_LOCALFOLDER);
			if(!is_dir(TOOLS_LOCALFOLDER)) {
				throw new Exception("Unable to create folder '" . TOOLS_LOCALFOLDER . "'");
			}
		}
		chdir(TOOLS_LOCALFOLDER);
		if(is_dir('.git')) {
			throw new Exception("git already configured in folder\n" . TOOLS_LOCALFOLDER . "\nbut the following file can't be found:\n" . $i18n);
		}
		write("Cloning tools repository... ");
		run('git', 'clone git://github.com/' . TOOLS_GITHUB_OWNER . '/' . TOOLS_GITHUB_REPOSITORY . '.git .');
		run('git', 'checkout ' . TOOLS_GITHUB_BRANCH);
		write("done.\n");
	}
	write("Generating .pot file... ");
	run('php', escapeshellarg($i18n) . ' --webroot=' . escapeshellarg($webRoot) . ' --indent=no --createpot=yes --createpo=no --compile=no');
	write("done.\n");
	write("Moving the .pot file... ");
	$srcFile = $webRoot . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR . 'messages.pot';
	if(!is_file($srcFile)) {
		throw new Exception("Unable to find the file '$srcFile'");
	}
	$destFile = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, POT_FILE_FOR_TRANSIFEX);
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
	write("POT file generated successfully: $destFile");
}
catch(Exception $x) {
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
}
