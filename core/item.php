<?php
	namespace Core;

	class Item implements \Iterator, \ArrayAccess, \Countable
	{
		/**
		  * @var string
		  */
		protected $_name;

		/**
		  * @var Core\MyArrayObject[]
		  */
		protected $_arrayObject;

		/**
		  * @var Core\Item[]
		  */
		protected $_datasObject;


		public function __construct($name, MyArrayObject $objects)
		{
			$this->_name = $name;

			$this->_arrayObject = $objects;
			$this->_format($this->_arrayObject);
		}

		protected function _format(MyArrayObject $objects)
		{
			$className = static::class;
			$this->_datasObject = new MyArrayObject();

			foreach($objects as $name => $object)
			{
				if(is_object($object)) {
					$this->_datasObject[$name] = new $className($name, $object);
				}
				else {
					$this->_datasObject[$name] = $object;
				}
			}
		}

		public function keys()
		{
			return $this->_datasObject->keys();
		}

		public function key_exists($key)
		{
			return $this->_datasObject->key_exists($key);
		}

		public function rewind()
		{
			return $this->_datasObject->rewind();
		}

		public function current()
		{
			return $this->_datasObject->current();
		}

		public function key()
		{
			return $this->_datasObject->key();
		}

		public function next()
		{
			return $this->_datasObject->next();
		}

		public function valid()
		{
			return $this->_datasObject->valid();
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
			return count($this->_datasObject);
		}

		public function toArray()
		{
			return $this->_arrayObject->toArray();
		}

		public function toObject($customObject = true)
		{
			if($customObject) {
				return $this->_arrayObject;
			}
			else {
				return $this->_arrayObject->toObject();
			}
		}

		public function __isset($name)
		{
			return $this->_datasObject->key_exists($name);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'name': {
					return $this->_name;
				}
				default:
				{
					if(isset($this->{$name})) {
						return $this->_datasObject[$name];
					}
					else {
						throw new Exception("Attribute name '".$name."' does not exist", E_USER_ERROR);
					}
				}
			}
		}
	}