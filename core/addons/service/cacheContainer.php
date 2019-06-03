<?php
	namespace Core\Addon;

	abstract class Service_CacheContainer extends Service_Container
	{
		public function register($id, $object)
		{
			$this->_register($id, $object);
			return $this;
		}

		public function unregister($id)
		{
			$this->_unregister($id);
			return $this;
		}
	}