<?php
	namespace Cli\Shell;

	use ErrorException;

	use Core as C;

	abstract class Main
	{
		const PHP_MIN_VERSION = '7.1.0';

		/**
		  * @var Core\Config
		  */
		protected $_CONFIG;

		/**
		  * @var bool
		  */
		protected $_debug = false;

		/**
		  * @var bool
		  */
		protected $_addonDebug = false;

		/**
		  * @var bool
		  */
		protected $_applicationDebug = false;


		public function __construct($configFilename)
		{
			set_error_handler(array(static::class, 'errorHandler'));

			if(version_compare(PHP_VERSION, self::PHP_MIN_VERSION) === -1) {
				throw new Exception("Version PHP inférieure à ".self::PHP_MIN_VERSION.", PHP ".self::PHP_MIN_VERSION." min requis", E_USER_ERROR);
			}

			$this->_initDebug();

			$this->_CONFIG = C\Config::getInstance();
			$this->_CONFIG->loadConfigurations($configFilename, false);
		}

		protected function _initDebug()
		{
			$debug = getenv('PHPCLI_DEBUG');
			$this->_debug = (mb_strtolower($debug) === "true" || $debug === 1);

			$debug = getenv('PHPCLI_ADDON_DEBUG');
			$this->_addonDebug = (mb_strtolower($debug) === "true" || $debug === 1);

			$debug = getenv('PHPCLI_APPLICATION_DEBUG');
			$this->_applicationDebug = (mb_strtolower($debug) === "true" || $debug === 1);
		}

		protected function _throwException(Exception $exception)
		{
			C\Tools::e(PHP_EOL.PHP_EOL."Exception --> ".$exception->getMessage()." [".$exception->getFile()."] {".$exception->getLine()."}", 'red');
		}

		public static function errorHandler($errno, $errstr, $errfile, $errline)
		{
			/**
			  * Ne pas désactiver le buffer ici sinon il y aura une différence entre les ErrorException et les Exception
			  * Lors d'une Error et donc d'une ErrorException, le buffer, s'il est activé, serait désactivé
			  * mais lors d'une Exception "normale" le buffer resterait activé
			  */
			/*$bufferLevel = ob_get_level();

			for($i=0; $i<$bufferLevel; $i++) {
				ob_end_flush();
			}*/

			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'config':
				case 'configuration': {
					return $this->_CONFIG;
				}
				default: {
					throw new Exception('Attribute "'.$name.'" does not exist', E_USER_ERROR);
				}
			}
		}
	}