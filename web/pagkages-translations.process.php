<?php
if(!@ini_get('session.auto_start')) {
	session_start();
}
define('C5TT_NOTIFYERRORS', false);
define('C5TT_IS_WEB', true);
require_once dirname(realpath(__FILE__)) . '/../includes/startup.php';

switch($action = Request::getString('action', true)) {
	case 'login':
		$username = Request::postString('username', true);
		$password = Request::postString('password', true, false);
		require_once C5TT_INCLUDESPATH . '/db.php';
		$rs = DB::query('select * from C5TTUser where uUsername = ' . DB::escape($username) . ' and uPassword = ' . DB::escape($password) . ' and uDisabled = 0 limit 1');
		$user = $rs->fetch_assoc();
		$rs->close();
		if(!$user) {
			throw new Exception('Invalid username/password, or account disabled');
		}
		unset($user['uPassword']);
		$user['uId'] = intval($user['uId']);
		$user['uDisabled'] = !empty($user['uDisabled']);
		$_SESSION['user'] = $user;
		@setcookie('c5tt_username', $username, strtotime('+30 days'));
		$result = $user;
		break;
	case 'logout':
		unset($_SESSION['user']);
		$result = true;
		break;
	case 'get-transifex-project':
		if(!array_key_exists('user', $_SESSION)) {
			throw new Exception('Access denied');
		}
		$result = C5TT_TRANSIFEX_PACKAGES_PROJECT;
		break;
	case 'get-packages':
		if(!array_key_exists('user', $_SESSION)) {
			throw new Exception('Access denied');
		}
		$result = Package::getAll();
		break;
	case 'save-package':
		if(!array_key_exists('user', $_SESSION)) {
			throw new Exception('Access denied');
		}
		$editing = null;
		$s = Request::postString('handleOld', false);
		if(strlen($s)) {
			$editing = Package::getByHandle($s);
			if(!$editing) {
				throw new Exception('Package not found: ' . $s);
			}
		}
		$inDB = Request::postInt('inDB', true, -1, 1);
		$inTX = (Request::postInt('inTX', true, 0, 1) === 0) ? false : true;
		require_once C5TT_INCLUDESPATH . '/db.php';
		require_once C5TT_INCLUDESPATH . '/transifexer.php';

		if(($inDB === 0) && (!$inTX)) {
			$newHandle = '';
		}
		else {
			$newHandle = Request::postString('handle', true);
			$newHandle = preg_replace('/\\s+|_+/', '-', strtolower($newHandle));
			if(!preg_match('/^[a-z]([a-z0-9\\-]*[a-z0-9])?$/', $newHandle)) {
				throw new Exception('Invalid handle format');
			}
			if($editing && strcasecmp($editing->pHandle, $newHandle)) {
				if(strlen($editing->pNameTX)) {
					throw new Exception("It's not possible to change the handle of a package that's in Transifex");
				}
				if(Package::getByHandle($newHandle)) {
					throw new Exception("There's already another package with the handle '$newHandle'");
				}
			}
			elseif(!$editing) {
				if(Package::getByHandle($newHandle)) {
					throw new Exception("There's already another package with the handle '$newHandle'");
				}
			}
		}
		$sql = '';
		if($inDB === 0) {
			if($editing && strlen($editing->pNameDB)) {
				$sql = 'delete from C5TTPackage where pHandle = ' . DB::escape($editing->pHandle) . ' limit 1';
			}
		}
		else {
			$dbFields = array();
			$dbFields['pHandle'] = DB::escape($newHandle);
			$dbFields['pName'] = DB::escape(Request::postString('name', true));
			$dbFields['pSourceUrl'] = DB::escape(Request::postString('sourceurl', false), true);
			$dbFields['pDisabled'] = ($inDB < 0) ? '1' : '0';
			foreach($dbFields as $k => $v) {
				if(!strlen($sql)) {
					$sql = ($editing && strlen($editing->pNameDB)) ? 'update C5TTPackage set ' : 'insert into C5TTPackage set ';
				}
				else {
					$sql .= ', ';
				}
				$sql .= "$k = $v";
			}
			if($editing && strlen($editing->pNameDB)) {
				$sql .= ' where pHandle = ' . DB::escape($editing->pHandle) . ' limit 1';
			}
		}
		$txOperation = '';
		$txUpdatePot = null;
		$txData = array();
		if($inTX === false) {
			if($editing && strlen($editing->pNameTX)) {
				$txOperation = 'DELETE';
			}
		}
		else {
			$txData['name'] = Request::postString('name', true);
			if($editing && strlen($editing->pNameTX)) {
				if(strcasecmp($txData['name'], $editing->pNameTX)) {
					$txOperation = 'UPDATE';
				}
				$txUpdatePot = Request::file('potfile', false);
			}
			else {
				$txOperation = 'CREATE';
				$potFile = Request::file('potfile', true);
				$txData['slug'] = $newHandle;
				$txData['i18n_type'] = 'PO';
				$txData['content'] = file_get_contents($potFile['file']);
			}
		}
		DB::setAutocommit(false);
		try {
			if(strlen($sql)) {
				DB::query($sql);
			}
			if(strlen($txOperation) || (!is_null($txUpdatePot))) {
				$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);
				if(strlen($txOperation)) {
					switch($txOperation) {
						case 'CREATE':
							$transifexer->createResource(C5TT_TRANSIFEX_PACKAGES_PROJECT, $txData);
							break;
						case 'UPDATE':
							$transifexer->updateResource(C5TT_TRANSIFEX_PACKAGES_PROJECT, $editing->pHandle, $txData);
							break;
						case 'DELETE':
							$transifexer->deleteResource(C5TT_TRANSIFEX_PACKAGES_PROJECT, $editing->pHandle);
							break;
					}
				}
				if(!is_null($txUpdatePot)) {
					$transifexer->updateResourcePot(C5TT_TRANSIFEX_PACKAGES_PROJECT, $editing->pHandle, file_get_contents($txUpdatePot['file']));
				}
			}
			if(strlen($newHandle)) {
				$result = Package::getByHandle($newHandle);
			}
			else {
				$result = true;
			}
			DB::commit();
			try {
				DB::setAutocommit(true);
			}
			catch(Exception $x) {
			}
		}
		catch(Exception $x) {
			try {
				DB::rollback();
			}
			catch(Exception $x) {
			}
			try {
				DB::setAutocommit(true);
			}
			catch(Exception $x) {
			}
			throw $x;
		}
		break;
	default:
		throw new Exception("Unknown action: $action");
}
header('Content-Type: application/json; charset=UTF-8', true);
echo json_encode($result);
die();

