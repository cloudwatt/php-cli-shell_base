<?php
	namespace Core;

	use DirectoryIterator;
	use FilesystemIterator;

	class Phar
	{
		/**
		  * @var \Phar
		  */
		protected $_PHAR = null;

		/**
		  * @var string
		  */
		protected $_filename = null;

		/**
		  * @var bool
		  */
		protected $_compress = false;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public function __construct()
		{
		}

		public function filename($filename)
		{
			if(preg_match('#^(.+\.phar)$#i', $filename) && \Phar::isValidPharFilename($filename, true)) {
				$this->_filename = $filename;
				return true;
			}
			else {
				return false;
			}
		}

		public function compress($state = true)
		{
			$this->_compress = (bool) $state;

			if($this->_pharExists())
			{
				if(!$this->_compress) {
					$this->_PHAR = $this->_PHAR->convertToExecutable(\Phar::PHAR);
				}
				else {
					$this->_PHAR = $this->_PHAR->convertToExecutable(\Phar::TAR, \Phar::GZ);
				}
			}

			return true;
		}

		public function canWrite()
		{
			return \Phar::canWrite();
		}

		protected function _pharExists()
		{
			return ($this->_PHAR !== null);
		}

		protected function _preparePhar()
		{
			if(!$this->_pharExists()) {
				$this->_PHAR = new \Phar($this->_filename, FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, $this->_filename);
				$this->_PHAR->startBuffering();
			}

			return true;
		}

		public function write($filename, $pharname = null)
		{
			if($this->canWrite() && $this->_preparePhar())
			{
				if(file_exists($filename))
				{
					$cwd = Tools::getWorkingPathname();

					if(is_file($filename)) {
						$this->_writeFile($cwd, $filename, $pharname);
					}
					elseif(is_dir($filename)) {
						$this->_writeDirectory($cwd, $filename);
					}
					else {
						throw new Exception("Filename '".$filename."' is not valid", E_USER_ERROR);
					}

					return true;
				}
				else {
					throw new Exception("Filename '".$filename."' does not exist", E_USER_ERROR);
				}
			}

			return false;
		}

		protected function _writeFile($cwd, $filename, $pharname)
		{
			if($pharname === null) {
				$pharname = str_replace($cwd.DIRECTORY_SEPARATOR, '', $filename);
			}

			$this->_PHAR->addFile($filename, $pharname);
		}

		protected function _writeDirectory($cwd, $pathname)
		{
			$DirectoryIterator = new DirectoryIterator($pathname);

			foreach($DirectoryIterator as $fileinfo)
			{
				$filename = $fileinfo->getPathname();

				if($fileinfo->isFile()) {
					$pharname = str_replace($cwd.DIRECTORY_SEPARATOR, '', $filename);
					$this->_PHAR->addFile($filename, $pharname);
				}
				elseif(!$fileinfo->isDot() && $fileinfo->isDir()) {
					$this->_writeDirectory($cwd, $filename);
				}
			}
		}

		public function import($pathname)
		{
			if($this->canWrite() && $this->_preparePhar())
			{
				if(file_exists($pathname))
				{
					if(is_dir($pathname)) {
						$this->_PHAR->buildFromDirectory($pathname);
						return true;
					}
					else {
						throw new Exception("Directory '".$pathname."' is not valid", E_USER_ERROR);
					}
				}
				else {
					throw new Exception("Directory '".$pathname."' does not exist", E_USER_ERROR);
				}
			}

			return false;
		}

		public function main($cli = null, $web = null)
		{
			if($this->canWrite() && $this->_pharExists()) {
				$stub = $this->_PHAR->createDefaultStub($cli, $web);
				$this->_PHAR->setStub($stub);
				return true;
			}
			else {
				return false;
			}
		}

		public function close()
		{
			if($this->canWrite() && $this->_pharExists()) {
				$this->_PHAR->stopBuffering();
			}
		}

		public function __destruct()
		{
			$this->close();
		}
	}