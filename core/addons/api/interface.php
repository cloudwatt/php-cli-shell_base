<?php
	namespace Core\Addon;

	interface Api_Interface
	{
		/**
		  * @return string
		  */
		public static function getObjectType();

		/**
		  * @param mixed $objectId
		  * @return bool
		  */
		public static function objectIdIsValid($objectId);

		/**
		  * @return bool
		  */
		public function hasObjectId();

		/**
		  * @return mixed
		  */
		public function getObjectId();

		/**
		  * @return bool
		  */
		public function objectExists();

		/**
		  * @return bool
		  */
		public function hasObjectLabel();

		/**
		  * @return string
		  */
		public function getObjectLabel();

		/**
		  * @param mixed $name
		  * @return mixed
		  */
		public function __get($name);
	}