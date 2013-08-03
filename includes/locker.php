<?php

/** A lock-file manager class. */
class Locker {
	/** If the instance is successfully created, contains the keys 'filename' and 'handle'.
	* @var array
	*/
	private $file;
	/** Contains all the active instances (please remark that some of its keys may have been unset).
	* @var array
	*/
	private static $instances = array();
	/** Get an exclusive lock on a file. It that file does not exist, it'll be created. If it exists, it'll be truncated (in both cases it'll be deleted on class disposition).
	* @param string $filename The lock file name.
	* @throws Exception Throws an Exception if the file couldn't be locked, or in case of other errors.
	*/
	public function __construct($filename) {
		$folder = dirname($filename);
		if(!is_dir($folder)) {
			@mkdir($folder, 0777, true);
			if(!is_dir(dirname(C5TT_LOCKFILE))) {
				throw new Exception("Unable to create the directory '$folder' for the lock file '$filename'.");
			}
		}
		if(!is_writable($folder)) {
			throw new Exception("The folder '$folder' for the lock file is not writable.");
		}
		if(!($handle = @fopen($filename, 'wb'))) {
			throw new Exception("Unable to open the lock file '$filename'.\nIs it already locked?");
		}
		try {
			if(!@flock($handle, LOCK_EX | LOCK_NB)) {
				throw new Exception("Unable to lock the file '$filename'.\nIs it already locked?");
			}
		}
		catch(Exception $x) {
			@fclose($handle);
			throw $x;
		}
		$this->file = array('filename' => $filename, 'handle' => $handle);
		self::$instances[] = $this;
	}
	/** Releases the lock and deletes the lock file. */
	function __destruct() {
		$this->release();
	}
	/** Releases the lock and deletes the lock file. */
	public function release() {
		if(isset($this->file)) {
			@flock($this->file['handle'], LOCK_UN);
			@fclose($this->file['handle']);
			@unlink($this->file['filename']);
			unset($this->file);
			foreach(array_keys(self::$instances) as $key) {
				if(self::$instances[$key] === $this) {
					unset(self::$instances[$key]);
				}
			}
		}
	}
	/** Releases all the Locker instances. */
	public static function releaseAll() {
		foreach(array_keys(self::$instances) as $key) {
			if(self::$instances[$key] instanceof Locker) {
				self::$instances[$key]->release();
			}
			unset(self::$instances[$key]);
		}
	}
}
