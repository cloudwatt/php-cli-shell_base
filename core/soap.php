<?php
	namespace Core;

	use SoapClient;

    class Soap
    {
        protected $_service;
        protected $_server;
        protected $_options;
        protected $_handle;

        protected $_debug = false;


        public function __construct($server, $service = null, $debug = false)
		{
            $this->_server = $server;
			$this->_service = (Tools::is('string&&!empty', $service)) ? ($service) : ('Unknown');

            $this->debug($debug);

			$this->_init();
        }

        protected function _init()
		{
            $this->resetOpts();
        }

        public function enableTrace()
        {
            $this->_options['trace'] = 1;
            return $this;
        }

        public function disableTrace()
        {
            $this->_options['trace'] = 0;
            return $this;
        }

        public function resetOpts()
		{
			$this->_options = array('trace' => 1);
			return $this;
		}

		public function getOpt($optName)
		{
			if(array_key_exists($optName, $this->_options)) {
                return $this->_options[$optName];
            }
            else {
                return false;
            }
		}

		public function getOpts()
		{
			return $this->_options;
        }

        public function setOpt($optName, $optValue)
        {
            $this->_options[$optName] = $optValue;
            return $this;
        }

        public function setOpts(array $opts)
        {
            $this->_options = $opts;
            return $this;
        }

        public function start()
        {
            if($this->_handle === null) {
               $this->_handle = new SoapClient($this->_server, $this->_options);
            }

            return true;
        }

        public function __call($name, array $arguments)
        {
            $status = $this->start();

            if($status) {
                return call_user_func_array(array($this->_handle, $name), $arguments);
            }
            else {
                throw new Exception("It is not possible to execute SOAP call for ".$this->_service." service", E_USER_ERROR);
            }
        }

        public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
    }