<?php
	namespace Core;

	class Config extends MyArrayObject
	{
		/**
		  * @var Core\Config
		  */
		private static $_instance;

		/**
		  * @var array
		  */
		private $_filenames = array();

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public static function getInstance($filename = null)
		{
			if(self::$_instance === null) {
				self::$_instance = new self();
			}

			if($filename !== null) {
				self::$_instance->loadConfigurations($filename);
			}

			return self::$_instance;
		}

		public function loadConfigurations($filenames, $fileMissingError = true)
		{
			$status = true;
			$filenames = (array) $filenames;

			foreach($filenames as $filename) {
				$_status = $this->_readConfiguration($filename, $fileMissingError);
				$status = $status && $_status;
			}

			return $status;
		}

		private function _readConfiguration($filename, $fileMissingError = true)
		{
			if(file_exists($filename))
			{
				if(is_readable($filename))
				{
					if(!in_array($filename, $this->_filenames, true))
					{
						$this->_filenames[] = $filename;
						$json = file_get_contents($filename);
						$configuration = json_decode($json, true);

						if($configuration === null) {
							throw new Exception("Configuration file '".$filename."' is not a valid JSON", E_USER_ERROR);
						}

						$this->replace_recursive($configuration);
					}
				}
				else {
					throw new Exception("Configuration file '".$filename."' is not readable", E_USER_ERROR);
				}
			}
			elseif($fileMissingError) {
				throw new Exception("Configuration file '".$filename."' does not exist", E_USER_ERROR);
			}
			else {
				return false;
			}

			return true;
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}