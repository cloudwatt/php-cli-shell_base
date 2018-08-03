<?php
	class Tools
	{
		public static function arrayToObject($input = array())
		{
			if(is_array($input))
			{
				foreach($input as &$i) {
					$i = self::arrayToObject($i);
				}

				return new ArrayObject($input, ArrayObject::ARRAY_AS_PROPS);
			}
			else {
				return $input;
			}
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

		public static function t($text, $c = "\t", $maxC = 3, $malusC = 0, $dividend = 7)
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
	}

	class Utils extends Tools
	{
	}
