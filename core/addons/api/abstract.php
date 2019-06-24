<?php
	namespace Core\Addon;

	use ReflectionClass;

	use Core as C;

	abstract class Api_Abstract implements Api_Interface
	{
		const WILDCARD = '*';

		/**
		  * @var Service
		  */
		protected $_service = null;

		/**
		  * @var Adapter
		  */
		protected $_adapter = null;

		/**
		  * @var string
		  */
		protected $_errorMessage = null;

		/**
		  * @var int
		  */
		protected $_objectId = null;

		/**
		  * @var bool
		  */
		protected $_objectExists = null;		// /!\ Important null pour forcer la detection

		/**
		  * @var string
		  */
		protected $_objectLabel = null;			// /!\ Important null pour forcer la detection

		/**
		  * @var array
		  */
		protected $_objectDatas = null;


		/**
		  * @param mixed $objectId
		  * @param Service $service
		  * @return Api_Abstract
		  */
		public function __construct($objectId = null, Service $service = null)
		{
			$this->_initService($service);
			$this->_initAdapter($this->_service);

			$this->_setObjectId($objectId);
		}

		/**
		  * @param mixed $objectId
		  * @param Addon\Service $service
		  * @return Addon\Api_Abstract
		  */
		public static function factory($objectId, Service $service = null)
		{
			if($service === null) {
				$service = static::_getService();
			}

			$store = $service->store;

			if($store !== false && $store->isReady(static::OBJECT_TYPE))
			{
				$storeContainer = $store->getContainer(static::OBJECT_TYPE);
				$api = $storeContainer->retrieve($objectId);

				if($api !== false) {
					return $api;
				}
				else {
					unset($api);
				}
			}
			else {
				$storeContainer = false;
			}

			$cache = $service->cache;
			$className = static::class;

			if($cache !== false && $cache->isReady(static::OBJECT_TYPE))
			{
				$cacheContainer = $cache->getContainer(static::OBJECT_TYPE);
				$object = $cacheContainer->retrieve($objectId);

				if($object !== false)
				{
					$api = new $className(null, $service);
					$status = $api->_wakeup($object);

					if(!$status) {
						unset($api);
					}
				}
			}

			if(!isset($api)) {
				$api = new $className($objectId, $service);
			}

			if($storeContainer !== false) {
				$storeContainer->assign($api);
			}

			return $api;
		}

		public static function getObjectType()
		{
			return static::OBJECT_TYPE;
		}

		protected function _initService(Service $service = null)
		{
			/**
			  * Permet de garder la référence du service
			  * actuellement activé pour cette instance d'Api
			  */
			if($service !== null) {
				$this->_service = $service;
			}
			else {
				$this->_service = static::_getService();
			}
		}

		protected function _initAdapter(Service $service)
		{
			$this->_adapter = $service->adapter;
		}

		public function hasObjectId()
		{
			return ($this->_objectId !== null);
		}

		public function getObjectId()
		{
			return $this->_objectId;
		}

		protected function _setObjectId($objectId)
		{
			if($this->objectIdIsValid($objectId)) {
				$this->_objectId = (int) $objectId;
				$this->objectExists();
			}
			elseif($objectId !== null) {
				throw new Exception("This object ID must be an integer greater to 0, '".gettype($objectId)."' is not valid", E_USER_ERROR);
			}
		}

		abstract protected function _getObject();

		protected function _wakeup(array $datas)
		{
			if(static::objectIdIsValid($datas[static::FIELD_ID]))
			{
				$this->_objectId = $datas[static::FIELD_ID];
				$this->_objectLabel = $datas[static::FIELD_NAME];
				$this->_objectDatas = $datas;
				$this->_objectExists = true;
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @return bool
		  */
		public function isOnline()
		{
			$this->_objectDatas = null;
			$this->_objectExists = null;
			return $this->objectExists();
		}

		protected function _getField($field, $validator = null, $cast = false)
		{
			if($this->objectExists())
			{
				$object = $this->_getObject();

				if($object !== false && ($validator === null || C\Tools::is($validator, $object[$field])))
				{
					switch($cast)
					{
						case 'int': {
							return (int) $object[$field];
						}
						case 'string': {
							return (string) $object[$field];
						}
						case 'bool': {
							return (bool) $object[$field];
						}
						default: {
							return $object[$field];
						}
					}
				}
			}

			return false;
		}

		protected static function _filterObjects($objects, $field, $value)
		{
			if(is_array($objects))
			{
				if($value !== null)
				{
					$results = array();
					$values = (array) $value;

					foreach($objects as $object)
					{
						if(in_array($object[$field], $values, true)) {
							$results[] = $object;
						}
					}

					return $results;
				}
				else {
					return $objects;
				}
			}
			else {
				return false;
			}
		}

		protected static function _searchObjects($objects, $field, $value, $strict = false)
		{
			if(is_array($objects))
			{
				if($value !== null)
				{
					$results = array();
					$value = preg_quote($value, '#');
					$value = str_replace('\\*', '.*', $value);
					$value = ($strict) ? ('^('.$value.')$') : ('^('.$value.')');

					foreach($objects as $object)
					{
						if(preg_match('#'.$value.'#i', $object[$field])) {
							$results[] = $object;
						}
					}

					return $results;
				}
				else {
					return $objects;
				}
			}
			else {
				return false;
			}
		}

		/**
		  * @return $this
		  */
		public function refresh()
		{
			$objectId = $this->_objectId;
			$this->_softReset(true);
			$this->_setObjectId($objectId);
			return $this;
		}

		/**
		  * @param bool $resetObjectId
		  * @return void
		  */
		protected function _softReset($resetObjectId = false)
		{
			if($resetObjectId) {
				$this->_objectId = null;
			}

			$this->_objectExists = null;
			$this->_objectLabel = null;
			$this->_objectDatas = null;
		}

		/**
		  * @return $this
		  */
		public function reset()
		{
			$objectId = $this->_objectId;
			$this->_hardReset(true);
			$this->_setObjectId($objectId);
			return $this;
		}

		/**
		  * @param bool $resetObjectId
		  * @return void
		  */
		abstract protected function _hardReset($resetObjectId = false);

		/**
		  * @return $this
		  */
		protected function _registerToStore()
		{
			if($this->objectExists)
			{
				$store = $this->_service->store;
				$objectId = $this->getObjectId();

				if($store !== false && $store->isReady(static::OBJECT_TYPE) && !isset($store[$objectId])) {
					$store->getContainer(static::OBJECT_TYPE)->assign($this);
				}
			}

			return $this;
		}

		/**
		  * @return $this
		  */
		protected function _unregisterFromStore()
		{
			if($this->objectExists)
			{
				$store = $this->_service->store;
				$objectId = $this->getObjectId();

				if($store !== false && $store->isReady(static::OBJECT_TYPE) && isset($store[$objectId])) {
					$store->getContainer(static::OBJECT_TYPE)->unassign($this);
				}
			}

			return $this;
		}

		public function hasErrorMessage()
		{
			return ($this->_errorMessage !== null);
		}

		public function getErrorMessage()
		{
			return $this->_errorMessage;
		}

		protected function _setErrorMessage($message)
		{
			$this->_errorMessage = $message;
		}

		protected function _resetErrorMessage()
		{
			return $this->_errorMessage = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'service': {
					return $this->_service;
				}
				case 'adapter': {
					return $this->_adapter;
				}
				case 'id': {
					return $this->getObjectId();
				}
				case 'name':
				case 'label': {
					return $this->getObjectLabel();
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}

		public function __call($name, array $arguments)
		{
			if(substr($name, 0, 3) === 'get')
			{
				$name = substr($name, 3);
				$name = mb_strtolower($name);

				switch($name)
				{
					case 'service': {
						return $this->_service;
					}
					case 'adapter': {
						return $this->_adapter;
					}
					case 'id': {
						return $this->getObjectId();
					}
					case 'name':
					case 'label': {
						return $this->getObjectLabel();
					}
				}
			}

			throw new Exception("This method '".$name."' does not exist", E_USER_ERROR);
		}

		public static function __callStatic($name, array $arguments)
		{
			if(substr($name, 0, 3) === 'get')
			{
				$name = substr($name, 3);
				$name = mb_strtolower($name);

				switch($name)
				{
					case 'service': {
						return static::_getService();
					}
					case 'adapter': {
						return static::_getAdapter();
					}
				}
			}

			throw new Exception("This method '".$name."' does not exist", E_USER_ERROR);
		}

		/**
		  * @return Orchestrator
		  */
		abstract protected static function _getOrchestrator();

		/**
		  * @return Service
		  */
		protected static function _getService()
		{
			$service = static::_getOrchestrator()->service;

			if($service instanceof Service)
			{
				$isReady = $service->initialization();

				if($isReady) {
					return $service;
				}
				else {
					throw new Exception("Addon service is not ready", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Addon service is not available", E_USER_ERROR);
			}
		}

		/**
		  * @return Adapter
		  */
		protected static function _getAdapter()
		{
			$service = static::_getService();
			return $service->adapter;
		}

		/**
		  * @param string $type
		  * @param Adapter $adapter
		  * @return false|array
		  */
		protected function _getThisCache($type, Adapter $adapter = null)
		{
			if($adapter !== null) {
				$service = $adapter->service;
			}
			else {
				$service = $this->_service;
			}

			return static::_getServiceCache($service, $type);
		}

		/**
		  * @param string $type
		  * @param Adapter $adapter
		  * @return false|array
		  */
		protected static function _getSelfCache($type, Adapter $adapter = null)
		{
			if($adapter !== null) {
				$service = $adapter->service;
			}
			else {
				$service = static::_getService();
			}

			return static::_getServiceCache($service, $type);
		}

		/**
		  * @param Service $service
		  * @param string $type
		  * @return false|array
		  */
		protected static function _getServiceCache(Service $service, $type)
		{
			$cache = $service->cache;

			if($cache !== false && $cache->isEnabled())
			{
				$container = $cache->getContainer($type);

				if($container !== false) {
					return $container->getAll();
				}
			}

			return false;
		}
	}