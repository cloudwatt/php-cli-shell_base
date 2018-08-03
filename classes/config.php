<?php
	include_once(__DIR__ . '/arrayObject.php');

	class CONFIG implements IteratorAggregate
	{
		private static $_instance;

		private $_filename;
		private $_configuration;

		private $_position = 0;


		private function __construct($filename)
		{
			$this->_filename = array();
			$this->_configuration = new MyArrayObject();

			$status = $this->_readConfig($filename);

			if(!$status) {
				throw new Exception("Config file does not exist or is not readable", E_USER_ERROR);
			}
		}

		private function _readConfig($filename)
		{
			if(file_exists($filename) && is_readable($filename))
			{
				if(!in_array($filename, $this->_filename, true))
				{
					$this->_filename[] = $filename;
					$json = file_get_contents($filename);
					$configuration = json_decode($json, true);
					$this->_configuration->merge_recursive($configuration);
				}

				return true;
			}
			else {
				return false;
			}
		}

		public static function getInstance($filename = null)
		{
			if(self::$_instance === null)
			{
				if($filename !== null) {
					self::$_instance = new self($filename);
				}
				else {
					throw new Exception("Config filename argument is null", E_USER_ERROR);
				}
			}
			else {
				self::$_instance->_readConfig($filename);
			}

			return self::$_instance;
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