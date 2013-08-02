<?php

/** A Git manager */
class Gitter {
	/** Git host.
	* @var string
	*/
	private $host;
	/** Git repository owner.
	* @var string
	*/
	private $owner;
	/** Git repository.
	* @var string
	*/
	private $repository;
	/** Git repository branch.
	* @var string
	*/
	private $branch;
	/** Local repository path.
	* @var string
	*/
	private $localPath;
	/** Will we write to this repository?
	* @var string
	*/
	private $writeEnabled;
	/** Initializes the instance.
	* @param string $host Git host.
	* @param string $owner Git repository owner.
	* @param string $repository Git repository.
	* @param string $branch Git repository branch.
	* @param string $localPath Local repository path.
	* @param bool $writeEnabled [default: false] Enable writing to this repository?
	*/
	public function __construct($host, $owner, $repository, $branch, $localPath, $writeEnabled = false) {
		$this->host = trim($host, '/');
		$this->owner = $owner;
		$this->repository = $repository;
		$this->branch = $branch;
		$this->localPath = rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $localPath), DIRECTORY_SEPARATOR);
		$this->writeEnabled = $writeEnabled ? true : false;
	}
	/** Is the local folder existing and with a git repository?
	* @return boolean
	*/
	public function localFolderIsGit() {
		return is_dir(Enviro::mergePath($this->localPath, '.git')) ? true : false;
	}
	/** Ensures that the local repository is initialized and that it matches the remote repository.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function pullOrInitialize() {
		if($this->localFolderIsGit()) {
			$this->pull();
		}
		else {
			$this->initialize();
		}
	}
	private function getRemotePath() {
		if($this->writeEnabled) {
			return "git@{$this->host}:{$this->owner}/{$this->repository}.git";
		}
		else {
			return "git://{$this->host}/{$this->owner}/{$this->repository}.git";
		}
	}
	/** Initializes the local folder with the remote repository data.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function initialize() {
		if(!is_dir($this->localPath)) {
			@mkdir($this->localPath, 0777, true);
			if(!is_dir($this->localPath)) {
				throw new Exception("Unable to create the folder '{$this->localPath}'.");
			}
		}
		if($this->localFolderIsGit()) {
			throw new Exeption("Local repository already initialized.");
		}
		$prevDir = getcwd();
		chdir($this->localPath);
		try {
			Enviro::write("Initializing local repository {$this->owner}/{$this->repository}... ");
			Enviro::run('git', "clone {$this->getRemotePath()} .");
			Enviro::run('git', "checkout {$this->branch}");
			Enviro::write("done.\n");
			chdir($prevDir);
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
	}
	/** Ensures that the local folder contains the remote data.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function pull() {
		if(!is_dir(Enviro::mergePath($this->localPath, '.git'))) {
			throw new Exception("The folder '{$this->localPath}' is not a git repository.");
		}
		$prevDir = getcwd();
		chdir($this->localPath);
		try {
			Enviro::write("Updading local repository {$this->owner}/{$this->repository}... ");
			Enviro::run('git', "checkout {$this->branch}");
			Enviro::run('git', "fetch origin");
			Enviro::run('git', "reset --hard origin/{$this->branch}");
			Enviro::run('git', "clean -f -d");
			Enviro::write("done.\n");
			chdir($prevDir);
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
	}
	/** Commit everything.
	* @param string $comment Commit comment.
	* @param string $authors [default: ''] A pipe-separated list of authors that'll taken randomly when committing.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function commit($comment, $authors = '') {
		$author = null;
		if(is_string($authors) && strlen($authors = trim($authors))) {
			$l = array();
			foreach(explode('|', $authors) as $a) {
				$a = trim($a);
				if(preg_match('/^(.+)[ \t]*<(.+)>$/', trim($a), $m)) {
					$l[] = array('name' => trim($m[1]), 'email' => trim($m[2]));
				}
			}
			switch(count($l)) {
				case 0:
					break;
				case 1:
					$author = $l[0];
					break;
				default:
					$author = $l[mt_rand(0, count($l) - 1)];
					break;
			}
		}
		Enviro::write("Committing to {$this->repository}/{$this->branch}... ");
		$prevDir = getcwd();
		chdir($this->localPath);
		try {
			if($author) {
				Enviro::run('git', 'config --local user.name "' . str_replace('"', "'", $author['name']) . '"');
				Enviro::run('git', 'config --local user.email "' . str_replace('"', "'", $author['email']) . '"');
			}
			else {
				Enviro::run('git', 'config --local --unset user.name');
				Enviro::run('git', 'config --local --unset user.email');
			}
			Enviro::run('git', 'add --all');
			$args = 'commit';
			if($author) {
				$args .= ' --author="' . str_replace('"', "'", "{$author['name']} <{$author['email']}>") . '"';
			}
			if(strlen($comment)) {
				$args .= ' --message="' . str_replace('"', "'", $comment) . '"';
			}
			Enviro::run('git', $args);
			Enviro::write("done.\n");
			chdir($prevDir);
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
	}
	/** Push to the remote git server.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function push() {
		Enviro::write("Pushing to {$this->host}/{$this->owner}/{$this->repository}... ");
		$prevDir = getcwd();
		chdir($this->localPath);
		try {
			Enviro::run('git', "push origin {$this->branch}");
			Enviro::write("done.\n");
			chdir($prevDir);
		}
		catch(Exception $x) {
			chdir($prevDir);
			throw $x;
		}
	}
}
