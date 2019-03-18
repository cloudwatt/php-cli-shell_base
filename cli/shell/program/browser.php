<?php
	namespace Cli\Shell\Program;

	abstract class Browser extends Program
	{
		/**
		  * @var array
		  */
		protected $_pathIds;

		/**
		  * @var array
		  */
		protected $_pathApi;


		public function updatePath(array $pathIds, array $pathApi)
		{
			$this->_pathIds = $pathIds;
			$this->_pathApi = $pathApi;
			return $this;
		}

		protected function _browser($path = null, $returnCurrentApi = true)
		{
			$pathIds = $this->_pathIds;
			$pathApi = $this->_pathApi;

			if($path !== null) {
				// /!\ browser modifie pathIds et pathApi, passage par référence
				$this->_SHELL->browser($pathIds, $pathApi, $path);
			}

			return ($returnCurrentApi) ? (end($pathApi)) : ($pathApi);
		}

		public function getOptions($path = null)
		{
			$options = array();
			$objects = $this->_getObjects($path);

			foreach($objects as $type => $list)
			{
				if(count($list) > 0)
				{
					foreach($list as $fields)
					{
						if(array_key_exists($type, $this->_OPTION_FIELDS)) {
							$optFields = array_flip($this->_OPTION_FIELDS[$type]['fields']);
							$option = array_intersect_key($fields, $optFields);
							$options = array_merge($options, array_values($option));
						}
					}
				}
			}

			return $options;
		}

		/**
		  * /!\ Do not return reference!
		  *
		  * @return array
		  */
		protected function _getPathApi()
		{
			return $this->_pathApi;
		}

		/**
		  * @return mixed Addon API
		  */
		protected function _getRootPathApi()
		{
			/*reset($this->_pathApi);
			return current($this->_pathApi);*/
			return $this->_pathApi[0];
		}

		/**
		  * @return mixed Addon API
		  */
		protected function _getCurrentPathApi()
		{
			return end($this->_pathApi);
		}

		/**
		  * @param false|string API class name
		  * @return false|mixed Addon API
		  */
		protected function _getLastPathApi($apiClassName = false)
		{
			return $this->_searchLastPathApi($this->_pathApi, $apiClassName);
		}

		/**
		  * @param array Addon API array
		  * @param false|string API class name
		  * @return false|mixed Addon API
		  */
		protected function _searchLastPathApi(array $pathApi, $apiClassName = false)
		{
			if($apiClassName === false) {
				return end($pathApi);
			}
			else	
			{
				$pathApi = array_reverse($pathApi);

				foreach($pathApi as $api)
				{
					if(get_class($api) === $apiClassName) {
						return $api;
					}
				}

				return false;
			}
		}
	}