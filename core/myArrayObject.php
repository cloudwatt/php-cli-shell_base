<?php
	namespace Core;

	use ArrayObject;

	class MyArrayObject implements \Iterator, \ArrayAccess, \Countable
	{
		protected $_array;

		public function __construct($input = array())
		{
			$this->_array = (array) $input;
		}

		public function merge($input)
		{
			$input = $this->_toArray($input);
			$this->_array = array_merge($this->_array, $input);
			return $this;
		}

		public function merge_recursive($input)
		{
			$input = $this->_toArray($input);
			$this->_array = array_merge_recursive($this->_array, $input);
			return $this;
		}

		public function replace($input)
		{
			$input = $this->_toArray($input);
			$this->_array = array_replace($this->_array, $input);
			return $this;
		}

		public function replace_recursive($input)
		{
			$input = $this->_toArray($input);
			$this->_array = array_replace_recursive($this->_array, $input);
			return $this;
		}

		public function keys()
		{
			return array_keys($this->_array);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_array);
		}

		public function rewind()
		{
			$return = reset($this->_array);
			return $this->_format($return);
		}

		public function current()
		{
			$return = current($this->_array);
			return $this->_format($return);
		}

		public function key()
		{
			return key($this->_array);
		}

		public function next()
		{
			$return = next($this->_array);
			return $this->_format($return);
		}

		public function valid()
		{
			return (key($this->_array) !== null);
		}

		public function offsetSet($offset, $value)
		{
			if(is_null($offset)) {
				$this->_array[] = $value;
			} else {
				$this->_array[$offset] = $value;
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
			if($this->offsetExists($offset)) {
				return $this->{$offset};
			}
			else {
				return null;
			}
		}

		public function count()
		{
			return count($this->_array);
		}

		public function toArray()
		{
			return $this->_toArray($this->_array, true);
		}

		public function toObject()
		{
			$array = $this->_toArray($this->_array, true);
			return new ArrayObject($array, ArrayObject::ARRAY_AS_PROPS);
		}

		protected function _toArray($input, $recursive = false)
		{
			// @todo coder $recursive

			/**
			  * Ne pas utiliser de référence foreach($input as &$array) sinon:
			  * Uncaught Error: An iterator cannot be used with foreach by reference
			  */
			foreach($input as $key => $array)
			{
				if(is_array($array) || $array instanceof MyArrayObject || $array instanceof ArrayObject) {
					$input[$key] = $this->_toArray($array);
				}
			}

			if(is_array($input)) {
				return $input;
			}
			elseif(is_object($input))
			{
				// /!\ If get_class() is called with anything other than an object, an E_WARNING level error is raised
				switch(get_class($input))
				{
					//case get_class(): {
					case static::class: {
						return $input->toArray();
					}
					case 'ArrayObject': {
						return $input->getArrayCopy();
					}
				}
			}

			throw new Exception('Argument 1 passed must be of the type array or ArrayObject', E_USER_ERROR);
		}

		protected function _format($data)
		{
			if(is_array($data)) {
				$className = static::class;
				return new $className($data);
			}
			else {
				return $data;
			}
		}

		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		public function __unset($name)
		{
			unset($this->_array[$name]);
		}

		public function __get($name)
		{
			if(isset($this->{$name})) {
				return $this->_format($this->_array[$name]);
			}
			else {
				throw new Exception("Attribute '".$name."' does not exist", E_USER_ERROR);
			}
		}

		public function __set($name, $value)
		{
			$this->_array[$name] = $value;
		}

		public function debug()
		{
			return $this->toArray();
		}
	}