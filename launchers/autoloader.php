<?php
	require_once(PROJECT_ROOT_DIR . '/core/autoloader.php');

	use Core as C;

	class Launcher_Autoloader extends C\Autoloader
	{
		protected static $_replacements = array(
			'#^Addon$#i' => 'Addons',
			'#^App$#i' => 'Applications',
			'#^Launcher$#i' => 'Launchers',
		);

		protected static $_ROOT_DIR = null;


		protected static function _load($class)
		{
			$class .= static::PHP_FILE_EXT;
			$rootDir = static::_getRootDir();

			if(file_exists($rootDir.'/'.$class)) {
				require_once($rootDir.'/'.$class);
				return;
			}

			/*$autoloadFcts = spl_autoload_functions();

			if($autoloadFcts === false || count($autoloadFcts) <= 1) {
				require_once($class);
			}*/
		}

		protected static function _getRootDir()
		{
			if(defined('PROJECT_ROOT_DIR')) {
				return PROJECT_ROOT_DIR;
			}
			elseif(($phar = Phar::running()) !== '') {
				return $phar;
			}
			else
			{
				if(static::$_ROOT_DIR === null) {
					static::$_ROOT_DIR = dirname(dirname(__FILE__));
				}

				return static::$_ROOT_DIR;
			}
		}
	}