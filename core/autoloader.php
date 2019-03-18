<?php
	namespace Core;

	class Autoloader
	{
		protected static $_replacements = array();


		public static function setReplacements(array $replacements)
		{
			static::$_replacements = $replacements;
		}

		public static function addReplacement($regex, $replacement)
		{
			static::$_replacements[$regex] = $replacement;
		}

		public static function register()
		{
			$status = spl_autoload_register(array(static::class, 'autoload'), true);

			if(!$status) {
				throw new Exception("Unable to register SPL Autoloader", E_USER_ERROR);
			}
		}

		public static function autoload($class)
		{
			$class = static::_prepare($class);
			static::_load($class);
		}

		protected static function _prepare($class)
		{
			$class = str_replace(array("\\", '_'), DIRECTORY_SEPARATOR, $class);
			$parts = explode(DIRECTORY_SEPARATOR, $class);
			$className = array_pop($parts);

			$parts = preg_replace(array_keys(static::$_replacements), array_values(static::$_replacements), $parts);

			$parts[] = $className;
			$parts = array_map('lcfirst', $parts);
			$class = implode(DIRECTORY_SEPARATOR, $parts);

			return $class;
		}

		protected static function _load($class)
		{
			if(file_exists($class.'.php')) {
				require_once($class.'.php');
			}
		}
	}