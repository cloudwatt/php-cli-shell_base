<?php
	namespace Core\Addon;

	use Core as C;

	abstract class Service_Plugin implements \Iterator, \ArrayAccess, \Countable
	{
		const PLUGIN_TYPE = 'unknown';
		const PLUGIN_NAME = 'noname';

		/**
		  * @var Core\Addon\Service
		  */
		protected $_service = null;

		/**
		  * Plugin state (enable or disable)
		  * @var bool
		  */
		protected $_state = true;

		/**
		  * @var Core\Addon\Service_Container[]
		  */
		protected $_containers = array();

		/**
		  * @var bool
		  */
		protected $_debug = false;


		/**
		  * @param Core\Addon\Service $service
		  * @param bool $state Turn on or off plugin
		  * @return Core\Addon\Plugin
		  */
		public function __construct(Service $service, $state = true)
		{
			$this->_service = $service;
			$this->_state($state);
		}

		/**
		  * @return Core\Addon\Service
		  */
		public function getService()
		{
			return $this->_service;
		}

		/**
		  * @param bool $state Turn on or off plugin
		  * @return bool
		  */
		protected function _state($state)
		{
			if(C\Tools::is('bool', $state))
			{
				$this->_state = $state;

				if($this->_state) {
					$this->initialization();
				}
				else {
					$this->reset();
				}

				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @return bool
		  */
		abstract public function initialization();

		/**
		  * @param bool $state Turn on or off plugin
		  * @return bool
		  */
		public function state($state = null)
		{
			$this->_state($state);
			return $this->_state;
		}

		/**
		  * @param bool $state Turn on or off plugin
		  * @return bool
		  */
		public function setState($state)
		{
			return $this->_state($state);
		}

		/**
		  * @return bool
		  */
		public function enable()
		{
			return $this->state(true);
		}

		/**
		  * @return bool
		  */
		public function disable()
		{
			return $this->state(false);
		}

		/**
		  * @return bool
		  */
		public function getState()
		{
			return $this->_state;
		}

		/**
		  * @return bool
		  */
		public function isEnabled()
		{
			return ($this->_state === true);
		}

		/**
		  * @return bool
		  */
		public function isDisabled()
		{
			return ($this->_state === false);
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		public function isReady($type)
		{
			return $this->getContainerState($type);
		}

		/**
		  * @return bool
		  */
		public function getPluginState()
		{
			return $this->getState();
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		public function getContainerState($type)
		{
			return ($this->getPluginState() && $this->hasContainer($type));
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		public function hasContainer($type)
		{
			return $this->key_exists($type);
		}

		/**
		  * @param string $type
		  * @return false|Core\Addon\Service_Container
		  */
		public function newContainer($type)
		{
			if($this->isEnabled())
			{
				if(!$this->hasContainer($type)) {
					$container = $this->_newContainer($type);
					$this->_containers[$type] = $container;
					$container->debug($this->_debug);
				}

				return $this->_containers[$type];
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $type
		  * @return Core\Addon\Service_Container
		  */
		abstract protected function _newContainer($type);

		/**
		  * @param string $type
		  * @param bool $autoCreate
		  * @return false|Core\Addon\Service_Container
		  */
		public function getContainer($type, $autoCreate = false)
		{
			if($autoCreate) {
				return $this->newContainer($type);
			}
			elseif($this->hasContainer($type)) {
				return $this->_containers[$type];
			}
			else {
				return false;
			}
		}

		public function keys()
		{
			return array_keys($this->_containers);
		}

		public function key_exists($key)
		{
			return array_key_exists($key, $this->_containers);
		}

		public function rewind()
		{
			return reset($this->_containers);
		}

		public function current()
		{
			return current($this->_containers);
		}

		public function key()
		{
			return key($this->_containers);
		}

		public function next()
		{
			return next($this->_containers);
		}

		public function valid()
		{
			return (key($this->_containers) !== null);
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
				return $this->_containers[$offset];
			}
			else {
				return null;
			}
		}

		public function count()
		{
			return count($this->_containers);
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		public function cleaner($type)
		{
			if($this->hasContainer($type)) {
				$this->_containers[$type]->reset();
				return true;
			}
			else {
				return false;
			}
		}
	
		/**
			* @param string $type
			* @return bool
			*/
		public function erase($type)
		{
			unset($this->_containers[$type]);
			return true;
		}
	
		/**
			* @return bool
			*/
		public function reset()
		{
			$this->_containers = array();
			return true;
		}

		/**
		  * @param mixed $name
		  * @return bool
		  */
		public function __isset($name)
		{
			return $this->key_exists($name);
		}

		/**
		  * @param mixed $name
		  * @return mixed
		  */
		public function __get($name)
		{
			if($name === 'service') {
				return $this->getService();
			}
			else
			{
				$container = $this->getContainer($name);

				if($container !== false) {
					return $container;
				}
				else {
					throw new Exception("Unable to retrieve ".static::PLUGIN_NAME." container '".$name."'", E_USER_ERROR);
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

			foreach($this->_containers as $container) {
				$container->debug($this->_debug);
			}

			return $this;
		}
	}