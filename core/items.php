<?php
	namespace Core;

	class Items implements \Iterator, \ArrayAccess, \Countable
	{
		/**
		  * @var string
		  */
		protected static $_itemClassName = 'Item';

		/**
		  * @var array
		  */
		protected $_datas = array();


		public function __construct(MyArrayObject $objects)
		{
			$this->_format($objects);
		}

		protected function _format(MyArrayObject $objects)
		{
			foreach($objects as $name => $object)
			{
				if(is_object($object)) {
					$this->_datas[$name] = new static::$_itemClassName($name, $object);
				}
				else {
					$this->_datas[$name] = $object;
				}
			}
		}

		public function keys()
		{
			return array_keys($this->_datas);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_datas);
		}

		public function rewind()
		{
			return reset($this->_datas);
		}

		public function current()
		{
			return current($this->_datas);
		}

		public function key()
		{
			return key($this->_datas);
		}

		public function next()
		{
			return next($this->_datas);
		}

		public function valid()
		{
			return (key($this->_datas) !== null);
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
				return $this->{$offset};
			}
			else {
				return null;
			}
		}

		public function count()
		{
			return count($this->_datas);
		}

		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		public function __get($name)
		{
			if(isset($this->{$name})) {
				return $this->_datas[$name];
			}
			else {
				throw new Exception("Attribute name '".$name."' does not exist", E_USER_ERROR);
			}
		}
	}