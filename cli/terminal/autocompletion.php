<?php
	namespace Cli\Terminal;

	use Core as C;

	class Autocompletion
	{
		/**
		  * @var Cli\Terminal\Autocompletion_Commands
		  */
		protected $_cliAcCommands = null;

		/**
		  * @var Cli\Terminal\Autocompletion_Arguments
		  */
		protected $_cliAcArguments = null;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public function __construct(array $defCommands, array $defInlineArgs = array(), array $defOutlineArgs = array())
		{
			$this->_cliAcCommands = new Autocompletion_Commands($defCommands);
			$this->_cliAcArguments = new Autocompletion_Arguments($defInlineArgs, $defOutlineArgs);

			$this->_cliAcCommands->declareArguments($this->_cliAcArguments);
		}

		public function setInlineArg($name, $value)
		{
			$this->_cliAcArguments->setInlineArg($name, $value);
			return $this;
		}

		/**
		  * acWithOption: autocomplete (AC) with the option when there is only one option returned
		  * acWithSpace: autocomplete (AC) with space at the end of the command for some requirements
		  */
		public function _($cmd, $acWithOption = true, $acWithSpace = true)
		{
			try {
				$status = $this->_cliAcCommands->_($cmd, $acWithOption, $acWithSpace);
			}
			catch(Exception $e) {
				if($this->_debug) { throw $e; }
				$status = false;
			}

			if($this->_debug) {
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {main} '.__LINE__.' [SYS] {command status}:'.PHP_EOL.(int) $status, 'orange');
			}

			/**
			  * On ne doit pas autoriser l'ajout d'arguments tant que la commande n'est pas indiquée complète
			  * Donc si et seulement si on arrive sur une commande finale, alors on doit traiter les arguments
			  *
			  * ls mon/chemin/a/lister
			  * cd mon/chemin/ou/aller
			  * find ou/lancer/ma/recherche
			  */
			if($status && $this->_cliAcCommands->cmdIsComplete)
			{
				/**
				  * On ne traite les arguments si et seulement si on n'a pas de choix multiples sur la commande
				  * Ceci est un test de sécurité, mais normalement on ne devrait jamais avoir d'exception pour ce cas
				  */
				if(count($this->_cliAcCommands->options) > 0) {
					throw new Exception("Command is available and complete but there is many options/choices", E_USER_ERROR);
				}

				try {
					$status = $this->_cliAcArguments->_($cmd, $acWithOption, $acWithSpace);
				}
				catch(Exception $e) {
					if($this->_debug) { throw $e; }
					$status = false;
				}
			}
			else
			{
				/**
				  * /!\ Important, dans le cas où on ne traiterait pas les arguments, on doit forcer le reset
				  * Ceci permet d'éviter de garder un ancien traitement d'arguments lors d'une précédente commande
				  */
				$this->_cliAcArguments->reset();
			}

			if($this->_debug) {
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {main} '.__LINE__.' [SYS] {arguments status}:'.PHP_EOL.(int) $status, 'orange');
			}

			return $status;
		}

		public function getCommand()
		{
			return $this->_cliAcCommands->command;
		}

		public function getArguments()
		{
			return $this->_cliAcArguments->arguments;
		}

		public function getCall()
		{
			return (string) $this;
		}

		public function getOptions()
		{
			if(!$this->_cliAcCommands->cmdIsComplete) {
				return $this->_cliAcCommands->options;
			}
			else {
				return $this->_cliAcArguments->options;
			}
		}

		public function getStatus()
		{
			$cmdStatus = $this->_cliAcCommands->status;
			$argsStatus = $this->_cliAcArguments->status;
			return ($cmdStatus && $argsStatus);
		}

		public function __toString()
		{
			$command = (string) $this->_cliAcCommands;
			$arguments = (string) $this->_cliAcArguments;

			if($this->_cliAcCommands->cmdIsComplete && $command !== '' && $arguments !== '') {
				$command = rtrim($command, ' ').' '.$arguments;
			}

			return $command;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'cmd':
				case 'command':
					return $this->getCommand();
				case 'args':
				case 'arguments':
					return $this->getArguments();
				case 'call':
					return $this->getCall();
				case 'opts':
				case 'options':
					return $this->getOptions();
				case 'status':
					return $this->getStatus();
				case 'cmdStatus':
					return $this->_cliAcCommands->status;
				case 'argsStatus':
					return $this->_cliAcArguments->status;
				case 'cmdIsIncomplete':
					return $this->_cliAcCommands->cmdIsIncomplete;
				case 'cmdIsAvailable':
					return $this->_cliAcCommands->cmdIsAvailable;
				case 'cmdIsComplete':
					return $this->_cliAcCommands->cmdIsComplete;
				case 'acCommands':
					return $this->_cliAcCommands;
				case 'acArguments':
					return $this->_cliAcArguments;
				default:
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
			}
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			$this->_cliAcCommands->debug($debug);
			$this->_cliAcArguments->debug($debug);
			return $this;
		}
	}