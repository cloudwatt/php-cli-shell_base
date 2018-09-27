<?php
	require_once('cli.php');
	require_once('autocompletion.php');

	abstract class Shell_Abstract
	{
		const SHELL_PROMPT = ">";
		const SHELL_SPACER = "     ";

		protected $_Shell_Cli;
		protected $_Shell_Autocompletion;

		protected $_historyObject;
		protected $_historyFilename;
		protected $_historyLineLen;
		protected $_historyPosition;

		protected $_shPrompt = "";
		protected $_position = 0;

		protected $_debug = false;


		public function __construct(array $commands, array $inlineArgs = array(), array $outlineArgs = array(), array $manCommands = null)
		{
			$this->_Shell_Cli = new Shell_Cli($commands, $manCommands);
			$this->_Shell_Autocompletion = new Shell_Autocompletion($commands, $inlineArgs, $outlineArgs, $manCommands);
		}

		public function setInlineArg($name, $value)
		{
			$this->_Shell_Autocompletion->setInlineArg($name, $value);
			return $this;
		}

		public function getHistoryFilename()
		{
			return $this->_historyFilename;
		}

		public function setHistoryFilename($filename)
		{
			if(strpos($filename, '/') === false && defined('ROOT_DIR')) {
				$filename = ROOT_DIR.'/'.$filename;
			}

			if(!file_exists($filename) || (is_readable($filename) && is_writable($filename))) {
				$this->_historyFilename = $filename;
				return $this;
			}
			else {
				throw new Exception("Le fichier ".$filename." doit pouvoir être lu et modifié", E_USER_ERROR);
			}
		}

		public function getShellPrompt()
		{
			return $this->_shPrompt.self::SHELL_PROMPT;
		}

		public function setShellPrompt($shPrompt = '')
		{
			$shPrompt = trim($shPrompt, ' ');

			if(Tools::is('string&&!empty', $shPrompt)) {
				$this->_shPrompt = $shPrompt.' ';
			}
			else {
				$this->_shPrompt = '';
			}

			return $this;
		}

		public function resetShellPrompt()
		{
			$this->setShellPrompt();
			return $this;
		}

		protected function _start()
		{
			$this->_position = 0;
			$this->_printPrompt();
			readline_callback_handler_install('', function() { });
		}

		public function launch()
		{
			$this->_start();
			$cmd = "";

			while(true)
			{
				$r = array(STDIN);
				$w = NULL;
				$e = NULL;

				try {
					$n = stream_select($r, $w, $e, 1);
				}
				catch(Exception $e)
				{
					// /!\ stream_select() expects parameter 3 to be array, object given
					$r = array(STDIN);
					$w = NULL;
					$e = NULL;

					/**
					  * Quand on redimensionne le shell ou qu'on le split
					  * stream_select(): unable to select [4]: Interrupted system call (max_fd=0)
					  */
					$n = stream_select($r, $w, $e, 1);
				}

				if($n && in_array(STDIN, $r))
				{
					//$c = stream_get_contents(STDIN, 3);	// Ne fonctionne pas sans savoir pourquoi

					/**
					  * 3 octets pour les curseurs (\033[A, \033[B, ...)
					  * 4 octets pour touches (DELETE \033[3~)
					  * Indique au maximum et non une égalité ou un minimum
					  */
					$c = fread(STDIN, 255);

					$cmdLen = mb_strlen($cmd);

					//http://www.asciitable.com/
					//https://shiroyasha.svbtle.com/escape-sequences-a-quick-guide-1
					//https://unicodelookup.com/#ctrl/1
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
							$this->_clear();
							$cmd = "";
							break;
						}
						case "\033c":	// ALT+c
						case "\033C":	// ALT+C
						case "©":		// ALT+c (MacOS)
						{
							$this->_position = 0;
							$this->_refresh('');
							$cmd = "";
							break;
						}
						case "\033[A":	// UP
						{
							$cmd = $this->_getPrevHistoryLine();
							$this->_position = mb_strlen($cmd);
							$this->_refresh($cmd);
							break;
						}
						case "\033[B":	// DOWN
						{
							$cmd = $this->_getNextHistoryLine();
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
						case "\x7f":	// backspace
						{
							if($this->_position > 0) {
								$this->_position--;		// /!\ Reculer avant de supprimer
								$cmd = $this->_delete($cmd);
							}
							break;
						}
						case "?":
						case "\t":
						case "\x9":		// tab
						{
							$status = $this->_Shell_Autocompletion->_($cmd);

							$cmd = $this->_Shell_Autocompletion->command;
							$args = $this->_Shell_Autocompletion->arguments;
							$results = $this->_Shell_Autocompletion->results;		// CMD || ARG
//var_dump($cmd);
//var_dump($args);
//var_dump($results);
							if($status === false) {
								$this->_print('None command found', 'red');
							}
							else
							{
								$resultsCount = count($results);

								// ID #0
								if($resultsCount === 0)
								{
									if($c === "?")
									{
										$msg = $this->_Shell_Cli->getManCmd($cmd);

										if(Tools::is('string&&!empty', $msg)) {
											$this->_print($msg, 'cyan');
										}
									}

									if(Tools::is('array&&count>0', $args)) {
										$cmd .= ' '.implode(' ', $args);
									}

									if($c === "\t") {
										$this->_print($cmd, 'green');
									}
								}
								// ID #1
								elseif($resultsCount === 1)
								{
									if($c === "?")
									{
										$msg = $this->_Shell_Cli->getManMsg($results, $cmd);

										if(Tools::is('string&&!empty', $msg)) {
											$this->_print($msg, 'cyan');
										}
									}

									if(Tools::is('array&&count>0', $args)) {
										$cmd .= ' '.implode(' ', $args);
									}

									$cmd .= ' '.current($results);
								}
								// ID #2
								else
								{
									$msg = ($c === "?") ? ($this->_Shell_Cli->getManMsg($results, $cmd)) : ('');

									if(!Tools::is('string&&!empty', $msg)) {
										$msg = implode(self::SHELL_SPACER, array_unique($results));
									}

									$this->_print($msg, 'cyan');

									if($this->_Shell_Autocompletion->cmdIsComplete)
									{
										if(Tools::is('array&&count>0', $args))
										{
											$cmd .= ' '.implode(' ', $args);

											if(in_array(end($args), $results, true)) {
												$this->_print($cmd, 'green');
											}
										}
									}
								}
							}

							$this->_position = mb_strlen($cmd);
							$this->_printPrompt($cmd);
							break;
						}
						case PHP_EOL:
						{
							$status = $this->_Shell_Autocompletion->_($cmd);

							$cmd = $this->_Shell_Autocompletion->command;
							$args = $this->_Shell_Autocompletion->arguments;

							/**
							  * Dans certains cas, un espace peut être autocomplété à la fin de la commande afin de faciliter à l'utilisateur la CLI.
							  * Exemple: show => array('host', 'subnet') --> "show " afin que l'utilisateur puisse poursuivre la commande
							  *
							  * Cependant, si l'on souhaite autoriser "show" comme commande valide alors il faut nettoyer l'autocompletion
							  */
							$cmd = rtrim($cmd, ' ');

							// Si on ne souhaite garder que les commandes valides: if($status)
							$this->_setHistoryLine($cmd.' '.implode(' ', $args));

							$this->_end();
							echo PHP_EOL;

							return array(0 => $cmd, 1 => $args, 'command' => $cmd, 'arguments' => $args);
						}
						default:
						{
							// /!\ Penser à modifier $charAllowed dans le else
							if(preg_match("<^([a-z0-9_\-\"'~#*+()@%\[\]?,.;/:! \t]+)$>i", $c))
							{
								$cmdLen = mb_strlen($cmd);
								$c = str_replace("\t", "", $c);

								if($cmdLen === $this->_position) {
									$cmd .= $c;
									$this->_position += mb_strlen($c);
									Tools::e($c, 'white', false, false);
								}
								elseif($this->_position < $cmdLen) {
									$cmd = $this->_insert($cmd, $c);
								}
								else {
									throw new Exception("Positionnement incorrect", E_USER_ERROR);
								}
							}
							else {
								$charAllowed = "a-z0-9_-\"'~#*+()@%[]?,.;/:! ";
								$this->_print("Caractères autorisés '".$charAllowed."'", 'orange');
								$this->_printPrompt($cmd);
							}
						}
					}
				}
			}
		}

		protected function _end()
		{
			// /!\ Ne pas utiliser unset
			$this->_historyObject = null;
		}

		protected function _historyInit()
		{
			if($this->_historyFilename !== null && $this->_historyObject === null)
			{
				$result = touch($this->_historyFilename);

				if($result)
				{
					$this->_historyObject = new SplFileObject($this->_historyFilename, 'r');
					$this->_historyObject->seek(PHP_INT_MAX);

					$this->_historyLineLen = $this->_historyObject->key()+1;
					$this->_historyPosition = $this->_historyLineLen; 
					return true;
				}
			}

			return ($this->_historyObject !== null);
		}

		protected function _getPrevHistoryLine()
		{
			if($this->_historyInit())
			{
				if($this->_historyPosition > 0) {
					$this->_historyPosition--;
				}

				$this->_historyObject->seek($this->_historyPosition);

				// /!\ SplFileObject::fgets ne fonctionne pas correctement
				return trim($this->_historyObject->current(), PHP_EOL);
			}
			else {
				return false;
			}
		}

		protected function _getNextHistoryLine()
		{
			if($this->_historyInit())
			{
				if($this->_historyPosition < $this->_historyLineLen)
				{
					$this->_historyPosition++;
					$this->_historyObject->seek($this->_historyPosition);

					// /!\ SplFileObject::fgets ne fonctionne pas correctement
					return trim($this->_historyObject->current(), PHP_EOL);
				}
				else {
					return '';
				}
			}
			else {
				return false;
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

			// /!\ Ne pas utiliser unset
			$this->_historyObject = null;

			try
			{
				/**
				  * /!\ SplFileObject::fwrite ne permet pas d'ajouter du contenu, il faut à chaque fois tout renvoyer
				  */
				//$this->_historyObject->fwrite($line.PHP_EOL);

				if(Tools::is('string&&!empty', $line)) {
					file_put_contents($this->_historyFilename, PHP_EOL.$line, FILE_APPEND|LOCK_EX);
					$this->_historyPosition = $this->_historyLineLen;
					$this->_historyLineLen++;
					return true;
				}
			}
			catch(Exception $e) {
				$this->_print('Can not write to history file', 'red');
			}

			return false;
		}

		protected function _getPrintedPrompt()
		{
			$prompt = Tools::e($this->_shPrompt, 'cyan', false, 'bold', true);
			$prompt .= Tools::e(self::SHELL_PROMPT, 'white', false, 'bold', true);
			return $prompt;
		}

		protected function _printPrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			$prompt .= Tools::e($text, 'white', false, false, true);
			$this->_print($prompt);
			return $this;
		}

		protected function _updatePrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			$prompt .= Tools::e($text, 'white', false, false, true);
			Tools::e($prompt);					// /!\ Pas de PHP_EOL
			return $this;
		}

		protected function _print($text = "", $textColor = 'white', $bgColor = false, $textStyle = false)
		{
			Tools::e(PHP_EOL.$text, $textColor, $bgColor, $textStyle);
			return $this;
		}

		protected function _move($position)
		{
			$userShPromptLen = mb_strlen($this->_shPrompt);
			$staticShPromptLen = mb_strlen(self::SHELL_PROMPT);
			echo "\033[".($position+$userShPromptLen+$staticShPromptLen+2)."G";
			/**
			  * Position +2:
			  * > x
			  * 123
			  */
		}

		protected function _insert($cmd, $char)
		{
			$_cmd_0 = substr($cmd, 0, $this->_position);
			$_cmd_1 = substr($cmd, $this->_position);
			$cmd = $_cmd_0.$char.$_cmd_1;

			$this->_position += mb_strlen($char);
			$this->_refresh($cmd);
			return $cmd;
		}

		protected function _delete($cmd)
		{
			$_cmd = str_split($cmd);
			unset($_cmd[$this->_position]);
			$cmd = implode('', $_cmd);

			$this->_refresh($cmd);
			return $cmd;
		}

		protected function _refresh($cmd)
		{
			echo "\033[2K";		// Supprime tout sur la ligne courante
			echo "\033[1G";		// Déplace le curseur au debut de la ligne

			$this->_updatePrompt($cmd);
			$this->_move($this->_position);
			return $this;
		}

		protected function _clear()
		{
			echo "\033[2J";			// Supprime tout ce qui est à l'écran
			echo "\033[1;1f";		// Déplace le curseur en haut à gauche

			$this->_position = 0;
			$this->_updatePrompt();
			$this->_move($this->_position);
			return $this;
		}

		public function printMessage($message)
		{
			Tools::e(PHP_EOL.$message);
			return $this;
		}

		public function insertMessage($message)
		{
			Tools::e($message);
			return $this;
		}

		public function updateMessage($message)
		{
			echo "\033[2K";			// Supprime tout sur la ligne courante
			echo "\033[1G";			// Déplace le curseur au debut de la ligne
			Tools::e($message);
			return $this;
		}

		public function deleteMessage($lineToDelete = 1, $lineUP = false)
		{
			for($i=0; $i<$lineToDelete; $i++)
			{
				echo "\033[2K";			// Supprime tout sur la ligne courante

				if($i < ($lineToDelete-1) || $lineUP) {
					echo "\033[1A";		// Déplace le curseur d'une ligne vers le haut
					// /!\ La commande pour se déplacer en fin de ligne n'existe pas
				}
				else {
					echo "\033[1G";		// Déplace le curseur au debut de la ligne
				}
			}

			return $this;
		}

		public function history()
		{
			if($this->_historyInit())
			{
				foreach($this->_historyObject as $index => $line)
				{
					$line = trim($line, PHP_EOL);

					if(Tools::is('string&&!empty', $line)) {
						$this->_print($index.': '.trim($line, PHP_EOL), 'white');
					}
				}
			}
			return $this;
		}

		public function help()
		{
			$helpMsg = $this->_Shell_Cli->getHelpMsg();
			$this->_print($helpMsg, 'white');
			return $this;
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			$this->_Shell_Cli->debug($debug);
			$this->_Shell_Autocompletion->debug($debug);
			return $this;
		}
	}