<?php
	class Shell_Autocompletion
	{
		protected $_defCommands = null;
		protected $_defInlineArgs = null;
		protected $_defOutlineArgs = null;
		protected $_manCommands = null;
		protected $_helpMessage = null;

		protected $_command = null;
		protected $_inlineArgs = null;
		protected $_outlineArgs = null;
		protected $_arguments = null;
		protected $_results = null;

		protected $_cmdIsIncomplete = null;
		protected $_cmdIsAvailable = null;
		protected $_cmdIsComplete = null;

		protected $_debug = false;


		public function __construct(array $defCommands, array $defInlineArgs = array(), array $defOutlineArgs = array(), array $manCommands = null)
		{
			$this->_defCommands = $defCommands;
			$this->_defInlineArgs = $defInlineArgs;
			$this->_defOutlineArgs = $defOutlineArgs;
			$this->_manCommands = $manCommands;
		}

		public function setInlineArg($name, $value)
		{
			$this->_defInlineArgs[$name] = $value;
			return $this;
		}

		public function _($cmd)
		{
			$this->_init();

			try {
				$this->_prepare($cmd);
			}
			catch(Exception $e) {
				return false;
			}

			$status = $this->_setup($cmd);

			if($this->_debug) {
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {status}:'.PHP_EOL.(int) $status, 'orange');
			}

			return $status;
		}

		protected function _init()
		{
			if($this->_debug) {
				echo PHP_EOL;
			}

			$this->_command = null;
			$this->_inlineArgs = array();
			$this->_outlineArgs = array();
			$this->_arguments = array();
			$this->_results = array();

			$this->_cmdIsIncomplete = null;
			$this->_cmdIsAvailable = null;
			$this->_cmdIsComplete = null;
		}

		// @todo a peaufiner voir si fonctionnel
		protected function _prepareNG(&$cmd)
		{
			if(Tools::is('string&&!empty', $cmd))
			{
				/**
				  * /!\ Tests: https://regex101.com
				  * find  --test "tu tu" -arg1 test /titi/toto "ti ti" ppp"pppp
				  */
				$pattern = '#(?:(?:(?:(?<=^)(?<cmd>[a-z0-9_\-]+))|\G)';
				$pattern .= '(?: +(?:(?<outline>-+[a-z0-9_\-]+ +(?:(?:[a-z0-9_\-\/]+)|(?:"[^"]+")))|';
				$pattern .= '(?<inline>(?:[a-z0-9_\-\/]+)|(?:"[^"]+")))))(?![[:graph:]])#i';
				preg_match_all($pattern, $cmd, $matches);

				// Traitement commandes
				// --------------------
				$cmd = preg_replace('# {2,}#i', ' ', $cmd);
				$isStrictCmd = (bool) preg_match('#^[^ ]+( )$#i', $cmd);
				$cmd = explode(' ', ltrim($cmd, ' '));				// /!\ Garder l'espace à droite pour le cas #0.1

				if($this->_debug) {
					$debug = print_r($cmd, true);
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {parts}:'.PHP_EOL.$debug, 'orange');
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {strict cmd}:'.PHP_EOL. (int) $isStrictCmd, 'orange');
				}
				// --------------------

				return $isStrictCmd;
			}
			else {
				$cmd = array('');
				return false;
			}
		}

		protected function _prepare(&$cmd)
		{
			// @todo est-ce utile? a creer
			//$pattern = '#^.*$#i';
			//if(Tools::is('string&&!empty', $cmd) && preg_match_all($pattern, $cmd))

			if(Tools::is('string&&!empty', $cmd))
			{
				/**
				  * /!\ Tests: https://regex101.com
				  * find  --test "tu tu" -arg1 test /titi/toto "ti ti" ppp"pppp
				  * find  --test "tu "tu" -arg1 test /titi/toto "ti ti" ppp"pppp
				  */
				$pattern = '#(?: +(?:(?:(?<outline>-+[a-z0-9_\-]+ +(?:(?:"[^"]+")|(?:[a-z0-9_\-\/]+))))|(?<inline>"[^"]+")))#i';
				$hasArgs = (bool) preg_match_all($pattern, $cmd, $matches);
				$cmd = preg_replace($pattern, '', $cmd);

				if(preg_match_all('#"|( +-+)#i', $cmd)) {
					throw new Exception('Command is invalid', E_USER_ERROR);
				}

				// Traitement arguments inline & outline
				// -------------------------------------
				foreach(array('inline' => &$this->_inlineArgs, 'outline' => &$this->_outlineArgs) as $argType => &$_args)
				{
					if(isset($matches[$argType]) && count($matches[$argType]) > 0) {
						$results = array_filter($matches[$argType], 'mb_strlen');
						$_args = array_merge($_args, $results);
					}
				}
				unset($_args);

				if($this->_debug)
				{
					$debug = print_r($this->_inlineArgs, true);
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [ARGS] {inline}:'.PHP_EOL.$debug, 'orange');

					$debug = print_r($this->_outlineArgs, true);
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [ARGS] {outline}:'.PHP_EOL.$debug, 'orange');
				}
				// -------------------------------------

				// Traitement commandes
				// --------------------
				/**
				  * Nettoyer les espaces à droite de la commande si on trouve précédement des arguments inline ou outline
				  * Cela permet d'éviter le mode strict pour essayer de trouver une commande qui serait partielle mais unique
				  * [OK] {xx} [fi -name titi]				--> [find -name titi] === [find -name titi]
				  */
				if($hasArgs) {
					$cmd = rtrim($cmd, ' ');
				}

				if($this->_debug) {
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {filtered}:'.PHP_EOL.'|'.$cmd.'|', 'orange');
				}

				$cmd = preg_replace('# {2,}#i', ' ', $cmd);
				$cmd = explode(' ', ltrim($cmd, ' '));				// /!\ Garder l'espace à droite pour le cas #0.1

				if($this->_debug) {
					$debug = print_r($cmd, true);
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {parts}:'.PHP_EOL.$debug, 'orange');
				}
				// --------------------
			}
			else {
				$cmd = array('');
			}
		}

		protected function _setup(&$cmd)
		{
			$cmdIsIncomplete = false;
			$cmdIsAvailable = false;
			$cmdIsComplete = false;
			$cmdPosition = $this->_defCommands;

			$cmdCounter = count($cmd);

			$retryMode = false;																			// Voir #1.1
			$strictCmd = ($cmdCounter > 0 && end($cmd) === '');											// Voir #0.1
			$forceKey = ($cmdCounter > 1);																// Voir #0.1
			$bestEffort = (count($this->_inlineArgs) > 0 || count($this->_outlineArgs) > 0);			// Voir #0.1

			if($this->_debug) {
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {strict cmd}:'.PHP_EOL. (int) $strictCmd, 'orange');
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {force key}:'.PHP_EOL. (int) $forceKey, 'orange');
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {best effort}:'.PHP_EOL. (int) $bestEffort, 'orange');
			}

			// ID #0
			foreach($cmd as $index => &$cmdPart)
			{
				if($this->_debug) {
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {part}:'.PHP_EOL.$cmdPart, 'orange');
				}

				if(Tools::is('array', $cmdPosition))
				{
					/** ID #0.1
					  *
					  * /!\ results doit être trié par natcasesort au préalable afin d'éviter des traitements inutiles
					  *
					  * Il ne faut pas appliquer array_unique car dans certains cas on peut avoir des doublons:
					  * array('help', 'help' => 'me') --> On a 2 choix, help et help mais l'un est une commande complète et l'autre non
					  * Dans ce cas là, on ne sait quel commande souhaite l'utilisateur, on ne doit donc pas rajouter d'espace à la fin
					  *
					  * Le strict mode permet d'indiquer une recherche strict pour la commande: ^([cmd])$
					  * Il ne doit être appliqué que sur l'avant dernier élément, donc count-2 ou index+2
					  * [OK] {xx} [ssh ]				--> [ssh one ] === [ssh one ]
					  *
					  * Le forceKey mode permet d'indiquer une recherche exclusive sur les clés et non sur les valeurs
					  * Il ne doit être appliqué que lorsque la commande est composée et jamais sur le dernier élément
					  * [OK] {xx} [help ]				--> [help me] === [help me]
					  *
					  * le bestEffort mode permet une toute derniere recherche afin d'essayer d'isoler la commande souhaitée
					  * [OK] {xx} [cd "toto"]			--> [cd "toto"] !== [cd "toto"]
					  *
					  * Lorsque plus d'une commande existe avec le début identique alors il ne faut pas se déplacer sur la commande la plus courte
					  * C'est à l'utilisateur d'ajouter manuellement un espace à droite pour indiquer quelle commande il souhaite utiliser et forcer
					  * ssh + sshd
					  * java + javaws
					  */
					$strictMode = (($index+2) === $cmdCounter && $strictCmd);
					$forceKeyMode = (($index+1) < $cmdCounter && $forceKey);
					$bestEffortMode = $bestEffort;

					searchCommands:
					$results = $this->_getAllCommands($cmdPosition, $cmdPart, $strictMode, $forceKeyMode, $bestEffortMode);

					if($this->_debug) {
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {strictMode}:'.PHP_EOL. (int) $strictMode, 'orange');
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {forceKeyMode}:'.PHP_EOL. (int) $forceKeyMode, 'orange');
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {bestEffortMode}:'.PHP_EOL. (int) $bestEffortMode, 'orange');
					}

					if($this->_debug)
					{
						/*$debug = var_export($cmdPosition, true);
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [POSITION] {original}:'.PHP_EOL.$debug, 'orange');*/

						$debug = print_r($results, true);
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [RESULTS] {original}:'.PHP_EOL.$debug, 'orange');
					}

					switch(count($results))
					{
						case 0:		// ID #1
						{
							/** ID #1.1
							  *
							  * Solution de secours au cas où l'utilisateur rentrerait une commande complète avec un espace à la fin mais sans arguments
							  * [OK] {xx} [cd ]				--> [cd] === [cd]
							  *
							  * Solution de secours au cas ou l'utilisateur rentrerait une commande complète avec un argument inline et sans espace à la fin
							  * [OK] {xx} [cd toto]			--> [cd toto] !== [cd toto]
							  *
							  * Solution de secours au cas ou l'utilisateur rentrerait une commande incomplète avec un argument inline et sans espace à la fin
							  * [OK] {xx} [fi toto]			--> [find toto] !== [find toto]
							  */
							if(!$retryMode) {
								$retryMode = true;
								$forceKeyMode = false;
								$bestEffortMode = true;
								goto searchCommands;
							}
							else
							{
								if($this->_debug) {
									Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND NOT FOUND', 'orange');
								}

								$cmdIsIncomplete = false;
								$cmdIsAvailable = false;
								break(2);
							}
						}
						case 1:		// ID #2
						{
							if($this->_debug) {
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS UNIQUE', 'orange');
							}

							/**
							  * /!\ cmdPosition est modifiée par #0.1
							  *
							  * Lors de la recherche de commandes correspondantes en #0.1, il est plus facile de déléguer la mise à jour de cmdPosition
							  * En effet, cmdPosition peut pointer sur un tableau et donc utiliser la clé pour avancer ou sur une valeur
							  * Il faut donc synchroniser cmdPosition et les résultats de la recherche de commandes correspondantes
							  */
							$cmdPart = current($results);

							$cmdIsIncomplete = false;
							$cmdIsAvailable = true;

							if($this->_debug) {
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {part fix}:'.PHP_EOL.$cmdPart, 'orange');
							}

							break;
						}
						default:	// ID #3
						{
							if($this->_debug) {
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS INCOMPLETE', 'orange');
							}

							foreach($results as $i => $result)
							{
								if($i === 0) {
									$baseCmdPart = str_split($result);
								}
								else
								{
									foreach(str_split($result) as $i => $letter)
									{
										if(!isset($baseCmdPart[$i])) {
											break;
										}
										elseif($baseCmdPart[$i] !== $letter) {
											array_splice($baseCmdPart, $i);
										}
									}

									if($i < count($baseCmdPart)-1) {
										array_splice($baseCmdPart, $i+1);
									}
								}
							}

							$cmdPart = implode('', $baseCmdPart);
							$this->_results = $results;

							if($this->_debug) {
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {part base}:'.PHP_EOL.$cmdPart, 'orange');
							}

							$cmdIsIncomplete = true;
							$cmdIsAvailable = false;
							break(2);
						}
					}

					if(Tools::is('string', $cmdPosition))
					{
						if($this->_debug) {
							Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS COMPLETE', 'orange');
						}

						$cmdIsComplete = true;
						break;
					}
				}
				else
				{
					if($this->_debug) {
						Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {info}:'.PHP_EOL.'COMMAND IS COMPLETE', 'orange');
					}

					$cmdPart = $cmdPosition;
					$cmdIsIncomplete = false;
					$cmdIsAvailable = true;
					$cmdIsComplete = true;
					break;
				}
			}

			/**
			  * On garde tout ce qui n'a pas été traité par #0 car à partir d'ici c'est soit invalide soit des arguments
			  * arg peut contenir soit des arguments si la commande est complète soit une partie invalide de la commande
			  */
			$unknow = array_slice($cmd, $index+1, null);

			/**
			  * On supprime tout le reste même les args car cela n'a pas été traité par #0
			  * cmd peut contenir soit une commande partielle soit complète voir même une commande invalide
			  */
			$cmd = array_slice($cmd, 0, $index+1);
			$cmd = implode(' ', $cmd);

			/**
			  * S'exécute seulement pour le cas #2
			  * On attend la fin du traitement de toutes les parties de la commande
			  */
			if(!$cmdIsIncomplete && $cmdIsAvailable && !$cmdIsComplete) {
				$cmd .= ' ';
			}

			if($this->_debug)
			{
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {cmdIsIncomplete}:'.PHP_EOL.(int) $cmdIsIncomplete, 'orange');
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {cmdIsAvailable}:'.PHP_EOL.(int) $cmdIsAvailable, 'orange');
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [SYS] {cmdIsComplete}:'.PHP_EOL.(int) $cmdIsComplete, 'orange');

				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {final}:'.PHP_EOL.'|'.$cmd.'|', 'orange');

				$debug = print_r($unknow, true);
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [CMD] {unknow}:'.PHP_EOL.$debug, 'orange');

				$debug = print_r($this->_results, true);
				Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [RESULTS] {final}:'.PHP_EOL.$debug, 'orange');
			}

			/**
			  * On ne doit pas autoriser l'ajout d'arguments tant que la commande n'est pas complète
			  * Donc si on arrive sur une commande finale, alors on traite les arguments inline et outline
			  *
			  * ls mon/chemin/a/lister
			  * cd mon/chemin/ou/aller
			  * find ou/lancer/ma/recherche
			  */
			if($cmdIsComplete)
			{
				if(array_key_exists($cmd, $this->_defInlineArgs) && (count($unknow) > 0 || count($this->_inlineArgs) > 0))
				{
					$this->_inlineArgs = array_merge($unknow, $this->_inlineArgs);
					$inlineArgs = implode(' ', $this->_inlineArgs);

					if(Tools::is('array', $this->_defInlineArgs[$cmd]))
					{
						if(count($this->_results) === 0)
						{
							$this->_results = $this->_getAllArguments($cmd, $this->_defInlineArgs, $this->_inlineArgs, $this->_arguments);

							if($this->_debug) {
								$debug = print_r($this->_results, true);
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [RESULTS] {inlineArgs autocompletion}:'.PHP_EOL.$debug, 'orange');
							}
						}
					}
					elseif(preg_match($this->_defInlineArgs[$cmd], $inlineArgs)) {
						$this->_arguments = array_merge($this->_inlineArgs, $this->_arguments);			// /!\ inline + empty
					}
				}

				if(array_key_exists($cmd, $this->_defOutlineArgs) && count($this->_outlineArgs) > 0)
				{
					$outlineArgs = implode(' ', $this->_outlineArgs);

					if(Tools::is('array', $this->_defOutlineArgs[$cmd]))
					{
						if(count($this->_results) === 0)
						{
							$this->_results = $this->_getAllArguments($cmd, $this->_defOutlineArgs, $this->_outlineArgs, $this->_arguments);

							if($this->_debug) {
								$debug = print_r($this->_results, true);
								Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [RESULTS] {outlineArgs autocompletion}:'.PHP_EOL.$debug, 'orange');
							}
						}
					}
					elseif(preg_match($this->_defOutlineArgs[$cmd], $outlineArgs)) {
						$this->_arguments = array_merge($this->_arguments, $this->_outlineArgs);			// /!\ inline + outline
					}
				}

				if($this->_debug) {
					$debug = print_r($this->_arguments, true);
					Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] '.__LINE__.' [ARGS] {final}:'.PHP_EOL.$debug, 'orange');
				}
			}

			$this->_command = $cmd;

			$this->_cmdIsIncomplete = $cmdIsIncomplete;
			$this->_cmdIsAvailable = $cmdIsAvailable;
			$this->_cmdIsComplete = $cmdIsComplete;

			return ($cmdIsIncomplete || $cmdIsAvailable || $cmdIsComplete);
		}

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
				if(Tools::is('string', $commandKey))
				{
					if($cmdPart === "" || preg_match('#^('.preg_quote($cmdPart, "#").')'.$endFlag.'#i', $commandKey))
					{
						$cmds[] = $commandKey;
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
				elseif(!$forceKey && Tools::is('string', $commandValue))
				{				
					if($cmdPart === "" || preg_match('#^('.preg_quote($cmdPart, "#").')'.$endFlag.'#i', $commandValue)) {
						$cmds[] = $commandValue;
						$cmdKey = $commandKey;
					}
				}
			}
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
					if($bestEffort && ($cmdKey = array_search($cmdPart, $cmds, true)) !== false) {
						$cmds = array($cmdPart);
						$commands = $commands[$cmdKey];
					}
					else {
						natcasesort($cmds);
						$cmds = array_values($cmds); // /!\ Reindexe correctement
						// /!\ Ne pas appliquer array_unique, voir #0.1
					}
				}
			}
			// ----------------------------

			return $cmds;
		}

		protected function _getAllArguments($cmd, $defArgs, $userArgs, &$argsFiltered)
		{
			$argsAvailable = array();

			foreach($userArgs as $index => $userArg)
			{
				if(isset($defArgs[$cmd][$index]))
				{
					$defArg = $defArgs[$cmd][$index];

					if(Tools::is('array', $defArg))
					{
						$results = preg_grep('#^('.preg_quote($userArg, '#').')#i', $defArg);
						$results = array_values($results);
						$resultsCounter = count($results);

						if($resultsCounter === 0) {
							$argsAvailable = $defArg;
							$argsFiltered[] = $userArg;
							break;
						}
						elseif($resultsCounter === 1) {
							$argsFiltered[] = $results[0];
						}
						elseif(in_array($userArg, $results, true)) {
							$argsFiltered[] = $userArg;
						}
						else {
							$argsFiltered[] = $userArg;
							$argsAvailable = $results;
							break;
						}
					}
					else
					{
						if(preg_match($defArg, $userArg)) {
							$argsFiltered[] = $userArg;
						}
						else {
							break;
						}
					}
				}
			}

			return $argsAvailable;
		}

		public function getCommand()
		{
			return $this->_command;
		}

		public function getArguments()
		{
			return $this->_arguments;
		}

		public function getResults()
		{
			return $this->_results;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'cmd':
				case 'command':
					return $this->getCommand();
				case 'arg':
				case 'args':
				case 'argument':
				case 'arguments':
					return $this->getArguments();
				case 'cmds':
				case 'commands':
				case 'result':
				case 'results':
					return $this->getResults();
				case 'cmdIsIncomplete':
					return $this->_cmdIsIncomplete;
				case 'cmdIsAvailable':
					return $this->_cmdIsAvailable;
				case 'cmdIsComplete':
					return $this->_cmdIsComplete;
				default:
					throw new Exception('This attribute does not exist', E_USER_ERROR);
			}
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;
			return $this;
		}
	}