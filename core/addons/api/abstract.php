<?php
	namespace Core\Addon;

	use ReflectionClass;

	use Core as C;

	abstract class Api_Abstract implements Api_Interface
	{
		const WILDCARD = '*';

		/**
		  * @var Core\Api\Adapter
		  */
		protected static $_adapter = null;				// Global adapter (enabled)

		/**
		  * @var Core\Api\Adapter[]
		  */
		protected static $_allAdapters = array();		// a = all/array/available adapter

		/**
		  * @var Core\Api\Adapter
		  */
		protected $_ownerAdapter = null;				// Local adapter (for this instance)

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


		public function __construct($objectId = null)
		{
			/**
			  * Permet de garder la référence de l'adapter
			  * actuellement activé pour cette instance d'Api_Main
			  */
			$this->_ownerAdapter = self::$_adapter;

			// @todo temp
			//$this->_setObjectId($objectId);
		}

		public static function factoryFromDatas(array $datas)
		{
			$className = static::class;
			$instanceObject = new $className();
			$status = $instanceObject->_setObject($datas);
			return ($status) ? ($instanceObject) : (false);
		}

		public static function getObjectType()
		{
			return static::OBJECT_TYPE;
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

		protected function _setObject(array $datas)
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

		/**
		  * @param string|Core\Api\Adapter $adapter
		  * @return bool
		  */
		protected static function _isValidAdapter($adapter)
		{
			$ReflectionClass = new ReflectionClass($adapter);
			return $ReflectionClass->isSubclassOf(static::$_parentAdapter);
		}

		/**
		  * @param Core\Api\Adapter|Core\Api\Adapter[] $adapter
		  * @throw Core\Exception
		  * @return bool
		  */
		public static function setAdapter($adapter)
		{
			if(!is_array($adapter)) {
				$adapters = array($adapter);
			}
			else {
				$adapters = $adapter;
			}

			if(C\Tools::is('array&&count>0', $adapters))
			{
				foreach($adapters as $adapter)
				{
					if(!static::_isValidAdapter($adapter)) {
						throw new Exception("Unable to set adapter object(s), it is not '".static::$_parentAdapter."' instance or an array of it", E_USER_ERROR);
					}
					elseif(!($adapter instanceof Adapter)) {
						throw new Exception("Unable to set adapter object(s), it is not Core\Api\Adapter instance or an array of it", E_USER_ERROR);
					}
				}

				self::$_adapter = current($adapters);

				if(count($adapters) > 1) {
					self::$_allAdapters = $adapters;
				}

				return true;
			}

			return false;
		}

		/**
		  * @return null|Core\Api\Adapter|Core\Api\Adapter[]
		  */
		public static function getAdapter()
		{
			return (count(self::$_allAdapters) > 0) ? (self::$_allAdapters) : (self::$_adapter);
		}

		/**
		  * @param string $key
		  * @return bool
		  */
		public static function enableAdapter($key)
		{
			if(array_key_exists($key, self::$_allAdapters)) {
				self::$_adapter = self::$_allAdapters[$key];
				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * @return null|Core\Api\Adapter
		  */
		public static function getAdapterEnabled()
		{
			return self::$_adapter->getServerId();
		}

		/**
		  * @param bool $state Turn on or off cache feature
		  * @param Core\Api\Adapter|Core\Api\Adapter[] $adapter Adapter or an array of it
		  * @return bool
		  */
		public static function cache($state = null, $adapter = null)
		{
			if(C\Tools::is('bool', $state))
			{
				if($adapter === null) {
					$adapter = self::getAdapter();
				}

				if($adapter instanceof Adapter) {
					$adapter = array($adapter);
				}

				if(is_array($adapter))
				{
					foreach($adapter as $_adapter)
					{
						$id = $_adapter->getServerId();
						static::$_cache[$id] = $state;

						if(!static::$_cache[$id]) {
							static::_cacheCleaner($_adapter);
						}
					}

					return true;
				}
			}

			return false;
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return bool
		  */
		public static function cacheEnabled(Adapter $adapter = null)
		{
			if($adapter === null) {
				$adapter = self::$_adapter;
			}

			$id = $adapter->getServerId();

			return (array_key_exists($id, static::$_cache)) ? ((static::$_cache[$id] === true)) : (false);
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return bool
		  */
		public static function cacheDisabled(Adapter $adapter = null)
		{
			if($adapter === null) {
				$adapter = self::$_adapter;
			}

			$id = $adapter->getServerId();

			return (array_key_exists($id, static::$_cache)) ? ((static::$_cache[$id] === false)) : (false);
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return bool
		  */
		public static function refreshCache(Adapter $adapter = null)
		{
			if(static::cacheEnabled($adapter)) {
				static::_cacheCleaner($adapter);
				return static::_setObjects($adapter);
			}
			else {
				return false;
			}
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return void
		  */
		protected static function _cacheCleaner(Adapter $adapter = null)
		{
			if($adapter === null) {
				$adapter = self::$_adapter;
			}

			$id = $adapter->getServerId();
			static::$_objects[$id] = array();
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return array
		  */
		protected static function _getObjects(Adapter $adapter = null)
		{
			if($adapter === null) {
				$adapter = self::$_adapter;
			}

			$id = $adapter->getServerId();

			if(!array_key_exists($id, static::$_objects)) {
				static::_setObjects($adapter);
			}

			return static::$_objects[$id];
		}

		/**
		  * @param Core\Api\Adapter $adapter
		  * @return bool
		  */
		protected static function _setObjects(Adapter $adapter = null)
		{
			if($adapter === null) {
				$adapter = self::$_adapter;
			}

			$id = $adapter->getServerId();

			static::$_objects[$id] = array();
			return false;
		}
	}