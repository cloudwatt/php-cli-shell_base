<?php
	namespace Cli\Terminal;

	use Closure;

	use Core as C;

	abstract class Main
	{
		const STDIN = 0;
		const STDOUT = 1;
		const STDERR = 2;

		/**
		  * @var string
		  */
		protected $_shPrompt = "";

		/**
		  * @var int
		  */
		protected $_position = 0;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		/**
		  * Gets shell prompt
		  *
		  * @return string Prompt
		  */
		public function getShellPrompt()
		{
			return $this->_shPrompt;
		}

		public function setShellPrompt($prompt = '')
		{
			$prompt = trim($prompt, ' ');

			if(C\Tools::is('string&&!empty', $prompt)) {
				$this->_shPrompt = $prompt;
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
		}

		protected function _streamSelect(array &$r)
		{
			$w = NULL;
			$e = NULL;

			try {
				$n = stream_select($r, $w, $e, 1);
			}
			catch(Exception $e)
			{
				// /!\ stream_select() expects parameter 3 to be array, object given
				$w = NULL;
				$e = NULL;

				/**
				  * Quand on redimensionne le shell ou qu'on le split
				  * stream_select(): unable to select [4]: Interrupted system call (max_fd=0)
				  */
				$n = stream_select($r, $w, $e, 1);
			}

			return $n;
		}

		public function launch(Closure $dispatchCmdCall)
		{
			$cmd = "";
			$this->_start();

			// readline appelé plus d'une fois provoque un segmentation faults
			/*if(function_exists('readline_callback_handler_install')) {
				readline_callback_handler_install('', function() {});
			}*/

			$sttyCommand = 'stty -g';					// -g identique à --save
			//$sttyCommand = 'stty --save';				// Non dispo sous MacOS
			exec($sttyCommand, $outputs, $status);

			if(count($outputs) !== 1 || $status !== 0) {
				throw new Exception("Unable to execute stty command '".$sttyCommand."'", E_USER_ERROR);
			}

			$sttySettings = current($outputs);
			shell_exec('stty -icanon -echo min 1 time 0');

			$pipes = array(STDIN);
			stream_set_blocking(STDIN, false);				// Flux non bloquant, temps réel
			//stream_set_chunk_size(STDIN, 4096);
			//stream_set_read_buffer(STDIN, 8192);

			while(true)
			{
				$r = $pipes;	// copy
				$n = $this->_streamSelect($r);

				if($n !== false)
				{
					if($n > 0 && in_array(STDIN, $r))
					{
						/**
						  * stream_get_contents impose stream_set_blocking à false pour du temp réel
						  */
						$input = stream_get_contents(STDIN, -1);

						$inputs = explode(PHP_EOL, $input);					
						$counterEOL = (count($inputs)-1);

						foreach($inputs as $index => $input)
						{
							/**
							  * explode(PHP_EOL, 'cmd1'.PHP_EOL.'cmd2'.PHP_EOL);
							  * array(3) { [0]=> string(4) "cmd1" [1]=> string(4) "cmd2" [2]=> string(0) "" }
							  */
							if(C\Tools::is('string&&!empty', $input)) {
								$this->_actions($cmd, $input);
							}

							/**
							  * explode(PHP_EOL, PHP_EOL);
							  * array(2) { [0]=> string(0) "" [1]=> string(0) "" }
							  */
							if($index < $counterEOL)
							{
								$result = $this->_actions($cmd, PHP_EOL);

								if($result !== null)
								{
									list($cmd, $args) = $result;
									$exit = $dispatchCmdCall($cmd, $args);

									if(!$exit) {
										$cmd = "";
										$this->_start();
									}
									else
									{
										// readline appelé plus d'une fois provoque un segmentation faults
										/*if(function_exists('readline_callback_handler_remove')) {
											readline_callback_handler_remove();
										}*/

										shell_exec('stty "'.$sttySettings.'"');

										/**
										  * Ne pas ferme STDIN directement sinon il ne sera plus disponible pour ce processus PHP
										  * stream_select ( array &$read ...) $read est modifié par stream_select
										  *
										  * Si on souhaite fermer, utiliser php://stdin
										  */
										//fclose($r[0]);

										return true;
									}
								}
							}
						}
					}
				}
				else {
					return false;
				}
			}
		}

		/**
		  * Méthode réservée pour des traitements comme _actions mais
		  * pour les classes filles, voir Cli\Terminal\Question
		  */
		/*protected function _exec()
		{	
		}*/

		protected function _actions(&$cmd, $c)
		{
		}

		protected function _end()
		{
		}

		protected function _getPrintedPrompt()
		{
			/**
			  * Laisser les classes enfants choisir comment le prompt doit être affiché
			  */
			return $this->_shPrompt;
		}

		protected function _print($text = "", $textColor = 'white', $bgColor = false, $textStyle = false)
		{
			C\Tools::e(PHP_EOL.$text, $textColor, $bgColor, $textStyle);
			return $this;
		}

		protected function _update($text = "", $textColor = 'white', $bgColor = false, $textStyle = false)
		{
			C\Tools::e($text, $textColor, $bgColor, $textStyle);
			return $this;
		}

		protected function _printPrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			$text = C\Tools::e($text, 'white', false, false, true);
			$this->_print($prompt.$text, false, false, false);
			return $this;
		}

		protected function _updatePrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			$text = C\Tools::e($text, 'white', false, false, true);
			$this->_update($prompt.$text, false, false, false);
			return $this;
		}

		protected function _move($position)
		{
			/**
			  * Ne pas compter avec mb_strlen les codes de formatage pour les terminaux
			  */
			$shPrompt = preg_replace("#\033\[([0-9]+;?)+m#", '', $this->_shPrompt);

			$shPromptLen = mb_strlen($shPrompt);
			echo "\033[".($position+$shPromptLen+2)."G";
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

			$this->_position += mb_strlen($char);		// /!\ Important, ne pas fixer la valeur avec mb_strlen($cmd) pour le mode search par exemple
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

		protected function _refresh($cmd = "")
		{
			echo "\033[2K";			// Supprime tout sur la ligne courante
			echo "\033[1G";			// Déplace le curseur au debut de la ligne

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
			C\Tools::e(PHP_EOL.$message);
			return $this;
		}

		public function insertMessage($message)
		{
			C\Tools::e($message);
			return $this;
		}

		public function updateMessage($message)
		{
			echo "\033[2K";			// Supprime tout sur la ligne courante
			echo "\033[1G";			// Déplace le curseur au debut de la ligne
			C\Tools::e($message);
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

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}