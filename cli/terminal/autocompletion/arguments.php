<?php
	namespace Cli\Terminal;

	use Closure;

	use Core as C;

	class Autocompletion_Arguments extends Autocompletion_Abstract implements \Countable
	{
		/**
		  * @var array
		  */
		protected $_defInlineArgs;

		/**
		  * @var array
		  */
		protected $_defOutlineArgs;

		/**
		  * @var string
		  */
		protected $_command = null;

		/**
		  * @var int
		  */
		protected $_position = 0;

		/**
		  * @var bool
		  */
		protected $_acWithOption = true;

		/**
		  * @var bool
		  */
		protected $_acWithSpace = true;

		/**
		  * @var array
		  */
		protected $_quoteArgs = array();

		/**
		  * @var array
		  */
		protected $_inlineArgs = array();

		/**
		  * @var array
		  */
		protected $_outlineArgs = array();

		/**
		  * @var array
		  */
		protected $_arguments = array();

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


		public function __construct(array $defInlineArgs = array(), array $defOutlineArgs = array())
		{
			$this->_defInlineArgs = $defInlineArgs;
			$this->_defOutlineArgs = $defOutlineArgs;
		}

		public function setInlineArg($name, $value)
		{
			$this->_defInlineArgs[$name] = $value;
			return $this;
		}

		public function hasDefArguments()
		{
			return ($this->hasDefInlineArgs() || $this->hasDefOutlineArgs());
		}

		public function hasDefInlineArgs()
		{
			$command = $this->getCommand();

			if(C\Tools::is('string&&!empty', $command)) {
				return array_key_exists($command, $this->_defInlineArgs);
			}
			else {
				return (count($this->_defInlineArgs) > 0);
			}
		}

		public function hasDefOutlineArgs()
		{
			$command = $this->getCommand();

			if(C\Tools::is('string&&!empty', $command)) {
				return array_key_exists($command, $this->_defOutlineArgs);
			}
			else {
				return (count($this->_defOutlineArgs) > 0);
			}
		}

		/**
		  * @param string $command Command to register
		  * @return $this
		  */
		public function setCommand($command)
		{
			if(C\Tools::is('string&&!empty', $command)) {
				$this->_command = $command;
			}

			return $this;
		}

		/**
		  * @return null|string Command registered
		  */
		public function getCommand()
		{
			return $this->_command;
		}

		/**
		  * @param int $position Command to register
		  * @return $this
		  */
		public function setPosition($position)
		{
			if(C\Tools::is('int&&>=0', $position)) {
				$this->_position = $position;
			}

			return $this;
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

			$this->_quoteArgs = array();

			$this->_inlineArgs = array();
			$this->_outlineArgs = array();
			$this->_arguments = array();
			$this->_options = array();
			$this->_status = false;
		}

		/**
		  * acWithOption: autocomplete (AC) with the option when there is only one option returned
		  * acWithSpace: autocomplete (AC) with space at the end of the command for some requirements
		  */
		public function _($args, $acWithOption = true, $acWithSpace = true)
		{
			$this->_init();

			$this->_acWithOption = (bool) $acWithOption;
			$this->_acWithSpace = (bool) $acWithSpace;

			try {
				$status = $this->_prepare($args);
			}
			catch(\Exception $e) {
				if($this->_debug) { throw $e; }
				$status = false;
			}

			if($status)
			{
				$status = $this->_setup();

				if($this->_debug) {
					C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [SYS] {status}:'.PHP_EOL.(int) $status, 'orange');
				}
			}

			return $status;
		}

		protected function _prepare($args)
		{
			if(C\Tools::is('string&&!empty', $args))
			{
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
				$pattern = '#(?:(?:(?<!\\\\)(\'|")(?:(?:\\\\\1|[^\1])*?)($|\1))|((?:[^\s\'"]|(?<=\\\\)[\s\'"])+))+#is';
				$regexStatus = (preg_match_all($pattern, $args, $matches, PREG_SET_ORDER) !== false);

				if($regexStatus !== false && count($matches) > 0)
				{
					$index = 0;
					$isOutlineArg = false;
					$matches = array_slice($matches, $this->_position, null);

					/**
					  * Permet de détecter l'espace ajouté par l'utilisateur à la fin des arguments
					  *
					  * Si l'utilisateur termine les arguments par le début d'un argument outline suivi d'un espace alors
					  * celui-ci pourra permettre l'enregistrement de l'argument outline sinon l'argument outline sera ignoré
					  *
					  * /!\ Trés important, la valeur null permet d'indiquer plus tard que c'est un espace à la fin et non un argument vide!
					  *
					  * Cas particulier pour les arguments outline:
					  * Puisque un argument outline sans valeur peut exister, alors un espace a la fin ne changera rien.
					  * L'argument outline aura toujours null comme valeur qu'il y ait ou non un espace à la fin.
					  *
					  * Example:
					  * find  "to"to" -name "tu -tu "  "titi -tata"
					  * find  "to"to" -name "tu -tu "  "titi -tata" "
					  */
					if(substr($args, -1, 1) === ' ') {
						$matches[] = array(null, null);
					}

					$matches = $this->_argumentsCleaner($matches);

					foreach($matches as $matchInfos)
					{
						$match = $matchInfos[0];
						$quote = $matchInfos[1];

						if(substr($match, 0, 1) === '-')
						{
							$isOutlineArg = true;
							$outlineArg = $match;

							/**
							  * /!\ Important pour les args outline sans datas (run job --continueOnError)
							  */
							$this->_outlineArgs[$outlineArg] = null;
							$this->_quoteArgs[$outlineArg] = null;

							continue;
						}
						else
						{
							if($match !== null) {
								$match = preg_replace('#(?<!\\\\)(\'|")((?:\\\\\1|[^\1])*?)($|\1)#is', '\2', $match);
								$match = preg_replace('#\\\\(\s|\'|")#is', '\1', $match);
							}

							if($isOutlineArg)
							{
								$argKey = (string) $outlineArg;
								$this->_outlineArgs[$argKey] = $match;

								$isOutlineArg = false;
								$outlineArg = null;
							}
							else
							{
								$argKey = $index;
								$this->_inlineArgs[] = $match;

								/**
								  * A cause des arguments outline, on ne peut pas se baser sur l'index du foreach
								  */
								$index++;
							}

							if(C\Tools::is('string&&!empty', $quote)) {
								$this->_quoteArgs[$argKey] = $quote;
							}
							else {
								$this->_quoteArgs[$argKey] = null;
							}
						}
					}

					if($this->_debug)
					{
						$debug = print_r($this->_inlineArgs, true);
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [ARGS] {inline}:'.PHP_EOL.$debug, 'orange');

						$debug = print_r($this->_outlineArgs, true);
						C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [ARGS] {outline}:'.PHP_EOL.$debug, 'orange');
					}

					return true;
				}
				else {
					throw new Exception("Arguments '".$args."' is not valid", E_USER_ERROR);
				}
			}

			return false;
		}

		/**
		  * Nettoie les arguments outline qui ont la clé et la valeur de collés
		  *
		  * Example:
		  * find  "to"to" -name "tu -tu "  "titi -tata"
		  * find  "to"to" -name "tu -tu "  "titi -tata" "
		  */
		protected function _argumentsCleaner(array $matches)
		{
			$results = array();

			foreach($matches as $matchInfos)
			{
				$match = $matchInfos[0];

				if(substr($match, 0, 1) === '-')
				{
					$value = strpbrk($match, "'\"");

					if($value !== false)
					{
						$quote = substr($value, 0, 1);
						$key = strstr($match, $quote, true);

						$results[] = array($key, null);
						$results[] = array($value, $quote);
						continue;
					}
				}

				$results[] = $matchInfos;
			}

			return $results;
		}

		protected function _setup()
		{
			/**
			  * De base les arguments ne sont jamais obligatoires
			  * On ne peut pas savoir si un argument est obligatoire ou non
			  * De ce fait, si il n'y a aucun argument à traiter alors le statut est true
			  */
			$this->_status = true;

			if(array_key_exists($this->_command, $this->_defInlineArgs) && count($this->_inlineArgs) > 0)
			{
				$inlineArgsStatus = $this->_getInlineArguments($this->_defInlineArgs[$this->_command], $this->_inlineArgs, $this->_arguments, $this->_options, $this->_acWithOption);

				if($this->_debug) {
					$debug = print_r($this->_options, true);
					C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [RESULTS] {inlineArgs autocompletion}:'.PHP_EOL.$debug, 'orange');
				}

				$this->_status = $this->_status && $inlineArgsStatus;
			}

			if(array_key_exists($this->_command, $this->_defOutlineArgs) && count($this->_outlineArgs) > 0)
			{
				$outlineArgsStatus = $this->_getOutlineArguments($this->_defOutlineArgs[$this->_command], $this->_outlineArgs, $this->_arguments, $this->_options, $this->_acWithOption);

				if($this->_debug) {
					$debug = print_r($this->_options, true);
					C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [RESULTS] {outlineArgs autocompletion}:'.PHP_EOL.$debug, 'orange');
				}

				$this->_status = $this->_status && $outlineArgsStatus;
			}

			if($this->_debug) {
				$debug = print_r($this->_arguments, true);
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [ARGS] {final}:'.PHP_EOL.$debug, 'orange');
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [ARGS] {status}:'.PHP_EOL. (int) $this->_status, 'orange');
			}

			return $this->_status;
		}

		protected function _getInlineArguments($defArg, array $userArgs, array &$filteredArgs, array &$availableArgs, $acWithOption)
		{
			$status = true;

			if(C\Tools::is('string', $defArg))
			{
				$_userArgs = array();

				// /!\ Ne pas utiliser array_reduce a cause d'un bug
				foreach($userArgs as $argKey => $userArg)
				{
					/**
					  * Dans ce mode où la définition des arguments est une regex alors on ne peut pas traiter un espace à la fin des arguments
					  */
					if($userArg !== null)
					{
						/**
						  * Pour la comparaison complète avec une expression régulière alors les arguments sont automatiquement protégés par "
						  */
						$userArg = '"'.str_ireplace('"', '\"', $userArg).'"';
						$_userArgs[] = $userArg;
					}
				}

				if(count($_userArgs) > 0)
				{
					$_userArgs = implode(' ', $_userArgs);

					if(!preg_match($defArg, $_userArgs)) {
						$status = false;
					}
				}

				/**
				  * Permet de gérer le(s) espace(s) après un argument. Il faut ignorer ces "faux" arguments
				  * Un espace peut être ajouté automatiquement par l'autocompletion ou par l'utilisateur directement
				  *
				  * Ne filtrer que les valeurs null correspondantes à un espace à la fin des arguments
				  */
				$userArgs = array_filter($userArgs, function($item) {
					return ($item !== null);
				});

				$filteredArgs = array_merge($filteredArgs, $userArgs);
				// /!\ Priorité à ce qui existe déjà dans $filteredArgs
			}
			else
			{
				/**
				  * Permet de garantir que si la def de l'arg est un object alors on cast pour obtenir:
				  * array(0 => [object])
				  */
				$defArg = (array) $defArg;

				$index = 0;
				$argCounter = count($userArgs);
				$autoCompletion = false;															// Voir #2.1
				//$autoCompletion = (end($userArgs) !== null);										// Voir #2.1

				/**
				  * autoCompletion & acWithOption
				  * Voir _setupAllArguments
				  */

				/**
				  * Ne pas arrêter le traitement des args même si il en comporte des mauvais
				  * Cela permet à l'utilisateur de corriger facilement sa commande et donc les args
				  */
				foreach($userArgs as $argKey => $userArg)
				{
					$argFound = array_key_exists($argKey, $defArg);

					if($argFound)
					{
						$autoCompletionMode = $autoCompletion;
						//$isLastItem = (($index+1) === $argCounter);
						//$autoCompletionMode = (!$isLastItem || $autoCompletion);					// Voir #2.1

						try {
							$setupStatus = $this->_setupAllArguments($defArg, $argKey, $userArg, $filteredArgs, $availableArgs, $autoCompletionMode, $acWithOption, false);
						}
						catch(\Exception $e)
						{
							if($this->_debug) {
								throw $e;
							}

							$status = false;
							break;
						}

						/**
						  * status doit être assigné à false dés qu'un setup échoue
						  * il ne doit pas être possible que status redevienne true
						  */
						if(!$setupStatus) {
							$status = false;
						}
					}
					/**
					  * Lorsqu'un espace est ajouté à la fin, il sera traité comme un argument inline (sauf car particulier d'un argument outline sans valeur)
					  * Si aucun augument n'est possible à cette position d'index alors on ignore cet espace c'est à dire sa valeur null
					  */
					elseif($userArg !== null) {
						$status = false;
						$filteredArgs[$argKey] = $userArg;
					}

					$index++;
				}
			}

			return $status;
		}

		protected function _getOutlineArguments($defArg, array &$userArgs, array &$filteredArgs, array &$availableArgs, $acWithOption)
		{
			$status = true;

			if(C\Tools::is('string', $defArg))
			{
				$_userArgs = array();

				// /!\ Ne pas utiliser array_reduce a cause d'un bug
				foreach($userArgs as $argKey => $userArg)
				{
					/**
					  * Dans ce mode où la définition des arguments est une regex alors on ne peut pas traiter un espace à la fin des arguments
					  * Pour les arguments outline sans valeur, cela revient au même niveau traitement, on ne garde que la clé
					  */
					if($userArg !== null)
					{
						/**
						  * Pour la comparaison complète avec une expression régulière alors les arguments sont automatiquement protégés par "
						  */
						$userArg = '"'.str_ireplace('"', '\"', $userArg).'"';
						$_userArgs[] = $argKey.' '.$userArg;
					}
					else {
						$_userArgs[] = $argKey;
					}
				}

				$_userArgs = implode(' ', $_userArgs);

				if(!preg_match($defArg, $_userArgs)) {
					$status = false;
				}

				$filteredArgs = array_merge($filteredArgs, $userArgs);
				// /!\ Priorité à ce qui existe déjà dans $filteredArgs
			}
			else
			{
				/**
				  * Permet de garantir que si la def de l'arg est un object alors on cast pour obtenir:
				  * array(0 => [object])
				  */
				$defArg = (array) $defArg;

				$index = 0;
				$argCounter = count($userArgs);
				$autoCompletion = true;					// Voir #2.1

				/**
				  * autoCompletion & acWithOption
				  * Voir _setupAllArguments
				  *
				  * Cas particulier pour les arguments outline:
				  * Puisque un argument outline sans valeur peut exister, alors un espace a la fin ne changera rien.
				  * L'argument aura toujours null comme valeur. C'est pourquoi l'autoCompletion est toujours activée pour les arguments outline.
				  */

				/**
				  * Ne pas arrêter le traitement des args même si il en comporte des mauvais
				  * Cela permet à l'utilisateur de corriger facilement sa commande et donc les args
				  */
				foreach($userArgs as $keyArg => $userArg)
				{
					$argFound = true;

					$isLastItem = (($index+1) === $argCounter);
					$autoCompletionMode = $autoCompletion;

					if(!array_key_exists($keyArg, $defArg))
					{
						if($autoCompletionMode || $acWithOption)
						{
							$pattern = preg_quote($keyArg, '#');
							$defArgKeys = array_keys($defArg);
							$defArgKeys = preg_grep('#^('.$pattern.')#i', $defArgKeys);
							$defArgKeys = array_values($defArgKeys);	//preg_grep garde la correspondance des clés

							if(count($defArgKeys) === 1 && !array_key_exists($defArgKeys[0], $userArgs))
							{
								$keyArgFix = $defArgKeys[0];
								$userArgs[$keyArgFix] = $userArg;
								$this->_quoteArgs[$keyArgFix] = $this->_quoteArgs[$keyArg];

								unset($userArgs[$keyArg]);
								unset($this->_quoteArgs[$keyArg]);

								$keyArg = $keyArgFix;				// /!\ Important pour la suite du la méthode
							}
							else {
								$argFound = false;
							}
						}
						else {
							$argFound = false;
						}
					}

					if($argFound)
					{
						try {
							$setupStatus = $this->_setupAllArguments($defArg, $keyArg, $userArg, $filteredArgs, $availableArgs, $autoCompletionMode, $acWithOption, true);
						}
						catch(\Exception $e)
						{
							if($this->_debug) {
								throw $e;
							}

							$status = false;
							break;
						}

						/**
						  * status doit être assigné à false dés qu'un setup échoue
						  * il ne doit pas être possible que status redevienne true
						  */
						if(!$setupStatus) {
							$status = false;
						}
					}
					else {
						$status = false;
						$filteredArgs[$keyArg] = $userArg;
					}

					$index++;
				}
			}

			return $status;
		}

		/**
		  * autoCompletion & acWithOption
		  *
		  * Cas 1 : TAB
		  * Lorsque l'utilisateur ne renseigne pas d'argument mais qu'il n'y a qu'un choix possible alors acWithOption permet de sélectionner ce unique choix							#2.1	acWithOption = true
		  * Lorsque l'utilisateur commence a renseigner un argument et qu'il n'y a qu'un choix possible alors acWithOption permet de sélectionner ce unique choix						#2.1	acWithOption = true
		  * Lorsque l'utilisateur commence a renseigner un argument et qu'il y a plusieurs choix possibles alors acWithOption permet de pré-compléter l'argument						#3.1	acWithOption = true
		  *
		  * Cas 2 : ENTER
		  * Lorsque l'utilisateur ne renseigne pas d'argument mais qu'il n'y a qu'un choix possible alors autoCompletion NE DOIT PAS permettre de sélectionner ce unique choix			#2.1	autoCompletionMode = false
		  * Lorsque l'utilisateur commence a renseigner un argument et qu'il n'y a qu'un choix possible alors autoCompletion NE DOIT PAS permettre de sélectionner ce unique choix		#2.1	autoCompletionMode = false
		  * Lorsque l'utilisateur commence a renseigner un argument et qu'il y a plusieurs choix possibles alors autoCompletion NE DOIT PAS permettre de pré-compléter l'argument		#3.1	autoCompletionMode not used
		  */
		protected function _setupAllArguments($defArgs, $keyArg, $userArg, array &$filteredArgs, array &$availableArgs, $autoCompletionMode, $acWithOption, $allowNullArg)
		{
			$defArgs = $defArgs[$keyArg];

			/**
			  * Evite d'avoir des options ne correspondant pas au dernier argument
			  *
			  * Puisque l'on ne force pas l'arrêt du traitement des arguments, alors
			  * on doit ne garder que les options correspondant au dernier argument
			  */
			$availableArgs = array();

			if(C\Tools::is('string', $defArgs))
			{
				if($userArg !== null && preg_match($defArgs, $userArg)) {
					$filteredArgs[$keyArg] = $userArg;
					return true;
				}
				// else actions par défaut
			}
			else
			{
				/**
				  * Dans certains cas il n'est pas possible de récupérer des résultats
				  * Pour ces cas là on initialise results afin de pouvoir poursuivre le traitement
				  */
				$results = array();

				/**
				  * Mode avancé lorsque la définition des arguments est une Closure
				  * Dans ce mode, des actions en plus doivent être effectuées
				  */
				$advancedMode = false;

				if($defArgs instanceof Closure) {
					$advancedMode = true;
					$Core_StatusValue = $defArgs($this->_command, $userArg);
					$status = $Core_StatusValue->status;
					$results = $Core_StatusValue->options;
				}
				elseif(C\Tools::is('array', $defArgs))
				{
					switch(count($defArgs))
					{
						case 0:
						{
							/**
							  * Si la définition des arguments possède une déclaration vide alors on traite en arrêtant le traitement
							  * /!\ Ne pas effectuer les actions par défaut, il faut supprimer l'argument correspondant à l'index actuel
							  *
							  * Example:
							  * command => array(0 => array())
							  */

							/**
							  * Ne pas arrêter le traitement afin que l'utilisateur corrige lui même
							  * Pour arrêter le traitement des arguments: throw new Exception
							  */
							break;
							// --> actions par défaut
						}
						/**
						  * Ne pas forcer l'unique argument définit car cela pourrait engendrer des actions non souhaitées par l'utilisateur
						  */
						/*case 1: {
							$results = $defArgs;
							break;
						}*/
						default:
						{
							if($userArg !== null) {
								$results = preg_grep('#^('.preg_quote($userArg, '#').')#i', $defArgs);
							}
							else {
								$results = $defArgs;
							}
						}
					}
				}
				elseif($allowNullArg && $defArgs === null) {
					$filteredArgs[$keyArg] = $userArg;
					return true;
				}
				else
				{	
					/**
					  * Ne pas arrêter le traitement afin que l'utilisateur corrige lui même
					  * Pour arrêter le traitement des arguments: throw new Exception
					  */
					// --> actions par défaut
				}

				$resultsCounter = count($results);

				switch($resultsCounter)
				{
					case 0: {		// ID #1
						// Actions par défaut
						break;
					}
					case 1:			// ID #2
					{
						/** ID #2.1
						  *
						  * Permet d'indiquer si il faut ou non compléter automatiquement lorsque la recherche ne retourne
						  * qu'un unique résultat: En mode ENTREE il ne faut pas mais en mode TAB il le faut.
						  *
						  * CAS 1
						  * Tous les arguments sauf le dernier si celui-ci est vide:
						  * Forcer l'autocompletion (autoCompletionMode = true) afin de compléter les arguments incomplets mais uniques
						  *
						  * CAS 2
						  * Seulement le dernier argument si celui-ci est vide:
						  * Ne pas forcer l'autocompletion (autoCompletionMode = false), dépend donc exclusivement de acWithOption
						  *
						  * /!\ Le cas 1 a été désactivé pour éviter que lors d'un renseignement de chemin pour la commande CD et
						  * qu'un seul sous répertoire existe, l'autoCompletion autocomplète automatiquement l'unique choix possible.
						  * Cela générait des actions non souhaitées ce qui oblige la désactivation de cette option.
						  *
						  * /!\ Lorsque pour la commande CD l'utilisateur ajoutait un espace à la fin autoCompletionMode s'activait.
						  * Cela générait des actions non souhaitées ce qui oblige la désactivation de cette option.
						  */
						if($autoCompletionMode || $acWithOption)
						{
							/**
							  * En mode avancé, on doit faire le traitement qui suit
							  * à partir des clés et non des valeurs
							  */
							if($advancedMode) {
								$results = array_keys($results);
							}

							/**
							  * On autocomplète pour l'utilisateur car il n'y a qu'un seul résultat qui correspond
							  * donc l'utilisateur n'aurait pas d'autres choix que celui-ci
							  */
							$filteredArgs[$keyArg] = current($results);				// /!\ Index 0 incertain
							return (isset($status)) ? ($status) : (true);			// On continue le traitement car il n'y a qu'une seule possibilité
						}

						break;
					}
					default:		// ID #3
					{
						natcasesort($results);									// /!\ Correspondance key/value conservée

						/**
						  * On souhaite informer à l'utilisateur que plusieurs choix s'offre à lui !
						  * array_values Pour que availableArgs soit propre avec des index numériques
						  */
						$availableArgs = array_values($results);

						/**
						  * En mode avancé, on doit faire le traitement qui suit
						  * à partir des clés et non des valeurs
						  */
						if($advancedMode) {
							$results = array_keys($results);
							natcasesort($results);
						}

						/** ID #3.1
						  *
						  * Permet d'indiquer si il faut ou non pré-compléter automatiquement
						  * En mode ENTREE il ne faut pas mais en mode TAB il le faut.
						  */
						if($acWithOption) {
							$userArg = $this->_crossSubStr($results, $userArg, false);
						}

						if($allowNullArg || $userArg !== null) {
							$filteredArgs[$keyArg] = $userArg;
						}

						/**
						  * Lorsque l'argument renseigné correspond à un résultat du traitement alors on doit sélectionner celui-ci et continuer le traitement
						  *
						  * Exemple:
						  * 'cdautocomplete en'
						  * 'export configuration juniper_junos'
						  */
						if(in_array(mb_strtolower($userArg), array_map('mb_strtolower', $results), true)) {
							return (isset($status)) ? ($status) : (true);			// On continue le traitement car l'argument est présent dans les résultats
						}
						else
						{
							/**
							  * Ne pas arrêter le traitement afin que l'utilisateur corrige lui même
							  * Pour arrêter le traitement des arguments: throw new Exception
							  */
							return false;											// On continue le traitement pour ne pas effectuer les actions par défaut
						}
					}
				}
			}

			// Actions par défaut !
			// --------------------
			// On propose à l'utilisateur la liste des arguments disponibles
			if($defArgs instanceof Closure) {
				$Core_StatusValue = $defArgs($this->_command, false);
				$availableArgs = $Core_StatusValue->options;
				$availableArgs = array_values($availableArgs);
			}
			elseif(C\Tools::is('array&&count>0', $defArgs)) {
				$availableArgs = $defArgs;
			}

			/**
			  * On rajoute l'argument de l'utilisateur même si il n'est pas valide 
			  */
			if($allowNullArg || $userArg !== null) {
				$filteredArgs[$keyArg] = $userArg;
			}

			if($this->_debug)
			{
				$debug = print_r($availableArgs, true);
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [ARGS] {setupAllArguments}:'.PHP_EOL.$debug, 'orange');

				$debug = print_r($filteredArgs, true);
				C\Tools::e(PHP_EOL.'DEBUG [AUTOCOMPLETION] {ARGUMENTS} '.__LINE__.' [RESULTS] {setupAllArguments}:'.PHP_EOL.$debug, 'orange');
			}
			// --------------------

			/**
			  * De base les arguments ne sont jamais obligatoires
			  * On ne peut pas savoir si un argument est obligatoire ou non
			  * De ce fait, si il n'y a aucun argument à traiter alors le statut est true
			  */
			return ($userArg === null);
		}

		public function count()
		{
			return count($this->getOptions());
		}

		public function getArguments()
		{
			return $this->_arguments;
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
			$arguments = $this->getArguments();

			if(count($arguments) > 0)
			{
				foreach($arguments as $argKey => &$argument)
				{
					$quote = $this->_quoteArg($argKey, $argument);

					if($quote !== false)
					{
						switch($quote)
						{
							case null:
							case '\\': {
								$argument = preg_replace('#(\s|\'|")#i', '\\\\\1', $argument);
								break;
							}
							default: {
								$argument = '"'.str_ireplace('"', '\"', $argument).'"';
							}
						}
					}

					/**
					  * Outline argument
					  */
					if(C\Tools::is('string', $argKey))
					{
						if($argument !== null) {
							$argument = $argKey.' '.$argument;
						}
						else {
							$argument = $argKey;
						}
					}
				}
				unset($argument);

				$arguments = implode(' ', $arguments);

				if($this->_acWithSpace && $this->getStatus() && count($this) <= 1) {
					$arguments .= ' ';
				}

				return $arguments;
			}
			else {
				return '';
			}
		}

		protected function _quoteArg($key, $argument)
		{
			$quote = (array_key_exists($key, $this->_quoteArgs)) ? ($this->_quoteArgs[$key]) : (null);
			return ($quote !== null || ($argument !== null && (strpos($argument, "'") !== false || strpos($argument, '"') !== false || strpos($argument, ' ') !== false))) ? ($quote) : (false);
		}

		/**
		  * Retourne true seulement si le dernier argument est protégé par ' ou "
		  *
		  * @return bool
		  */
		public function lastArgIsQuoted()
		{
			$arguments = $this->getArguments();

			if(count($arguments) > 0) {
				$keys = array_keys($arguments);
				$lastKey = end($keys);
				$quote = $this->_quoteArg($lastKey, $arguments[$lastKey]);
				return ($quote === '"' || $quote === "'");
			}
			else {
				return false;
			}
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'defInlineArgs':
					return $this->_defInlineArgs;
				case 'defOutlineArgs':
					return $this->_defOutlineArgs;
				case 'hasDefArguments':
					return $this->hasDefArguments();
				case 'args':
				case 'arguments':
					return $this->getArguments();
				case 'results':
				case 'options':
					return $this->getOptions();
				case 'status':
					return $this->getStatus();
				case 'hasArguments':
					$arguments = $this->getArguments();
					return (count($arguments) > 0);
				case 'hasOptions':
					$options = $this->getOptions();
					return (count($options) > 0);
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