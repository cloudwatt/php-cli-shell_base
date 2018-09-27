<?php
	require_once(__DIR__ . '/arrayObject.php');

	class CONFIG implements IteratorAggregate
	{
		private static $_instance;

		private $_filename;
		private $_configuration;

		private $_position = 0;


		private function __construct()
		{
			$this->_filename = array();
			$this->_configuration = new MyArrayObject();
		}

		private function _readConfig($filename, $fileMissingError = true)
		{
			if(file_exists($filename))
			{
				if(is_readable($filename))
				{
					if(!in_array($filename, $this->_filename, true))
					{
						$this->_filename[] = $filename;
						$json = file_get_contents($filename);
						$configuration = json_decode($json, true);

						if($configuration === null) {
							throw new Exception("Configuration '".$filename."' is not a valid JSON", E_USER_ERROR);
						}

						$this->_configuration->replace_recursive($configuration);
					}
				}
				else {
					throw new Exception("Configuration '".$filename."' is not readable", E_USER_ERROR);
				}
			}
			elseif($fileMissingError) {
				throw new Exception("Configuration '".$filename."' does not exist", E_USER_ERROR);
			}
			else {
				return false;
			}

			return true;
		}

		public static function getInstance($filename = null)
		{
			if(self::$_instance === null) {
				self::$_instance = new self();
			}

			if($filename !== null) {
				self::loadConfigurations($filename);
			}

			return self::$_instance;
		}

		public static function loadConfigurations($filenames, $fileMissingError = true)
		{
			$status = true;
			$filenames = (array) $filenames;

			foreach($filenames as $filename) {
				$_status = self::$_instance->_readConfig($filename, $fileMissingError);
				$status = $status && $_status;
			}

			return $status;
		}

		public function getIterator()
		{
			return $this->_configuration->getIterator();
		}

		public function __get($name)
		{
			if($this->_configuration->key_exists($name)) {
				return $this->_configuration[$name];
			}
			else {
				throw new Exception("Configuration ".$name." does not exist", E_USER_ERROR);
			}
		}
	}