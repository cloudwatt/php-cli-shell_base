<?php
	namespace Core\Addon;

	use Core as C;

	interface Orchestrator_Interface
	{
		/**
		  * @return bool
		  */
		public static function hasInstance();

		/**
		  * @param Core\Config $config
		  * @return Addon\Dcim\Orchestrator
		  */
		public static function getInstance(C\Config $config = null);
	}