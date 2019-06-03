<?php
	namespace Core\Addon;

	abstract class Service_Container implements \Iterator, \ArrayAccess, \Countable
	{
		/**
		  * @var Core\Addon\Service
		  */
		protected $_service = null;

		/**
		  * @var string
		  */
		protected $_type = null;

		/**
		  * @var bool
		  */
		protected $_allowOverwrite = false;

		/**
		  * @var array
		  */
		protected $_container = array();

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public function __construct(Service $service, $type, $allowOverwrite = false)
		{
			$this->_service = $service;
			$this->_type = $type;
			$this->allowOverwrite($allowOverwrite);
		}

		public function allowOverwrite($status)
		{
			$this->_allowOverwrite = (bool) $status;
		}

		public function registerSet($field, array $objects)
		{
			foreach($objects as $object)
			{
				$status = $this->register($object[$field], $object);

				if(!$status) {
					return false;
				}
			}

			return true;
		}

		/*abstract public function register($id, $object);*/

		protected function _register($id, $object)
		{
			$id = (string) $id;

			if(!$this->key_exists($id) || $this->_allowOverwrite) {
				$this->_container[$id] = $object;
				return true;
			}
			else {
				throw new Exception("Unable to register object with ID '".$id."', object ID already exists", E_USER_ERROR);
			}
		}

		/*abstract public function unregister($id);*/

		protected function _unregister($id)
		{
			$id = (string) $id;

			if($this->key_exists($id)) {
				$object = $this->_container[$id];
				unset($this->_container[$id]);
				return $object;
			}
			else {
				return false;
			}
		}

		public function retrieve($id)
		{
			$id = (string) $id;

			if($this->key_exists($id)) {
				return $this->_container[$id];
			}
			else {
				return false;
			}
		}

		public function get($id)
		{
			return $this->retrieve($id);
		}

		public function locate($field, $value)
		{
			foreach($this->_container as $object)
			{
				if($object[$field] === $value) {
					return $object;
				}
			}

			return false;
		}

		public function search($field, $value)
		{
			if($value !== null)
			{
				$results = array();
				$value = preg_quote($value, '#');
				//$value = str_replace('\\*', '.*', $value);
				$value = ($strict) ? ('^('.$value.')$') : ('^('.$value.')');

				foreach($this->_container as $object)
				{
					if(preg_match('#'.$value.'#i', $object[$field])) {
						$results[] = $object;
					}
				}

				return $results;
			}
			else {
				return $this->_container;
			}
		}

		/**
		 * @return array
		 */
		public function getAll()
		{
			return $this->_container;
		}

		/**
		  * @return $this
		  */
		public function reset()
		{
			$this->_container = array();
			return $this;
		}

		public function keys()
		{
			return array_keys($this->_container);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_container);
		}

		public function rewind()
		{
			return reset($this->_container);
		}

		public function current()
		{
			return current($this->_container);
		}

		public function key()
		{
			return key($this->_container);
		}

		public function next()
		{
			return next($this->_container);
		}

		public function valid()
		{
			return (key($this->_container) !== null);
		}

		public function offsetSet($offset, $value)
		{
		}

		public function offsetExists($offset)
		{
			return $this->key_exists($offset);
		}

		public function offsetUnset($offset)
		{
			unset($this->_container[$name]);
		}

		public function offsetGet($offset)
		{
			if($this->offsetExists($offset)) {
				return $this->_container[$offset];
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
			return count($this->_container);
		}

		/**
		 * @return bool
		 */
		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		/**
		 * @return void
		 */
		public function __unset($name)
		{
			unset($this->_container[$name]);
		}

		/**
		 * @param mixed $name
		 * @return mixed
		 */
		public function __get($name)
		{
			$result = $this->retrieve($name);

			if($result !== false) {
				return $result;
			}
			else {
				throw new Exception("Unable to retrieve object '".$name."'", E_USER_ERROR);
			}
		}

		/**
		  * @param bool $debug
		  * @return $this
		  */
		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}