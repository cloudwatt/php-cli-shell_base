<?php
	abstract class NETWORK_Tools
	{
		public static function cidrMatch($ip, $subnet)
		{
			list($subnet, $mask) = explode('/', $subnet);

			if(($subnet === '0.0.0.0' || $subnet === '::') && $mask === '0') {
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
			if(Tools::is('ipv4', $netMask)) {
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
			if(($isIPv4 = Tools::is('ipv4', $ip)) === true || ($isIPv6 = Tools::is('ipv6', $ip)) === true)
			{
				if(Tools::is('int&&>0', $mask)) {
					$IPv = ($isIPv4) ? (4) : (6);
					$mask = self::cidrMaskToBinary($mask, $IPv);
				}
				elseif(defined('AF_INET6') && Tools::is('ipv4', $mask)) {
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
			if(($isIPv4 = Tools::is('ipv4', $ip)) === true || ($isIPv6 = Tools::is('ipv6', $ip)) === true)
			{
				if(Tools::is('int&&>0', $mask)) {
					$IPv = ($isIPv4) ? (4) : (6);
					$mask = self::cidrMaskToBinary($mask, $IPv);
				}
				elseif(defined('AF_INET6')) {
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
			if(Tools::is('ipv4', $ip)) {
				return self::lastSubnetIp($ip, $mask);
			}
			elseif(Tools::is('ipv6', $ip)) {
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