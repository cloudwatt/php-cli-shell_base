<?php
	namespace Cli\Shell;

	use Closure;

	use Core as C;
	use Core\Exception as E;

	use Cli;
	use Cli\Terminal;

	abstract class Shell extends Main
	{
		const CLI_OPTION_DELIMITER = ';';

		/**
		  * @var Cli\Terminal\Main
		  */
		protected $_TERMINAL;

		/**
		  * @var Cli\Shell\Program\Main
		  */
		protected $_PROGRAM;

		/**
		  * @var Cli\Results
		  */
		protected $_RESULTS;

		/**
		  * @var array 
		  */
		protected $_commands = array();

		/**
		  * Arguments ne commencant pas par - mais étant dans le flow de la commande
		  *
		  * ls mon/chemin/a/lister
		  * cd mon/chemin/ou/aller
		  * find ou/lancer/ma/recherche
		  *
		  * @var array
		  */
		protected $_inlineArgCmds = array();

		/**
		  * Arguments commencant pas par - ou -- donc hors flow de la commande
		  *
		  * find ... -type [type] -name [name]
		  *
		  * @var array
		  */
		protected $_outlineArgCmds = array();

		/**
		  * /!\ Ordre important
		  *
		  * L'ordre des commandes ci-dessous sera respecté
		  * afin que la configuration soit valide
		  *
		  * @var array
		  */
		protected $_cliOptions = array();

		/**
		  * @var array
		  */
		protected $_cliToCmd = array();

		/**
		  * @var array
		  */
		protected $_manCommands = array();

		/**
		  * @var bool
		  */
		protected $_waitingMsgFeature = true;

		/**
		  * @var bool
		  */
		protected $_waitingMsgState = false;

		/**
		  * @var bool
		  */
		protected $_isOneShotCall = null;

		/**
		  * @var bool
		  */
		protected $_shellDebug = false;

		/**
		  * @var bool
		  */
		protected $_terminalDebug = false;


		public function __construct($configFilename)
		{
			/**
			  * Tant que l'on ne connait pas le mode on bufferise
			  * Mode: Normal, OneShotCall, Test
			  */
			ob_start();

			/**
			  * Déplace le curseur d'une ligne vers le haut
			  * Fix le saut de ligne lors de la touche entrée pour lancer le script CLI
			  *
			  * Permet d'harmoniser le traitement des sauts de lignes:
			  * --> Saut de ligne avant un texte et non après!
			  */
			if(!$this->isOneShotCall()) {
				echo "\033[1A";
			}

			parent::__construct($configFilename);

			$this->_TERMINAL = new Terminal\Terminal($this->_commands, $this->_inlineArgCmds, $this->_outlineArgCmds, $this->_manCommands);
			$this->_TERMINAL->debug($this->_terminalDebug)->setHistoryFilename(static::SHELL_HISTORY_FILENAME);

			$this->_RESULTS = new Cli\Results();
		}

		protected function _initDebug()
		{
			parent::_initDebug();

			$debug = getenv('PHPCLI_SHELL_DEBUG');
			$this->_shellDebug = (mb_strtolower($debug) === "true" || $debug === 1);

			$debug = getenv('PHPCLI_TERMINAL_DEBUG');
			$this->_terminalDebug = (mb_strtolower($debug) === "true" || $debug === 1);
		}

		protected function _init()
		{
			$this->_oneShotCall();

			/**
			  * Mode normal, on flush le buffer puis on le désactive
			  * Test si buffer actif afin de permettre plus de flexibilité
			  */
			if(ob_get_level() > 0) {
				ob_end_flush();
			}

			$this->_preLauchingShell();
			$this->_launchShell();
			$this->_postLauchingShell();

			return $this;
		}

		protected function _initOneShotCall()
		{
			$this->waitingMsgFeature(false);
			$this->_preLauchingShell(false);

			return $this;
		}

		public function test($cmd, array $args = array())
		{
			/**
			  * Mode test, on flush et on désactive le buffer
			  */
			if(ob_get_level() > 0) {
				ob_end_flush();
			}

			$this->EOL();

			$exit = $this->dispatchCmdCall($cmd, $args);

			$Cli_Results = $this->_RESULTS;
			$this->_RESULTS = new Cli\Results();
			return $Cli_Results;
		}

		public function isOneShotCall()
		{
			if($this->_isOneShotCall === null) {
				$this->_isOneShotCall = ($_SERVER['argc'] > 1);
			}

			return $this->_isOneShotCall;
		}

		protected function _oneShotCall()
		{
			if($this->isOneShotCall())
			{
				$this->_initOneShotCall();

				if($_SERVER['argc'] === 2)
				{
					$cmd = $_SERVER['argv'][1];
					$status = $this->_routeCliCmdCall($cmd);

					if($status) {
						$results = $this->_RESULTS->results;
						ob_end_clean();							// Mode OneShotCall (json), on nettoie et on désactive le buffer
						echo json_encode($results);
						$exitCode = 0;
					}
					else {
						$this->error("Commande invalide", 'red', false, 'bold');
						$this->_TERMINAL->help();
						$buffer = ob_get_clean();				// Mode OneShotCall (error), on récupère et on désactive le buffer
						echo json_encode(array('error' => $buffer));
						$exitCode = 1;
					}
				}
				else {
					ob_end_flush();								// Mode OneShotCall (sript), on flush et on désactive le buffer
					$exitCode = $this->_dispatchCliCall();
				}

				$this->_postLauchingShell(false);
				exit($exitCode);
			}
		}

		protected function _routeCliCmdCall($cmd)
		{
			$Autocompletion = new Terminal\Autocompletion($this->_commands, $this->_inlineArgCmds, $this->_outlineArgCmds, $this->_manCommands);
			$Autocompletion->debug($this->_terminalDebug);

			$status = $Autocompletion->_($cmd);

			if($status)
			{
				$cmd = $Autocompletion->command;
				$args = $Autocompletion->arguments;

				$exit = $this->dispatchCmdCall($cmd, $args);

				return (
					$this->_RESULTS->isTrue() ||
					$this->_RESULTS->isNull()
				);
			}
			else {
				return false;
			}
		}

		protected function _dispatchCliCall()
		{
			$this->_isOneShotCall = false;
			$this->waitingMsgFeature(true);

			$options = getopt($this->_cliOptions['short'], $this->_cliOptions['long']);

			// Permet de garantir l'ordre d'exécution des commandes
			foreach($this->_cliOptions['long'] as $cli)
			{
				$cli = str_replace(':', '', $cli);

				if(isset($options[$cli]))
				{
					$option = (array) $options[$cli];

					foreach($option as $_option)
					{
						$status = $this->_cliOptToCmdArg($cli, $_option);

						if(!$status) {
							return 1;
						}
					}
				}
			}

			return 0;
		}

		protected function _cliOptToCmdArg($cli, $option)
		{
			return false;
		}

		protected function _preLauchingShell($welcomeMessage = true)
		{
			if($welcomeMessage) {
				$this->EOL();
				$this->print("CTRL+C ferme le shell, utilisez ALT+C à la place", 'blue', false, 'italic');
				$this->print("Utilisez UP et DOWN afin de parcourir votre historique de commandes", 'blue', false, 'italic');
				$this->print("Utilisez TAB pour l'autocomplétion et ? afin d'obtenir davantage d'informations", 'blue', false, 'italic');
				$this->EOL();
			}
		}

		protected function _postLauchingShell($goodbyeMessage = true)
		{
			if($goodbyeMessage) {
				$this->EOL();
				$this->print("Merci d'avoir utilisé TOOLS-CLI by NOC", 'blue', false, 'italic');
				$this->EOL();
			}
		}

		protected function _launchShell()
		{
			$exit = false;

			while(!$exit) {
				$dispatchCmdCall = Closure::fromCallable(array($this, 'dispatchCmdCall'));
				$exit = $this->_TERMINAL->launch($dispatchCmdCall);
			}
		}

		public function executeCmdCall($cmd)
		{
			return $this->_routeCliCmdCall($cmd);
		}

		public function dispatchCmdCall($cmd, array $args)
		{
			$this->_preRoutingShellCmd($cmd, $args);
			$exit = $this->_routeShellCmd($cmd, $args);
			$this->_postRoutingShellCmd($cmd, $args);
			return $exit;
		}

		protected function _preRoutingShellCmd(&$cmd, array &$args)
		{
			foreach($args as &$arg) {
				$arg = preg_replace('#^("|\')|("|\')$#i', '', $arg);
			}
			unset($arg);

			$this->displayWaitingMsg(false, false);
		}

		protected function _routeShellCmd($cmd, array $args)
		{
			switch($cmd)
			{
				case '': {
					$this->print("Tape help for help !", 'blue');
					break;
				}
				case 'history': {
					$this->deleteWaitingMsg();
					$this->_TERMINAL->history();
					$this->EOL();
					break;
				}
				case 'help': {
					$this->deleteWaitingMsg();
					$this->_TERMINAL->help();
					$this->EOL();
					break;
				}
				case 'exit':
				case 'quit': {
					$this->deleteWaitingMsg();
					return true;
				}
				default: {
					$this->error("Commande inconnue... [".$cmd."]", 'red');
					$this->_RESULTS->status(false);
				}
			}

			if(isset($status)) {
				$this->_routeShellStatus($cmd, $status);
			}

			return false;
		}

		protected function _routeShellStatus($cmd, $status)
		{
			$this->_RESULTS->status($status);

			if(!$status && !$this->isOneShotCall())
			{
				if(array_key_exists($cmd, $this->_manCommands)) {
					$this->error($this->_manCommands[$cmd], 'red');
				}
				else {
					$this->error("Une erreur s'est produite lors de l'exécution de cette commande", 'red');
				}
			}

			return $this;
		}

		protected function _postRoutingShellCmd($cmd, array $args) {}

		public function displayWaitingMsg($startEOL = true, $finishEOL = false, $infos = null)
		{
			if($this->waitingMsgFeature() && !$this->_waitingMsgState)
			{
				/**
				  * /!\ Ne pas inclure les sauts de lignes dans le traitement de la police
				  * $infos ne doit pas contenir de saut de lignes sinon la desactivation ne fonctionnera pas complètement
				  */
				$message = ($startEOL) ? (PHP_EOL) : ('');

				$message .= C\Tools::e("Veuillez patienter ...", 'orange', false, 'bold', true);
				if($infos !== null) { $message .= C\Tools::e(' ('.$infos.')', 'orange', false, 'bold', true); }

				if($finishEOL) { $message .= PHP_EOL; }
				$this->_TERMINAL->insertMessage($message);
				$this->_waitingMsgState = true;
				return true;
			}
			else {
				return false;
			}
		}

		public function deleteWaitingMsg($lineUP = true)
		{
			if($this->waitingMsgFeature() && $this->_waitingMsgState) {
				$this->_TERMINAL->deleteMessage(1, $lineUP);
				$this->_waitingMsgState = false;
				return true;
			}
			else {
				return false;
			}
		}

		public function waitingMsgFeature($status = null)
		{
			if($status === true || $status === false) {
				$this->_waitingMsgFeature = $status;
			}
			return $this->_waitingMsgFeature;
		}

		protected function _e($text, $textColor = false, $bgColor = false, $textStyle = false, $doNotPrint = false)
		{
			return ($this->isOneShotCall()) ? ($text) : (C\Tools::e($text, $textColor, $bgColor, $textStyle, $doNotPrint));
		}

		public function format($text, $textColor = 'green', $bgColor = false, $textStyle = false)
		{
			return $this->_e($text, $textColor, $bgColor, $textStyle, true);
		}

		/**
		  * @param int $multiplier
		  * @param string $textColor
		  * @param string $bgColor
		  * @param string $textStyle
		  * @param bool $autoDelWaitingMsg
		  * @return $this
		  */
		public function EOL($multiplier = 1, $textColor = false, $bgColor = false, $textStyle = false, $autoDelWaitingMsg = true)
		{
			if($autoDelWaitingMsg) {
				$this->deleteWaitingMsg();
			}

			$this->_e(str_repeat(PHP_EOL, $multiplier), $textColor, $bgColor, $textStyle, false);
			return $this;	// /!\ Important
		}

		/**
		  * @param string $text
		  * @param string $textColor
		  * @param string $bgColor
		  * @param string $textStyle
		  * @param bool $autoDelWaitingMsg
		  * @return $this
		  */
		public function echo($text, $textColor = 'green', $bgColor = false, $textStyle = false, $autoDelWaitingMsg = true)
		{
			if($autoDelWaitingMsg) {
				$this->deleteWaitingMsg();
			}

			$this->_e($text, $textColor, $bgColor, $textStyle, false);
			return $this;	// /!\ Important
		}

		/**
		  * @param string $text
		  * @param string $textColor
		  * @param string $bgColor
		  * @param string $textStyle
		  * @param bool $autoDelWaitingMsg
		  * @return $this
		  */
		public function print($text, $textColor = 'green', $bgColor = false, $textStyle = false, $autoDelWaitingMsg = true)
		{
			if($autoDelWaitingMsg) {
				$this->deleteWaitingMsg();
			}

			/** 
			  * /!\ Ne doit pas être formaté comme le texte
			  * /!\ Ne pas supprimer le message d'attente:
			  * - Déjà traité dans cette méthode
			  * - Si $autoDelWaitingMsg === false
			  */
			$this->EOL(1, false, false, false, false);
			$this->_e($text, $textColor, $bgColor, $textStyle, false);
			return $this;	// /!\ Important
		}

		/**
		  * @param string $text
		  * @param string $textColor
		  * @param string $bgColor
		  * @param string $textStyle
		  * @param bool $autoDelWaitingMsg
		  * @return $this
		  */
		public function debug($text, $textColor = 'black', $bgColor = 'white', $textStyle = false, $autoDelWaitingMsg = true)
		{
			return $this->print($text, $textColor, $bgColor, $textStyle, $autoDelWaitingMsg);
		}

		/**
		  * @param string|Exception $text
		  * @param string $textColor
		  * @param string $bgColor
		  * @param string $textStyle
		  * @param bool $autoDelWaitingMsg
		  * @return $this
		  */
		public function error($text, $textColor = 'red', $bgColor = false, $textStyle = false, $autoDelWaitingMsg = true)
		{
			if($autoDelWaitingMsg) {
				$this->deleteWaitingMsg();
			}

			/** 
			  * /!\ Ne doit pas être formaté comme le texte
			  * /!\ Ne pas supprimer le message d'attente:
			  * - Déjà traité dans cette méthode
			  * - Si $autoDelWaitingMsg === false
			  */
			$this->EOL(1, false, false, false, false);

			if($text instanceof \Exception) {
				$this->throw($text, false, false);
			}
			else {
				$this->_e($text, $textColor, $bgColor, $textStyle, false);
			}

			return $this;	// /!\ Important
		}

		/**
		  * @param Exception $exception
		  * @param bool $throwUnknownException
		  * @param bool $exitAfterProcess
		  * @return $this
		  */
		public function throw(\Exception $exception, $throwUnknownException = true, $exitAfterProcess = false)
		{
			if($exception instanceof E\Message)
			{
				$codeColors = array(
					E_USER_ERROR => 'red',
					E_USER_WARNING => 'orange',
					E_USER_NOTICE => 'blue',
				);
			}
			else
			{
				$codeColors = array(
					E_USER_ERROR => 'red',
					E_USER_WARNING => 'red',
					E_USER_NOTICE => 'orange',
				);
			}

			$eCode = $exception->getCode();
			$eMessage = $exception->getMessage();

			switch($exception->getCode())
			{
				case E_USER_ERROR:
				case E_USER_WARNING:
				case E_USER_NOTICE: {
					$this->error($eMessage, $codeColors[$eCode]);
					break;
				}
				default:
				{
					if($throwUnknownException) {
						throw $exception;
					}
					else {
						$this->error($eMessage, 'red');
					}
				}
			}

			if($exitAfterProcess) {
				exit;
			}

			return $this;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'terminal': {
					return $this->_TERMINAL;
				}
				case 'results': {
					return $this->_RESULTS;
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}