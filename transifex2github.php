<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

try {
	pullTransifex();
	write("Looking for downloaded for .po files... ");
	$translations = Translation::getAll();
	if(empty($translations)) {
		throw new Exception('No translations found');
	}
	write("done (" . count($translations) . " files found)\n");
	foreach($translations as $translation) {
		write("Compiling {$translation->poRelative}... ");
		$translation->compile();
		write("done.\n");
	}
	if(!is_dir(LANGCOPY_LOCALFOLDER)) {
		@mkdir(LANGCOPY_LOCALFOLDER, 0777, true);
		if(!is_dir(LANGCOPY_LOCALFOLDER)) {
			throw new Exception("Unable to create folder '" . LANGCOPY_LOCALFOLDER . "'");
		}
	}
	chdir(LANGCOPY_LOCALFOLDER);
	if(!is_dir('.git')) {
		write("Initializing git... ");
		run('git', 'clone git://github.com/' . LANGCOPY_GITHUB_OWNER . '/' . LANGCOPY_GITHUB_REPOSIORY . '.git .');
		run('git', 'checkout ' . LANGCOPY_GITHUB_BRANCH);
		write("done.\n");
	}
	else {
		write("Updading local repository... ");
		run('git', 'checkout ' . LANGCOPY_GITHUB_BRANCH);
		run('git', 'fetch origin');
		run('git', 'reset --hard origin/' . LANGCOPY_GITHUB_BRANCH);
		run('git', 'clean -f -d');
		write("done.\n");
	}
	$someChanged = false;
	foreach($translations as $translation) {
		write("Cheking changes for {$translation->poRelative}... ");
		if($translation->detectChanges()) {
			$translation->copyToGit();
			write("CHANGED!\n");
			$someChanged = true;
		}
		else {
			write("unchanged.\n");
		}
	}
	if(!$someChanged) {
		write("No change detected: git untouched.\n");
	}
	else {
		write("Creating statistics file...");
		$statsFile = LANGCOPY_LOCALFOLDER . DIRECTORY_SEPARATOR . 'stats.txt';
		if(!($hStats = @fopen($statsFile, 'wb'))) {
			throw new Exception("Error opening '$statsFile' for writing");
		}
		try {
			foreach($translations as $translation) {
				$translation->writeStats($hStats);
			}
		}
		catch(Exception $x) {
			@fclose($hStats);
			throw $x;
		}
		@fclose($hStats);
		write("done.\n");
		write("Committing to git...");
		run('git', 'add --all');
		run('git', 'commit -m "Transifex update"');
		run('git', 'push origin ' . LANGCOPY_GITHUB_BRANCH);
		write("done.\n");
	}
}
catch(Exception $x) {
	write($x->getMessage(), true);
	die($x->getCode() ? $x->getCode() : 1);
}
