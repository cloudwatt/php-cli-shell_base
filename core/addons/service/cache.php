<?php
	namespace Core\Addon;

	use Core as C;

	abstract class Service_Cache extends Service_Plugin
	{
		const PLUGIN_TYPE = 'cache';
		const PLUGIN_NAME = 'cache';


		/**
		  * @param string $type
		  * @return bool
		  */
		public function refresh($type)
		{
			if($this->isEnabled()) {
				return $this->_refresh($type);
			}

			return false;
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		abstract protected function _refresh($type);
	}