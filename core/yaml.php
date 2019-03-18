<?php
	namespace Core;

	use ArrayObject;

	class Yaml implements \ArrayAccess
	{
		const DEFAULT_ROOT_KEY = 'host';

		protected static $_path = 'outputs/';

		protected $_file = null;
		protected $_vars = null;


		public function __construct($file = null)
		{
			$this->_init();
			$this->setFile($file);
		}

		public function _init()
		{
			$this->_vars = new ArrayObject();
			$this->_vars->setFlags(ArrayObject::ARRAY_AS_PROPS);
		}

		public static function setPath($path)
		{
			self::$_path = $path;
			return true;
		}

		public function setFile($file)
		{
			$this->_file = $file;
			return $this;
		}

		public function setVars(array $vars, $key = null)
		{
			if($key === null) {
				$this->_vars->exchangeArray($vars);
			}
			else {
				$this->_vars->{$key} = $vars;
			}
			return $this;
		}

		public function addVars(array $vars)
		{
			return $this->replaceVars($vars);
		}

		public function addFrom(Yaml $Yaml)
		{
			return $this->addVars($Yaml->toArray());
		}

		public function replaceVars(array $vars)
		{
			$vars = array_replace_recursive($this->toArray(), $vars);
			$this->_vars->exchangeArray($vars);
			return $this;
		}

		public function mergeVars(array $vars)
		{
			$vars = array_merge_recursive($this->toArray(), $vars);
			$this->_vars->exchangeArray($vars);
			return $this;
		}

		public function save($fileName = null, $rootKey = null)
		{
			if($fileName === null)
			{
				if($this->_file !== null) {
					$fileName = $this->_file;
				}
				else {
					throw new Exception("Impossible de sauvegarder le YAML sans nom de fichier", E_USER_ERROR);
				}
			}

			return file_put_contents(rtrim(self::$_path, '/').'/'.$fileName.".yaml", $this->toString($rootKey), LOCK_EX);
		}

		/**
		  * /!\ Quand un tableau possède pour clés des chiffres alors c'est toujours de type INT
		  * Lorsque les clés sont de type INT alors c'est une liste
		  * Lorsque les clés sont de type STING alors c'est un tableau
		  * Pour forcer des clés INT en STRING par example pour des VLANs alors on utilise __type__
		  */
		protected function _build(array $vars, $tabCount = 0)
		{
			$yaml = '';
			uksort($vars, 'strnatcasecmp');

			if(array_key_exists('__type__', $vars)) {
				$__type__ = $vars['__type__'];
				unset($vars['__type__']);
			}
			else {
				$__type__ = null;
			}

			foreach($vars as $name => $value)
			{
				if(is_array($value) && count($value) > 0) {
					$value = PHP_EOL.$this->_build($value, ($tabCount+1));
				}
				elseif((is_array($value) && count($value) === 0) || $value === "" || $value === null) {
					$value = '~';
				}
				elseif(is_bool($value)) {
					$value = ($value) ? ('TRUE') : ('FALSE');
				}
				else {
					$value = '"'.$value.'"';
				}

				$yaml .= str_repeat(" ", $tabCount*2);

				switch($__type__)
				{
					case 'string': {
						$name = (string) $name;
						break;
					}
				}

				if(is_int($name)) {
					$yaml .= "- ".$value.PHP_EOL;
				}
				elseif(is_string($name)) {
					$yaml .= $name.": ".$value.PHP_EOL;
				}
			}

			return $yaml;
		}

		public function toArray()
		{
			return $this->_vars->getArrayCopy();
		}

		public function toString($rootKey = null)
		{
			if($rootKey === null) {
				$rootKey = self::DEFAULT_ROOT_KEY;
			}

			$datas = $this->_build($this->toArray(), 1);
			return "---".PHP_EOL.$rootKey.':'.PHP_EOL.$datas;
		}

		public function offsetSet($offset, $value)
		{
			if (is_null($offset)) {
				$this->_vars[] = $value;
			} else {
				$this->_vars[$offset] = $value;
			}
		}

		public function offsetExists($offset)
		{
			return isset($this->{$offset});
		}

		public function offsetUnset($offset)
		{
			unset($this->{$offset});
		}

		public function offsetGet($offset)
		{
			return (isset($this->{$offset})) ? ($this->_vars[$offset]) : (null);
		}

		public function __isset($name)
		{
			return array_key_exists($name, $this->_vars);
		}

		public function __unset($name)
		{
			unset($this->_vars[$offset]);
		}

		public function __get($name)
		{
			return (isset($this->{$name})) ? ($this->_vars[$name]) : (false);
		}

		public function __set($name, $value)
		{
			$this->_vars[$name] = $value;
		}

		public function __toString()
		{
			return $this->toString();
		}
	}