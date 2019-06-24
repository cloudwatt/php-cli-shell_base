<?php
	require_once(PROJECT_ROOT_DIR . '/launchers/autoloader.php');

	abstract class Launcher_Abstract
	{
		public function __construct()
		{
			Launcher_Autoloader::register();
		}
	}