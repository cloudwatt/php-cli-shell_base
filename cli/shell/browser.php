<?php
	namespace Cli\Shell;

	abstract class Browser extends Shell
	{
		protected $_printBothObjectAndList = true;

		protected $_pathIds = null;
		protected $_pathApi = null;


		protected function _preLauchingShell($welcomeMessage = true)
		{
			parent::_preLauchingShell($welcomeMessage);
			$this->_moveToRoot();
		}

		protected function _routeShellCmd($cmd, array $args)
		{
			switch($cmd)
			{
				case 'ls':
				case 'll':
				{
					$isPrinted = $this->_PROGRAM->printObjectInfos($args, true);

					if(!$isPrinted || $this->_printBothObjectAndList)
					{
						if(!$isPrinted) {
							$this->deleteWaitingMsg(true);					// Fix PHP_EOL lié au double message d'attente successif lorsque la commande precedente n'a rien affichée
						}

						$path = (isset($args[0])) ? ($args[0]) : (null);
						$objects = $this->_PROGRAM->printObjectsList($path);
						$this->_RESULTS->append($objects);
					}
					break;
				}
				case 'cd':
				{
					if(isset($args[0]))
					{
						$path = $args[0];
						$path = explode('/', $path);

						if($path[0] === "" || $path[0] === '~') {
							array_shift($path);
							$this->_moveToRoot();
						}

						$this->_moveToPath($path);
					}
					else {
						$this->_moveToRoot();
					}

					$this->deleteWaitingMsg();
					break;
				}
				case 'pwd':
				{
					$currentPath = $this->_getCurrentPath();
					$this->print($currentPath, 'white');
					$this->_RESULTS->append($currentPath);
					break;
				}
				default: {
					return parent::_routeShellCmd($cmd, $args);
				}
			}

			if(isset($status)) {
				$this->_routeShellStatus($cmd, $status);
			}

			return false;
		}

		protected function _moveToRoot()
		{
			array_splice($this->_pathIds, 1);
			array_splice($this->_pathApi, 1);

			$this->_PROGRAM->updatePath($this->_pathIds, $this->_pathApi);

			$this->refreshPrompt();
			return $this->_pathApi[0];
		}

		protected function _moveToPath($path)
		{
			$this->browser($this->_pathIds, $this->_pathApi, $path);
			$this->_PROGRAM->updatePath($this->_pathIds, $this->_pathApi);

			$this->refreshPrompt();
			return end($this->_pathApi);
		}

		public function refreshPrompt()
		{
			$currentPath = $this->_getCurrentPath();
			$this->_TERMINAL->setShellPrompt($currentPath);
			return $this;
		}

		abstract public function browser(array &$pathIds, array &$pathApi, $path);

		/**
		  * Doit être compatible avec avec des root non égaux à DIRECTORY_SEPARATOR
		  * Example: Root de Windows peut être C: donc il faut y ajouter DIRECTORY_SEPARATOR
		  *
		  * @return string Pathname
		  */
		protected function _getCurrentPath()
		{
			$pathname = '';

			foreach($this->_pathApi as $pathApi)
			{
				$pathname .= $pathApi->getObjectLabel();

				if($pathname !== DIRECTORY_SEPARATOR) {
					$pathname .= DIRECTORY_SEPARATOR;
				}
			}

			return $pathname;
		}
	}