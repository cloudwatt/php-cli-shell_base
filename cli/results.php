<?php
	namespace Cli;

	use Core as C;

	class Results extends C\StatusValues
	{
		/**
		  * Flag KEEP_FAILED_STATUS is mandatory
		  *
		  * @param int $flags
		  * @return $this
		  */
		public function __construct($flags = self::ALLOW_CHANGES | self::KEEP_FAILED_STATUS)
		{
			/**
			  * Do not permit to set a first value since the constructor
			  * to avoid a default value (null) to be append to local storage
			  */

			$this->setFlags($flags);
		}

		/**
		  * Flag KEEP_FAILED_STATUS is mandatory
		  *
		  * @param int $flags
		  * @return $this
		  */
		public function setFlags($flags)
		{
			if(C\Tools::is('int&&>=0', $flags)) {
				$this->_flags = ($flags | self::KEEP_FAILED_STATUS);
			}
			elseif($flags === true || $flags === null) {
				$this->_flags = (self::ALLOW_CHANGES | self::KEEP_FAILED_STATUS);
			}
			elseif($flags === false) {
				$this->_flags = (self::DENY_CHANGES | self::KEEP_FAILED_STATUS);
			}

			return $this;
		}
	}