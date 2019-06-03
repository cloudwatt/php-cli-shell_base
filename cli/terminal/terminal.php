<?php
	namespace Cli\Terminal;

	use Closure;

	use Core as C;

	class Terminal extends Main
	{
		const SHELL_PROMPT = ">";
		const SHELL_MODE_SEARCH = 'search';
		const SHELL_PROMPT_MODES = array(
				self::SHELL_MODE_SEARCH => 'search: '
		);
		const SHELL_SPACER = "     ";

		/**
		  * @var Cli\Terminal\History
		  */
		protected $_history;

		/**
		  * @var Cli\Terminal\Console
		  */
		protected $_console;

		/**
		  * @var Cli\Terminal\Autocompletion
		  */
		protected $_autocompletion;

		/**
		  * @var string
		  */
		protected $_shPromptBck = "";

		/**
		  * @var string
		  */
		protected $_shMode = false;

		/**
		  * @var string
		  */
		protected $_userCmd = "";		// /!\ A utiliser pour remplacer &$cmd

		/**
		  * @var string
		  */
		protected $_modeCmd = "";		// /!\ Peut servir pour tous les modes


		public function __construct(array $commands, array $inlineArgs = array(), array $outlineArgs = array(), array $manCommands = null)
		{
			$this->_history = new History();
			$this->_console = new Console($commands, $manCommands);
			$this->_autocompletion = new Autocompletion($commands, $inlineArgs, $outlineArgs);

			// Initialise le prompt
			$this->setShellPrompt();
		}

		public function setInlineArg($name, $value)
		{
			$this->_autocompletion->setInlineArg($name, $value);
			return $this;
		}

		public function getHistoryFilename()
		{
			return $this->_history->getFilename();
		}

		public function setHistoryFilename($filename)
		{
			$this->_history->setFilename($filename);
			return $this;
		}

		/**
		  * Gets shell prompt
		  *
		  * @return string Shell prompt
		  */
		public function getShellPrompt()
		{
			return $this->_shPromptBck;
		}

		/**
		  * Sets shell prompt
		  *
		  * @param string $prompt Shell prompt
		  * @return $this
		  */
		public function setShellPrompt($prompt = '')
		{
			$prompt = trim($prompt, ' ');

			if(C\Tools::is('string', $prompt)) {
				$this->_shPrompt = $this->_prepareShellPrompt($prompt, true);
				$this->_shPromptBck = $prompt;		// /!\ Sauvegarde prompt brut
			}
			else {
				$this->_shPrompt = $this->_shPromptBck = '';
			}

			return $this;
		}

		/**
		  * Sets shell prompt
		  * Shell prompt backup is not updated
		  *
		  * @param string $prompt Shell prompt
		  * @return $this
		  */
		protected function _setShellPrompt($prompt)
		{
			$prompt = trim($prompt, ' ');

			if(C\Tools::is('string&&!empty', $prompt)) {
				$this->_shPrompt = $this->_prepareShellPrompt($prompt, false);
			}
			else {
				$this->_shPrompt = '';
			}

			return $this;
		}

		/**
		  * Prepare shell prompt
		  *
		  * @param string $prompt Shell prompt
		  * @return string Shell prompt
		  */
		protected function _prepareShellPrompt($prompt, $appendSuffix = true)
		{
			$shPrompt = '';

			if($prompt !== '') {
				$shPrompt .= C\Tools::e($prompt.' ', 'cyan', false, 'bold', true);
			}

			$shPrompt .= C\Tools::e(self::SHELL_PROMPT, 'white', false, 'bold', true);

			return $shPrompt;
		}

		/**
		  * Rollback shell prompt
		  *
		  * @return $this
		  */
		protected function _rollbackShellPrompt()
		{
			$prompt = $this->getShellPrompt();
			$this->_shPrompt = $this->_prepareShellPrompt($prompt);
			return $this;
		}

		protected function _prepare()
		{
			parent::_prepare();

			$this->_userCmd = "";
			$this->_modeCmd = "";

			$this->_shMode = false;
			return $this;
		}

		protected function _actions(&$cmd, $c)
		{
			$cmdLen = mb_strlen($cmd);

			//http://www.asciitable.com/
			//https://shiroyasha.svbtle.com/escape-sequences-a-quick-guide-1
			//https://unicodelookup.com/#ctrl/1
			//http://www.disinterest.org/resource/MUD-Dev/1997q1/000244.html
			switch($c)
			{
				// On ne peut pas capturer CTRL+C
				/*case "\x3":	// CTRL+C
				{
					echo 'CTRL+C';
					break;
				}*/
				case "\xC":		// CTRL+l
				{
					$this->_exitMode();
					$this->_clear();
					$cmd = "";
					break;
				}
				case "\033c":	// ALT+c
				case "\033C":	// ALT+C
				case "©":		// ALT+c (MacOS)
				{
					$this->_exitMode($cmd);
					$this->_position = 0;
					$this->_refresh('');
					$cmd = "";
					break;
				}
				case "\033b":	// ALT+b
				case "\033B":	// ALT+B
				{
					$this->_exitMode($cmd);

					$moveStatus = preg_match_all('# ([\S])#i', $cmd, $matches, PREG_OFFSET_CAPTURE);

					if(!$moveStatus) {
						$matches = array();
					}
					else {
						$matches = $matches[1];
					}

					$previousPosition = 0;
					$matches = array_reverse($matches);

					foreach($matches as $match)
					{
						if($match[1] < $this->_position) {
							$previousPosition = $match[1];
							break;
						}
					}

					$this->_position = $previousPosition;
					$this->_move($this->_position);
					break;
				}
				case "\033f":	// ALT+f
				case "\033F":	// ALT+F
				{
					$this->_exitMode($cmd);

					$moveStatus = preg_match('# ([\S])#i', $cmd, $matches, PREG_OFFSET_CAPTURE, $this->_position);

					if(!$moveStatus) {
						$matches = array();
						$matches[1][1] = $cmdLen;
					}

					$this->_position = $matches[1][1];
					$this->_move($this->_position);
					break;
				}
				case "\033":	// ECHAP
				{
					$this->_exitMode($cmd);
					break;
				}
				case "\033[A":	// UP
				{
					$this->_exitMode($cmd);

					$cmd = $this->_history->getPrevLine();
					$this->_position = mb_strlen($cmd);
					$this->_refresh($cmd);
					break;
				}
				case "\033[B":	// DOWN
				{
					$this->_exitMode($cmd);

					$cmd = $this->_history->getNextLine();
					$this->_position = mb_strlen($cmd);
					$this->_refresh($cmd);
					break;
				}
				case "\033[C":	// RIGHT
				{
					if($this->_position < $cmdLen) {
						$this->_position++;
						echo $c;
					}
					break;
				}
				case "\033[D":	// LEFT
				{
					if($this->_position > 0) {
						$this->_position--;
						echo $c;
					}
					break;
				}
				case "\033[H":	// home
				case "\033[1~":
				case "\x1":		// CTRL+a (MacOS)
				{
					if($this->_position > 0) {
						$this->_position = 0;
						$this->_move($this->_position);
					}
					break;
				}
				case "\033[2~":	// insert
				{
					break;
				}
				case "\033[3~":	// delete
				{
					if($this->_position >= 0) {
						$cmd = $this->_delete($cmd);
					}
					break;
				}
				case "\033[F":	// end
				case "\033[4~":
				case "\x5":		// CTRL+e (MacOS)
				{
					if($this->_position < $cmdLen) {
						$this->_position = $cmdLen;
						$this->_move($this->_position);
					}
					break;
				}
				case "\033[5~":	// page up
				{
					break;
				}
				case "\033[6~":	// page down
				{
					break;
				}
				case "\x12":	// CTRL+r
				{
					$this->_searchMode($cmd);
					$this->_searchCmd($cmd, false);
					break;
				}
				case "\x7f":	// backspace
				{
					if($this->_position > 0) {
						$this->_position--;		// /!\ Reculer avant de supprimer
						$cmd = $this->_delete($cmd);
					}

					$this->_searchCmd($cmd, true);
					break;
				}
				case "?":
				{
					$this->_exitMode($cmd);
					$this->_history->reset();

					$this->_autocompletion->_($cmd, false, true);

					if($this->_autocompletion->cmdStatus)
					{
						$cmd = $this->_autocompletion->command;
						$options = $this->_autocompletion->options;		// CMD || ARG

						$cmdMsg = $this->_console->getManCmd($cmd);

						if(C\Tools::is('string&&!empty', $cmdMsg)) {
							$this->_print($cmdMsg, 'cyan');
						}

						if(count($options) > 0)
						{
							$resultsMsg = $this->_console->getManMsg($options, $cmd);

							if(C\Tools::is('string&&!empty', $resultsMsg)) {
								$this->_print($resultsMsg, 'cyan');
							}
						}

						$cmd = (string) $this->_autocompletion;
					}

					$this->_position = mb_strlen($cmd);
					$this->_printPrompt($cmd);
					break;
				}
				case "\t":
				case "\x9":		// tab
				{
					$positionBonusMalus = 0;

					$this->_exitMode($cmd);
					$this->_history->reset();

					$this->_autocompletion->_($cmd, true, true);

					if(!$this->_autocompletion->cmdStatus) {
						$this->_print("None command found for '".$cmd."'", 'red');
					}
					else
					{
						$cmd = (string) $this->_autocompletion;
						$options = $this->_autocompletion->options;		// CMD || ARG
						$status = $this->_autocompletion->status;

						$optionsCount = count($options);

						if($optionsCount === 0) {
							$color = ($status) ? ('green') : ('orange');
							$this->_print($cmd, $color);
						}
						elseif($optionsCount > 1)
						{
							$options = array_unique($options);
							$columns = $this->_console->getColumns();

							if($columns !== false) {
								$msg = C\Tools::cutShellTable($options, $columns, false, false, false);
							}
							else {
								$msg = C\Tools::formatShellTable(array($options), false, false, false, true);
							}

							$this->_print($msg, 'cyan');

							$color = ($status) ? ('green') : ('orange');
							$this->_print($cmd, $color);
						}

						/**
						  * Permet de gérer correctement la position du curseur lorsque le dernier argument
						  * est protégé par ' ou " et que l'on souhaite que l'utilisateur puisse corriger
						  */
						if(!$status && $this->_autocompletion->acArguments->lastArgIsQuoted()) {
							$positionBonusMalus = -1;
						}
					}

					$this->_position = mb_strlen($cmd) + $positionBonusMalus;

					$this->_printPrompt($cmd);
					$this->_move($this->_position);
					break;
				}
				case PHP_EOL:
				{
					$this->_exitMode($cmd);
					$this->_history->reset();

					$this->_autocompletion->_($cmd, false, false);

					$cmd = $this->_autocompletion->command;
					$args = $this->_autocompletion->arguments;

					/**
					  * Toujours exécuter la commande même si celle-ci semble invalide car:
					  * - on ne peut pas toujours retourner l'ensemble des arguments possibles (cd .., cd ~, ..)
					  * - l'erreur à afficher doit dépendre de la commande donc c'est à elle de gérer l'erreur
					  */
					$call = (string) $this->_autocompletion;
					$this->_setHistoryLine($call);

					echo PHP_EOL;
					$this->_end();

					return array(0 => $cmd, 1 => $args, 'command' => $cmd, 'arguments' => $args);
				}
				default:
				{
					$this->_history->reset();

					$c = preg_replace('<\s>i', ' ', $c);

					//https://www.regular-expressions.info/posixbrackets.html#class
					if(preg_match("<[[:cntrl:]]>i", $c)) {
						// do nothing
					}
					/**
					  * /!\ Flag u pour unicode indispensable pour les caractères accentués
					  */
					elseif(preg_match("<^([[:print:]]+)$>iu", $c))
					{
						$cmdLen = mb_strlen($cmd);
						//$c = str_replace("\t", "", $c);

						if($cmdLen === $this->_position) {
							$cmd .= $c;
							$this->_position += mb_strlen($c);
							$this->_update($c, 'white', false, false);
						}
						elseif($this->_position < $cmdLen) {
							$cmd = $this->_insert($cmd, $c);
						}
						else {
							throw new Exception("Positionnement incorrect", E_USER_ERROR);
						}

						$this->_searchCmd($cmd, true);
					}
					else {
						$this->_print("Caractères non autorisés '".$c."'", 'orange');
						$this->_printPrompt($cmd);
					}
				}
			}

			return null;
		}

		protected function _end()
		{
			$this->_history->close();
		}

		protected function _setShellPromptMode($mode)
		{
			$shPromptMode = self::SHELL_PROMPT_MODES[$mode];
			$this->_setShellPrompt($shPromptMode);
			return $this;
		}

		protected function _searchMode(&$cmd = null)
		{
			switch($this->_shMode)
			{
				case false:
				{
					$this->_position = 0;
					$this->_refresh();
					$cmd = "";

					$this->_setShellPromptMode(self::SHELL_MODE_SEARCH);
					$this->_shMode = self::SHELL_MODE_SEARCH;
					$this->_printPrompt();
					break;
				}
			} 
		}

		protected function _searchCmd($cmd, $resetPosition = false)
		{
			if($this->_shMode === self::SHELL_MODE_SEARCH)
			{
				if($resetPosition) {
					$this->_history->goToLastLine();
				}

				$result = $this->_history->search($cmd, true);

				if($result === false) {
					$result = "";
					$status = false;
				}
				else {
					$status = true;
				}

				echo "\033[1A";		// Déplace le curseur d'une ligne vers le haut
				$this->_rollbackShellPrompt();
				$this->_refresh($result);

				echo "\033[1B";		// Déplace le curseur d'une ligne vers le bas
				$this->_setShellPromptMode(self::SHELL_MODE_SEARCH);
				$this->_move($this->_position);

				$this->_modeCmd = $result;
				return $status;
			}

			return false;
		}

		protected function _exitMode(&$cmd = null)
		{
			switch($this->_shMode)
			{
				case self::SHELL_MODE_SEARCH:
				{
					$this->deleteMessage(1, true);

					$cmd = $this->_modeCmd;
					$this->_position = mb_strlen($this->_modeCmd);
					$this->_modeCmd = "";

					$this->_rollbackShellPrompt();
					$this->_move($this->_position);
					$this->_shMode = false;
					break;
				}
			}
		}

		protected function _setHistoryLine($line)
		{
			/**
			  * On ne doit pas enregistrer dans l'historique les commandes suivantes
			  * ## # # #PHP_EOL# ...
			  * On doit nettoyer les commandes suivantes avant de les enregistrer dans l'historique
			  * # cd # # cd PHP_EOL# ...
			  */
			$line = trim($line, " \r\n\t");

			try {
				$status = $this->_history->writeLine($line);
			}
			catch(\Exception $e) {
				$this->_print('Can not write command to history file', 'red');
			}

			return $status;
		}

		public function history()
		{
			if($this->_history->open())
			{
				foreach($this->_history as $index => $line)
				{
					$line = trim($line, PHP_EOL);

					if(C\Tools::is('string&&!empty', $line)) {
						$this->_print($index.': '.trim($line, PHP_EOL), 'white');
					}
				}
			}
			return $this;
		}

		public function help()
		{
			$helpMsg = $this->_console->getHelpMsg();
			$this->_print($helpMsg, 'white');
			return $this;
		}

		public function debug($debug = true)
		{
			parent::debug($debug);
			$this->_console->debug($debug);
			$this->_autocompletion->debug($debug);
			return $this;
		}
	}