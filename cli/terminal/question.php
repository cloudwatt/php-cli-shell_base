<?php
	namespace Cli\Terminal;

	use Core as C;

	class Question extends Main
	{
		/**
		  * @var string
		  */
		protected $_hideAnswer = null;


		public function readLine($prompt)
		{
			if(!function_exists('readline_callback_handler_install')) {
				throw new Exception("Readline is not available, please compile your PHP with this option", E_USER_ERROR);
			}

			$exit = false;
			$answer = null;

			/**
			  * readline_callback_handler_install ne permet pas d'afficher un prompt formaté
			  * workaround, faire un echo et passer '' à readline_callback_handler_install
			  * Souci avec un backspace, le prompt est effacé, workaround PHP_EOL
			  */
			echo $prompt.PHP_EOL;

			/**
			  * readline_callback_handler_install n'exécutera la fonction
			  * que lorsque l'utilisateur appuyera sur la touche entrée
			  */
			readline_callback_handler_install('', function($input) use (&$answer, &$exit) {
				readline_callback_handler_remove();
				$answer = $input;
				$exit = true;
			});

			while(!$exit)
			{
				$r = array(STDIN);
				$n = $this->_streamSelect($r);

				if($n && in_array(STDIN, $r)) {
					readline_callback_read_char();
				}
			}

			return $answer;
		}

		public function question($prompt, $multiLines = false, $multiLinesEndingRegex = null)
		{
			if($multiLines && !C\Tools::is('string&&!empty', $multiLinesEndingRegex)) {
				throw new Exception("Multilines question ending regex is required", E_USER_ERROR);
			}

			$this->_ask($prompt, $multiLines);
			return $this->_answer($multiLines, $multiLinesEndingRegex);
		}

		public function password($prompt, $hideAnswer = '*')
		{
			$this->_ask($prompt);

			if(C\Tools::is('string&&!empty', $hideAnswer)) {
				$this->_hideAnswer = substr($hideAnswer, 0, 1);
			}

			try {
				$answer = $this->_answer();
			}
			catch(\Exception $e) {
				$this->_hideAnswer = null;
				throw $e;
			}

			$this->_hideAnswer = null;
			return $answer;
		}

		protected function _ask($prompt, $multiLines = false)
		{
			$this->setShellPrompt($prompt);
			$this->_start();

			if($multiLines) {
				echo PHP_EOL;
			}

			return $this;
		}

		protected function _answer($multiLines = false, $multiLinesEndingRegex = null)
		{
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

			$exit = false;
			$answer = '';
			$answers = array();

			$pipes = array(STDIN);
			stream_set_blocking(STDIN, false);				// Flux non bloquant, temps réel

			while(!$exit)
			{
				$r = $pipes;	// copy
				$n = $this->_streamSelect($r);

				if($n !== false)
				{
					if($n > 0 && in_array(STDIN, $r))
					{
						$input = stream_get_contents(STDIN, -1);

						if(!$multiLines) {
							$inputs = explode(PHP_EOL, $input, 2);
							$counterEOL = (count($inputs)-1);
							unset($inputs[1]);
						}
						else {
							$inputs = explode(PHP_EOL, $input);
							$counterEOL = (count($inputs)-1);
						}

						foreach($inputs as $index => $input)
						{
							$this->_exec($answer, $answers, $input, $multiLines, $multiLinesEndingRegex);

							if($index < $counterEOL)
							{
								$exit = $this->_exec($answer, $answers, PHP_EOL, $multiLines, $multiLinesEndingRegex);

								if($exit)
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
								}
							}
						}
					}
				}
				else {
					return false;
				}
			}

			return $answer;
		}

		protected function _exec(&$answer, array &$answers, $input, $multiLines, $multiLinesEndingRegex)
		{
			/**
			  * Ordre des tests importants
			  * "\x7f" est accepté par [\S ]
			  */
			/*case "\033[C":	// RIGHT
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
			case "\033[F":	// end
			case "\033[4~":
			case "\x5":		// CTRL+e (MacOS)
			{
				if($this->_position < $cmdLen) {
					$this->_position = $cmdLen;
					$this->_move($this->_position);
				}
				break;
			}*/
			if($input === "\x7f")				// backspace
			{
				if($this->_position > 0) {
					$this->_position--;			// /!\ Reculer avant de supprimer
					$answer = $this->_delete($answer);
				}
			}
			elseif($input === PHP_EOL)
			{
				if($multiLines)
				{
					$answers[] = $answer;
					
					if(!preg_match($multiLinesEndingRegex, $answer)) {
						$this->_position = 0;
						$answer = '';
						echo PHP_EOL;
						return false;
					}
					else {
						$answer = implode(PHP_EOL, $answers);
					}
				}

				echo PHP_EOL;
				return true;
			}
			else
			{
				$input = preg_replace('<\s>i', ' ', $input);

				//https://www.regular-expressions.info/posixbrackets.html#class
				if(preg_match("<[[:cntrl:]]>i", $input)) {
					// do nothing
				}
				elseif(preg_match("<^([\S ]+)$>i", $input)) {
					$answer .= $input;
					$this->_position += mb_strlen($input);
					$this->_update($input, 'white', false, false);
				}
			}

			return false;
		}

		/**
		  * /!\ text ne doit pas comporter de formatage (couleur, style, ...)
		  */
		protected function _print($text = "", $textColor = 'white', $bgColor = false, $textStyle = false)
		{
			if($this->_hideAnswer !== null) {
				$text = str_repeat($this->_hideAnswer, mb_strlen($text));
			}

			C\Tools::e(PHP_EOL.$text, $textColor, $bgColor, $textStyle);
			return $this;
		}

		/**
		  * /!\ text ne doit pas comporter de formatage (couleur, style, ...)
		  */
		protected function _update($text = "", $textColor = 'white', $bgColor = false, $textStyle = false)
		{
			if($this->_hideAnswer !== null) {
				$text = str_repeat($this->_hideAnswer, mb_strlen($text));
			}

			C\Tools::e($text, $textColor, $bgColor, $textStyle);
			return $this;
		}

		protected function _printPrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			C\Tools::e(PHP_EOL.$prompt, 'white', false, false);
			$this->_update($text, 'white', false, false);			// /!\ Ne pas utiliser _insert à cause du PHP_EOL
			return $this;
		}

		protected function _updatePrompt($text = "")
		{
			$prompt = $this->_getPrintedPrompt().' ';
			C\Tools::e($prompt, 'white', false, false);
			$this->_update($text, 'white', false, false);
			return $this;
		}
	}