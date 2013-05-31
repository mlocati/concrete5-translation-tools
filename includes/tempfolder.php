<?php

/** A temporary folder manager. */
class TempFolder {
	/** The full path of the temporary folder.
	* @var string
	*/
	private $name;
	/** An open handle to a lock file (just to avoid deleting the folder while the instance exists.
	* @var resource
	*/
	private $hLock;
	/** Initializes the instance (creates a new temporary folder).
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function __construct() {
		$this->name = '';
		$this->hLock = null;
		$eligible = array();
		if(function_exists('sys_get_temp_dir')) {
			$eligible[] = @sys_get_temp_dir();
		}
		foreach(array('TMPDIR', 'TEMP', 'TMP') as $env) {
			$eligible[] = @getenv($env);
		}
		$parent = '';
		foreach($eligible as $f) {
			if(is_string($f) && strlen($f)) {
				$f2 = @realpath($f);
				if(($f2 !== false) && @is_dir($f2) && is_writable($f2)) {
					$parent = $f2;
					break;
				}
			}
		}
		if(!strlen($parent)) {
			throw new Exception('The temporary directory cannot be found.');
		}
		for($i = 0; ; $i++) {
			$folder = Enviro::mergePath($parent, "c5tt-$i");
			if(!file_exists($folder)) {
				break;
			}
		}
		@mkdir($folder, 0777);
		if(!is_dir($folder)) {
			throw new Exception("The temporary directory '$folder' could not be created.");
		}
		$this->name = $folder;
		$this->hLock = @fopen(Enviro::mergePath($this->name, '.lock'), 'wb');
	}
	/** Destroys this instance (and deletes its temporary folder). */
	function __destruct() {
		if($this->hLock) {
			@fflush($this->hLock);
			@fclose($this->hLock);
			$this->hLock = null;
		}
		if(strlen($this->name)) {
			Enviro::deleteFolder($this->name);
			$this->name = '';
		}
	}
	/** Gets the full path to the temporary folder.
	* @return string
	*/
	public function getName() {
		return $this->name;
	}
	/** Gets the name (full path) of a new file inside the temporary folder.
	* @param bool $createEmpty [default: false] Should the function create an empty file?
	* @return string
	* @throws Exception Throws an Exception if the creation of the temporary file fails.
	 */
	public function getNewFile($createEmpty = false) {
		for($i = 0; ; $i++) {
			$filename = Enviro::mergePath($this->name, "tmp-$i");
			if(!file_exists($filename)) {
				if($createEmpty) {
					if(@touch($filename) === false) {
						throw new Exception("Error creating the temporary file '$filename'.");
					}
				}
				return $filename;
			}
		}
	}
	/** Gets the default TempFolder instance.
	* @return TempFolder
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function getDefault() {
		static $instance;
		if(!(isset($instance) && strlen($instance->name))) {
			$class = __CLASS__;
			$instance = new $class();
		}
		return $instance;
	}
}
