<?php
	namespace Cli\Shell\Program;

	use SplFileInfo;
	use FilesystemIterator;

	use Core as C;

	use Cli as Cli;

	abstract class Program extends Main
	{
		/**
		  * @var Cli\Terminal\Main
		  */
		protected $_TERMINAL;

		/**
		  * @var Cli\Shell\Main
		  */
		protected $_SHELL;

		/**
		  * @var Cli\Results
		  */
		protected $_RESULTS;


		public function __construct(Cli\Shell\Main $SHELL)
		{
			$this->_SHELL = $SHELL;
			$this->_TERMINAL = $SHELL->terminal;
			$this->_CONFIG = $SHELL->config;
			$this->_RESULTS = $SHELL->results;
		}

		/**
		  * Affiche les informations d'un seul type d'éléments ou d'objets
		  * Le code doit pouvoir fonctionner sur un tableau simple ou sur un tableau d'objets
		  */
		protected function _printInformations($type, $items, $title = false)
		{
			if($items !== false && C\Tools::is('array&&count>0', $items))
			{
				if($this->_SHELL->isOneShotCall()) {
					return true;
				}

				$results = array();

				if($title === false)
				{
					if(array_key_exists($type, $this->_PRINT_TITLES)) {
						$title = $this->_PRINT_TITLES[$type];
					}
					else {
						$title = 'INFORMATIONS';
					}
				}

				$this->_SHELL->EOL()->print($title, 'black', 'white', 'bold');

				/**
				  * /!\ item peut être un objet donc il faut que le code qui le concerne puisse fonctionner sur un objet
				  * Par exemple: array_key_exists ne peut pas fonctionner, mais isset oui grâce à __isset
				  */
				foreach($items as $index => $item)
				{
					/**
					  * Il faut réinitialiser $infos pour chaque item
					  * Permet aussi de garder l'ordre de _PRINT_FIELDS
					  */
					$infos = array();

					foreach($this->_PRINT_FIELDS[$type] as $key => $format)
					{
						// /!\ Code compatible array et object !
						if((is_array($item) && array_key_exists($key, $item)) || isset($item[$key]))
						{
							$field = $item[$key];

							if(is_object($field))
							{
								if(method_exists($field, '__toString')) {
									$field = (string) $field;
								}
								elseif(method_exists($field, 'toArray')) {
									$field = $field->toArray();
								}
							}
							elseif(C\Tools::is('bool', $field) || $field === null) {
								continue;	// On ne peut pas savoir si on doit caster en int ou string
							}

							$field = vsprintf($format, $field);

							switch($key)
							{
								case 'header':
									$field = $this->_SHELL->format($field, 'green', false, 'bold');
									break;
							}

							$infos[] = $field;
						}
					}

					if(count($infos) > 0) {
						$results[] = $infos;
						$this->_SHELL->EOL()->print(implode(PHP_EOL, $infos), 'grey');
					}
				}

				$this->_SHELL->EOL();
				return true;
			}
			else
			{
				if($this->_SHELL->isOneShotCall()) {
					return false;
				}

				$this->_SHELL->error("Aucun élément à afficher", 'orange');
			}

			return false;
		}

		/**
		  * Permet d'afficher les informations d'un seul type d'éléments ou d'objets
		  */
		abstract public function printObjectInfos(array $args, $fromCurrentContext = true);

		/**
		  * Récupère les informations d'un seul type d'éléments ou d'objets puis les affiche
		  * Le code doit pouvoir fonctionner sur un tableau simple ou sur un tableau d'objets
		  */
		protected function _printObjectInfos(array $cases, array $args, $fromCurrentContext = true)
		{
			if(isset($args[0]))
			{
				foreach($cases as $type => $method)
				{
					$callable = array($this, $method);
					$objects = call_user_func($callable, $args[0], $fromCurrentContext);

					if(count($objects) > 0) {
						$objectType = $type;
						break;
					}
				}

				if(isset($objectType)) {
					$status = $this->_printInformations($objectType, $objects);
					return array($status, $objectType, $objects);
				}
			}

			$this->_SHELL->deleteWaitingMsg();		// Garanti la suppression du message
			return false;
		}

		/**
		  * Permet d'afficher les informations de plusieurs types d'éléments ou d'objets
		  */
		public function printObjectsList($context = null)
		{
			$this->_SHELL->displayWaitingMsg();
			$objects = $this->_getObjects($context);
			return $this->_printObjectsList($objects);
		}

		/**
		  * Récupère les informations de l'ensemble des éléments ou des objets
		  *
		  * @todo optimiser garder en cache en fonction de context
		  *
		  * @param string $context Context/path where retrieve objects
		  * @param array $args Arguments from commands (optionnal)
		  * @return array Objects
		  */
		abstract protected function _getObjects($context = null, array $args = null);

		/**
		  * Affiche les informations de plusieurs types d'éléments ou d'objets
		  * Le code doit pouvoir fonctionner sur un tableau simple ou sur un tableau d'objets
		  */
		protected function _printObjectsList(array $objects)
		{
			if(!$this->_SHELL->isOneShotCall())
			{
				foreach($objects as $type => &$items)
				{
					if(count($items) > 0)
					{
						$this->_SHELL->EOL()->print($this->_LIST_TITLES[$type], 'black', 'white', 'bold');

						/**
						  * /!\ L'ordre de base dans items est conservé ce qui rend le résultat incertain
						  * Préférer l'utilisation de la méthode C\Tools::arrayFilter qui filtre et garanti l'ordre
						  */
						//$item = array_intersect_key($item, array_flip($this->_LIST_FIELDS[$type]['fields']));

						if($this->_LIST_FIELDS[$type]['fields'] !== false) {
							$items = C\Tools::arrayFilter($items, $this->_LIST_FIELDS[$type]['fields']);
						}

						$table = C\Tools::formatShellTable($items);
						$this->_SHELL->EOL()->print($table, 'grey')->EOL();
					}
				}
				unset($items);

				$this->_SHELL->deleteWaitingMsg();		// Garanti la suppression du message
			}

			return $objects;
		}

		// ----------------- AutoCompletion -----------------
		/**
		  * For false search, that is bad arg, return default values or nothing
		  * For null search, that is no arg (space), return default values
		  * For string search, that is a valid arg, return the values found
		  *
		  * Options return must have key for system and value for user
		  * Key are used by AutoComplete arguments to find the true argument
		  * Value are used by AutoComplete arguments to inform user all available arguments
		  * Be carreful to always return Core\StatusValue object
		  *
		  * @param string $cmd Command
		  * @param false|null|string $search Search
		  * @param string $cwd
		  * @return Core\StatusValue
		  */
		public function shellAutoC_filesystem($cmd, $search = null, $cwd = null)
		{
			$Core_StatusValue = new C\StatusValue(false, array());

			if($search === null) {
				$search = '';
			}
			elseif($search === false) {
				return $Core_StatusValue;
			}

			if($search !== DIRECTORY_SEPARATOR && substr($search, -1, 1) === DIRECTORY_SEPARATOR) {
				$search = substr($search, 0, -1);
			}

			$input = $search;
			$firstChar = substr($search, 0, 1);

			if($firstChar === DIRECTORY_SEPARATOR) {
				$mode = 'absolute';
				$base = $search;
			}
			elseif($firstChar === '~') {
				$mode = 'home';
				$homePathname = C\Tools::getHomePathname();
				$base = preg_replace('#^(~)#i', $homePathname, $search);
			}
			elseif($cwd !== null)
			{
				if(C\Tools::is('string&&!empty', $cwd) && ($cwd = realpath($cwd)) !== false) {
					$mode = 'relative';
					$workingPathname = $cwd;
				}
				else {
					throw new Exception("Current working directory '".$cwd."' is not valid", E_USER_ERROR);
				}
			}
			else {
				$mode = 'relative';
				$workingPathname = C\Tools::getWorkingPathname();
			}

			if($mode === 'relative')
			{
				if($workingPathname !== DIRECTORY_SEPARATOR) {
					$base = $workingPathname.DIRECTORY_SEPARATOR.$search;
				}
				else {
					$base = $workingPathname.$search;
				}
			}

			$path = realpath($base);

			/*$this->_SHELL->print('MODE: '.$mode.PHP_EOL, 'green');
			$this->_SHELL->print('BASE: '.$base.PHP_EOL, 'orange');
			$this->_SHELL->print('PATH: '.$path.PHP_EOL, 'orange');
			$this->_SHELL->print('SEARCH: '.$search.PHP_EOL, 'green');*/

			if($path === false)
			{
				$parts = explode(DIRECTORY_SEPARATOR, $base);
				$search = array_pop($parts);
				$base = implode(DIRECTORY_SEPARATOR, $parts);

				$parts = explode(DIRECTORY_SEPARATOR, $input);
				array_pop($parts);
				$input = implode(DIRECTORY_SEPARATOR, $parts);

				if($base === '')
				{
					switch($mode)
					{
						case 'home': {
							$base = $homePathname;
							break;
						}
						case 'relative': {
							$base = $workingPathname;
							break;
						}
						case 'absolute': {
							$base = DIRECTORY_SEPARATOR;
							$input = DIRECTORY_SEPARATOR;
							break;
						}
					}
				}
				else {
					$base = realpath($base);
				}
			}
			else {
				$base = $path;
				$search = null;
			}

			/*$this->_SHELL->print('BASE: '.$base.PHP_EOL, 'blue');
			$this->_SHELL->print('INPUT: '.$input.PHP_EOL, 'blue');
			$this->_SHELL->print('SEARCH: '.$search.PHP_EOL, 'blue');*/

			if($base !== false)
			{
				if(file_exists($base))
				{
					$SplFileInfo = new SplFileInfo($base);

					if($SplFileInfo->isLink()) {
						$LinkBase = $SplFileInfo->getRealPath();
						$SplFileInfo = new SplFileInfo($LinkBase);
					}

					if($SplFileInfo->isDir())
					{
						/**
						  * Permet d'harmoniser les répertoire avec un / à la fin
						  * Voir _shellAutoC_filesystem_browser qui fait de même
						  *
						  * /!\ Ne pas faire cette action avant ce stade puisque
						  * l'on ne sait pas si base et input sont des répertoires
						  */
						// --------------------------------------------------
						if($base !== DIRECTORY_SEPARATOR) {
							$base .= DIRECTORY_SEPARATOR;
						}

						if($input !== '' && $input !== DIRECTORY_SEPARATOR) {
							$input .= DIRECTORY_SEPARATOR;
						}
						// --------------------------------------------------

						$Core_StatusValue__browser = $this->_shellAutoC_filesystem_browser($base, $search);

						$status = $Core_StatusValue__browser->status;
						$results = $Core_StatusValue__browser->results;

						if(count($results) === 0)
						{
							// empty directory
							if($search === null) {
								$status = true;
								$results = array('');	// Workaround retourne un seul resultat avec en clé input et en valeur ''
							}
							// no result found
							else
							{
								$Core_StatusValue__browser = $this->_shellAutoC_filesystem_browser($base, null);

								$status = $Core_StatusValue__browser->status;
								$results = $Core_StatusValue__browser->results;
							}
						}
					}
					elseif($SplFileInfo->isFile()) {
						$status = true;
						$results = array('');			// Workaround retourne un seul resultat avec en clé input et en valeur ''
					}
					else {
						return $Core_StatusValue;
					}

					//$this->_SHELL->print('INPUT: '.$input.PHP_EOL, 'blue');

					$options = array();

					foreach($results as $result)
					{
						if(substr($result, -1, 1) === DIRECTORY_SEPARATOR) {
							$result = substr($result, 0, -1);
							$isDir = true;
						}
						else {
							$isDir = false;
						}

						$output = explode(DIRECTORY_SEPARATOR, $result);
						$output = end($output);

						if($isDir) {
							$output .= DIRECTORY_SEPARATOR;
						}

						$options[$input.$output] = $output;
					}

					/*$this->_SHELL->print('STATUS: '.$status.PHP_EOL, 'blue');
					$this->_SHELL->print('OPTIONS: '.PHP_EOL, 'blue');
					var_dump($options); $this->_SHELL->EOL();*/

					$Core_StatusValue->setStatus($status);
					$Core_StatusValue->setOptions($options);
				}
			}

			return $Core_StatusValue;
		}

		/**
		  * @param string $base
		  * @param null|string $search
		  * @return Core\StatusValue
		  */
		protected function _shellAutoC_filesystem_browser($base, $search = null)
		{
			$status = true;
			$results = array();

			try {
				$FilesystemIterator = new FilesystemIterator($base, FilesystemIterator::CURRENT_AS_SELF | FilesystemIterator::FOLLOW_SYMLINKS | FilesystemIterator::SKIP_DOTS);
			}
			catch(\Exception $e) {}

			if(!isset($e))
			{
				foreach($FilesystemIterator as $item)
				{
					$match = false;
					$pathname = $item->getPathname();

					//$this->_SHELL->print('PATHNAME: '.$pathname.PHP_EOL, 'red');

					if($search !== null)
					{
						$parts = explode(DIRECTORY_SEPARATOR, $pathname);
						$lastPart = end($parts);

						if(preg_match('#^('.preg_quote($search, '#').')#i', $lastPart)) {
							$match = true;
						}
					}
					else {
						$match = true;
					}

					if($match)
					{
						//$this->_SHELL->print('PATHNAME: '.$pathname.PHP_EOL, 'red');

						if($item->isDir()) {
							$status = false;
							$pathname .= DIRECTORY_SEPARATOR;
						}

						/**
						  * Workaround, bug FilesystemIterator with /
						  * //tmp	//usr	//var	//home	...
						  */
						$results[] = preg_replace('#^(//)#i', '/', $pathname);
					}
				}
			}

			return new C\StatusValue($status, $results);
		}
		// --------------------------------------------------

		public function __get($name)
		{
			switch($name)
			{
				case 'terminal': {
					return $this->_TERMINAL;
				}
				case 'shell': {
					return $this->_SHELL;
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