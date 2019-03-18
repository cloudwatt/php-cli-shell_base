<?php
	namespace Core\Addon;

	interface Api_Interface
	{
		public static function getObjectType();
		public static function objectIdIsValid($objectId);

		public function hasObjectId();
		public function getObjectId();
		public function objectExists();
		public function hasObjectLabel();
		public function getObjectLabel();
	}