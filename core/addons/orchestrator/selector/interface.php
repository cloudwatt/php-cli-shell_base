<?php
	namespace Core\Addon;

	interface Orchestrator_Selector_Interface
	{
		/**
		  * @param array $selectors A set of regex with service ID as key
		  * @return Core\Addon\Orchestrator_Selector_Interface
		  */
		public function __construct(array $selectors = array());

		/**
		  * @param array $selectors A set of regex with service ID as key
		  * @return bool
		  */
		public function assign(array $selectors);

		/**
		  * @param string $serviceId Service ID
		  * @param string $selector Regex
		  * @return bool
		  */
		public function append($serviceId, $selector);

		/**
		  * @param mixed $attribute
		  * @return false|string Service ID
		  */
		public function match($attribute);

		/**
		  * @param string $serviceId Service ID
		  * @return bool
		  */
		public function reset($serviceId);

		/**
		  * @param string $serviceId Service ID
		  * @return bool
		  */
		public function erase($serviceId);

		/**
		  * @return array
		  */
		public function keys();

		/**
		  * @param mixed $key
		  * @return bool
		  */
		public function key_exists($key);

		/**
		  * @param mixed $name
		  * @return bool
		  */
		public function __isset($name);

		/**
		  * @param mixed $name
		  * @return void
		  */
		public function __unset($name);
	}