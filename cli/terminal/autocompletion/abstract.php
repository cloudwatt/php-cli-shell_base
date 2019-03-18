<?php
	namespace Cli\Terminal;

	use Core as C;

	abstract class Autocompletion_Abstract
	{
		protected function _crossSubStr(array $items, $default = null, $caseSensitive = true)
		{
			return C\Tools::crossSubStr($items, $default, $caseSensitive);
		}
	}