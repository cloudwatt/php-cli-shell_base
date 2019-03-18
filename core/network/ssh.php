<?php
	namespace Core\Network;

	use Closure;

	use Core as C;

	class Ssh
	{
		const METHODS = array(
			'kex' => 'diffie-hellman-group-exchange-sha256,diffie-hellman-group-exchange-sha1,diffie-hellman-group14-sha1',
			'hostkey' => 'ssh-rsa,ssh-dss',
			'client_to_server' => array(
				'crypt' => 'aes128-ctr,aes192-ctr,aes256-ctr,aes128-cbc,aes192-cbc,aes256-cbc',
				'comp' => 'none,zlib@openssh.com,zlib',
				'mac' => 'hmac-sha2-256,hmac-sha2-512,hmac-sha1'
			),
			'server_to_client' => array(
				'crypt' => 'aes128-ctr,aes192-ctr,aes256-ctr,aes128-cbc,aes192-cbc,aes256-cbc',
				'comp' => 'none,zlib@openssh.com,zlib',
				'mac' => 'hmac-sha2-256,hmac-sha2-512,hmac-sha1'
			)
		);

		const AUTH__SSH_AGENT = 'sshAgent';
		const AUTH__CREDENTIALS = 'credentials';

		protected $_usePhpSshLib = true;

		protected $_remoteHost = false;
		protected $_remotePort = 22;
		protected $_bastionHost = false;
		protected $_bastionPort = 22;
		protected $_portForwarding = false;

		protected $_options = array();

		protected $_authMethod;
		protected $_username;
		protected $_password;

		/**
		  * @var Process
		  */
		protected $_sshProcess = null;

		/**
		  * @var resource
		  */
		protected $_sshSession = null;

		/**
		  * @var bool
		  */
		protected $_isLogged = false;


		public function __construct($remoteHost, $remotePort = 22, $bastionHost = false, $bastionPort = 22, $portForwarding = false, $usePhpSshLib = true)
		{
			$this->remoteHost($remoteHost);
			$this->remotePort($remotePort);
			$this->bastionHost($bastionHost);
			$this->bastionPort($bastionPort);
			$this->portForwarding($portForwarding);

			$this->_usePhpSshLib = ($usePhpSshLib && function_exists('ssh2_connect'));
		}

		public function remoteHost($remoteHost)
		{
			if(C\Tools::is('string&&!empty', $remoteHost)) {
				$this->disconnect();
				$this->_remoteHost = $remoteHost;
			}

			return $this;
		}

		public function remotePort($remotePort)
		{
			if(C\Tools::is('int&&>0', $remotePort) && $remotePort <= 65535) {
				$this->disconnect();
				$this->_remotePort = $remotePort;
			}

			return $this;
		}

		public function bastionHost($bastionHost = false)
		{
			if(C\Tools::is('string&&!empty', $bastionHost) || $bastionHost === false) {
				$this->disconnect();
				$this->_bastionHost = $bastionHost;
			}

			return $this;
		}

		public function bastionPort($bastionPort)
		{
			if(C\Tools::is('int&&>0', $bastionPort) && $bastionPort <= 65535) {
				$this->disconnect();
				$this->_bastionPort = $bastionPort;
			}

			return $this;
		}

		public function portForwarding($portForwarding)
		{
			if(C\Tools::is('int&&>0', $portForwarding) && $portForwarding <= 65535) {
				$this->disconnect();
				$this->_portForwarding = $portForwarding;
			}

			return $this;
		}

		public function addOption($option)
		{
			$option = preg_replace('#^(-o +)#i', '', $option);

			if(C\Tools::is('string&&!empty', $option)) {
				$this->_options[] = $option;
			}

			return $this;
		}

		public function addOptions(array $options)
		{
			foreach($options as $option) {
				$this->addOption($option);
			}

			return $this;
		}

		public function setOptions(array $options = null)
		{
			$this->_options = array();
			$this->addOptions($options);
			return $this;
		}

		public function useSshAgent($username)
		{
			if(C\Tools::is('string&&!empty', $username)) {
				$this->_authMethod = self::AUTH__SSH_AGENT;
				$this->_username = $username;
				$this->_password = null;
			}

			return $this;
		}

		public function setCredentials($username, $password)
		{
			if(C\Tools::is('string&&!empty', $username) && C\Tools::is('string&&!empty', $password)) {
				$this->_authMethod = self::AUTH__CREDENTIALS;
				$this->_username = $username;
				$this->_password = $password;
			}

			return $this;
		}

		protected function _usePhpSshLib()
		{
			return ($this->_usePhpSshLib && !$this->_isTunnel());
		}

		protected function _isTunnel()
		{
			return ($this->_bastionHost !== false && $this->_portForwarding !== false);
		}

		protected function _isReady()
		{
			return (
				$this->_authMethod !== null && $this->_remoteHost !== false &&
				($this->_bastionHost === false || $this->_portForwarding !== false) &&
				$this->_sshProcess === null && $this->_sshSession === null
			);
		}

		protected function _getOptions()
		{
			if(count($this->_options) > 0) {
				return '-o '.implode(' -o ', $this->_options);
			}
			else {
				return '';
			}
		}

		protected function _isConnected()
		{
			return ($this->_sshProcess !== null || $this->_sshSession !== null);
		}

		protected function _isLogged()
		{
			return $this->_isLogged;
		}

		protected function _getSshProcessStatus()
		{
			return ($this->_sshProcess !== null) ? ($this->_sshProcess->isRunning()) : (false);
		}

		protected function _startSshProcess()
		{
			if(!$this->_getSshProcessStatus())
			{
				if($this->_authMethod === self::AUTH__CREDENTIALS)
				{
					$setEnvStatus = putenv("SSHPASS=".$this->_password);

					if(!$setEnvStatus) {
						throw new Exception("Unable to set environment variable for SSH password", E_USER_ERROR);
					}

					$cmdPrefix = "sshpass -e ";
					$cmdSuffix = "";
				}
				else {
					$cmdPrefix = "";
					$cmdSuffix = "";
				}

				/**
				  * -q : https://bugs.debian.org/cgi-bin/bugreport.cgi?bug=134589
				  * -t -t : https://stackoverflow.com/questions/48648572/how-to-deal-with-pseudo-terminal-will-not-be-allocated-because-stdin-is-not-a-t
				  */
				if(!$this->_isTunnel()) {
					$command = $cmdPrefix."ssh -t -t -q ".$this->_getOptions()." -p ".$this->_remotePort." ".$this->_username."@".$this->_remoteHost.$cmdSuffix;
				}
				else {
					$tunnelCmd = "-L ".$this->_portForwarding.":".$this->_remoteHost.":".$this->_remotePort;
					$command = $cmdPrefix."ssh -t -t -q ".$this->_getOptions()." ".$tunnelCmd." -p ".$this->_bastionPort." ".$this->_username."@".$this->_bastionHost.$cmdSuffix;
				}

				$this->_sshProcess = new C\Process($command);
				$sshProcessStatus = $this->_sshProcess->start();

				if($sshProcessStatus)
				{
					$callback = Closure::fromCallable(array($this, '_sshProcessIsReady'));

					try {
						$sshProcessIsReady = $this->_sshProcess->waitingPipes($callback, 10);
					}
					catch(Exception $exception) {
						$sshProcessIsReady = false;
					}

					if($sshProcessIsReady) {
						$this->_isLogged = true;
						return true;
					}
					else
					{
						try {
							$isClosed = $this->_sshProcess->waitingClose(5);
						}
						catch(Exception $e) {
							$isClosed = false;
						}

						if($isClosed) {
							$stderr = $this->_sshProcess->stderr;
							$exitCode = $this->_sshProcess->exitCode;
							throw new Exception("SSH process returned exit code ".$exitCode." with message: ".$stderr, E_USER_ERROR);
						}
						elseif(isset($exception)) {
							throw $exception;
						}
					}
				}
			}
			else {
				return true;
			}

			return false;
		}

		protected function _sshProcessIsReady($stdout, $stderr)
		{
			if($stderr !== "") {
				throw new Exception("[SSH ERROR] ".$stderr, E_USER_ERROR);
			}
			else {
				return ($this->_getSshProcessStatus() && $stdout !== "");
			}
		}

		protected function _stopSshProcess()
		{
			$status = $this->_sshProcess->stop();

			if($status) {
				$this->_sshProcess = null;
				$this->_isLogged = false;
			}

			return $status;
		}

		public function connect()
		{
			if($this->_isReady())
			{
				if($this->_usePhpSshLib())
				{
					$callbacks = array(
						'debug' => array($this, '_cb_debug'),
						'ignore' => array($this, '_cb_ignore'),
						'disconnect' => array($this, '_cb_disconnect')
					);

					try {
						$result = ssh2_connect($this->_remoteHost, $this->_remotePort, self::METHODS, $callbacks);
					}
					catch(Exception $e) {
						$result = false;
					}

					if($result !== false)
					{
						$this->_sshSession = $result;

						try {
							$authStatus = $this->_sshSessionAuth();
						}
						catch(Exception $e) {
							$this->disconnect();
							$authStatus = false;
						}

						return $authStatus;
					}
					else {
						throw new Exception("Unable SSH to remote host '".$this->_remoteHost.":".$this->_remotePort."'", E_USER_ERROR);
					}
				}
				else {
					return $this->_startSshProcess();
				}
			}

			return false;
		}

		protected function _sshSessionAuth()
		{
			if($this->_isConnected() && !$this->_isLogged() && $this->_usePhpSshLib())
			{
				switch($this->_authMethod)
				{
					case self::AUTH__CREDENTIALS:
					{
						if(function_exists('ssh2_auth_password')) {
							$result = ssh2_auth_password($this->_sshSession, $this->_username, $this->_password);
						}
						else {
							throw new Exception("SSH function 'ssh2_auth_password' is missing", E_USER_ERROR);
						}

						break;
					}
					case self::AUTH__SSH_AGENT:
					{
						if(function_exists('ssh2_auth_agent')) {
							$result = ssh2_auth_agent($this->_sshSession, $this->_username);
						}
						else {
							throw new Exception("SSH function 'ssh2_auth_agent' is missing", E_USER_ERROR);
						}

						break;
					}
					default: {
						throw new Exception("Authentication method '".$this->_authMethod."' is not supported", E_USER_ERROR);
					}
				}
			}

			if($result === true) {
				$this->_isLogged = true;
			}

			return $result;
		}

		/*public function exec($command, &$stdout, &$stderr)
		{
			if($this->_isConnected() && $this->_isLogged() && !$this->_isTunnel() && C\Tools::is('string&&!empty', $command))
			{
				if($this->_usePhpSshLib())
				{
					$stdoutS = ssh2_exec($this->_sshSession , $command);

					if($stdoutS !== false)
					{
						$stderrS = ssh2_fetch_stream($stdoutS, SSH2_STREAM_STDERR);
						stream_set_blocking($stdoutS, true);
						stream_set_blocking($stderrS, true);
						$stdout = stream_get_contents($stdoutS);
						$stderr = stream_get_contents($stderrS);
						fclose($stdoutS);
						fclose($stderrS);
						return true;
					}
				}
				else
				{
					$status = $this->_sshProcess->exec($command);

					if($status) {
						$status = $this->_sshProcess->waitingPipes(Closure $callback, 10);
					}
				}
			}

			return false;
		}*/

		public function putFile($localFile, $remoteFile, $recursively = false, $fileMode = 0644)
		{
			if($this->_isConnected() && $this->_isLogged() && !$this->_isTunnel() &&
				C\Tools::is('string&&!empty', $localFile) && C\Tools::is('string&&!empty', $remoteFile) && C\Tools::is('int&&>0', $fileMode))
			{
				if($this->_usePhpSshLib()) {
					return ssh2_scp_send($this->_sshSession, $localFile, $remoteFile, $fileMode);
				}
				else {
					$recursively = ($recursively) ? ('-r') : ('');
					$command = "scp ".$this->_getOptions()." ".$recursively." -P ".$this->_remotePort." ".$localFile." ".$this->_username."@".$this->_remoteHost.":".$remoteFile;
					return $this->_scp($command);
				}
			}

			return false;
		}

		public function getFile($localFile, $remoteFile, $recursively = false)
		{
			if($this->_isConnected() && $this->_isLogged() && !$this->_isTunnel() &&
				C\Tools::is('string&&!empty', $localFile) && C\Tools::is('string&&!empty', $remoteFile))
			{
				if($this->_usePhpSshLib()) {
					return ssh2_scp_recv($this->_sshSession, $remoteFile, $localFile);
				}
				else {
					$recursively = ($recursively) ? ('-r') : ('');
					$command = "scp ".$this->_getOptions()." ".$recursively." -P ".$this->_remotePort." ".$this->_username."@".$this->_remoteHost.":".$remoteFile." ".$localFile;
					return $this->_scp($command);
				}
			}

			return false;
		}

		protected function _scp($command)
		{
			$cmdPrefix = ($this->_authMethod === self::AUTH__CREDENTIALS) ? ("sshpass -e ") : ("");

			$scpProcess = new C\Process($cmdPrefix.$command);
			$scpProcessStatus = $scpProcess->start();

			if($scpProcessStatus)
			{
				$scpProcessIsClosed = $scpProcess->waitingClose();
				$scpProcessIsStopped = $scpProcess->stop();

				if($scpProcess->stderr === '')
				{
					if($scpProcessIsClosed && $scpProcessIsStopped && $scpProcess->exitCode === 0) {
						return true;
					}
				}
				else {
					throw new Exception("An error occurred while executing SCP: ".$scpProcess->stderr, E_USER_ERROR);
				}
			}

			return false;
		}

		public function disconnect()
		{
			if($this->_isConnected())
			{
				if($this->_usePhpSshLib())
				{
					$status = ssh2_disconnect($this->_sshSession);

					if($status !== false) {
						$this->_sshSession = null;
						$this->_isLogged = false;
					}
				}
				else {
					$status = $this->_stopSshProcess();
				}

				if($status === false) {
					$host = ($this->_isTunnel()) ? ($this->_bastionHost) : ($this->_remoteHost);
					throw new Exception("Unable to disconnect to remote host '".$host."'", E_USER_ERROR);
				}
			}

			return true;
		}

		/*public function __get($name)
		{
			switch($name)
			{
				case 'stdout':
				case 'output': {
					
					break;
				}
				case 'stderr':
				case 'error': {
					
					break;
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}*/

		public function __destruct()
		{
			try {
				$this->disconnect();
			}
			catch(Exception $e) {
				var_dump($e->getMessage());
			}
		}

		protected function _cb_ignore($message)
		{
			throw new Exception("Server ignore with message: ".$message, E_USER_ERROR);
		}

		protected function _cb_debug($message, $language, $always_display)
		{
			throw new Exception("SSH debug: [".$message."] {".$language."} (".$always_display.")", E_USER_ERROR);
		}

		protected function _cb_disconnect($reason, $message, $language)
		{
			throw new Exception("Server disconnected with reason code [".$reason."] and message: ".$message, E_USER_ERROR);
		}
	}