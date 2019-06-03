<?php
	namespace Core\Addon;

	use Core as C;

	abstract class Orchestrator implements Orchestrator_Interface, \Iterator, \ArrayAccess, \Countable
	{
		const SERVICE_NAME = 'unknown';

		/**
		  * @var Core\Config
		  */
		protected $_config = null;

		/**
		  * @var Core\Addon\Orchestrator_Selector_Service
		  */
		protected $_serviceSelector = null;

		/**
		  * @var Core\Addon\Service[]
		  */
		protected $_services = array();

		/**
		  * @var string
		  */
		protected $_serviceIdToUse = null;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		/**
		  * @param Core\Config $config
		  * @return Core\Addon\Orchestrator
		  */
		public function __construct(C\Config $config = null)
		{
			$this->_config = $config;
		}

		/**
		  * @return bool
		  */
		public function hasConfig()
		{
			return ($this->_config !== null);
		}

		/**
		  * @return null|Core\Config
		  */
		public function getConfig()
		{
			return $this->_config;
		}

		/**
		  * @param string $id
		  * @return Core\Addon\Service
		  */
		public function newService($id)
		{
			$id = (string) $id;

			if(!$this->key_exists($id)) {
				$service = $this->_newService($id);
				$this->_services[$id] = $service;
				$service->debug($this->_debug);
			}

			return $this->_services[$id];
		}

		/**
		  * @param string $id
		  * @return Core\Addon\Service
		  */
		abstract protected function _newService($id);

		/**
		  * @param string[] $ids
		  * @return $this
		  */
		public function newServices(array $ids)
		{
			foreach($ids as $id) {
				$this->newService($id);
			}

			return $this;
		}

		/**
		  * @param Core\Addon\Service $service
		  * @return bool
		  */
		public function setService(Service $service)
		{
			$id = (string) $service->id;

			if(!$this->key_exists($id)) {
				$this->_services[$id] = $service;
				$service->debug($this->_debug);
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @param Core\Addon\Service[] $ids
		  * @return bool
		  */
		public function setServices(array $services)
		{
			$status = false;

			foreach($services as $service)
			{
				$status = $this->setService($service);

				if($status === false) {
					break;
				}
			}

			return $status;
		}

		/**
		  * @param string $id
		  * @return false|Core\Addon\Service
		  */
		public function getService($id)
		{
			$id = (string) $id;

			if($this->key_exists($id)) {
				return $this->_services[$id];
			}
			else {
				return false;
			}
		}

		/**
		  * @return Core\Addon\Service[]
		  */
		public function getServices()
		{
			return $this->_services;
		}

		/**
		  * @param string $id
		  * @return bool
		  */
		public function useServiceId($id)
		{
			$id = (string) $id;

			if($this->key_exists($id)) {
				$this->_serviceIdToUse = $id;
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $id
		  * @return false|Core\Addon\Service
		  */
		public function service($id)
		{
			$status = $this->useServiceId($id);
			return ($status) ? ($this->getCurrentService()) : (false);
		}

		/**
		  * @param string $id
		  * @return false|Core\Addon\Service
		  */
		public function getCurrentService()
		{
			if($this->_serviceIdToUse !== null) {
				return $this->_services[$this->_serviceIdToUse];
			}
			elseif(count($this) === 1) {
				return current($this->_services);
			}
			else {
				return false;
			}
		}

		/**
		  * @param Core\Addon\Orchestrator_Selector_Service $selector
		  * @return $this
		  */
		public function setServiceSelector(Orchestrator_Selector_Service $selector)
		{
			$this->_serviceSelector = $selector;
			return $this;
		}

		/**
		  * @param mixed $attribute
		  * @return false|Core\Addon\Service
		  */
		public function selectService($attribute)
		{
			if($this->_serviceSelector !== null)
			{
				$id = $this->_serviceSelector->match($attribute);
				return $this->getService($id);
			}
			else {
				return false;
			}
		}

		public function keys()
		{
			return array_keys($this->_services);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_services);
		}

		public function rewind()
		{
			return reset($this->_services);
		}

		public function current()
		{
			return current($this->_services);
		}

		public function key()
		{
			return key($this->_services);
		}

		public function next()
		{
			return next($this->_services);
		}

		public function valid()
		{
			return (key($this->_services) !== null);
		}

		public function offsetSet($offset, $value)
		{
		}

		public function offsetExists($offset)
		{
			return $this->key_exists($offset);
		}

		public function offsetUnset($offset)
		{
		}

		public function offsetGet($offset)
		{
			if($this->offsetExists($offset)) {
				return $this->_services[$offset];
			}
			else {
				return null;
			}
		}

		public function count()
		{
			return count($this->_services);
		}

		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		/**
		  * @param mixed $name
		  * @return void
		  */
		public function __unset($name)
		{
			unset($this->_services[$name]);
		}

		public function __get($name)
		{
			if($name === 'service')
			{
				$service = $this->getCurrentService();

				if($service !== false) {
					return $service;
				}
				elseif(count($this) > 0) {
					throw new Exception("Unable to retrieve ".static::SERVICE_NAME." service, specify the service ID", E_USER_ERROR);
				}
				else {
					throw new Exception("Unable to retrieve ".static::SERVICE_NAME." service, no service available", E_USER_ERROR);
				}
			}
			else
			{
				$service = $this->getService($name);

				if($service !== false) {
					return $service;
				}
				else {
					throw new Exception(static::SERVICE_NAME." service '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}

		/**
		  * @param bool $debug
		  * @return $this
		  */
		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;

			foreach($this->_services as $service) {
				$service->debug($this->_debug);
			}

			return $this;
		}
	}