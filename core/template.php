<?php
	namespace Core;

	use Core\Exception as E;

	class Template
	{
		/**
		  * @var string
		  */
		protected $_scriptFilename;

		/**
		  * @var string
		  */
		protected $_exportFilename;

		/**
		  * @var array
		  */
		protected $_vars;


		/**
		  * @param string $script Script filename
		  * @param string $export Export filename
		  * @param array $vars Variables for rendering
		  * @return $this
		  * @throws Exception|Core\Exception\Message
		  */
		public function __construct($script = null, $export = null, array $vars = null)
		{
			$this->setScript($script);
			$this->setExport($export);
			$this->setVars($vars);
		}

		/**
		  * @param string $filename Script filename
		  * @return $this
		  * @throws Exception|Core\Exception\Message
		  */
		public function setScript($filename)
		{
			if(Tools::is('string&&!empty', $filename))
			{
				$filename = Tools::filename($filename, true, false);

				if(!file_exists($filename)) {
					throw new E\Message("Le fichier du script '".$filename."' n'existe pas", E_USER_ERROR);
				}
				elseif(!is_readable($filename)) {
					throw new E\Message("Le fichier du script '".$filename."' ne peut être lu", E_USER_ERROR);
				}

				$this->_scriptFilename = $filename;
			}
			else {
				throw new Exception("Template script filename is not valid", E_USER_ERROR);
			}

			return $this;
		}

		/**
		  * @param string $filename Export filename
		  * @return $this
		  * @throws Exception|Core\Exception\Message
		  */
		public function setExport($filename = null)
		{
			if(Tools::is('string&&!empty', $filename))
			{
				$filename = Tools::filename($filename, true, false);
				$pathname = pathinfo($filename, PATHINFO_DIRNAME);
				$pathname = Tools::pathname($pathname, true, true);		// Permet juste le mkdir

				if(!file_exists($pathname)) {
					throw new E\Message("Le dossier d'export '".$pathname."' n'existe pas", E_USER_ERROR);
				}
				elseif(!is_writable($pathname)) {
					throw new E\Message("Le dossier d'export '".$pathname."' ne peut être modifié", E_USER_ERROR);
				}
				elseif(file_exists($filename) && !is_writable($filename)) {
					throw new E\Message("Le fichier d'export '".$filename."' ne peut être modifié", E_USER_ERROR);
				}
			}
			elseif($filename !== null) {
				throw new Exception("Template export filename is not valid", E_USER_ERROR);
			}

			$this->_exportFilename = $filename;
			return $this;
		}

		/**
		  * @param array $vars Variables for rendering
		  * @return $this
		  */
		public function setVars(array $vars)
		{
			$this->_vars = $vars;
			return $this;
		}

		/**
		  * @param array $vars Variables for rendering
		  * @return $this
		  */
		public function addVars(array $vars)
		{
			$this->_vars = array_merge($this->_vars, $vars);
			return $this;
		}

		/**
		  * @return string|false Buffer or false if error occur
		  */
		public function rendering()
		{
			if($this->_scriptFilename !== null)
			{
				ob_start();
				require $this->_scriptFilename;
				$buffer = ob_get_clean();

				$status = $this->_export($buffer);
				return ($status) ? ($buffer) : (false);
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $buffer
		  * @return bool
		  */
		protected function _export($buffer)
		{
			if($this->_exportFilename !== null)
			{
				// Workaround CRLF
				if(PHP_EOL !== "\r\n")
				{
					/**
					  * \R matches any Unicode newline sequence; can be modified using verbs
					  * u modifier: unicode. Pattern strings are treated as UTF-16
					  */
					$buffer = preg_replace("#\R#u", PHP_EOL, $buffer);
				}

				$status = file_put_contents($this->_exportFilename, $buffer, LOCK_EX);

				if($status !== false) {
					return true;
				}
				elseif(file_exists($this->_exportFilename)) {
					unlink($this->_exportFilename);
				}

				return false;
			}
			else {
				return true;
			}
		}

		/**
		  * @param string $name
		  * @return null|mixed
		  */
		public function _($name)
		{
			if(array_key_exists($name, $this->_vars)) {
				return $this->_vars[$name];
			}
			else {
				return null;
			}
		}

		/**
		  * @param string $name
		  * @return mixed
		  */
		public function __get($name)
		{
			switch($name)
			{
				case 'script': {
					return $this->_scriptFilename;
				}
				case 'export': {
					return $this->_exportFilename;
				}
				default:
				{
					if(array_key_exists($name, $this->_vars)) {
						return $this->_vars[$name];
					}
					else {
						throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
					}
				}
			}
		}
	}