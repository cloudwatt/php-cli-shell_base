<?php
	namespace Cli\Terminal;

	use Core as C;

	class Autocompletion_Commands extends Autocompletion_Abstract implements \Countable
	{
		/**
		  * @var array
		  */
		protected $_defCommands;

		/**
		  * @var Cli\Terminal\Autocompletion_Arguments
		  */
		protected $_cliAcArguments;

		/**
		  * @var bool
		  */
		protected $_acWithOption = true;

		/**
		  * @var bool
		  */
		protected $_acWithSpace = true;

		/**
		  * @var bool
		  */
		protected $_hasArguments = false;

		/**
		  * @var bool
		  */
		protected $_cmdIsIncomplete;

		/**
		  * @var bool
		  */
		protected $_cmdIsAvailable;

		/**
		  * @var bool
		  */
		protected $_cmdIsComplete;

		/**
		  * @var string
		  */
		protected $_command = null;

		/**
		  * @var array
		  */
		protected $_commands = array();

		/**
		  * @var array
		  */
		protected $_options = array();

		/**
		  * @var bool
		  */
		protected $_status = false;

		/**
		  * @var bool
		  */
		protected $_debug = false;


		public function __construct(array $defCommands, Autocompletion_Arguments $shAcArguments = null)
		{
			$this->_defCommands = $defCommands;

			if($shAcArguments !== null) {
				$this->declareArguments($shAcArguments);
			}
		}

		public function declareArguments(Autocompletion_Arguments $shAcArguments)
		{
			$this->_cliAcArguments = $shAcArguments;
			return $this;
		}

		public function argumentsExists()
		{
			return ($this->_cliAcArguments !== null);
		}

		public function reset()
		{
			$this->_init();
			return $this;
		}

		protected function _init()
		{
			if($this->_debug) {
				echo PHP_EOL;
			}

			$this->_acWithOption = true;
			$this->_acWithSpace = true;
			$this->_hasArguments = false;

			$this->_cmdIsIncomplete = null;
			$this->_cmdIsAvailable = null;
			$this->_cmdIsComplete = null;

			$this->_command = null;
			$this->_commands = array();
			$this->_options = array();
			$this->_status = false;
		}

		/**
		  * acWithOption: autocomplete (AC) with the option when there is only one option returned
		  * acWithSpace: autocomplete (AC) with space at the end of the command for some requirements
		  */
		public function _($cmd, $acWithOption = true, $acWithSpace = true)
		{
			$this->_init();

			$this->_acWithOption = (bool) $acWithOption;
			$this->_acWithSpace = (bool) $acWithSpace;

			try {
				$status = $this->_prepare($cmd);
			}
			catch(\Exception $e) {
				if($this->_debug) { throw $e; }
				return false;
			}

			if($status)
			{
				$status = $this->_setup();

				if($this->_debug) {
					C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {status}:'.PHP_EOL.(int) $status, 'orange');
				}
			}

			return $status;
		}

		protected function _prepare($cmd)
		{
			if(C\Tools::is('string&&!empty', $cmd))
			{
				$status = false;

				/**
				  * /!\ Tests: https://regex101.com
				  * https://stackoverflow.com/questions/17848618/parsing-command-arguments-in-php
				  * https://www.regular-expressions.info/refcapture.html
				  * https://www.regular-expressions.info/refext.html
				  * https://www.regular-expressions.info/refadv.html
				  * https://www.regular-expressions.info/refrepeat.html
				  *
				  * find  --test "tu tu" -arg1 test /titi/toto "ti ti" ppp"pppp
				  * find  --test "tu "tu" -arg1 test /titi/toto "ti ti" ppp"pppp
				  * find  --test "tu "tu" -arg1 test /titi/toto "ti ti" ppp"pppp aaaa\ bbbb\ cccc
				  * find  --test "tu "tu" -arg1 test /titi/toto "ti ti" ppp"pppp aaaa\e\ bb"\""bb\ cccc
				  *
				  * Bash:
				  * OK: ls "noc_"config_compute.txt
				  * OK: ls "noc_""config_compute.txt"
				  * KO: ls "noc_"config_comp"ute.txt
				  */
				$pattern = '#(?<!\\\\)(\'|")(?:[^\\\\]|\\\\.)*?($|\1)|(?:[^\s]|(?<=\\\\)\s)+#is';
				$regexStatus = (preg_match_all($pattern, $cmd, $matches) !== false);
				$commands = $matches[0];

				if($regexStatus !== false && count($commands) > 0)
				{
					$position = 0;

					foreach($commands as $command)
					{
						if(strpos($command, "'") !== false || strpos($command, '"') !== false ||
							strpos($command, ' ') !== false || substr($command, 0, 1) === '-')
						{
							$this->_hasArguments = true;
							break;
						}
						else {
							// Ne pas utiliser l'index car sur le dernier élément il faut faire +1
							$position++;
						}
					}

					if($position > 0)
					{
						$commands = array_slice($commands, 0 , $position);

						/**
						  * Permet de détecter l'espace ajouté par l'utilisateur à la fin de la commande
						  * Ne pas le prendre en compte si on trouve précédement des arguments inline ou outline
						  * Cela permet d'éviter le mode strict pour essayer de trouver une commande qui serait partielle mais unique
						  *
						  * Example avec prise en compte de l'espace
						  * [OK] {xx} [cd ]							--> [cd ] == [cd ]
						  *
						  * Example sans prise en compte de l'espace
						  * [OK] {xx} [fi -name titi]				--> [find -name titi] === [find -name titi]
						  */
						if(!$this->_hasArguments && substr($cmd, -1, 1) === ' ') {
							$commands[] = null;
						}

						if($this->_debug) {
							$debug = print_r($commands, true);
							C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {parts}:'.PHP_EOL.$debug, 'orange');
						}

						$status = true;
					}
				}

				if($status === false) {
					throw new Exception("Command '".$cmd."' is not valid", E_USER_ERROR);
				}
			}
			else {
				$commands = array('');
			}

			$this->_commands = $commands;
			return true;
		}

		protected function _getSetupParams()
		{
			$cmdCounter = count($this->_commands);

			$retryMode = false;																			// Voir #1.1
			$strictCmd = (end($this->_commands) === null);												// Voir #0.1
			$forceKey = ($cmdCounter > 1);																// Voir #0.1
			$bestEffort = $this->_hasArguments;															// Voir #0.1
			$autoCompletion = (end($this->_commands) !== null);											// Voir #0.1

			/**
			  * autoCompletion & acWithOption
			  *
			  * Cas 1 : TAB
			  * Lorsque l'utilisateur ne renseigne pas de (partie) commande mais qu'il n'y a qu'un choix possible alors acWithOption permet de sélectionner ce unique choix
			  * Lorsque l'utilisateur commence a renseigner une (partie) commande et qu'il n'y a qu'un choix possible alors acWithOption permet de sélectionner ce unique choix
			  *
			  * Cas 2 : ENTER
			  * Lorsque l'utilisateur ne renseigne pas de (partie) commande mais qu'il n'y a qu'un choix possible alors autoCompletion NE DOIT PAS permettre de sélectionner ce unique choix
			  * Lorsque l'utilisateur commence a renseigner une (partie) commande et qu'il n'y a qu'un choix possible alors autoCompletion doit permettre de sélectionner ce unique choix
			  */

			return array($strictCmd, $forceKey, $bestEffort, $autoCompletion);
		}

		protected function _setup()
		{
			$cmdPosition = $this->_defCommands;

			list($strictCmd, $forceKey, $bestEffort, $autoCompletion) = $this->_getSetupParams();

			if($this->_debug) {
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {strict cmd}:'.PHP_EOL. (int) $strictCmd, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {force key}:'.PHP_EOL. (int) $forceKey, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {best effort}:'.PHP_EOL. (int) $bestEffort, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {auto completion}:'.PHP_EOL. (int) $autoCompletion, 'orange');
			}

			$cmdCounter = count($this->_commands);

			// ID #0
			foreach($this->_commands as $index => &$cmdPart)
			{
				if($this->_debug) {
					C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {part}:'.PHP_EOL.$cmdPart, 'orange');
				}

				if(C\Tools::is('array', $cmdPosition))
				{
					$isLastItem = (($index+1) === $cmdCounter);
					$isBeforeLastItem = (($index+2) === $cmdCounter);

					/** ID #0.1
					  *
					  * /!\ results doit être trié par natcasesort au préalable afin d'éviter des traitements inutiles
					  *
					  * Il ne faut pas appliquer array_unique car dans certains cas on peut avoir des doublons:
					  * array('help', 'help' => 'me') --> On a 2 choix, help et help mais l'un est une commande complète et l'autre non
					  * Dans ce cas là, on ne sait quel commande souhaite l'utilisateur, on ne doit donc pas rajouter d'espace à la fin
					  *
					  * Le strict mode permet d'indiquer une recherche strict pour la commande: ^([cmd])$
					  * Il ne doit être appliqué que sur l'avant dernier élément de la commande, donc count-2 ou index+2
					  * Il ne doit être actif que lorsque la commande comporte un espace à la fin afin de forcer la sélection de ce dernier élément
					  * [OK] {xx} [ssh ]				--> [ssh one ] === [ssh one ]
					  *
					  * Le forceKey mode permet d'indiquer une recherche exclusive sur les clés et non sur les valeurs
					  * Il ne doit être appliqué que lorsque la commande est composée et jamais sur le dernier élément
					  * [OK] {xx} [help ]				--> [help me] === [help me]
					  *
					  * Le bestEffort mode permet une toute derniere recherche afin d'essayer d'isoler la commande souhaitée
					  * [OK] {xx} [cd "toto"]			--> [cd "toto"] !== [cd "toto"]
					  *
					  * Le autoCompletion mode permet d'indiquer si il faut ou non compléter automatiquement lorsque la recherche ne retourne
					  * qu'un unique résultat. Cela doit concerner toutes les parties de la commande sauf la dernière si celle-ci est vide.
					  * Pour la dernière partie, si celle-ci est vide, en mode ENTREE il ne faut pas auto-compléter mais en mode TAB il le faut.
					  *
					  * Lorsque plus d'une commande existe avec le début identique alors il ne faut pas se déplacer sur la commande la plus courte
					  * C'est à l'utilisateur d'ajouter manuellement un espace à droite pour indiquer quelle commande il souhaite utiliser et forcer
					  * ssh + sshd
					  * java + javaws
					  */
					$retryMode = false;											// /!\ Important, réinitialise pour la prochaine partie de la commande
					$strictMode = ($isBeforeLastItem && $strictCmd);
					$forceKeyMode = (!$isLastItem && $forceKey);
					$bestEffortMode = (!$isLastItem || $bestEffort);
					$autoCompletionMode = (!$isLastItem || $autoCompletion);

					searchCommands:

					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {retryMode}:'.PHP_EOL. (int) $retryMode, 'orange');
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {strictMode}:'.PHP_EOL. (int) $strictMode, 'orange');
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {forceKeyMode}:'.PHP_EOL. (int) $forceKeyMode, 'orange');
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {bestEffortMode}:'.PHP_EOL. (int) $bestEffortMode, 'orange');
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {autoCompletionMode}:'.PHP_EOL. (int) $autoCompletionMode, 'orange');
					}

					$results = $this->_getAllCommands($cmdPosition, $cmdPart, $strictMode, $forceKeyMode, $bestEffortMode);
					$status = $this->_setupAllCommands($cmdPosition, $cmdPart, $results, $autoCompletionMode, $this->_acWithOption);

					if($this->_debug)
					{
						/*$debug = var_export($cmdPosition, true);
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [POSITION] {original}:'.PHP_EOL.$debug, 'orange');*/

						$debug = print_r($results, true);
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [RESULTS] {original}:'.PHP_EOL.$debug, 'orange');

						$debug = print_r($results, true);
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {status setup}:'.PHP_EOL. (int) $status, 'orange');
					}

					if(!$status)
					{
						/** ID #1.1
						  *
						  * Lorsque l'utilisateur renseigne une commande avec un espace à la fin, la recherche regarde en premier les clés et non les valeurs
						  * de la déclaration des commandes disponibles, hors la commande peut être déclarée en valeur et que l'espace sert à commencer une liste
						  * d'arguments ou est tout simplement une erreur de l'utilisateur.
						  *
						  * Solution de secours au cas ou l'utilisateur rentrerait une commande incomplète avec un argument inline et sans espace à la fin
						  * strictMode doit etre désactivé car une partie de la commande n'est pas complète et doit donc être trouvée de façon automatique
						  * {xx} [OK] [fi toto]			--> [find toto] !== [find toto]
						  * {xx} [KO] [test ]			--> [] !== [test_]
						  *
						  * Solution de secours au cas où l'utilisateur rentrerait une commande complète avec un espace à la fin mais sans arguments
						  * forceKeyMode doit être désactivé car la commande n'est pas une clé mais une valeur et la suite serait des arguments
						  * {xx} [OK] [cd ]				--> [cd] === [cd]
						  * {xx} [KO] [pwd ]			--> [] !== [pwd]
						  *
						  * Solution de secours au cas ou l'utilisateur rentrerait une commande complète avec un argument inline et sans espace à la fin
						  * forceKeyMode doit être désactivé car la commande n'est pas une clé mais une valeur et la suite serait des arguments
						  * {xx} [OK] [cd toto]			--> [cd toto] !== [cd toto]
						  */
						if(!$this->_cmdIsIncomplete && !$this->_cmdIsAvailable && !$retryMode) {
							$retryMode = true;
							$strictMode = false;
							$forceKeyMode = false;
							$bestEffortMode = true;
							goto searchCommands;
						}

						break;
					}
				}
				else
				{
					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS COMPLETE', 'orange');
					}

					$cmdPart = $cmdPosition;
					$this->_cmdIsIncomplete = false;
					$this->_cmdIsAvailable = true;
					$this->_cmdIsComplete = true;
					break;
				}
			}
			unset($cmdPart);

			$position = $index+1;

			/**
			  * On supprime tout le reste même les args car cela n'a pas été traité par #0
			  * cmd peut contenir soit une commande partielle soit complète voir même une commande invalide
			  */
			$this->_commands = array_slice($this->_commands, 0, $position);

			/**
			  * Permet de gérer le(s) espace(s) à la fin de la commande. Il faut ignorer ces "fauses" commandes
			  *
			  * Cas de présence d'un espace à la fin:
			  * #2.2 #3.2 : Lorsqu'il y a un espace à la fin de la commande, et que le traitement de la dernière partie de la commande n'est pas remplacée
			  *
			  * Ne filtrer que les valeurs null correspondantes à un espace à la fin de la commande
			  * '' (empty) correspond à une partie de commande non déterminée, il ne faut pas nettoyer
			  */
			$this->_commands = array_filter($this->_commands, function($item) {
				return ($item !== null);
			});

			$this->_command = implode(' ', $this->_commands);

			if($this->argumentsExists()) {
				$this->_cliAcArguments->setCommand($this->_command);
				$this->_cliAcArguments->setPosition($position);
			}

			if($this->_debug)
			{
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {cmdIsIncomplete}:'.PHP_EOL.(int) $this->_cmdIsIncomplete, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {cmdIsAvailable}:'.PHP_EOL.(int) $this->_cmdIsAvailable, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [SYS] {cmdIsComplete}:'.PHP_EOL.(int) $this->_cmdIsComplete, 'orange');

				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {value}:'.PHP_EOL.'('.gettype($this->_command).') |'.$this->_command.'|', 'orange');

				$debug = print_r($this->_options, true);
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {options}:'.PHP_EOL.$debug, 'orange');
			}

			if($this->_cmdIsIncomplete || $this->_cmdIsAvailable || $this->_cmdIsComplete) {
				// /!\ Par défaut, _status doit être égale à false
				$this->_status = true;
			}

			if($this->_debug) {
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {final}:'.PHP_EOL.'|'.$this->_command.'|', 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {COMMANDS} '.__LINE__.' [CMD] {status}:'.PHP_EOL. (int) $this->_status, 'orange');
			}

			return $this->_status;
		}

		/**
		  * Retourne l'ensemble des arguments correspondants
		  *
		  * /!\ Ne pas passer $cmdPart par référence car il pourrait modifier $cmd d'origine, voir #0
		  * /!\ Ne doit pas effectuer d'auto-complétion, ce n'est pas son rôle. Voir _setupAllCommands
		  */
		protected function _getAllCommands(array &$commands, $cmdPart, $strictCmd = false, $forceKey = false, $bestEffort = false)
		{
			$cmds = array();

			// Recherche commandes
			// -------------------
			foreach($commands as $commandKey => &$commandValue)
			{
				$endFlag = ($strictCmd) ? ('$') : ('');

				/**
				  * On test d'abord pour clé car une clé string sera toujours prioritaire sur une valeur
				  *
				  * 0 => array('', '')			// Ignore
				  * 'show' => array('', '')		// Key
				  * 'help' ==> 'me'				// Key
				  * 0 => 'command'				// Value
				  */
				if(C\Tools::is('string', $commandKey))
				{
					if($cmdPart === null || preg_match('#^('.preg_quote($cmdPart, "#").')'.$endFlag.'#i', $commandKey))
					{
						$cmds[$commandKey] = $commandKey;
						$cmdKey = $commandKey;

						/**
						  * /!\ Important fix
						  *
						  * Lorsqu'une commande est renseignée comme ceci 'quit' => 'me' en fait c'est une erreur
						  * La commande devrait être codée de la façon suivante 'quit' => array('me')
						  * Afin de l'autoriser et de faciliter l'écriture des commandes, on fix:
						  *
						  * [KO] {xx} [qu]							--> [quit] !== [quit ]
						  */
						$commandValue = (array) $commandValue;
					}
				}
				elseif(!$forceKey && C\Tools::is('string', $commandValue))
				{				
					if($cmdPart === null || preg_match('#^('.preg_quote($cmdPart, "#").')'.$endFlag.'#i', $commandValue)) {
						$cmds[$commandKey] = $commandValue;
						$cmdKey = $commandKey;
					}
				}
			}
			unset($commandValue);
			// -------------------

			// Traitement commande position
			// ----------------------------
			switch(count($cmds))
			{
				case 0:
					break;
				case 1:
					$commands = $commands[$cmdKey];
					break;
				default:
				{
					// @todo array_search(mb_strtolower($cmdPart), array_map('mb_strtolower', $cmds), true)
					if($bestEffort && ($cmdKey = array_search($cmdPart, $cmds, true)) !== false) {
						$cmds = array($cmdPart);
						$commands = $commands[$cmdKey];
					}
					else {
						natcasesort($cmds);
						// /!\ Ne pas appliquer array_unique, voir #0.1
					}
				}
			}
			// ----------------------------

			$cmds = array_values($cmds);	// /!\ Reindexe correctement et sans les clés
			// /!\ Ne pas appliquer array_unique, voir #0.1
			return $cmds;
		}

		/**
		  * Auto-complète les arguments si besoin et retourne un status
		  */
		protected function _setupAllCommands($cmdPosition, &$cmdPart, array $results, $autoCompletionMode, $acWithOption)
		{
			switch(count($results))
			{
				case 0:		// ID #1
				{
					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND NOT FOUND', 'orange');
					}

					$this->_cmdIsIncomplete = false;
					$this->_cmdIsAvailable = false;
					return false;
				}
				case 1:		// ID #2
				{
					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS UNIQUE', 'orange');
					}

					$this->_cmdIsIncomplete = false;
					$this->_cmdIsAvailable = true;

					/**
					  * /!\ cmdPosition est modifiée par #0.1
					  *
					  * Lors de la recherche de commandes correspondantes en #0.1, il est plus facile de déléguer la mise à jour de cmdPosition
					  * En effet, cmdPosition peut pointer sur un tableau et donc utiliser la clé pour avancer ou sur une valeur
					  * Il faut donc synchroniser cmdPosition et les résultats de la recherche de commandes correspondantes
					  */

					/** ID #2.1
					  *
					  * Permet d'indiquer si il faut ou non compléter automatiquement lorsque la recherche ne retourne
					  * qu'un unique résultat: En mode ENTREE il ne faut pas mais en mode TAB il le faut.
					  *
					  * CAS 1
					  * Toutes les parties de la commande sauf la dernière si celle-ci est vide:
					  * Forcer l'autocompletion (autoCompletionMode = true) afin de compléter les parties de commande incomplètes mais uniques
					  *
					  * CAS 2
					  * Seulement la dernière partie de la commande si celle-ci est vide:
					  * Ne pas forcer l'autocompletion (autoCompletionMode = false), dépend donc exclusivement de acWithOption
					  */
					if($autoCompletionMode || $acWithOption)
					{
						$cmdPart = current($results);

						if($this->_debug) {
							C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {part fix}:'.PHP_EOL.$cmdPart, 'orange');
						}

						if(C\Tools::is('string', $cmdPosition))
						{
							if($this->_debug) {
								C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS COMPLETE', 'orange');
							}

							$this->_cmdIsComplete = true;
							return false;
						}
						else {
							// Seul cas ou on autorise de continuer le traitement de la commande
							return true;
						}
					}
					else
					{
						$this->_options = $results;
						return false;

						/** ID #2.2
						  *
						  * $cmdPart est égale à vide '', donc lors de l'implode il y aura un espace à la fin de la commande
						  * Ceci n'est pas un souci, il ne faut pas nettoyer l'espace
						  */
					}
				}
				default:	// ID #3
				{
					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS INCOMPLETE', 'orange');
					}

					/** ID #3.1
					  *
					  * Permet d'indiquer si il faut ou non pré-compléter automatiquement
					  * En mode ENTREE il ne faut pas mais en mode TAB il le faut.
					  */
					if($autoCompletionMode || $acWithOption) {
						$cmdPart = $this->_crossSubStr($results);
					}

					$this->_options = $results;

					if($this->_debug) {
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {part base}:'.PHP_EOL.$cmdPart, 'orange');
					}

					$this->_cmdIsIncomplete = true;
					$this->_cmdIsAvailable = false;
					return false;

					/** ID #3.2
					  *
					  * $cmdPart peut être égale à vide '', donc lors de l'implode il y aura un espace à la fin de la commande
					  * Ceci n'est pas un souci, la partie de commande est juste incomplète et vide, il ne faut pas nettoyer l'espace
					  */
				}
			}
		}

		protected function _cmdDefArgsExists()
		{
			return ($this->argumentsExists() && $this->_cliAcArguments->hasDefArguments);
		}

		protected function _areCmdArgsPresent()
		{
			return ($this->argumentsExists() && ($this->_cliAcArguments->hasArguments || $this->_cliAcArguments->hasOptions));
		}

		public function count()
		{
			return count($this->getOptions());
		}

		public function getCommand()
		{
			return $this->_command;
		}

		public function getOptions()
		{
			return $this->_options;
		}

		public function getStatus()
		{
			return $this->_status;
		}

		public function __toString()
		{
			$command = $this->_command;

			if(C\Tools::is('string&&!empty', $command))
			{
				/**
				  * Lorsque qu'une partie de la commande est complète et disponible alors
				  * on rajoute un espace afin de faciliter la saisie par l'utilisateur
				  *
				  * Commande incomplète: L'utilisateur doit préciser quelle commande il souhaite
				  * Commande disponible: L'utilisateur n'a pas à corriger sa commande puisqu'elle est disponible
				  * Commande non complète: On ajoute un espace pour que l'utilisateur puisse poursuivre la saisie de la commande
				  * Def arguments: on ajoute un espace pour la saisie des arguments si la définition des arguments l'autorise
				  * Has arguments: on ajoute un espace pour l'ajout des arguments renseignés par l'utilisateur, même si la définition ne l'autorise pas
				  */
				if($this->_acWithSpace && !$this->_cmdIsIncomplete && $this->_cmdIsAvailable && (!$this->_cmdIsComplete || $this->_cmdDefArgsExists() || $this->_areCmdArgsPresent())) {
					$command .= ' ';
				}
			}
			else {
				$command = '';
			}

			return $command;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'defCommands':
					return $this->_defCommands;
				case 'cmd':
				case 'command':
					return $this->getCommand();
				case 'results':
				case 'options':
					return $this->getOptions();
				case 'status':
					return $this->getStatus();
				case 'cmdIsIncomplete':
					return $this->_cmdIsIncomplete;
				case 'cmdIsAvailable':
					return $this->_cmdIsAvailable;
				case 'cmdIsComplete':
					return $this->_cmdIsComplete;
				default:
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
			}
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}