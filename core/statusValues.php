<?php
	namespace Core;

	use ArrayIterator;

	class StatusValues extends StatusValue implements \IteratorAggregate, \ArrayAccess, \Countable
	{
		/**
		  * @var array
		  */
		protected $_value = array();


		/**
		  * @param int $flags
		  * @return $this
		  */
		public function __construct($flags = self::ALLOW_CHANGES)
		{
			/**
			  * Do not permit to set a first value since the constructor
			  * to avoid a default value (null) to be append to local storage
			  */

			$this->setFlags($flags);
		}

		/**
		  * @param int $flags
		  * @return $this
		  */
		public function setFlags($flags)
		{
			if(Tools::is('int&&>=0', $flags)) {
				$this->_flags = $flags;
			}
			elseif($flags === true || $flags === null) {
				$this->_flags = self::ALLOW_CHANGES;
			}
			elseif($flags === false) {
				$this->_flags = self::DENY_CHANGES;
			}

			return $this;
		}

		public function setValue($value)
		{
			if((count($this->_value) === 0 || $this->_canChanges()) && is_array($value)) {
				$this->_value = $value;
				return true;
			}

			return false;
		}

		public function append($value)
		{
			if(count($this->_value) === 0 || $this->_canChanges()) {
				$this->_value[] = $value;
				return true;
			}

			return false;
		}

		// Voir StatusValue::__call
		/*public function setValues($values)
		{
			return $this->setValue($value);
		}

		public function hasValues()
		{
			return $this->hasValue();
		}

		public function getValues()
		{
			return $this->getValue();
		}*/

		public function keys()
		{
			return array_keys($this->_value);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_value);
		}

		public function getIterator()
		{
			return new ArrayIterator($this->_value);
		}

		public function offsetSet($offset, $value)
		{
			if(is_null($offset)) {
				$this->_value[] = $value;
			}
			else {
				$this->_value[$offset] = $value;
			}
		}

		public function offsetExists($offset)
		{
			return $this->key_exists($offset);
		}

		public function offsetUnset($offset)
		{
			unset($this->_value[$offset]);
		}

		public function offsetGet($offset)
		{
			if($this->offsetExists($offset)) {
				return $this->_value[$offset];
			}
			else {
				return null;
			}
		}

		public function count()
		{
			return count($this->_value);
		}

		public function __isset($name)
		{
			return $this->key_exists($name);
		}
	}