<?php
	namespace Core\Addon;

	use Core as C;

	abstract class Service
	{
		const SERVICE_NAME = 'unknown';

		const URL_CONFIG_FIELD = 'url';
		const LOGIN_CONFIG_FIELD = 'loginCredential';
		const LOGIN_ENV_CONFIG_FIELD = 'loginEnvVarName';
		const PASSWORD_CONFIG_FIELD = 'passwordCredential';
		const PASSWORD_ENV_CONFIG_FIELD = 'passwordEnvVarName';

		/**
		  * @var string
		  */
		protected $_id;

		/**
		  * @var Core\Config
		  */
		protected $_config;

		/**
		  * @var Core\Addon\Service_Cache
		  */
		protected $_cache;

		/**
		  * @var Core\Addon\Service_Store
		  */
		protected $_store;

		/**
		  * @var Core\Addon\Adapter
		  */
		protected $_adapter;

		/**
		  * @var bool
		  */
		protected $_isReady = false;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		/**
		  * @param string $id
		  * @param Core\Config $config
		  * @return Core\Addon\Service
		  */
		public function __construct($id, C\Config $config = null)
		{
			$this->_id = $id;
			$this->_config = $config;
		}

		/**
		  * @return bool
		  */
		public function hasConfig()
		{
			return isset($this->_config[$this->_id]);
		}

		/**
		  * @return false|Core\Config
		  */
		public function getConfig()
		{
			return $this->_getConfig(false);
		}

		/**
		  * @param string $default
		  * @return mixed|Core\Config
		  */
		protected function _getConfig($default = null)
		{
			return ($this->hasConfig()) ? ($this->_config[$this->_id]) : ($default);
		}

		/**
		  * @return bool
		  */
		public function initialization()
		{
			if(!$this->_isReady) {
				$this->_initCache();
				$this->_initStore();
				$this->_initAdapter();
				$this->_isReady = true;
			}

			return true;
		}

		protected function _initCache()
		{
			$this->_cache = $this->_newCache();
		}

		abstract protected function _newCache();

		protected function _initStore()
		{
			$this->_store = $this->_newStore();
		}

		abstract protected function _newStore();

		protected function _initAdapter()
		{
			$config = $this->_getConfig(null);

			if($this->_config === null || $config !== null) {
				$this->_adapter = $this->_newAdapter($config);
			}
			else {
				throw new Exception("Unable to retrieve ".static::SERVICE_NAME." service '".$this->_id."' configuration", E_USER_ERROR);
			}
		}

		abstract protected function _newAdapter(C\Config $config = null);

		protected function _getUrl(C\Config $serviceConfig, $id)
		{
			if($serviceConfig->key_exists(static::URL_CONFIG_FIELD)) {
				return $serviceConfig[static::URL_CONFIG_FIELD];
			}
			else {
				throw new Exception("Unable to retrieve '".static::URL_CONFIG_FIELD."' configuration for ".static::SERVICE_NAME." service '".$id."'", E_USER_ERROR);
			}
		}

		protected function _getCredentials(C\Config $serviceConfig, $id)
		{
			if($serviceConfig->key_exists(static::LOGIN_CONFIG_FIELD) && C\Tools::is('string&&!empty', $serviceConfig[static::LOGIN_CONFIG_FIELD])) {
				$loginCredential = $serviceConfig[static::LOGIN_CONFIG_FIELD];
			}
			elseif($serviceConfig->key_exists(static::LOGIN_ENV_CONFIG_FIELD) && C\Tools::is('string&&!empty', $serviceConfig[static::LOGIN_ENV_CONFIG_FIELD]))
			{
				$loginEnvVarName = $serviceConfig[static::LOGIN_ENV_CONFIG_FIELD];
				$loginCredential = getenv($loginEnvVarName);

				if($loginCredential === false) {
					throw new Exception("Unable to retrieve login credential for ".static::SERVICE_NAME." service '".$id."' from environment with variable name '".$loginEnvVarName."'", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Unable to retrieve '".static::LOGIN_CONFIG_FIELD."' or '".static::LOGIN_ENV_CONFIG_FIELD."' configuration for ".static::SERVICE_NAME." service '".$id."'", E_USER_ERROR);
			}

			if($serviceConfig->key_exists(static::PASSWORD_CONFIG_FIELD) && C\Tools::is('string&&!empty', $serviceConfig[static::PASSWORD_CONFIG_FIELD])) {
			$passwordCredential = $serviceConfig[static::PASSWORD_CONFIG_FIELD];
			}
			elseif($serviceConfig->key_exists(static::PASSWORD_ENV_CONFIG_FIELD) && C\Tools::is('string&&!empty', $serviceConfig[static::PASSWORD_ENV_CONFIG_FIELD]))
			{
				$passwordEnvVarName = $serviceConfig[static::PASSWORD_ENV_CONFIG_FIELD];
				$passwordCredential = getenv($passwordEnvVarName);

				if($passwordCredential === false) {
					throw new Exception("Unable to retrieve password credential for ".static::SERVICE_NAME." service '".$id."' from environment with variable name '".$passwordEnvVarName."'", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Unable to retrieve '".static::PASSWORD_CONFIG_FIELD."' or '".static::PASSWORD_ENV_CONFIG_FIELD."' configuration for ".static::SERVICE_NAME." service '".$id."'", E_USER_ERROR);
			}

			return array($loginCredential, $passwordCredential);
		}

		public function hasCache()
		{
			return ($this->_cache !== null);
		}

		public function getCache()
		{
			return ($this->hasCache()) ? ($this->_cache) : (false);
		}

		public function hasStore()
		{
			return ($this->_store !== null);
		}

		public function getStore()
		{
			return ($this->hasStore()) ? ($this->_store) : (false);
		}

		public function hasAdapter()
		{
			return ($this->_adapter !== null);
		}

		public function getAdapter()
		{
			return ($this->hasAdapter()) ? ($this->_adapter) : (false);
		}

		abstract public function getMethod();

		public function __isset($name)
		{
			switch($name)
			{
				case 'cache': {
					return $this->hasCache();
				}
				case 'store': {
					return $this->hasStore();
				}
				case 'adapter': {
					return $this->hasAdapter();
				}
				default: {
					return false;
				}
			}
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'id': {
					return $this->_id;
				}
				case 'config': {
					return $this->getConfig();
				}
				case 'cache': {
					return $this->getCache();
				}
				case 'store': {
					return $this->getStore();
				}
				case 'adapter': {
					return $this->getAdapter();
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
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

			if($this->_isReady) {
				$this->_cache->debug($this->_debug);
				$this->_store->debug($this->_debug);
				$this->_adapter->debug($this->_debug);
			}

			return $this;
		}
	}