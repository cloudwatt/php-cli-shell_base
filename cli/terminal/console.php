<?php
	namespace Cli\Terminal;

	use Core as C;

	class Console
	{
		/**
		  * @var array
		  */
		protected $_commands = null;

		/**
		  * @var array
		  */
		protected $_manCommands = null;

		/**
		  * @var string
		  */
		protected $_helpMessage = null;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public function __construct(array $commands, array $manCommands = null)
		{
			$this->_commands = $commands;
			$this->_manCommands = $manCommands;
		}

		protected function _getConsoleSize()
		{
			/*$sttyCommand = 'stty -a';
			exec($sttyCommand, $outputs, $status);

			if($status === 0 && count($outputs) >= 1)
			{
				// Linux
				if(preg_match('#rows (?<rows>[0-9]+); columns (?<columns>[0-9]+);#i', $outputs[0], $matches)) {
					return $matches;
				}
				// MacOS
				elseif(preg_match('#(?<rows>[0-9]+) rows; (?<columns>[0-9]+) columns;#i', $outputs[0], $matches)) {
					return $matches;
				}
			}

			return false;*/

			$sttyCommand = 'stty size';
			exec($sttyCommand, $outputs, $status);

			if($status === 0 && count($outputs) >= 1)
			{
				if(preg_match('#^(?<rows>[0-9]+) (?<columns>[0-9]+)$#i', $outputs[0], $matches)) {
					return $matches;
				}
			}

			return false;
		}

		public function getRows()
		{
			$consoleSize = $this->_getConsoleSize();
			return ($consoleSize !== false) ? ($consoleSize['rows']) : (false);
		}

		public function getColumns()
		{
			$consoleSize = $this->_getConsoleSize();
			return ($consoleSize !== false) ? ($consoleSize['columns']) : (false);
		}

		public function getCommands()
		{
			return $this->_commands;
		}

		public function getManCmds()
		{
			return $this->_manCommands;
		}

		public function getHelpMsg()
		{
			if($this->_helpMessage === null) {
				$this->_helpMessage = $this->getManMsg($this->_commands);
			}

			return $this->_helpMessage;
		}

		public function getManMsg(array $cmds, $cmdPrefix = null)
		{
			if($this->_manCommands !== null)
			{
				$manMsgs = array();
				$cmdPrefix = trim($cmdPrefix, ' ');

				foreach($cmds as $cmdKey => $cmdValue)
				{				
					$cmd = (C\Tools::is('array', $cmdValue)) ? ($cmdKey) : ($cmdValue);

					// /!\ Shell "> ?" va transmettre cmdPrefix vide
					if(C\Tools::is('string&&!empty', $cmdPrefix)) {
						$cmd = $cmdPrefix.' '.$cmd;
					}
						
					if(array_key_exists($cmd, $this->_manCommands)) {
						$manMsgs[] = $cmd.": ".$this->_manCommands[$cmd];
					}
				}

				return implode(PHP_EOL, $manMsgs);
			}
			else {
				return "";
			}
		}

		public function getManCmd($cmd)
		{
			$cmd = trim($cmd, ' ');

			if($this->_manCommands !== null && array_key_exists($cmd, $this->_manCommands)) {
				return $cmd.": ".$this->_manCommands[$cmd];
			}
			else {
				return "";
			}
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}

		public static function prepareTTY()
		{
			$command = 'stty -g';					// -g identique à --save
			//$command = 'stty --save';				// Non dispo sous MacOS
			exec($command, $outputs, $status);

			if(count($outputs) !== 1 || $status !== 0) {
				throw new Exception("Unable to execute stty command: ".$command, E_USER_ERROR);
			}

			//$sttySettings = current($outputs);
			shell_exec('stty -icanon -echo min 1 time 0');
		}

		public static function restoreTTY()
		{
			//$command = 'stty "'.$sttySettings.'"';
			$command = 'stty sane';
			//$command = 'reset';

			exec($command, $outputs, $status);

			if($status !== 0) {
				throw new Exception("Unable to execute stty command: ".$command, E_USER_ERROR);
			}
		}
	}