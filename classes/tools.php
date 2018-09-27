<?php
	class Tools
	{
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

					return new ArrayObject($input, ArrayObject::ARRAY_AS_PROPS);
				}
			}
			else {
				return $input;
			}
		}

		public static function arrayFilter(array $items, array $fields)
		{
			$results = array();

			foreach($items as $item)
			{
				if(is_object($item))
				{
					switch(true)
					{
						// ArrayObject
						case method_exists($item, 'getArrayCopy'):
							$item = $item->getArrayCopy();
							break;
						// MyArrayObject
						case method_exists($item, 'toArray'):
							$item = $item->toArray();
							break;
						default:
							throw new Exception('Unable to convert Object to Array', E_USER_ERROR);
					}
				}
				elseif(!is_array($item)) {
					throw new Exception("Var type '".gettype($item)."' is not allowed", E_USER_ERROR);
				}

				if(count($item) > 0)
				{
					$result = array();

					foreach($fields as $field)
					{
						if(array_key_exists($field, $item)) {
							$result[$field] = $item[$field];
						}
					}

					if(count($result) > 0) {
						$results[] = $result;
					}
				}
			}

			return $results;
		}

		public static function a($action, array &$array1, array $array2 = null)
		{
			if(is_array($array2))
			{
				switch($action)
				{
					case 'merge':
						$array1 = array_merge($array1, $array2);
						break;
					case 'diff':
						$array1 = array_diff($array1, $array2);
						break;
					case 'mergerecu':
					case 'mergeRecu':
					case 'merge-recu':
					case 'merge_recu':
						$array1 = array_merge_recursive($array1, $array2);
						break;
					case 'filter':
						$array1 = self::arrayFilter($array1, $array2);
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
					case 'to_bject':
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

		public static function is($model, $value)
		{
			switch($model)
			{
				case 'null':
					return is_null($value);
				case 'bool':
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
					return (is_numeric($value) && floor($value) == $value);
				case 'int&&=0':
				case 'int&&==0':
				case 'int&&===0':
					return ($value === 0);
				case 'int&&>0':
					return (is_numeric($value) && floor($value) == $value && $value > 0);
				case 'int&&>=0':
					return (is_numeric($value) && floor($value) == $value && $value >= 0);
				case 'int&&<0':
					return (is_numeric($value) && floor($value) == $value && $value < 0);
				case 'int&&<=0':
					return (is_numeric($value) && floor($value) == $value && $value <= 0);
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

		// https://stackoverflow.com/questions/336605/how-can-i-find-the-largest-common-substring-between-two-strings-in-php
		public static function crossSubStr(array $items, $default = null)
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
						elseif($baseCmdPart[$i] !== $letter) {
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

		public static function filename($filename)
		{
			$firstChar = substr($filename, 0, 1);
				
			if($firstChar === '~')
			{
				if(array_key_exists('HOME', $_SERVER)) {
					$home = $_SERVER['HOME'];
				}
				elseif(function_exists('posix_getpwuid')) {
					$userInfos = posix_getpwuid(posix_getuid());
					$home = $userInfos['dir'];
				}

				$filename = str_replace('~', $home, $filename);
				return realpath($filename);
			}
			elseif($firstChar !== '/' && defined(ROOT_DIR)) {
				return ROOT_DIR.'/'.$filename;
			}
			else {
				return $filename;
			}
		}
	}

	class Utils extends Tools
	{
	}
