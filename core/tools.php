<?php
	namespace Core;

	use Phar;
	use ArrayObject;

	class Tools
	{
		/**
		  * @var string
		  */
		protected static $_OS = null;

		/**
		  * @var string
		  */
		protected static $_homePathname = null;


		public static function arrayToObject($input = array(), $MyArrayObject = true)
		{
			if(is_array($input))
			{
				if(class_exists('MyArrayObject') && $MyArrayObject) {
					return new MyArrayObject($input);
				}
				else
				{
					foreach($input as &$i) {
						$i = self::arrayToObject($i);
					}
					unset($i);

					return new ArrayObject($input, ArrayObject::ARRAY_AS_PROPS);
				}
			}
			else {
				return $input;
			}
		}

		public static function arrayKeyExists($array, $field)
		{
			if(is_object($array))
			{
				if(method_exists($array, 'offsetExists')) {
					return $array->offsetExists($field);
				}
				elseif(method_exists($array, 'key_exists')) {
					return $array->key_exists($field);
				}
				elseif(method_exists($array, '__isset')) {
					return $array->__isset($field);
				}
			}
			elseif(is_array($array)) {
				return array_key_exists($field, $array);
			}

			return false;
		}

		public static function arrayReduce(array $items, $field, $indexKey = null)
		{
			return array_column($items, $field, $indexKey);
		}

		public static function arrayFilter(array $items, $fields)
		{
			$fields = (array) $fields;

			if(count($fields) === 0) {
				return array();
			}
			else
			{
				$results = array();

				/**
				  * /!\ Doit être compatible avec les tableaux et les objets
				  * Ne pas utiliser array_filter qui n'accepte que les array
				  *
				  * Permet de garder l'ordre de $fields à la différence de array_intersect_key
				  * array_intersect_key n'accepte que les tableaux et non les objets comme array_filter
				  */
				array_walk($items, function($item) use(&$results, $fields)
				{
					if((is_array($item) || $item instanceof \ArrayAccess))
					{
						$result = array();

						foreach($fields as $field)
						{
							if(self::arrayKeyExists($item, $field)) {
								$result[$field] = $item[$field];
							}
						}

						if(count($result) > 0) {
							$results[] = $result;
						}
					}
				});

				return $results;
			}
		}

		public static function a($action, array &$array1, $arg2 = null)
		{
			if(is_array($arg2))
			{
				switch($action)
				{
					case 'merge':
						$array1 = array_merge($array1, $arg2);
						break;
					case 'diff':
						$array1 = array_diff($array1, $arg2);
						break;
					case 'mergerecu':
					case 'mergeRecu':
					case 'merge-recu':
					case 'merge_recu':
						$array1 = array_merge_recursive($array1, $arg2);
						break;
					case 'filter':
						$array1 = self::arrayFilter($array1, $arg2);
						break;
				}
			}
			elseif(self::is('string&&!empty', $arg2))
			{
				switch($action)
				{
					case 'reduce':
						$array1 = self::arrayReduce($array1, $arg2);
						break;
					case 'filter':
						$array1 = self::arrayFilter($array1, $arg2);
						break;
				}
			}
			else
			{
				switch($action)
				{
					case 'object':
					case 'toObject':
					case 'to-object':
					case 'to_object':
						$array1 = self::arrayToObject($array1);
						break;
				}
			}
		}

		public static function merge(array &$array1, array $array2 = null)
		{
			if(is_array($array2)) {
				$array1 = array_merge($array1, $array2);
			}
		}

		public static function diff(array &$array1, array $array2 = null)
		{
			if(is_array($array2)) {
				$array1 = array_diff($array1, $array2);
			}
		}

		/**
		  * @param string $model
		  * @param mixed $value
		  * @return bool
		  */
		public static function is($model, $value = null)
		{
			switch($model)
			{
				case 'null':
					return is_null($value);
				case 'bool':
				case 'boolean':
					return is_bool($value);
				case 'binary':
					return (((int) $value === 0 || (int) $value === 1) && (int) $value == $value);
				case 'string':
					return is_string($value);
				case 'string&&empty':
					return (is_string($value) && $value === "");
				case 'string&&!empty':
					return (is_string($value) && $value !== "");
				case 'int':
					return (is_numeric($value) && floor($value) == $value);						// /!\ Type int ou string
				case 'int&&=0':
				case 'int&&==0':
				case 'int&&===0':
					return ($value === 0);
				case 'int&&>0':
					return (is_numeric($value) && floor($value) == $value && $value > 0);		// /!\ Type int ou string
				case 'int&&>=0':
					return (is_numeric($value) && floor($value) == $value && $value >= 0);		// /!\ Type int ou string
				case 'int&&<0':
					return (is_numeric($value) && floor($value) == $value && $value < 0);		// /!\ Type int ou string
				case 'int&&<=0':
					return (is_numeric($value) && floor($value) == $value && $value <= 0);		// /!\ Type int ou string
				case 'integer':
					return is_int($value);
				case 'integer&&=0':
				case 'integer&&==0':
				case 'integer&&===0':
					return ($value === 0);
				case 'integer&&>0':
					return (is_int($value) && $value > 0);
				case 'integer&&>=0':
					return (is_int($value) && $value >= 0);
				case 'integer&&<0':
					return (is_int($value) && $value < 0);
				case 'integer&&<=0':
					return (is_int($value) && $value <= 0);
				case 'float':
					return is_float($value);
				case 'float&&=0':
				case 'float&&==0':
				case 'float&&===0':
					return ($value === 0.0);
				case 'float&&>0':
					return (is_float($value) && $value > 0);
				case 'float&&>=0':
					return (is_float($value) && $value >= 0);
				case 'float&&<0':
					return (is_float($value) && $value < 0);
				case 'float&&<=0':
					return (is_float($value) && $value <= 0);
				case 'human':
					return (self::is('integer', $value) || self::is('float', $value) || self::is('string', $value));		// /!\ No boolean, null or other type
				case 'array':
					return is_array($value);
				case 'array&&count=0':
				case 'array&&count==0':
				case 'array&&count===0':
					return (is_array($value) && count($value) === 0);
				case 'array&&count>0':
					return (is_array($value) && count($value) > 0);
				case 'array&&count>=0':
					return (is_array($value) && count($value) >= 0);
				case 'ip':
					return (self::is('ipv4', $value) || self::is('ipv6', $value));
				case 'ipv4':
					return (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
				case 'ipv6':
					return (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false);
				case 'os':
					return self::isOS($value);
				case 'linux':
					return self::isLinuxOS();
				case 'freebsd':
					return self::isFreebsdOS();
				case 'macos':
					return self::isMacOS();
				case 'windows':
					return self::isWindowsOS();
				default:
					throw new Exception('Test impossible pour ['.$model.']', E_USER_ERROR);
			}
		}

		public static function isIntSup0($int)
		{
			// /!\ floor return float type
			return (is_numeric($int) && floor($int) == $int && $int > 0);
		}

		public static function isIntSupEqual0($int)
		{
			// /!\ floor return float type
			return (is_numeric($int) && floor($int) == $int && $int >= 0);
		}

		public static function isOS($OS)
		{
			if(self::$_OS === null) {
				// @todo use PHP_OS_FAMILY ?
				$currentOS = php_uname('s');
				self::$_OS = mb_strtolower($currentOS);
			}

			$OS = mb_strtolower($OS);
			return ($OS === self::$_OS);
		}

		public static function isLinuxOS()
		{
			return self::isOS('linux');
		}

		public static function isFreebsdOS()
		{
			return self::isOS('freebsd');
		}

		public static function isMacOS()
		{
			return self::isOS('darwin');
		}

		public static function isWindowsOS()
		{
			return self::isOS('windows');
		}

		public static function e($text, $textColor = false, $bgColor = false, $textStyle = false, $doNotPrint = false)
		{
			$attrs = array();

			foreach(array(0 => $textColor, 10 => $bgColor) as $i => $color)
			{
				switch($color)
				{
					case 'grey':
						$color = 0;
						break;
					case 'black':
						$color = 30;
						break;
					case 'red':
						$color = 31;
						break;
					case 'yellow':
					case 'orange':
						$color = 33;
						break;
					case 'green':
						$color = 32;
						break;
					case 'cyan':
						$color = 36;
						break;
					case 'blue':
						$color = 34;
						break;
					case 'white':
						$color = 37;
						break;
					default:
						continue(2);
				}

				$attrs[] = $color+$i;
			}

			switch($textStyle)
			{
				case 'bold':
					$attrs[] = 1;
					break;
				case 'italic':
					$attrs[] = 3;
					break;
				case 'underline':
					$attrs[] = 4;
					break;
			}

			if(count($attrs) === 0) {
				$attrs = array(0);
			}

			$msg = "\033[".implode(';', $attrs)."m".$text."\033[0m";

			if(!$doNotPrint) {
				echo $msg;
			}

			return $msg;
		}

		// Default TAB equal to 8 spaces
		public static function t($text, $c = "\t", $maxC = 3, $malusC = 0, $dividend = 8)
		{
			$cmdLen = mb_strlen($text) + $malusC;
			$j = (int) $cmdLen/$dividend;
			$j = max(0, $maxC-$j);
			$result = "";

			for($i=0; $i<$j; $i++) {
				$result .= $c;
			}
			return $result;
		}

		public static function cutShellTable(array $items, $columnSize, $iSeparator = '+', $hSeparator = '-', $vSeparator = '|')
		{
			if(count($items) > 0)
			{
				$counter = 0;

				$cellWidthMalusI = ((mb_strlen($iSeparator) * 2) + 2);
				$cellWidthMalusV = ((mb_strlen($vSeparator) * 2) + 2);
				$cellWidthMalus = max($cellWidthMalusI, $cellWidthMalusV);

				$maxCellWidht = 0;
				$maxColumnsPerLine = 0;
				$maxColumnsPerLineTemp = 0;

				$items = array_values($items);

				foreach($items as $item)
				{
					$itemLen = mb_strlen($item);

					if($maxCellWidht === 0) {
						$maxCellWidht = $itemLen;
					}
					else {
						$maxCellWidht = max($maxCellWidht, $itemLen);
					}
				}

				foreach($items as $item)
				{
					$counter += ($maxCellWidht + $cellWidthMalus);

					if($counter < $columnSize) {
						$maxColumnsPerLineTemp++;
					}
					else
					{
						if($maxColumnsPerLine === 0) {
							$maxColumnsPerLine = $maxColumnsPerLineTemp;
						}
						else {
							$maxColumnsPerLine = min($maxColumnsPerLine, $maxColumnsPerLineTemp);
						}

						$counter = 0;
						$maxColumnsPerLineTemp = 0;
					}
				}

				if($maxColumnsPerLine === 0 && $maxColumnsPerLineTemp > 0) {
					$maxColumnsPerLine = $maxColumnsPerLineTemp;
				}

				if($maxColumnsPerLine > 0)
				{
					$lines = array();

					foreach($items as $index => $item) {
						$line = floor($index/$maxColumnsPerLine);
						$lines[$line][] = $item;
					}

					return self::formatShellTable($lines, $iSeparator, $hSeparator, $vSeparator, true);
				}
			}

			return '';
		}

		public static function formatShellTable(array $lines, $iSeparator = '+', $hSeparator = '-', $vSeparator = '|', $columnWidthFixed = false)
		{
			if($vSeparator === false) {
				$vSeparator = '';
			}

			$lines = array_filter($lines, function ($line) {
				return (count($line) > 0);
			});

			$numberOfLines = count($lines);
			$lines = array_values($lines);

			if($numberOfLines > 0)
			{
				$table = '';
				$numberOfColumns = 0;
				$maxColumnsLen = array();
				$columnsHeight = array();
				$maxColumnsHeight = array();

				foreach($lines as $indexL => &$line)
				{
					$line = array_values($line);

					// Check column
					// --------------------------------------------------
					if($indexL === 0) {
						$numberOfColumns = count($line);
					}
					elseif(($counterL = count($line)) !== $numberOfColumns) {
						$lineTmp = array_fill($counterL, $numberOfColumns-$counterL, '');
						$line = array_merge($line, $lineTmp);
					}
					// --------------------------------------------------

					$maxColumnsHeight[$indexL] = 0;

					foreach($line as $indexC => $column)
					{
						// Column height
						// --------------------------------------------------
						$columnLineNumber = substr_count($column, PHP_EOL);
						$columnsHeight[$indexL][$indexC] = $columnLineNumber;
						$maxColumnsHeight[$indexL] = max($maxColumnsHeight[$indexL], $columnLineNumber);
						// --------------------------------------------------

						// Column length
						// --------------------------------------------------
						if($columnLineNumber > 0)
						{
							$columnLines = explode(PHP_EOL, $column);
							
							array_walk($columnLines, function (&$columnLine) {
								$columnLine = mb_strlen($columnLine);
							});
							unset($columnLine);

							$columnLen = max($columnLines);
						}
						else {
							$columnLen = mb_strlen($column);
						}

						if(!array_key_exists($indexC, $maxColumnsLen)) {
							$maxColumnsLen[$indexC] = $columnLen;
						}
						else {
							$maxColumnsLen[$indexC] = max($columnLen, $maxColumnsLen[$indexC]);
						}
						// --------------------------------------------------
					}
				}
				unset($line);

				// Column width
				// --------------------------------------------------
				if($columnWidthFixed) {
					$columnWidth = max($maxColumnsLen);
					$maxColumnsLen = array_fill(0, count($maxColumnsLen), $columnWidth);
				}
				// --------------------------------------------------

				// Create interLine
				// --------------------------------------------------
				if($iSeparator !== false && $hSeparator !== false)
				{
					$interLines = $iSeparator;

					foreach($maxColumnsLen as $columnLen) {
						$interLines .= str_pad($iSeparator, $columnLen+3, $hSeparator, STR_PAD_LEFT);
					}

					$table .= $interLines;
				}
				else {
					$interLines = false;
				}
				// --------------------------------------------------

				// Create table
				// --------------------------------------------------
				foreach($lines as $indexL => $line)
				{
					foreach($line as $indexC => &$column)
					{
						// Width
						// --------------------------------------------------
						$columnPrefix = ($indexC === 0) ? ($vSeparator.' ') : (' ');
						$columnSuffix = ' '.$vSeparator;

						$columnLines = explode(PHP_EOL, $column);

						foreach($columnLines as &$columnLine)
						{
							// Workaround (optional)
							if(mb_strlen($columnLine) < $maxColumnsLen[$indexC]) {
								$columnLine = ' '.$columnLine;
							}

							$columnLine = $columnPrefix.str_pad($columnLine, $maxColumnsLen[$indexC], ' ', STR_PAD_BOTH).$columnSuffix;
						}
						unset($columnLine);

						$column = $columnLines;
						// --------------------------------------------------

						// Height
						// --------------------------------------------------
						$columnPhpEol = $columnPrefix.str_pad('', $maxColumnsLen[$indexC], ' ', STR_PAD_BOTH).$columnSuffix;

						$columnHeight = ($maxColumnsHeight[$indexL] - $columnsHeight[$indexL][$indexC]);

						if($columnHeight > 0)
						{
							$prepend = floor($columnHeight/2);
							$column = array_merge(array_fill(0, $prepend, $columnPhpEol), $column);

							$append = $columnHeight-$prepend;
							$column = array_merge($column, array_fill(0, $append, $columnPhpEol));
						}
						// --------------------------------------------------
					}
					unset($column);

					// Table
					// --------------------------------------------------
					$lineT = '';

					for($i=0; $i<=$maxColumnsHeight[$indexL]; $i++)
					{
						foreach($line as $indexC => $column) {
							$lineT .= $column[$i];
						}

						$lineT .= PHP_EOL;
					}

					if($interLines !== false) {
						$table .= PHP_EOL.$lineT.$interLines;
					}
					else {
						$table .= $lineT;
					}
					// --------------------------------------------------
				}
				// --------------------------------------------------

				return rtrim($table, PHP_EOL);
			}
			else {
				return '';
			}
		}

		/**
		  * Calculate the string intersection
		  * https://stackoverflow.com/questions/336605/how-can-i-find-the-largest-common-substring-between-two-strings-in-php
		  *
		  * $default can be bool or string or mixed, just test with null value to known return it or not
		  *
		  * @param array $items
		  * @param mixed $default
		  * @param bool $caseSensitive
		  * @return mixed Return string or $default
		  */
		public static function crossSubStr(array $items, $default = null, $caseSensitive = true)
		{
			// /!\ Important pour que le 1er index soit 0
			$items = array_values($items);

			foreach($items as $i => $item)
			{
				if($i === 0) {
					$baseCmdPart = str_split($item);
				}
				else
				{
					foreach(str_split($item) as $i => $letter)
					{
						if(!isset($baseCmdPart[$i])) {
							break;
						}
						elseif(($caseSensitive && $baseCmdPart[$i] !== $letter) ||
								(!$caseSensitive && mb_strtolower($baseCmdPart[$i]) !== mb_strtolower($letter)))
						{
							array_splice($baseCmdPart, $i);
						}
					}

					if($i < count($baseCmdPart)-1) {
						array_splice($baseCmdPart, $i+1);
					}
				}
			}

			$result = implode('', $baseCmdPart);
			return ($result === '' && $default !== null) ? ($default) : ($result);
		}

		/**
		  * Trailing delimiters, such as \ and /, are also removed
		  *
		  * @return string Return home pathname
		  */
		public static function getHomePathname()
		{
			if(self::$_homePathname === null)
			{
				if(array_key_exists('HOME', $_SERVER)) {
					$home = $_SERVER['HOME'];
				}
				elseif(function_exists('posix_getpwuid')) {
					$userInfos = posix_getpwuid(posix_getuid());
					$home = $userInfos['dir'];
				}
				else {
					$home = false;
				}

				if($home !== false) {
					$home = rtrim($home, DIRECTORY_SEPARATOR);
				}

				self::$_homePathname = $home;
			}

			return self::$_homePathname;
		}

		/**
		  * Trailing delimiters, such as \ and /, are also removed
		  *
		  * @return string Return working pathname
		  */
		public static function getWorkingPathname()
		{
			$wd = getcwd();

			if($wd === false) {
				$wd = realpath('.');
			}

			if($wd !== false)
			{
				if($wd !== DIRECTORY_SEPARATOR) {
					return rtrim($wd, DIRECTORY_SEPARATOR);
				}
				else {
					return DIRECTORY_SEPARATOR;
				}
			}
			else {
				return false;
			}
		}

		/**
		  * Resolve filename and return it
		  * Trailing delimiters, such as \ and /, are also removed
		  *
		  * @param string $filename Filename to resolve
		  * @param bool|string $rootDir TRUE use the ROOT_DIR constant. FALSE use the current working directory. STRING use it as root directory
		  * @param bool $touch Exec touch command on filename
		  * @return string Filename
		  */
		public static function filename($filename, $rootDir = true, $touch = false)
		{
			/**
			  * Do not process filename based on protocol
			  *
			  * http, https, phar, ...
			  */
			if(!preg_match('#:\/\/#i', $filename))
			{
				$firstChar = substr($filename, 0, 1);
					
				if($firstChar === '~')
				{	
					if(($home = self::getHomePathname()) !== false) {
						$filename = str_replace('~', $home, $filename);
					}
				}
				elseif($firstChar !== DIRECTORY_SEPARATOR)
				{
					if($rootDir === true)
					{
						if(defined('ROOT_DIR')) {
							$filename = ROOT_DIR.DIRECTORY_SEPARATOR.$filename;
						}
					}
					elseif($rootDir === false)
					{
						if(($cwd = self::getWorkingPathname()) !== false) {
							$filename = $cwd.DIRECTORY_SEPARATOR.$filename;
						}
					}
					elseif(self::is('string&&!empty', $rootDir)) {
						$filename = $rootDir.DIRECTORY_SEPARATOR.$filename;
					}
				}

				// realpath() returns FALSE on failure, e.g. if the file does not exist.
				// The running script must have executable permissions on all directories in the hierarchy, otherwise realpath() will return FALSE.

				$filenameParts = explode('..', $filename);

				for($i=0; $i<(count($filenameParts)-1); $i++) {
					$filenameParts[$i] = dirname($filenameParts[$i]);
				}

				$filename = implode(DIRECTORY_SEPARATOR, $filenameParts);
				$filename = preg_replace('#(/+\./+)|(/+\.)|(\./+)|(^\.$)|(/+)#i', '/', $filename);
				return ($filename === DIRECTORY_SEPARATOR) ? (DIRECTORY_SEPARATOR) : (rtrim($filename, DIRECTORY_SEPARATOR));		// A l'identique de realpath

				/*if(($status = file_exists($filename)) === false && $touch)
				{
					$status = mkdir(dirname($filename), 0777, true);

					if($status) {
						$status = touch($filename);
					}
				}

				return ($status) ? (realpath($filename)) : (false);*/
			}
			else {
				/**
				  * A l'identique de realpath
				  *
				  * Permet de supprimer le / de fin
				  * Ne sert pas pour les PHAR mais utile pour HTTP
				  */
				return rtrim($filename, DIRECTORY_SEPARATOR);
			}
		}

		public static function pathname($pathname, $useRootDir = true, $mkdir = true)
		{
			$pathname = self::filename($pathname, $useRootDir, false);

			if(($status = file_exists($pathname)) === false && $mkdir) {
				mkdir($pathname, 0777, true);
			}

			return $pathname;
		}

		public static function getCredentials(MyArrayObject $config, $prefix = null, $suffix = null, $loginIsRequired = false, $passwordIsRequired = false)
		{
			if(!self::is('human', $prefix)) {
				$prefix = '';
			}

			if(!self::is('human', $suffix)) {
				$suffix = '';
			}

			$loginCredential = $prefix.'loginCredential'.$suffix;
			$loginEnvVarName = $prefix.'loginEnvVarName'.$suffix;
			$passwordCredential = $prefix.'passwordCredential'.$suffix;
			$passwordEnvVarName = $prefix.'passwordEnvVarName'.$suffix;

			$loginCredential = self::getConfigEnvVar($config, $loginCredential, $loginEnvVarName, $loginIsRequired);
			$passwordCredential = self::getConfigEnvVar($config, $passwordCredential, $passwordEnvVarName, $passwordIsRequired);

			return array($loginCredential, $passwordCredential);
		}

		public static function getConfigEnvVar(MyArrayObject $config, $configVar, $envVar, $isRequired = false)
		{
			if($config->key_exists($configVar) && self::is('string&&!empty', $config[$configVar])) {
				return $config[$configVar];
			}
			elseif($config->key_exists($envVar) && self::is('string&&!empty', $config[$envVar]))
			{
				$envVar = $config[$envVar];
				$result = getenv($envVar);

				if($result === false && $isRequired) {
					throw new Exception("Unable to retrieve variable '".$envVar."' from environment", E_USER_ERROR);
				}

				return $result;
			}
			elseif($isRequired) {
				throw new Exception("Unable to retrieve configuration parameter '".$configVar."' or environment variable '".$envVar."'", E_USER_ERROR);
			}
			else {
				return false;
			}
		}

		public static function flushAllBuffer()
		{
			$bufferLevel = ob_get_level();

			for($i=0; $i<$bufferLevel; $i++) {
				ob_end_flush();
			}
		}

		public static function cleanAllBuffer()
		{
			$bufferLevel = ob_get_level();

			for($i=0; $i<$bufferLevel; $i++) {
				ob_end_clean();
			}
		}

		public static function isPharRunning()
		{
			return (Phar::running() !== '');
		}
	}