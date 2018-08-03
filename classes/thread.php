<?php
	class MyThread extends Thread
	{
		protected $_object;
		protected $_method;
		protected $_arguments;

		protected $_result;


		public function __construct(Threaded $object, $method, array $arguments)
		{
			$this->_object = $object;
			$this->_method = $method;
			$this->_arguments = $arguments;
		}

		protected function _getId()
		{
			return (string) $this->getThreadId();
		}

		public function run()
		{
			$this->_result[$this->_getId()] = call_user_func_array(array($this->_object, $this->_method), $this->_arguments);
		}

		public function getResult()
		{
			return $this->_result[$this->_getId()];
		}

		public function __get($name)
		{
			if($name === 'result') {
				return $this->getResult();
			}

			throw new Exception("This attribute does not exist", E_USER_ERROR);
		}
	}