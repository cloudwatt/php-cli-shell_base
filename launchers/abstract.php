<?php
	require_once(ROOT_DIR . '/core/autoloader.php');

	use Core as C;

	class Launcher_Autoloader extends C\Autoloader
	{
		protected static $_replacements = array(
			'#^Addon$#i' => 'Addons',
			'#^App$#i' => 'Applications',
			'#^Launcher$#i' => 'Launchers',
		);


		protected static function _load($class)
		{
			$class .= '.php';

			if(defined('ROOT_DIR'))
			{
				if(file_exists(ROOT_DIR . '/'.$class)) {
					require_once(ROOT_DIR . '/'.$class);
					return;
				}
				elseif(file_exists(ROOT_DIR . '/classes/'.$class)) {
					require_once(ROOT_DIR . '/classes/'.$class);
					return;
				}
			}

			/*$autoloadFcts = spl_autoload_functions();

			if($autoloadFcts === false || count($autoloadFcts) <= 1) {
				require_once($class);
			}*/
		}
	}

	abstract class Launcher_Abstract
	{
		public function __construct()
		{
			Launcher_Autoloader::register();
		}
	}