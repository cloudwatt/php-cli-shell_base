<?php
	namespace Cli\Terminal;

	use SplFileObject;

	use Core as C;

	class History implements \Iterator, \ArrayAccess, \Countable
	{
		/**
		  * @var \SplFileObject
		  */
		protected $_SplFileObject;

		/**
		  * @var string
		  */
		protected $_filename = null;

		/**
		  * @var int
		  */
		protected $_refPosition = 0;

		/**
		  * @var int
		  */
		protected $_lineCounter = 0;


		public function __construct($filename = null)
		{
			$this->setFilename($filename);
		}

		public function getFilename()
		{
			return $this->_filename;
		}

		public function setFilename($filename)
		{
			if(C\Tools::is('string&&!empty', $filename))
			{
				if(strpos($filename, '/') === false && defined('ROOT_DIR')) {
					$filename = ROOT_DIR.'/'.$filename;
				}

				if(!file_exists($filename) || (is_readable($filename) && is_writable($filename))) {
					$this->_filename = $filename;
					return $this->open();
				}
				else {
					throw new Exception('History file "'.$filename.'" must be readeable and writeable', E_USER_ERROR);
				}
			}
			else {
				return false;
			}
		}

		public function open()
		{
			if($this->_SplFileObject === null)
			{
				if($this->_filename !== null)
				{
					$result = touch($this->_filename);

					if($result)
					{
						$this->_SplFileObject = new SplFileObject($this->_filename, 'r');
						$this->_SplFileObject->seek(PHP_INT_MAX);

						$this->_lineCounter = $this->_SplFileObject->key()+1;
						$this->_refPosition = $this->_lineCounter;
					}
					else {
						throw new Exception('History file "'.$this->_filename.'" is not writeable', E_USER_ERROR);
					}
				}
				else {
					throw new Exception('History filename "'.$this->_filename.'" is missing', E_USER_ERROR);
				}
			}

			return true;
		}

		public function reset()
		{
			$this->goToNewLine();
			return $this;
		}

		public function close()
		{
			// /!\ Ne pas utiliser unset
			$this->_SplFileObject = null;
			return true;
		}

		public function search($value, $beforePosition = false, $afterPosition = false)
		{
			if($this->open() && C\Tools::is('string&&!empty', $value))
			{
				/**
				  * Si beforePosition alors on doit rechercher à partir de postion -1
				  * Si !beforePosition alors on doit rechercher à partir de la derniere ligne
				  */
				$i = ($beforePosition) ? ($this->_refPosition-1) : ($this->_lineCounter-1);

				for($i; $i>=0; $i--)
				{
					if($afterPosition && $i <= $this->_refPosition) {
						break;
					}

					$line = $this->getLine($i);

					if(preg_match('#'.preg_quote($value, '#').'#i', $line)) {
						return $line;
					}
				}
			}

			return false;
		}

		public function writeLine($line)
		{
			try
			{
				/**
				  * /!\ SplFileObject::fwrite ne permet pas d'ajouter du contenu, il faut à chaque fois tout renvoyer
				  */
				//$this->_historyObject->fwrite($line.PHP_EOL);

				if(C\Tools::is('string&&!empty', $line)) {
					$this->close();
					file_put_contents($this->_filename, PHP_EOL.$line, FILE_APPEND|LOCK_EX);
					return $this->open();
				}
			}
			catch(Exception $e) {
				throw $e;
			}

			return false;
		}

		public function goToPrevLine()
		{
			if($this->open())
			{
				if($this->_refPosition > 0) {
					$this->_refPosition--;
				}
			}
			else {
				$this->_refPosition = -1;
			}

			return $this->_refPosition;
		}

		public function goToNextLine()
		{
			if($this->open())
			{
				if($this->_refPosition < $this->_lineCounter) {
					$this->_refPosition++;
				}
			}
			else {
				$this->_refPosition = -1;
			}

			return $this->_refPosition;
		}

		public function goToFirstLine()
		{
			$this->_refPosition = ($this->open()) ? (0) : (-1);
			return $this->_refPosition;
		}

		public function goToLastLine()
		{
			$this->_refPosition = ($this->open()) ? ($this->_lineCounter-1) : (-1);
			return $this->_refPosition;
		}

		public function goToNewLine()
		{
			$this->_refPosition = ($this->open()) ? ($this->_lineCounter) : (-1);
			return $this->_refPosition;
		}

		public function goToLine($number)
		{
			if($this->open() && C\Tools::is('int&&>=0', $number) && $number < $this->_lineCounter) {
				$this->_refPosition = (int) $number;
				return true;
			}
			else {
				return false;
			}
		}

		public function getPrevLine()
		{
			$this->goToPrevLine();
			return $this->_getCurrLine();
		}

		public function getCurrLine()
		{
			return $this->_getCurrLine();
		}

		public function getNextLine()
		{
			$this->goToNextLine();
			return $this->_getCurrLine();
		}

		public function getFirstLine()
		{
			$this->goToFirstLine();
			return $this->_getCurrLine();
		}

		public function getLastLine()
		{
			$this->goToLastLine();
			return $this->_getCurrLine();
		}

		public function getLine($offset)
		{
			$this->goToLine($offset);
			return $this->_getCurrLine();
		}

		protected function _getCurrLine()
		{
			if($this->_refPosition < 0) {
				return false;
			}
			elseif($this->_refPosition >= $this->_lineCounter) {
				return '';
			}
			else {
				return $this->_getLine($this->_refPosition);
			}
		}

		protected function _getLine($offset)
		{
			$this->_SplFileObject->seek($offset);

			// /!\ SplFileObject::fgets ne fonctionne pas correctement
			return trim($this->_SplFileObject->current(), PHP_EOL);
		}

		/**
		  * /!\ Seul les fonctions d'iteration ne changent pas la position
		  * Toutes les autres fonctions (getLine, ...) changent la position
		  *
		  * Cela permet de garantir lors de recherches par exemple (search) de maintenir la position
		  */
		// -----------------------------------------
		public function rewind()
		{
			if($this->_SplFileObject !== null) {
				$this->_SplFileObject->rewind();
			}
		}

		public function current()
		{
			if($this->_SplFileObject !== null) {
				return $this->_SplFileObject->current();
			}
			else {
				return null;
			}
		}

		public function key()
		{
			if($this->_SplFileObject !== null) {
				return $this->_SplFileObject->key();
			}
			else {
				return null;
			}
		}

		public function next()
		{
			if($this->_SplFileObject !== null) {
				$this->_SplFileObject->next();
			}
		}

		public function valid()
		{
			if($this->_SplFileObject !== null) {
				return $this->_SplFileObject->valid();
			}
			else {
				return false;
			}
		}

		public function offsetSet($offset, $value)
		{
		}

		public function offsetExists($offset)
		{
			return isset($this->{$offset});
		}

		public function offsetUnset($offset)
		{
		}

		public function offsetGet($offset)
		{
			if($this->offsetExists($offset)) {
				return $this->_getLine($offset);
			}
			else {
				return null;
			}
		}
		// -----------------------------------------

		public function count()
		{
			return $this->_lineCounter;
		}

		public function __isset($name)
		{
			return ($offset >= 0 && $offset < $this->_lineCounter);
		}
	}