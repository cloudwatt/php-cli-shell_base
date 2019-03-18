<?php
	namespace Core\Network;

	use Core as C;

	abstract class Tools
	{
		public static function isIP($ip)
		{
			return (self::isIPv4($ip) || self::isIPv6($ip));
		}

		public static function isIPv($ip, $version)
		{
			if($version === 4) {
				return self::isIPv4($ip);
			}
			elseif($version === 6) {
				return self::isIPv6($ip);
			}
			else {
				return false;
			}
		}

		public static function isIPv4($ip)
		{
			return C\Tools::is('ipv4', $ip);
		}

		public static function isIPv6($ip)
		{
			return C\Tools::is('ipv6', $ip);
		}

		public static function isSubnet($subnet)
		{
			return (self::isSubnetV4($subnet) || self::isSubnetV6($subnet));
		}

		public static function isSubnetV($subnet, $version)
		{
			if($version === 4) {
				return self::isSubnetV4($subnet);
			}
			elseif($version === 6) {
				return self::isSubnetV6($subnet);
			}
			else {
				return false;
			}
		}

		public static function isSubnetV4($subnet)
		{
			if(C\Tools::is('string&&!empty', $subnet))
			{
				$subnetParts = explode('/', $subnet);

				// Be careful ::ffff:127.0.0.1 notation is valid
				//return (substr_count($subnet, '.') === 3 && strpos($subnet, ':') === false);
				return (count($subnetParts) === 2 && self::isIPv4($subnetParts[0]) && $subnetParts[1] >= 0 && $subnetParts[1] <= 32);
			}
			else {
				return false;
			}
		}

		public static function isSubnetV6($subnet)
		{
			if(C\Tools::is('string&&!empty', $subnet))
			{
				$subnetParts = explode('/', $subnet, 2);

				//return (strpos($subnet, ':') !== false);
				return (count($subnetParts) === 2 && self::isIPv6($subnetParts[0]) && $subnetParts[1] >= 0 && $subnetParts[1] <= 128);
			}
			else {
				return false;
			}
		}

		public static function isNetwork($network, $separator = '-')
		{
			return (self::isNetworkV4($network, $separator) || self::isNetworkV6($network, $separator));
		}

		public static function isNetworkV4($network, $separator = '-')
		{
			$networkParts = explode($separator, $network);

			if(count($networkParts) === 2)
			{
				return (
					self::isIPv4($networkParts[0]) &&
					self::isIPv4($networkParts[1]) &&
					strnatcasecmp($networkParts[0], $networkParts[1]) <= 0
				);
			}
			else {
				return false;
			}
		}

		public static function isNetworkV6($network, $separator = '-')
		{
			$networkParts = explode($separator, $network);

			if(count($networkParts) === 2)
			{
				return (
					self::isIPv6($networkParts[0]) &&
					self::isIPv6($networkParts[1]) &&
					strnatcasecmp($networkParts[0], $networkParts[1]) <= 0
				);
			}
			else {
				return false;
			}
		}

		public static function formatIPv6($IPv6)
		{
			if(C\Tools::is('ipv6', $IPv6))
			{
				if(defined('AF_INET6')) {
					/**
					  * To loweer case: Ff:: => ff::
					  * Format: 0:0:0:0:0:0:0:0: => ::
					  */
					return inet_ntop(inet_pton($IPv6));
				}
				else {
					return $IPv6;
				}
			}
			else {
				return false;
			}
		}

		public static function cidrMatch($ip, $subnet)
		{
			list($subnet, $mask) = explode('/', $subnet);

			if(($subnet === '0.0.0.0' || self::formatIPv6($subnet) === '::') && $mask === '0') {
				return true;
			}
			else {
				$ip = self::networkIp($ip, $mask);
				$subnet = self::networkIp($subnet, $mask);
				return ($ip !== false && $subnet !== false && $ip === $subnet);
			}
		}

		// /!\ is $a inside $b ?
		public static function subnetInSubnet($a, $b)
		{
			if($a === $b) {
				return true;
			}
			else
			{
				list($ip, $mask) = explode('/', $a);
				list($_ip, $_mask) = explode('/', $b);

				if($mask < $_mask) {
					return false;
				}
				else
				{
					$ip = self::networkIp($ip, $mask);

					if($ip !== false) {
						return self::cidrMatch($ip, $b);
					}
					else {
						return false;
					}
				}
			}
		}

		public static function IPv4ToLong($ip)
		{
			return ip2long($ip);
		}

		public static function longIpToIPv4($longIp)
		{
			return long2ip((float) $longIp);
		}

		public static function IpToBin($ip)
		{
			return (defined('AF_INET6')) ? (inet_pton($ip)) : (false);
		}

		public static function binToIp($ip)
		{
			return (defined('AF_INET6')) ? (inet_ntop($ip)) : (false);
		}

		public static function cidrMaskToNetMask($cidrMask)
		{
			if($cidrMask >= 0 && $cidrMask <= 32) {
				return long2ip(-1 << (32 - (int) $cidrMask));
			}
			else {
				return false;
			}
		}

		public static function cidrMaskToBinary($cidrMask, $IPv)
		{
			if($IPv === 4 && $cidrMask >= 0 && $cidrMask <= 32)
			{
				if(defined('AF_INET6')) {
					$netMask = self::cidrMaskToNetMask($cidrMask);
					return inet_pton($netMask);
				}
				else {
					return (~((1 << (32 - $cidrMask)) - 1));
				}
			}
			elseif($IPv === 6 && $cidrMask >= 0 && $cidrMask <= 128)
			{
				$netMask = str_repeat("f", $cidrMask / 4);

				switch($cidrMask % 4)
				{
					case 0:
						break;
					case 1:
						$netMask .= "8";
						break;
					case 2:
						$netMask .= "c";
						break;
					case 3:
						$netMask .= "e";
						break;
				}

				$netMask = str_pad($netMask, 32, '0');
				$binMask = pack("H*", $netMask);

				return $binMask;
			}

			return false;
		}

		public static function netMaskToCidr($netMask)
		{
			if(self::isIPv4($netMask)) {
				$longMask = ip2long($netMask);
				$longBase = ip2long('255.255.255.255');
				return 32 - log(($longMask ^ $longBase)+1, 2);
			}
			else {
				return false;
			}
		}

		public static function firstSubnetIp($ip, $mask)
		{
			if(($isIPv4 = self::isIPv4($ip)) === true || ($isIPv6 = self::isIPv6($ip)) === true)
			{
				if(C\Tools::is('int&&>=0', $mask)) {
					$IPv = ($isIPv4) ? (4) : (6);
					$mask = self::cidrMaskToBinary($mask, $IPv);
				}
				elseif(defined('AF_INET6') && self::isIPv4($mask)) {
					$mask = inet_pton($mask);
				}
				else {
					$mask = false;
				}

				if($mask !== false)
				{
					// IPv4 & IPv6 compatible
					if(defined('AF_INET6')) {
						$ip = inet_pton($ip);
						return inet_ntop($ip & $mask);
					}
					// IPv4 only
					elseif($isIPv4) {
						$netIp = (ip2long($ip) & $mask);
						return long2ip($netIp);
					}
				}
			}

			return false;
		}

		public static function lastSubnetIp($ip, $mask)
		{
			if(($isIPv4 = self::isIPv4($ip)) === true || ($isIPv6 = self::isIPv6($ip)) === true)
			{
				if(C\Tools::is('int&&>=0', $mask)) {
					$IPv = ($isIPv4) ? (4) : (6);
					$mask = self::cidrMaskToBinary($mask, $IPv);
				}
				elseif(defined('AF_INET6') && self::isIPv4($mask)) {
					$mask = inet_pton($mask);
				}
				else {
					return false;
				}

				// IPv4 et IPv6 compatible
				if(defined('AF_INET6')) {
					$ip = inet_pton($ip);
					return inet_ntop($ip | ~ $mask);
				}
				// IPv4 only
				elseif($isIPv4) {
					$bcIp = (ip2long($ip) | (~ $mask));
					return long2ip($bcIp);
				}
			}

			return false;
		}

		public static function networkIp($ip, $mask)
		{
			return self::firstSubnetIp($ip, $mask);
		}

		public static function broadcastIp($ip, $mask)
		{
			if(self::isIPv4($ip)) {
				return self::lastSubnetIp($ip, $mask);
			}
			elseif(self::isIPv6($ip)) {
				return 'ff02::1';
			}
			else {
				return false;
			}
		}

		public static function networkSubnet($cidrSubnet)
		{
			$subnetPart = explode('/', $cidrSubnet);

			if(count($subnetPart) === 2)
			{
				$networkIp = self::firstSubnetIp($subnetPart[0], $subnetPart[1]);

				if($networkIp !== false) {
					return $networkIp.'/'.$subnetPart[1];
				}
			}

			return false;
		}
	}