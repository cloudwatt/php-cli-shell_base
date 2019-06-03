<?php
	namespace Core;

	use Closure;

	class Process
	{
		const STDIN = 0;
		const STDOUT = 1;
		const STDERR = 2;

		const CHUNK_SIZE = 16384;
		const BLOCKING_TIME = 200000;

		const TIMEOUT_PIPES = 10;
		const TIMEOUT_CLOSE = 300;

		const STATUS__IS_READY = 'isReady';
		const STATUS__IS_RUNNING = 'isRunning';
		const STATUS__IS_TERMINATED = 'isTerminated';

		protected $_status;
		protected $_command;

		protected $_pid;
		protected $_pipes;
		protected $_process;

		protected $_startTime;
		protected $_lastOutputTime;

		protected $_stdout = "";
		protected $_lastOutput = "";

		protected $_stderr = "";
		protected $_lastError = "";

		protected $_exitInfos;


		public function __construct($command = null)
		{
			$this->setCommand($command);
		}

		protected function _init()
		{
			$this->_pid = null;
			$this->_pipes = null;
			$this->_process = null;

			$this->_startTime = null;
			$this->_lastOutputTime = null;

			$this->_stdout = "";
			$this->_lastOutput = "";

			$this->_stderr = "";
			$this->_lastError = "";

			$this->_exitInfos = null;

			return $this;
		}

		public function setCommand($command)
		{
			if(!$this->isRunning() && Tools::is('string&&!empty', $command)) {
				$this->_status = self::STATUS__IS_READY;
				$this->_command = $command;
				$this->_init();
			}

			return $this;
		}

		public function isReady()
		{
			return ($this->getStatus() === self::STATUS__IS_READY);
		}

		public function isRunning()
		{
			return ($this->getStatus() === self::STATUS__IS_RUNNING);
		}

		public function isTerminated()
		{
			return ($this->getStatus() === self::STATUS__IS_TERMINATED);
		}

		public function getPid()
		{
			return ($this->_pid !== null) ? ($this->_pid) : (false);
		}

		public function getStatus()
		{
			$this->_updateStatus();
			return $this->_status;
		}

		protected function _updateStatus()
		{
			if($this->_status === self::STATUS__IS_RUNNING)
			{
				try {
					$status = proc_get_status($this->_process);
				}
				catch(\Exception $e) {
					$status = false;
				}

				if($status === false || !$status['running']) {
					$this->_status = self::STATUS__IS_TERMINATED;
					$this->_exitInfos = $status;
					$this->_close();
				}
			}

			return $this;
		}

		public function getExitInfos()
		{
			return ($this->_exitInfos !== null) ? ($this->_exitInfos) : (false);
		}

		public function getExitCode()
		{
			return (($exitInfos = $this->getExitInfos()) !== false) ? ($exitInfos['exitcode']) : (false);
		}

		public function isSuccessful()
		{
			return ($this->getExitCode() === 0);
		}

		public function start($cwd = null, array $env = null)
		{
			if($this->isReady())
			{
				$this->_init();

				$descriptorspec = array(
					self::STDIN => array("pipe", "r"),			// stdin
					self::STDOUT => array("pipe", "w"),			// stdout
					self::STDERR => array("pipe", "w")			// strerr
				);

				$process = proc_open($this->_command, $descriptorspec, $this->_pipes, $cwd, $env);

				if(is_resource($process))
				{
					$this->_process = $process;
					$this->_startTime = microtime(true);
					$this->_status = self::STATUS__IS_RUNNING;
					$status = proc_get_status($this->_process);

					if($status !== false)
					{
						$this->_pid = $status['pid'];
						$this->unblockPipes();
						return true;
					}
					else {
						$this->close();
						return false;
					}
				}
				else {
					return false;
				}
			}
			else {
				return true;
			}
		}

		public function exec($command)
		{
			if($this->isRunning()) {
				$status = fwrite($this->_pipes[self::STDIN], $command);
				return ($status !== false);
			}
			else {
				return false;
			}
		}

		public function blockPipes(array $pipes = null)
		{
			foreach($this->_pipes as $std => $pipe)
			{
				if($pipes === null || in_array($std, $pipes, true)) {
					stream_set_blocking($pipe, true);
				}
			}
		}

		public function unblockPipes(array $pipes = null)
		{
			foreach($this->_pipes as $std => $pipe)
			{
				if($pipes === null || in_array($std, $pipes, true)) {
					stream_set_blocking($pipe, false);
				}
			}
		}

		public function waitingPipes(Closure $callback = null, $timeout = self::TIMEOUT_PIPES)
		{
			$timeoutMicro = microtime(true) + $timeout;

			do {
				$status = $this->_getDataPipes(true);
				$isTimeout = (microtime(true) >= $timeoutMicro);
				$hasOutput = ($this->_lastOutput !== "" || $this->_lastError !== "");
			}
			while($this->isRunning() && $status && !$isTimeout && !$hasOutput);

			if(!$status) {
				throw new Exception("Unable to get datas from pipes", E_USER_ERROR);
			}
			elseif($isTimeout) {
				throw new Exception("idle timeout (".$timeout."s) expired", E_USER_ERROR);
			}
			else
			{
				if($callback !== null) {
					return $callback($this->_lastOutput, $this->_lastError);
				}
				else {
					return array(self::STDOUT => $this->_lastOutput, self::STDERR => $this->_lastError);
				}
			}
		}

		public function waitingCustomClose(Closure $callback = null, $timeRate = 2, $timeout = self::TIMEOUT_CLOSE)
		{
			$this->_lastOutputTime = microtime(true);

			do
			{
				$startTime = microtime(true);
				$status = $this->_getDataPipes(true);
				$isTimeout = ($timeout < (microtime(true) - $this->_lastOutputTime));

				if($callback !== null) {
					$usTime = microtime(true) - $startTime;
					usleep(($timeRate * 1000000) - $usTime);
					$callback($this);
				}
			}
			while($this->isRunning() && $status && !$isTimeout);

			if(!$status) {
				throw new Exception("Unable to get datas from pipes", E_USER_ERROR);
			}
			elseif($isTimeout) {
				throw new Exception("idle timeout (".$timeout."s) expired", E_USER_ERROR);
			}
			else {
				return !$this->isRunning();
			}
		}

		public function waitingClose($timeout = self::TIMEOUT_CLOSE)
		{
			/*$this->_lastOutputTime = microtime(true);

			do {
				$status = $this->_getDataPipes(true);
				$isTimeout = ($timeout < (microtime(true) - $this->_lastOutputTime));
			}
			while($this->isRunning() && $status && !$isTimeout);

			if(!$status) {
				throw new Exception("Unable to get datas from pipes", E_USER_ERROR);
			}
			elseif($isTimeout) {
				throw new Exception("idle timeout (".$timeout."s) expired", E_USER_ERROR);
			}
			else {
				return !$this->isRunning();
			}*/

			return $this->waitingCustomClose(null, null, $timeout);
		}

		protected function _getDataPipes($blocking = true)
		{
			$w = $e = array();
			$readPipes = $this->_pipes;
			unset($readPipes[self::STDIN]);

			if(count($readPipes) > 0)
			{
				$tv_usec = ($blocking) ? (self::BLOCKING_TIME) : (0);

				try {
					$numChangedStreams = stream_select($readPipes, $w, $e, 0, $tv_usec);
				}
				catch(\Exception $e)	// stream_select(): supplied resource is not a valid stream resource
				{
					if($this->isRunning()) {
						throw $e;
					}
					else {
						$numChangedStreams = 0;
						// return true;
					}
				}

				if($numChangedStreams === false) {
					return false;
				}
				elseif($numChangedStreams > 0)
				{
					foreach($readPipes as $std => $pipe)
					{
						/**
						  * Prior PHP 5.4 the array passed to stream_select is modified and
						  * lose key association, we have to find back the key
						  */
						/*if(array_search($pipe, $this->_pipes, true) !== $std) {
							throw new Exception("STD '".$std."' mismatch", E_USER_ERROR);
						}*/

						switch($std)
						{
							case self::STDOUT: {
								$read = &$this->_stdout;
								$part = &$this->_lastOutput;
								break;
							}
							case self::STDERR: {
								$read = &$this->_stderr;
								$part = &$this->_lastError;
								break;
							}
							default: {
								continue(2);
							}
						}

						$part = "";

						do {
							$data = fread($pipe, self::CHUNK_SIZE);
							$part .= $data;
						}
						while(isset($data[0]) && isset($data[self::CHUNK_SIZE - 1]));

						$read .= $part;

						if(feof($pipe)) {
							fclose($pipe);
							unset($this->_pipes[$std]);
						}

						$this->_lastOutputTime = microtime(true);
					}

					return true;
				}
			}

			if($blocking) {
				usleep(1000);
			}

			return true;
		}

		public function stop($timeout = 10)
		{
			if($this->isRunning())
			{
				if(($pid = $this->getPid()) !== false)
				{
					$timeoutMicro = microtime(true) + $timeout;

					/**
					  * Le signal 15 ne permet pas de terminer les processus enfants
					  * On envoit donc le signal 15 à tous les processus parent et enfants
					  */
					$killStatus = proc_open("kill $(ps -o pid --no-heading --ppid ".$pid." | grep -o '[0-9]*')", array(2 => array('pipe', 'w')), $killPipes);

					if(is_resource($killStatus))
					{
						/**
						  * Bloque le script PHP tant que le pipe stderr n'est pas disponible
						  * /!\ Important afin de ne pas executer proc_terminate avant cette commande
						  */
						$killError = fgets($killPipes[2]);

						if($killError === false)
						{
							proc_terminate($this->_process, 15);

							foreach($killPipes as $pipe) {
								fclose($pipe);
							}

							proc_close($killStatus);
						}
					}

					do {
						usleep(1000);
					}
					while($this->isRunning() && microtime(true) < $timeoutMicro);

					if($this->isRunning()) {
						/**
						  * Le signal 9 permet de terminer les processus enfants
						  */
						//posix_kill($pid, 9);
						proc_terminate($this->_process, 9);
					}
				}
				else {
					throw new Exception("Unable to retrieve PID", E_USER_ERROR);
				}

				if($this->isRunning()) {
					$this->_close();
				}

				return !$this->isRunning();
			}
			else {
				return true;
			}
		}

		protected function _close()
		{
			if(is_resource($this->_process))
			{
				foreach($this->_pipes as $pipe) {
					fclose($pipe);
				}

				/**
				  * Solution de secours afin d'éviter que proc_close reste en attente
				  */
				//posix_kill($pid, 9);
				//proc_terminate($this->_process, 9);

				/**
				  * /!\ proc_close() waits for the process to terminate, and returns its exit code
				  */
				proc_close($this->_process);
			}

			return $this;
		}

		/*public function kill()
		{
			$this->stop();		// /!\ Fermeture des pipes

			if($this->getStatus()) {
				exec('kill '.$this->_pid);
				return $this->getStatus();		// /!\ reset
			}
			else {
				return true;
			}
		}*/

		public function getOutput()
		{
			return $this->_stdout;
		}

		public function getError()
		{
			return $this->_stderr;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'pid': {
					return $this->getPid();
				}
				case 'isReady': {
					return $this->isReady();
				}
				case 'isRunning': {
					return $this->isRunning();
				}
				case 'isTerminated': {
					return $this->isTerminated();
				}
				case 'status': {
					return $this->getStatus();
				}
				case 'exitCode': {
					return $this->getExitCode();
				}
				case 'exitInfos': {
					return $this->getExitInfos();
				}
				case 'command': {
					return $this->_command;
				}
				case 'stdout':
				case 'output': {
					return $this->_stdout;
				}
				case 'stderr':
				case 'error': {
					return $this->_stderr;
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}

		public function __destruct()
		{
			try {
				$this->stop();
			}
			catch(\Exception $e) {
				var_dump($e->getMessage());
			}
		}
	}