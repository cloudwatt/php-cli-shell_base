<?php
	namespace Core;

	class Translate implements \IteratorAggregate, \Countable
	{
		/**
		  * @var Core\Translate
		  */
		private static $_instance;

		/**
		  * @var array
		  */
		private $_filenames;

		/**
		  * @var Core\MyArrayObject
		  */
		private $_languages;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		private function __construct()
		{
			$this->_filenames = array();
			$this->_languages = new MyArrayObject();
		}

		public static function getInstance($filename = null)
		{
			if(self::$_instance === null) {
				self::$_instance = new self();
			}

			if($filename !== null) {
				self::$_instance->loadLanguages($filename);
			}

			return self::$_instance;
		}

		public function loadLanguages($filenames, $fileMissingError = true)
		{
			$status = true;
			$filenames = (array) $filenames;

			foreach($filenames as $filename) {
				$_status = $this->_readLanguage($filename, $fileMissingError);
				$status = $status && $_status;
			}

			return $status;
		}

		private function _readLanguage($filename, $fileMissingError = true)
		{
			if(file_exists($filename))
			{
				if(is_readable($filename))
				{
					if(!in_array($filename, $this->_filenames, true))
					{
						$this->_filenames[] = $filename;
						$json = file_get_contents($filename);
						$language = json_decode($json, true);

						if($language === null) {
							throw new Exception("Language file '".$filename."' is not a valid JSON", E_USER_ERROR);
						}

						$this->_languages->replace_recursive($language);
					}
				}
				else {
					throw new Exception("Language file '".$filename."' is not readable", E_USER_ERROR);
				}
			}
			elseif($fileMissingError) {
				throw new Exception("Language file '".$filename."' does not exist", E_USER_ERROR);
			}
			else {
				return false;
			}

			return true;
		}

		public function _($id)
		{
			$args = func_get_args();
			array_shift($args);

			return $this->__($id, $args);
		}

		public function __($id, array $args, $default = null)
		{
			$message = false;

			if($this->_languages->key_exists($id)) {
				$message = $this->_languages[$id];
				$message = vsprintf($message, $args);
			}

			if($message === false) {
				$message = ($default === null) ? ($id) : ($default);
			}

			if($this->_debug) {
				$message = '@'.$id.': '.$message;
			}

			return $message;
		}

		public function ___($id, array $args, $default = null)
		{
			$message = false;

			if($this->_languages->key_exists($id))
			{
				$message = $this->_languages[$id];

				foreach($args as $key => $arg)
				{
					$search = preg_quote('{%'.$key.'%}', '#');
					$message = preg_replace('#'.$search.'#i', $arg, $message);

					if($message === null) {
						$message = false;
						break;
					}
				}
			}

			if($message === false) {
				$message = ($default === null) ? ($id) : ($default);
			}

			if($this->_debug) {
				$message = '@'.$id.': '.$message;
			}

			return $message;
		}

		public function getIterator()
		{
			return $this->_languages->getIterator();
		}

		public function count()
		{
			return count($this->_languages);
		}

		public function __isset($name)
		{
			return $this->_languages->key_exists($name);
		}

		public function __get($name)
		{
			if($this->_languages->key_exists($name)) {
				return $this->_languages[$name];
			}
			else {
				throw new Exception("Language message '".$name."' does not exist", E_USER_ERROR);
			}
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}