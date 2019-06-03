<?php
	namespace Core\Addon;

	use Core as C;

	abstract class Adapter
	{
		const METHOD = 'unknown';

		/**
		  * @var Core\Addon\Service
		  */
		protected $_service;

		/**
		  * Addon configuration
		  * @var Core\Config
		  */
		protected $_config;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		/**
		  * @param Core\Addon\Service $service
		  * @param Core\Config $config
		  * @param bool $debug
		  * @return Core\Addon\Adapter
		  */
		public function __construct(Service $service, C\Config $config, $debug = false)
		{
			$this->debug($debug);

			$this->_service = $service;
			$this->_config = $config;
		}

		/**
		 * @return string Service ID
		 */
		public function getServiceId()
		{
			return $this->_service->id;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'service': {
					return $this->_service;
				}
				case 'config': {
					return $this->_config;
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}
	}