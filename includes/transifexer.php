<?php

/** A Transifex connector */
class Transifexer {
	/** Transifex host.
	* @var string
	*/
	private $host;
	/** Transifex user name.
	* @var string
	*/
	private $username;
	/** Transifex password.
	* @var string
	*/
	private $password;
	/** false to stop cURL from verifying the peer's certificate the existence of a common name in the SSL peer certificate.
	* @var bool
	*/
	private $checkSSL;
	/** Proxy configuration. If null: don't use proxy, else it's an array with the keys:<ul>
	*	<li>string <b>host</b> proxy host</li>
	*	<li>int <b>port</b> proxy port</li>
	*	<li>string <b>user</b> proxy username</li>
	*	<li>string <b>password</b> proxy password</li>
	* </ul>
	* @var array|null
	*/
	private $proxy;
	/** Initializes the instance.
	* @param string $host The Transifex host.
	* @param string $username The Transifex user name.
	* @param string $password The Transifex password.
	* @param bool $checkSSL [default: false] Set to false to stop cURL from verifying the peer's certificate the existence of a common name in the SSL peer certificate.
	* @param null|array $proxy If communication needs a proxy: specify an array with the keys:<ul>
	*	<li>string <b>host</b> proxy host</li>
	*	<li>int <b>port</b> proxy port</li>
	*	<li>string <b>user</b> proxy username</li>
	*	<li>string <b>password</b> proxy password</li>
	* </ul>
	*/
	public function __construct($host, $username, $password, $checkSSL = false, $proxy = null) {
		$this->host = rtrim($host, '/');
		$this->username = $username;
		$this->password = $password;
		$this->checkSSL = $checkSSL;
		if((!is_array($proxy)) || empty($proxy['host'])) {
			$this->proxy = null;
		}
		else {
			$this->proxy = array(
				'host' => strval($proxy['host']),
				'port' => empty($proxy['port']) ? 0 : @intval($proxy['port']),
				'user' => is_string($proxy['user']) ? $proxy['user'] : '',
				'password' => is_string($proxy['password']) ? $proxy['password'] : '',
			);
		}
	}
	/** Perform a query to Transifex.
	* @param string $query The Transifex url to query (without host).
	* @param array|null $postData The data to post (null for a GET operation).
	* @param bool $decodeJSON [default: true] Do we have to decode the result considering it as json?
	* @return mixed
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	protected function query($query, $postData = null, $decodeJSON = true) {
		global $php_errormsg;
		if(!function_exists('curl_init')) {
			throw TransifexerException::getByCode(TransifexerException::CURL_NOT_INSTALLED);
		}
		if(!$hCurl = @curl_init()) {
			throw TransifexerException::getByCode(TransifexerException::CURL_INIT_FAILED);
		}
		try {
			if($this->proxy) {
				if(!@curl_setopt($hCurl, CURLOPT_PROXY, $this->proxy['host'])) {
					throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
				}
				if($this->proxy['port'] && (!@curl_setopt($hCurl, CURLOPT_PROXYPORT, Config::get('HTTP_PROXY_PORT')))) {
					throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
				}
				if(strlen($this->proxy['user'])) {
					if(!@curl_setopt($hCurl, CURLOPT_PROXYUSERPWD, $this->proxy['user'] . ':' . $this->proxy['password'])) {
						throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
					}
				}
			}
			if(!@curl_setopt($hCurl, CURLOPT_SSL_VERIFYHOST, $this->checkSSL ? 2 : 1)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, $this->checkSSL ? true : false)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_USERPWD, $this->username . ':' . $this->password)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_BINARYTRANSFER, true)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_FOLLOWLOCATION, true)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!@curl_setopt($hCurl, CURLOPT_HEADER, false)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(!empty($postData)) {
				if(!@curl_setopt($hCurl, CURLOPT_POST, true)) {
					throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
				}
				if(!@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'))) {
					throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
				}
				if(!@curl_setopt($hCurl, CURLOPT_POSTFIELDS, json_encode($postData))) {
					throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
				}
			}
			$url = $this->host . '/api/2/' . ltrim($query, '/');
			if(!@curl_setopt($hCurl, CURLOPT_URL, $url)) {
				throw TransifexerException::getByCode(TransifexerException::CURL_SETOPT_FAILED);
			}
			if(($response = curl_exec($hCurl)) === false) {
				throw TransifexerException::getByCode(TransifexerException::CURL_EXEC_FAILED);
			}
			if(!($info = @curl_getinfo($hCurl))) {
				throw TransifexerException::getByCode(TransifexerException::CURL_GETINFO_FAILED);
			}
			@curl_close($hCurl);
		}
		catch(Exception $x) {
			@curl_close($hCurl);
			throw $x;
		}
		switch(empty($info['http_code']) ? 0 : $info['http_code']) {
			case 200:
				break;
			case 400:
				if(strpos($response, 'Unknown language code ') === 0) {
					throw TransifexerException::getByCode(TransifexerException::INVALID_LANGUAGE);
				}
				throw TransifexerException::getByCode(TransifexerException::UNEXPECTED_TRANSFER_ERROR, 'Error ' . $info['http_code'] . ' in response from Transifex');
			case 401:
				throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_LOGIN);
			case 404:
				throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
			default:
				throw TransifexerException::getByCode(TransifexerException::UNEXPECTED_TRANSFER_ERROR, 'Error ' . $info['http_code'] . ' in response from Transifex');
		}
		if($decodeJSON) {
			if(!function_exists('json_decode')) {
				throw TransifexerException::getByCode(TransifexerException::JSON_DECODE_NOT_AVAILABLE);
			}
			$jd = @json_decode($response, true);
			if($jd === false) {
				throw TransifexerException::getByCode(TransifexerException::JSON_DECODE_FAILED);
			}
			$response = $jd;
		}
		return $response;
	}
	/** List all the Transifex projects.
	* @return array[array] Returns a list of arrays, each one with these keys:<ul>
	*	<li>string <b>name</b> The project name</li>
	*	<li>string <b>slug</b> The project url slug</li>
	*	<li>string <b>description</b> The project description</li>
	*	<li>string <b>source_language_code</b> The code of the source language</li>
	* </ul>
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function listProjects() {
		return $this->query('projects/');
	}
	/** Returns info about a project.
	* @param string $projectSlug The url slug of the project.
	* @param bool $detailed [default: false] Do you want detailed info?
	* @return array Returns an array with these keys:<ul>
	*	<li>string <b>name</b> The project name</li>
	*	<li>string <b>slug</b> The project url slug</li>
	*	<li>string <b>source_language_code</b> The code of the source language</li>
	*	<li>string <b>description</b> The project description</li>
	* </ul>
	* If <b>$detailed</b> is true, you'll get also the following keys:<ul>
	*	<li>string <b>long_description</b> The project description (long version)</li>
	*	<li>string <b>homepage</b> The website of the project</li>
	*	<li>string <b>tags</b> A comma separated list of tags</li>
	*	<li>string <b>trans_instructions</b> Translator instructions url</li>
	*	<li>bool <b>anyone_submit</b> Allow translations submissions from any Transifex user</li>
	*	<li>bool <b>private</b> Is the project private?</li>
	*	<li>string <b>last_updated</b> UTC date/time of last update (eg '2012-12-31 13:15:59')</li>
	*	<li>bool <b>fill_up_resources</b> whether the system will fill up resources automatically with 100% similar matches from the Translation Memory</li>
	*	<li>string|null <b>outsource</b> If not null: the name of another Transifex project whose language teams can access this project</li>
	*	<li>array <b>owner</b> The project owner, an array with the following keys:<ul>
	*		<li>string <b>username</b> A Transifex user name</li>
	*	</ul></li>
	*	<li>array[array] <b>maintainers</b> The project maintainers: a list of arrays, each one has the following keys:<ul>
	*		<li>string <b>username</b> A Transifex user name</li>
	*	</ul></li>
	*	<li>array[array] <b>resources</b> The resources owned by this project. It's a list of arrays, each one with the following keys:<ul>
	*		<li>string <b>name</b> The resource name</li>
	*		<li>string <b>slug</b> The resource slug</li>
	*	</ul>
	*	<li>array[string] <b>teams</b> A list of language codes for which there are teams (eg: 'it_IT').</li>
	*	<li>string <b>bug_tracker</b></li>
	*	<li>string <b>feed</b></li>
	* </ul>
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function getProjectInfo($projectSlug, $detailed = false) {
		if(!preg_match('#^[\w\-]+$#', $projectSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		return $this->query("project/$projectSlug/" . ($detailed ? '?details' : ''));
	}
	/** Returns the list of resources associated to a project.
	* @param string $projectSlug The url slug of the project.
	* @return array[array] Returns a list of array with these keys:<ul>
	*	<li>string <b>name</b> The resource name.</li>
	*	<li>string <b>slug</b> The resource slug.</li>
	*	<li>string <b>i18n_type</b> The type of the resource ('PO', 'QT', 'INI', ...).</li>
	*	<li>string <b>source_language_code</b> The code of the source language</li>
	*	<li>string|null <b>category</b> The category of the resource.</li>
	* </ul>
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function getResources($projectSlug) {
		if(!preg_match('#^[\w\-]+$#', $projectSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		return $this->query("project/$projectSlug/resources/");
	}
	/** Returns info about a project resource.
	* @param string $projectSlug The url slug of the project.
	* @param string $resourceSlug The url slug of the resource.
	* @param bool $detailed [default: false] Do you want detailed info?
	* @return array Returns an array with these keys:<ul>
	*	<li>string <b>name</b> The resource name.</li>
	*	<li>string <b>slug</b> The resource slug.</li>
	*	<li>string <b>i18n_type</b> The type of the resource ('PO', 'QT', 'INI', ...).</li>
	*	<li>string <b>source_language_code</b> The code of the source language</li>
	*	<li>string|null <b>category</b> The category of the resource.</li>
	* </ul>
	* If <b>$detailed</b> is true, you'll get also the following keys:<ul>
	*	<li>string <b>created</b> UTC date/time of creation (eg '2012-12-31 13:15:59')</li>
	*	<li>string <b>last_updated</b> UTC date/time of last update (eg '2012-12-31 13:15:59')</li>
	*	<li>string <b>project_slug</b> The url slug of the project owning this resource</li>
	*	<li>int <b>total_entities</b> The number of translable strings</li>
	*	<li>int <b>wordcount</b> The total number of words in the translable strings</li>
	*	<li>bool <b>accept_translations</b> Does the resource accept translations?</li>
	*	<li>array[array] <b>available_languages</b> A list of available languages for this resource. Each item has the following keys:<ul>
	*		<li>string <b>name</b> Language name in the 'Language (Nationality)' format. The nationality can be missing for general languages like 'pt'</li>
	*		<li>string <b>code</b> The language code</li>
	*		<li>string <b>code_aliases</b> Known possible aliases separated with spaces</li>
	*		<li>string <b>description</b></li>
	*		<li>string <b>specialchars</b></li>
	*		<li>int <b>nplurals</b> Number of plurals allowed by the language; quite common in .po files</li>
	*		<li>string <b>pluralequation</b> Equation to distinguish the plural rules for the available nplurals; quite common in .po files</li>
	*		<li>string|null <b>rule_zero</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string|null <b>rule_two</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string|null <b>rule_one</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string|null <b>rule_few</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string|null <b>rule_many</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string|null <b>rule_other</b> Plural rule (see https://github.com/transifex/transifex/blob/master/transifex/languages/fixtures/all_languages.json)</li>
	*		<li>string <b></b> </li>
	*		<li>string <b></b> </li>
	*		<li>string <b></b> </li>
	*	</ul></li>
	* </ul>
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function getResourceInfo($projectSlug, $resourceSlug, $detailed = false) {
		if(!preg_match('#^[\w\-]+$#', $projectSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		if(!preg_match('#^[\w\-]+$#', $resourceSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		return $this->query("project/$projectSlug/resource/$resourceSlug/" . ($detailed ? '?details' : ''));
	}
	/** Retrieves statistics for a resource.
	* @param string $projectSlug The url slug of the project.
	* @param string $resourceSlug The url slug of the resource.
	* @param string $languageCode [default: ''] If specified, the statistics are just for one language; if not specified, every language is returned.
	* @return array If $languageCode is specified the result will be an array of arrays, whose keys are the language codes. The stats for the language is an array with the following keys:<ul>
	*	<li>string <b>last_commiter</b> The Transifex user name of the last committer</li>
	*	<li>string <b>last_update</b> UTC date/time of last update (eg '2012-12-31 13:15:59')</li>
	*	<li>string <b>completed</b> The percentual of translation progress (eg: '100%')</li>
	*	<li>int <b>reviewed</b> The number of reviewed translations</li>
	*	<li>string <b>reviewed_percentage</b> The percentual of reviewed translations (eg: '100%')</li>
	*	<li>int <b>translated_entities</b> The number of translated entities</li>
	*	<li>int <b>untranslated_entities</b> The number of untranslated entities</li>
	*	<li>int <b>translated_words</b> The number of translated words</li>
	*	<li>int <b>untranslated_words</b> The number of untranslated words</li>
	* </ul>
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function getResourceStats($projectSlug, $resourceSlug, $languageCode = '') {
		if(!preg_match('#^[\w\-]+$#', $projectSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		if(!preg_match('#^[\w\-]+$#', $resourceSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		$query = "project/$projectSlug/resource/$resourceSlug/stats/";
		if(is_string($languageCode) && strlen($languageCode)) {
			if(!preg_match('#^[\w\-]+$#', $languageCode)) {
				throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
			}
			$query .= "$languageCode/";
		}
		return $this->query($query);
	}
	/** Creates a new resource.
	* @param string $projectSlug The Transifex project slug.
	* @param array $data The data of the new resource. It's an array with these keys:<ul>
	*	<li>string <b>slug</b> [required] The new resource slug.</li>
	*	<li>string <b>name</b> [required] The new resource name.</li>
	*	<li>string <b>content</b> [required] The new resource content.</li>
	*	<li>string <b>i18n_type</b> [required] The type of the new resource ('PO', 'QT', 'INI', ...).</li>
	*	<li>bool <b>accept_translations</b> [optional] Does the resource accept translations?</li>
	*	<li>string <b>category</b> [optional] The category of the new resource.</li>
	* </ul>
	* @return array Returns the same result as of getResourceInfo() with detaled informations.
	* @throws TransifexerException Throws a TransifexerException in case of errors.
	*/
	public function createResource($projectSlug, $data) {
		if(!preg_match('#^[\w\-]+$#', $projectSlug)) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		if(!preg_match('#^[\w\-]+$#', @$data['slug'])) {
			throw TransifexerException::getByCode(TransifexerException::TRANSIFEX_BAD_COMMAND);
		}
		if(isset($data['accept_translations'])) {
			$data['accept_translations'] = $data['accept_translations'] ? '1' : '0';
		}
		$initialException = null;
		try {
			$this->query("project/$projectSlug/resources/", $data);
		}
		catch(TransifexerException $x) {
			if(($x->getCode() == TransifexerException::UNEXPECTED_TRANSFER_ERROR) && (strpos($x->getMessage(), 'Error 500 in response from Transifex') === 0)) {
				$initialException = $x;
			}
			else {
				throw $x;
			}
		}
		try {
			return $this->getResourceInfo($projectSlug, $data['slug'], true);
		}
		catch(Exception $x) {
			throw $initialException ? $initialException : $x;
		}
	}
	/** Pulls data from Transifex into a local folder (using the tx command).
	* @param string $projectSlug The Transifex project slug.
	* @param string $folder The local folder where to store data.
	* @param bool $reset [default: false] Force reload of all the translations (useful to clean potential dirty local .po files).
	* @param string $resourceSlug [default: ''] If specified, only this resource will be pulled.
	* @param bool $onlySource [default: false] Download only source .pot file.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function pull($projectSlug, $folder, $reset = false, $resourceSlug = '', $onlySource = false) {
		if(!is_dir($folder)) {
			@mkdir($folder, 0777, true);
			if(!is_dir($folder)) {
				throw new Exception("Unable to create the folder '" . $folder . "'");
			}
		}
		else {
			if($reset) {
				Enviro::deleteFolder($folder, true);
			}
		}
		$prevDir = getcwd();
		chdir($folder);
		try {
			if(!is_dir('.tx')) {
				Enviro::write("Initializing Transifex... ");
				Enviro::run('tx', 'init --host=' . escapeshellarg($this->host) . ' --user=' . escapeshellarg($this->username) . ' --pass=' . escapeshellarg($this->password));
				Enviro::write("done.\n");
			}
			Enviro::write("Updating Transifex resource list for $projectSlug... ");
			Enviro::run('tx', 'set --auto-remote ' . escapeshellarg($this->host . '/projects/p/' . $projectSlug . '/'));
			Enviro::write("done.\n");
			Enviro::write("Fetching Transifex resources... ");
			$args = array();
			$args[] = 'pull';
			$args[] = $onlySource ? ' --source' : ' --all';
			if(strlen($resourceSlug)) {
				$args[] = '--resource=' . escapeshellarg("$projectSlug.$resourceSlug");
			}
			$args[] = '--mode=developer';
			Enviro::run('tx', $args);
			Enviro::write("done.\n");
		}
		catch(Exception $x) {
			@chdir($prevDir);
			throw $x;
		}
		@chdir($prevDir);
	}
	public function push($folder, $projectSlug = '', $resourceSlug = '') {
		if(!is_dir(Enviro::mergePath($folder, '.tx'))) {
			throw new Exception("'$folder' is not a valid Transifex folder.");
		}
		$prevDir = getcwd();
		chdir($folder);
		try {
			$args = array();
			$args[] = 'push';
			$args[] = '--translations';
			if(strlen($projectSlug) && strlen($resourceSlug)) {
				$args[] = '--resource=' . escapeshellarg("$projectSlug.$resourceSlug");
			}
			Enviro::run('tx', $args);
		}
		catch(Exception $x) {
			@chdir($prevDir);
			throw $x;
		}
		@chdir($prevDir);
	}
}

/** An exception related to Transifexer */
class TransifexerException extends Exception {
	/** Error code on wrong Transifex username and/or password
	* @var int
	*/
	const TRANSIFEX_BAD_LOGIN = 100;
	/** Error code on invalid Transifex slug or command
	* @var int
	*/
	const TRANSIFEX_BAD_COMMAND = 101;
	/** Error code on curl not installed
	* @var int
	*/
	const CURL_NOT_INSTALLED = 200;
	/** Error code on curl_init() failure
	* @var int
	*/
	const CURL_INIT_FAILED = 201;
	/** Error code on curl_setopt() failure
	* @var int
	*/
	const CURL_SETOPT_FAILED = 202;
	/** Error code on curl_exec() failure
	* @var int
	*/
	const CURL_EXEC_FAILED = 203;
	/** Error code on curl_getinfo() failure
	* @var int
	*/
	const CURL_GETINFO_FAILED = 203;
	/** Error code on json_decode() not available
	* @var int
	*/
	const JSON_DECODE_NOT_AVAILABLE = 300;
	/** Error code on json_decode() failure
	* @var int
	*/
	const JSON_DECODE_FAILED = 301;
	/** Error code on unexpected error received during the communication with Transifex
	* @var int
	*/
	const UNEXPECTED_TRANSFER_ERROR = 501;
	/** Error code when a language code is wrong
	* @var int
	*/
	const INVALID_LANGUAGE = 502;
	/** Initializes the instance.
	* @param string $message The error message
	* @param int $code One of the TransifexerException:: constants
	*/
	public function __construct($message, $code) {
		parent::__construct($message, $code);
	}
	/** Returns a TransifexerException instance.
	* @param int $code One of the TransifexerException:: constants
	* @param string $message [default: ''] If specified, overrides the default error message
	* @return TransifexerException
	*/
	public static function getByCode($code, $message = '') {
		return new TransifexerException(strlen($message) ? $message : self::describeError($code), $code);
	}
	/** Describes an error code.
	* @param int $code One of the TransifexerException:: constants
	* @return string
	*/
	public static function describeError($code) {
		if(is_numeric($code)) {
			switch(@intval($code)) {
				case self::TRANSIFEX_BAD_LOGIN:
					return 'Wrong Transifex username and/or password';
				case self::TRANSIFEX_BAD_COMMAND:
					return 'Invalid Transifex slug or command';
				case self::CURL_NOT_INSTALLED:
					return 'curl is not installed';
				case self::CURL_INIT_FAILED:
					return 'The curl_init() function failed';
				case self::CURL_SETOPT_FAILED:
					return 'The curl_setopt() function failed';
				case self::CURL_EXEC_FAILED:
					return 'The curl_exec() function failed';
				case self::CURL_GETINFO_FAILED:
					return 'The curl_getinfo() function failed';
				case self::JSON_DECODE_NOT_AVAILABLE:
					return 'The json_decode() is not available';
				case self::JSON_DECODE_FAILED:
					return 'The function json_decode() failed';
				case self::UNEXPECTED_TRANSFER_ERROR:
					return 'An unexpected error occurred during the data transfer from Transifex';
				case self::INVALID_LANGUAGE:
					return 'An invalid language code was specified';
			}
		}
		return "Unknown error: $code";
	}
}

/** A local translation file (.po and .mo) from Transifex. */
class TransifexerTranslation {
	/** The project slug.
	* @var string
	*/
	public $projectSlug;
	/** The resource slug.
	* @var string
	*/
	public $resourceSlug;
	/** The language code.
	* @var string
	*/
	public $languageCode;
	/** Absolute location of the .po file (in the Transifex folder).
	* @var string
	*/
	public $poPath;
	/** Absolute location of the .mo file (in the Transifex folder).
	* @var string
	*/
	public $moPath;
	/** Initializes the instance.
	* @param string $poPath Absolute path to the po file.
	*/
	private function __construct($poPath) {
		$rxDirSep = preg_quote(DIRECTORY_SEPARATOR, '/');
		if(!preg_match('/(^|' . $rxDirSep . ')' . '([^' . $rxDirSep . ']+)' . '\\.([^' . $rxDirSep . ']+)' . $rxDirSep . '(([^' . $rxDirSep . ']+)\\.po)$/i', $poPath, $m)) {
			throw new Exception("Invalid relative po file name: '$poAbsolute'");
		}
		$this->projectSlug = $m[2];
		$this->resourceSlug = $m[3];
		$this->languageCode = $m[5];
		$this->poPath = $poPath;
		$this->moPath = preg_replace('/\\.po$/i', '.mo', $poPath);
	}
	/** Retrieves a string describing this instance.
	* @return string
	*/
	public function getName() {
		return "{$this->projectSlug}.{$this->resourceSlug}.{$this->languageCode}";
	}
	/** Compiles the .po file into the .mo file (and retrieve stats).
	* @param bool $checkFormat [default: false] Should we check the format?
	* @return array Returns an informational array about the translated strings; the array keys are<ul>
	*	<li>int <b>translated</b> The number of translated strings</li>
	*	<li>int <b>untranslated</b> The number of untranslated strings</li>
	*	<li>int <b>fuzzy</b> The number of fuzzy strings</li>
	*	<li>int <b>total</b> The number of total strings</li>
	*	<li>int <b>percentual</b> The percentual of translation (from 0 to 100)</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function compile($checkFormat = false) {
		Enviro::run('msgfmt', '--statistics ' . ($checkFormat ? ' --check-format' : '') . ' --check-header --check-domain --output-file=' . escapeshellarg($this->moPath) . ' ' . escapeshellarg($this->poPath), 0, $outputLines);
		$stats = null;
		foreach($outputLines as $outputLine) {
			if(preg_match('/(\\d+) translated messages/', $outputLine, $match)) {
				$stats = array(
					'translated' => intval($match[1]),
					'untranslated' => 0,
					'fuzzy' => 0
				);
				if(preg_match('/(\\d+) untranslated messages/', $outputLine, $match)) {
					$stats['untranslated'] = intval($match[1]);
				}
				if(preg_match('/(\\d+) fuzzy translations/', $outputLine, $match)) {
					$stats['fuzzy'] = intval($match[1]);
				}
				$stats['total'] = $stats['translated'] + $stats['untranslated'] + $stats['fuzzy'];
				$stats['percentual'] = ($stats['translated'] == $stats['total']) ? 100 : ($stats['total'] ? floor($stats['translated'] * 100 / $stats['total']) : 0);
				break;
			}
		}
		if(!$stats) {
			throw new Exception("Unable to parse statistics from the output\n" . implode("\n", $outputLines));
		}
		return $stats;
	}
	public static function getFilePath($folder, $projectSlug, $resourceSlug, $languageCode) {
		return Enviro::mergePath($folder, 'translations', "$projectSlug.$resourceSlug", "$languageCode.po");
	}
	/** Returns all the translations found in a local Transifex folder.
	* @param string $folder The local Transifex folder.
	* @return TransifexerTranslation[]
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function getAll($folder) {
		if(!is_dir($folder)) {
			throw new Exception("The folder folder '$folder' does not exist.");
		}
		$subFolders = array();
		$translations = array();
		$class = __CLASS__;
		if(!($hDir = @opendir($folder))) {
			throw new Exception("Unable to open folder '$folder'.");
		}
		try {
			while(($item = @readdir($hDir)) !== false) {
				switch($item) {
					case '.':
					case '..':
						break;
					default:
						$fullItem = Enviro::mergePath($folder, $item);
						if(is_dir($fullItem)) {
							$subFolders[] = $fullItem;
						}
						elseif(preg_match('/.\\.po$/i', $item)) {
							$translations[] = new $class($fullItem);
						}
						break;
				}
			}
			@closedir($hDir);
		}
		catch(Exception $x) {
			@closedir($hDir);
			throw $x;
		}
		foreach($subFolders as $subFolder) {
			$translations = array_merge($translations, self::getAll($subFolder));
		}
		usort($translations, array($class, 'sort'));
		return $translations;
	}
	/** Translations sorter
	* @param TransifexerTranslation $a
	* @param TransifexerTranslation $b
	* @return int
	*/
	private static function sort($a, $b) {
		if(!($i = strcasecmp($a->projectSlug, $b->projectSlug))) {
			if(!($i = strcasecmp($a->resourceSlug, $b->resourceSlug))) {
				$i = strcasecmp($a->languageCode, $b->languageCode);
			}
		}
		return $i;
	}
	/** Checks if the instance .po file and another .po file are different.
	* @return boolean
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function detectChanges($poPath) {
		if(!is_file($poPath)) {
			return true;
		}
		$thisData = @file_get_contents($this->poPath);
		if($thisData === false) {
			throw new Exception("Error reading file '{$this->poPath}'.");
		}
		$thatData = @file_get_contents($poPath);
		if($thatData === false) {
			throw new Exception("Error reading file '$poPath'.");
		}
		if(strcmp($thisData, $thatData) === 0) {
			return false;
		}
		$thisData = preg_replace('/(POT-Creation-Date|PO-Revision-Date): [0-9:\\-+ ]+/', '', $thisData);
		$thatData = preg_replace('/(POT-Creation-Date|PO-Revision-Date): [0-9:\\-+ ]+/', '', $thatData);
		if(strcmp($thisData, $thatData) === 0) {
			return false;
		}
		return true;
	}
	/** Copy the .po and .mo files to another folder.
	* @param string $folder The destination folder.
	* @param string $baseName The base name of the destination files (eg without .po/.mo extension).
	* @throws Exception Throws an Exception in case of errors.
	*/
	public function copyTo($folder, $baseName) {
		if(!@is_dir($folder)) {
			@mkdir($folder, 0777, true);
			if(!@is_dir($folder)) {
				throw new Exception("Unable to create the folder '$folder'");
			}
		}
		$dest = Enviro::mergePath($folder, $baseName . '.po');
		if(!@copy($this->poPath, $dest)) {
			throw new Exception("Error copying from\n{$this->poPath}\nto\n$dest");
		}
		$dest = Enviro::mergePath($folder, $baseName . '.mo');
		if(!@copy($this->moPath, $dest)) {
			throw new Exception("Error copying from\n{$this->moPath}\nto\n$dest");
		}
	}
}
