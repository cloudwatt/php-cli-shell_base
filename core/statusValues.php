<?php
	namespace Core;

	use ArrayIterator;

	class StatusValues extends StatusValue implements \IteratorAggregate, \Countable
	{
		/**
		  * @var array
		  */
		protected $_value = array();


		/**
		  * @param $flags int
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
		  * @param $flags int
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

		public function getIterator()
		{
			return new ArrayIterator($this->_value);
		}

		public function count()
		{
			return count($this->_value);
		}
	}