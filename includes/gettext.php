<?php
/** gettext-related helper functions. */
class Gettext {
	/** Search for the header entry in a .po/.pot file.
	* @param string $filename The .po/.pot file name.
	* @param bool $returnFullFileContent [default: false] Set to true to have the file content in the resulting array.
	* @return array Returns an array with the following keys:<ul>
	*	<li>int <b>start</b> The starting point of the header item in the file (-1 if not found).</li>
	*	<li>int <b>end</b> The ending point of the header item in the file (-1 if not found).</li>
	*	<li>array <b>properties</b> The properties found (key - values).</li>
	*	<li>string <b>contents</b> [only if $returnFullFileContent is true] The whole content of $filename.</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errors.
	*/
	private static function getHeader($filename, $returnFullFileContent = false) {
		$data = @file_get_contents($filename);
		if($data === false) {
			throw new Exception("Error reading the file '$filename'.");
		}
		$result = array(
			'start' => -1,
			'end' => -1,
			'properties' => array()
		);
		if($returnFullFileContent) {
			$result['contents'] = $data;
		}
		if(preg_match('/^[ \t]*msgid[ \t]+""[ \t]*$/m', $data, $match, PREG_OFFSET_CAPTURE)) {
			$start = $match[0][1];
			$end = $start + strlen($match[0][0]);
			$after = substr($data, $end);
			while(strlen($after) && (strpos(" \t\r\n", $after[0]) !== false)) {
				$end += 1;
				$after = substr($after, 1);
			}
			if(preg_match('/^msgstr[ \t]+"(.*)"[ \t]*($|[\r\n]+)/', $after, $match, PREG_OFFSET_CAPTURE) && ($match[0][1] === 0)) {
				$content = $match[1][0];
				$end += strlen($match[0][0]);
				$after = substr($after, strlen($match[0][0]));
				while(preg_match('/^[ \t]*"(.*)"[ \t]*($|[\r\n]+)/', $after, $match, PREG_OFFSET_CAPTURE)) {
					$content .= $match[1][0];
					$end += strlen($match[0][0]);
					$after = substr($after, strlen($match[0][0]));
				}
				$properties = array();
				$key = false;
				foreach(explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", stripcslashes($content)))) as $line) {
					if(preg_match('/^[ \t]*(\w.*?):(.*)$/', $line, $m)) {
						$key = trim($m[1]);
						if(!array_key_exists($key, $properties)) {
							$properties[$key] = ltrim($m[2]);
						}
					}
				}
				$result['start'] = $start;
				$result['end'] = $end;
				$result['properties'] = $properties;
			}
		}
		return $result;
	}
	/** Retrieves the properties in the header entry of a .po/.pot file.
	* @param string $filename The .po/.pot file name.
	* @return array Returns an array with keys = the property names, values = the property values.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function getPoProperties($filename) {
		$header = self::getHeader($filename);
		return $header['properties'];
	}
	/** Sets the properties (saved in the header entry) of a .po/.pot file.
	* @param array $properties The array with the new properties (keys = property name, values = property value).
	* @param string $filename The .po/.pot file name.
	* @param bool $merge [default: false] Set to true to merge old and new properties. If false only the new properties are set.
	* @param string $saveAs [default: ''] Set to a file name to save the modified .po/.pot file to another location. If empty: the $filename will be overwritten.
	* @throws Exception Throws an Exception in case of errors.
	*/
	public static function setPoProperties($properties, $filename, $merge = false, $saveAs = '') {
		$current = self::getHeader($filename, true);
		if($merge) {
			$properties = array_merge($current['properties'], $properties);
		}
		require_once Enviro::mergePath(C5TT_INCLUDESPATH, 'tempfolder.php');
		$tempFolder = TempFolder::getDefault();
		$tempFile = $tempFolder->getNewFile();
		if(!($hFile = @fopen($tempFile, 'wb'))) {
			throw new Exception("Error creating file '$tempFile'.");
		}
		try {
			if($current['start'] > 0) {
				if(@fwrite($hFile, substr($current['contents'], 0, $current['start'])) === false) {
					throw new Exception("Error writing to file '$tempFile'.");
				}
			}
			if(count($properties)) {
				$s = "msgid \"\"\n";
				$s .= "msgstr \"\"\n";
				foreach($properties as $key => $value) {
					$s .= "\"" . addcslashes("$key: $value\n", "\0..\37\\") . "\"\n";
				}
				$s .= "\n";
				if(@fwrite($hFile, $s) === false) {
					throw new Exception("Error writing to file '$tempFile'.");
				}
			}
			if($current['end'] <= 0) {
				if(@fwrite($hFile, $current['contents']) === false) {
					throw new Exception("Error writing to file '$tempFile'.");
				}
			}
			else {
				if(@fwrite($hFile, substr($current['contents'], $current['end'])) === false) {
					throw new Exception("Error writing to file '$tempFile'.");
				}
			}
		}
		catch(Exception $x) {
			@fclose($hFile);
			@unlink($tempFile);
			throw $x;
		}
		fclose($hFile);
		if(!strlen($saveAs)) {
			$saveAs = $filename;
		}
		if(!@rename($tempFile, $saveAs)) {
			throw new Exception("Error renaming file to '$saveAs'.");
		}
	}
}
