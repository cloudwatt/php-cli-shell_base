<?php
	namespace Core;

	use SoapClient;

    class Soap
    {
		/**
		  * @var string Service name
		  */
        protected $_service;

		/**
		  * @var string Service address
		  */
        protected $_server;

		/**
		  * @var array
		  */
        protected $_options = array();

		/**
		  * @var SoapClient
		  */
        protected $_handle = null;

		/**
		  * @var bool
		  */
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
            $this->_options['trace'] = true;
            return $this;
        }

        public function disableTrace()
        {
            $this->_options['trace'] = false;
            return $this;
        }

        public function resetOpts()
		{
			$this->_options = array(
				'trace' => true,
				'exceptions' => true,
				'keep_alive' => true,
				//'connection_timeout' => 5000,
				'cache_wsdl' => WSDL_CACHE_NONE,
				'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE
			);

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

            if($status)
			{
				try {
					return call_user_func_array(array($this->_handle, $name), $arguments);
				}
				catch(SoapFault $e)
				{
					// Uncaught SoapFault exception: [HTTP] Error Fetching http headers
					if(preg_match('#\[HTTP\] Error#i', $e->getMessage())) {
						sleep(1);
						return call_user_func_array(array($this->_handle, $name), $arguments);
					}
					else {
						throw $e;
					}
				}
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