class Request {
	private static function _string($array, $name, $required, $trim) {
		$v = (is_array($array) && array_key_exists($name, $array) && is_string($array[$name])) ? $array[$name] : '';
		if($trim) {
			$v = trim($v);
		}
		if($required && (!strlen($v))) {
			throw new Exception("Missing $name");
		}
		return $v;
	}
	public static function getString($name, $required, $trim = true) {
		return self::_string(isset($_GET) ? $_GET : array(), $name, $required, $trim);
	}
	public static function postString($name, $required, $trim = true) {
		return self::_string(isset($_POST) ? $_POST : array(), $name, $required, $trim);
	}
	private static function _int($array, $name, $required, $min, $max) {
		$s = self::_string($array, $name, $required, true);
		if(!strlen($s)) {
			return null;
		}
		if(!is_numeric($s)) {
			throw new Exception("Invalid value of $name: $s");
		}
		$s = @intval($s);
		if((!is_null($min)) && ($s < $min)) {
			throw new Exception("Invalid value of $name: $s");
		}
		if((!is_null($max)) && ($s > $max)) {
			throw new Exception("Invalid value of $name: $s");
		}
		return $s;
	}
	public static function getInt($name, $required, $min = null, $max = null) {
		return self::_int(isset($_GET) ? $_GET : array(), $name, $required, $min, $max);
	}
	public static function postInt($name, $required, $min = null, $max = null) {
		return self::_int(isset($_POST) ? $_POST : array(), $name, $required, $min, $max);
	}
	public static function file($name, $required) {
		$result = null;
		if(isset($_FILES) && is_array($_FILES) && array_key_exists($name, $_FILES)) {
			$file = $_FILES[$name];
			if((!is_array($file)) || (!array_key_exists('error', $file)) || is_array($file['error'])) {
				throw new Exception('Invalid file field received: ' . $name);
			}
			switch($file['error']) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_INI_SIZE:
					throw new Exception('The uploaded file exceeds the upload_max_filesize directive in php.ini');
				case UPLOAD_ERR_FORM_SIZE:
					throw new Exception('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
				case UPLOAD_ERR_PARTIAL:
					throw new Exception('The uploaded file was only partially uploaded');
				case UPLOAD_ERR_NO_FILE:
					throw new Exception('No file was uploaded');
				default:
					if(defined('UPLOAD_ERR_NO_TMP_DIR') && ($file['error'] == UPLOAD_ERR_NO_TMP_DIR)) {
						throw new Exception('Missing a temporary folder');
					}
					elseif(defined('UPLOAD_ERR_CANT_WRITE') && ($file['error'] == UPLOAD_ERR_CANT_WRITE)) {
						throw new Exception('Failed to write file to disk');
					}
					elseif(defined('UPLOAD_ERR_EXTENSION') && ($file['error'] == UPLOAD_ERR_EXTENSION)) {
						throw new Exception('A PHP extension stopped the file upload');
					}
					else {
						throw new Exception('Unknown error occurred during file upload');
					}
			}
			if(@empty($file['size']) || ($file['size'] < 0)) {
				throw new Exception('Uploaded file is empty');
			}
			if(!(@is_file(@$file['tmp_name']) && @is_readable($file['tmp_name']))) {
				throw new Exception('Uploaded file is not readable');
			}
			$result = array('name' => $file['name'], 'file' => $file['tmp_name'], 'size' => $file['size']);
		}
		if($required && (!$result)) {
			throw new Exception("Missing $name");
		}
		return $result;
	}
}

