<?php
	namespace Core\Addon;

	abstract class Service_StoreContainer extends Service_Container
	{
		public function assign(Api_Abstract $object)
		{
			return $this->register($object->id, $object);
		}

		public function unassign(Api_Abstract $object)
		{
			return $this->unregister($object->id);
		}

		public function register($id, Api_Abstract $object)
		{
			if($object->service->id !== $this->_service->id) {
				throw new Exception(ucfirst($object::OBJECT_NAME)." object service does not match Store container service", E_USER_ERROR);
			}

			$this->_register($id, $object);
			return $this;
		}

		public function unregister($id)
		{
			$this->_unregister($id);
			return $this;
		}

		public function get($id)
		{
			$object = $this->retrieve($id);

			if($object !== false) {
				return $object;
			}
			else
			{
				$object = $this->_new($id);

				if($object !== false) {
					$this->assign($object);
				}

				return $object;
			}
		}

		abstract protected function _new($id);

		protected function retrieveFromCache($id)
		{
			$cache = $this->_service->cache;

			if($cache !== false) {
				$container = $cache->getContainer($this->_type);
				return $container->retrieve($id);
			}
			else {
				return false;
			}
		}

		protected function getFromCache($id)
		{
			return $this->retrieveFromCache($id);
		}
	}