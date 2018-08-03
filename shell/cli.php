<?php
	class Shell_Cli
	{
		protected $_commands = null;
		protected $_manCommands = null;
		protected $_helpMessage = null;

		protected $_debug = false;


		public function __construct(array $commands, array $manCommands = null)
		{
			$this->_commands = $commands;
			$this->_manCommands = $manCommands;
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
					$cmd = (Tools::is('array', $cmdValue)) ? ($cmdKey) : ($cmdValue);

					// /!\ Shell "> ?" va transmettre cmdPrefix vide
					if(Tools::is('string&&!empty', $cmdPrefix)) {
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
	}