class Package {
	public $pHandle;
	public $pNameDB;
	public $pNameTX;
	public $pSourceUrl;
	public $pDisabled;
	private function __construct($data) {
		if(!is_array($data)) {
			$data = array();
		}
		$this->pHandle = (array_key_exists('pHandle', $data) && is_string($data['pHandle'])) ? $data['pHandle'] : '';
		$this->pNameDB = (array_key_exists('pNameDB', $data) && is_string($data['pNameDB'])) ? $data['pNameDB'] : '';
		$this->pNameTX = (array_key_exists('pNameTX', $data) && is_string($data['pNameTX'])) ? $data['pNameTX'] : '';
		$this->pSourceUrl = (array_key_exists('pSourceUrl', $data) && is_string($data['pSourceUrl'])) ? $data['pSourceUrl'] : '';
		$this->pDisabled = array_key_exists('pDisabled', $data) ? (!empty($data['pDisabled'])) : false;
	}
	public static function getAll() {
		$packages = array();
		require_once C5TT_INCLUDESPATH . '/db.php';
		$rs = DB::query('select * from C5TTPackage');
		while($row = $rs->fetch_assoc()) {
			$row['pNameDB'] = $row['pName'];
			$packages[] = new self($row);
		}
		$rs->close();
		require_once C5TT_INCLUDESPATH . '/transifexer.php';
		$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);
		foreach($transifexer->getResources(C5TT_TRANSIFEX_PACKAGES_PROJECT) as $tx) {
			$handle = $tx['slug'];
			$found = false;
			foreach(array_keys($packages) as $i) {
				if($packages[$i]->pHandle === $tx['slug']) {
					$packages[$i]->pNameTX = $tx['name'];
					$found = true;
					break;
				}
			}
			if(!$found) {
				$packages[] = new self(array('pHandle' => $tx['slug'], 'pNameTX' => $tx['name']));
			}
		}
		return $packages;
	}
	public static function getByHandle($handle) {
		$h = is_string($handle) ? trim($handle) : '';
		if(!strlen($h)) {
			throw new Exception('Missing package handle');
		}
		require_once C5TT_INCLUDESPATH . '/db.php';
		$rs = DB::query('select * from C5TTPackage where pHandle = ' . DB::escape($h) . ' limit 1');
		$dbData = $rs->fetch_assoc();
		$rs->close();
		$txData = null;
		require_once C5TT_INCLUDESPATH . '/transifexer.php';
		$transifexer = new Transifexer(C5TT_TRANSIFEX_HOST, C5TT_TRANSIFEX_USERNAME, C5TT_TRANSIFEX_PASSWORD);
		foreach($transifexer->getResources(C5TT_TRANSIFEX_PACKAGES_PROJECT) as $tx) {
			if(strcasecmp($tx['slug'], $h) === 0) {
				$txData = $tx;
				break;
			}
		}
		if($dbData) {
			$dbData['pNameDB'] = $dbData['pName'];
			$package = new self($dbData);
			if($txData) {
				$package->pNameTX = $txData['name'];
			}
			return $package;
		}
		elseif($txData) {
			return new self(array('pHandle' => $txData['slug'], 'pNameTX' => $txData['name']));
		}
		else {
			return null;
		}
	}
}
