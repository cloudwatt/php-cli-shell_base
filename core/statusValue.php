<?php
	namespace Core;

	class StatusValue
	{
		const DENY_CHANGES = 0;
		const ALLOW_CHANGES = 1;
		const KEEP_FAILED_STATUS = 2;

		/**
		  * @var bool
		  */
		protected $_status = null;

		/**
		  * @var mixed
		  */
		protected $_value = null;

		/**
		  * @var int
		  */
		protected $_flags = null;


		public function __construct($status = null, $value = null, $flags = self::ALLOW_CHANGES)
		{
			// /!\ be carreful to inheritance

			$this->setStatus($status);
			$this->setValue($value);
			$this->setFlags($flags);
		}

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

		public function enable()
		{
			$this->setFlags(self::ALLOW_CHANGES);
			return $this;
		}

		public function disable()
		{
			$this->setFlags(self::DENY_CHANGES);
			return $this;
		}

		protected function _canChanges()
		{
			return (($this->_flags & self::ALLOW_CHANGES) === self::ALLOW_CHANGES);
		}

		protected function _keepFailedStatus()
		{
			return (($this->_flags & self::KEEP_FAILED_STATUS) === self::KEEP_FAILED_STATUS);
		}

		protected function _canSetStatus()
		{
			return (($this->_status === null || $this->_canChanges()) && ($this->_status !== false || !$this->_keepFailedStatus()));
		}

		public function setStatus($status)
		{
			if($this->_canSetStatus() && Tools::is('bool', $status)) {
				$this->_status = $status;
				return true;
			}

			return false;
		}

		public function hasStatus()
		{
			return ($this->_status !== null);
		}

		public function getStatus()
		{
			// Workaround to never return null
			return ($this->hasStatus()) ? ($this->_status) : (false);
		}

		public function isOK()
		{
			return ($this->getStatus() === true);
		}

		public function isTrue()
		{
			return $this->isOK();
		}

		public function isSuccess()
		{
			return $this->isOK();
		}

		public function isKO()
		{
			return ($this->getStatus() === false);
		}

		public function isFalse()
		{
			return $this->isKO();
		}

		public function isFailed()
		{
			return $this->isKO();
		}

		public function isNA()
		{
			return !$this->hasStatus();
		}

		public function isNull()
		{
			return $this->isNA();
		}

		public function isUnknown()
		{
			return $this->isNA();
		}

		public function setValue($value)
		{
			if($this->_value === null || $this->_canChanges()) {
				$this->_value = $value;
				return true;
			}

			return false;
		}

		public function hasValue()
		{
			return ($this->_value !== null);
		}

		public function getValue()
		{
			return $this->_value;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'state':
				case 'status': {
					return $this->getStatus();
				}
				case 'value':
				case 'values':
				case 'option':
				case 'options':
				case 'result':
				case 'results': {
					return $this->getValue();
				}
				default: {
					throw new Exception('Attribute "'.$name.'" does not exist', E_USER_ERROR);
				}
			}
		}

		public function __set($name, $value)
		{
			switch($name)
			{
				case 'state':
				case 'status': {
					return $this->setStatus($value);
				}
				case 'value':
				case 'values':
				case 'option':
				case 'options':
				case 'result':
				case 'results': {
					return $this->setValue($value);
				}
				default: {
					throw new Exception('Attribute "'.$name.'" does not exist', E_USER_ERROR);
				}
			}
		}

		public function __call($name, array $arguments)
		{
			$method = mb_strtolower($name);
			$action = substr($method, 0, 3);

			if($action === 'get' || $action === 'set') {
				$attribute = substr($method, 3);
			}
			else {
				$action = null;
				$attribute = $name;
			}

			switch($attribute)
			{
				case 'state':
				case 'status':
				{
					switch($action)
					{
						case 'get': {
							return $this->getStatus();
						}
						case 'set': {
							return $this->setStatus($arguments[0]);
						}
						case null:
						{
							if(count($arguments) === 1) {
								$this->setStatus($arguments[0]);
							}

							return $this->getStatus();
						}
					}
				}
				case 'value':
				case 'values':
				case 'option':
				case 'options':
				case 'result':
				case 'results':
				{
					switch($action)
					{
						case 'get': {
							return $this->getValue();
						}
						case 'set': {
							return $this->setValue($arguments[0]);
						}
						case null:
						{
							if(count($arguments) === 1) {
								$this->setValue($arguments[0]);
							}

							return $this->getValue();
						}
					}
				}
				default: {
					throw new Exception("Unknown method '".$name."'", E_USER_ERROR);
				}
			}
		}
	}