<?php
	class MyArrayObject implements IteratorAggregate, ArrayAccess, Countable
	{
		protected $_array;

		public function __construct($input = array())
		{
			$this->_array = (array) $input;
		}

		public function merge(array $input)
		{
			// /!\ On ecrase les anciennes valeurs avec les nouvelles pour permettre l'override
			$this->_array = array_merge($this->_array, $input);
			return $this;
		}

		public function merge_recursive(array $input)
		{
			// /!\ On ecrase les anciennes valeurs avec les nouvelles pour permettre l'override
			$this->_array = array_merge_recursive($this->_array, $input);
			return $this;
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_array);
		}

		public function getIterator()
		{
			return new ArrayIterator($this->_array);
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
			return isset($this->_array[$offset]);
		}

		public function offsetUnset($offset)
		{
			unset($this->_array[$offset]);
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

		public function __isset($name)
		{
			return isset($this->_array[$name]);
		}

		public function __unset($name)
		{
			unset($this->_array[$name]);
		}

		public function __get($name)
		{
			if(array_key_exists($name, $this->_array))
			{
				if(is_array($this->_array[$name])) {
					$className = static::class;
					return new $className($this->_array[$name]);
				}
				else {
					return $this->_array[$name];
				}
			}
			else {
				throw new Exception("This attribute ".$name." does not exist", E_USER_ERROR);
			}
		}
	}