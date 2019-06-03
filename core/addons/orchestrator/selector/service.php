<?php
	namespace Core\Addon;

	use Core as C;

	class Orchestrator_Selector_Service implements Orchestrator_Selector_Interface, \Iterator, \ArrayAccess, \Countable
	{
		/**
		  * @var array
		  */
		protected $_selectors = array();


		/**
		  * @param array $selectors A set of regex with service ID as key
		  * @return Core\Addon\Orchestrator_Selector_Interface
		  */
		public function __construct(array $selectors = array())
		{
			$this->assign($selectors);
		}

		/**
		  * @param array $selectors A set of regex with service ID as key
		  * @return bool
		  */
		public function assign(array $selectors)
		{
			$this->_selectors = array();

			foreach($selectors as $serviceId => $selector)
			{
				$status = $this->append($serviceId, $selector);

				if(!$status) {
					return false;
				}
			}

			return true;
		}

		/**
		  * @param string $serviceId Service ID
		  * @param string $selector Regex
		  * @return bool
		  */
		public function append($serviceId, $selector)
		{
			if(C\Tools::is('string&&!empty', $serviceId)) {
				$this->_selectors[$serviceId] = $selector;
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $serviceId Service ID
		  * @return bool
		  */
		public function reset($serviceId)
		{
			if($this->key_exists($serviceId)) {
				$this->_selectors[$serviceId] = array();
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $serviceId Service ID
		  * @return bool
		  */
		public function erase($serviceId)
		{
			if($this->key_exists($serviceId)) {
				unset($this->_selectors[$serviceId]);
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @param mixed $attribute
		  * @return false|string Service ID
		  */
		public function match($attribute)
		{
			foreach($this->_selectors as $serviceId => $selector)
			{
				if(preg_match($selector, $attribute)) {
					return $serviceId;
				}
			}

			return false;
		}

		/**
		  * @return array
		  */
		public function keys()
		{
			return array_keys($this->_selectors);
		}

		/**
		  * @param mixed $key
		  * @return bool
		  */
		public function key_exists($key)
		{
			return array_key_exists($key, $this->_selectors);
		}

		public function rewind()
		{
			return reset($this->_selectors);
		}

		public function current()
		{
			return current($this->_selectors);
		}

		public function key()
		{
			return key($this->_selectors);
		}

		public function next()
		{
			return next($this->_selectors);
		}

		public function valid()
		{
			return (key($this->_selectors) !== null);
		}

		public function offsetSet($offset, $value)
		{
			if(is_null($offset)) {
				throw new Exception("Unable to add regex in Service Selector object, service ID is missing", E_USER_ERROR);
			}
			else {
				$this->_selectors[$offset] = $value;
			}
		}

		public function offsetExists($offset)
		{
			return $this->key_exists($offset);
		}

		public function offsetUnset($offset)
		{
			unset($this->_selectors[$offset]);
		}

		public function offsetGet($offset)
		{
			if($this->offsetExists($offset)) {
				return $this->_selectors[$offset];
			}
			else {
				return null;
			}
		}

		/**
		  * @return int
		  */
		public function count()
		{
			return count($this->_selectors);
		}

		/**
		  * @param mixed $name
		  * @return bool
		  */
		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		/**
		  * @param mixed $name
		  * @return void
		  */
		public function __unset($name)
		{
			unset($this->_selectors[$name]);
		}
	}