<?php
//Make sure script does not time-out. However, this cannot bypass Apache (or other server) time-outs.
set_time_limit(0);

/**
 * Generates a unique token for semaphore attachment.
 *
 * @param string $password       Password for token generation. Leave blank to use session ID.
 * @throw Exception              When $this->token is 0. 
 */

class Stoken
{
	public $token;
	
	public function __construct($password)
	{
		if(strlen($password) > 0)
		{
			$this->token = crc32($password);
		}
		else
		{
			$this->token = crc32(session_id());
		}
		
		//PHP will try to spawn infinite semaphore blocks if passed 0. This is here in case no session_id() gets passed for some reason. 
		if($this->token === 0)
		{
			throw new Exception("Invalid token (0).");
		}
	}
}

/**
 * A semaphore class designed to perform an encrypted shared memory operation while maintaining the smallest lock time possible. 
 *
 * @param integer $key             Generated key for memory identification.
 * @param integer $sharedkey       The shared key incorporated during encryption/decryption.
 * @return string                  Data obtained from semaphore operation (STDIN/STDOUT).
 * @return array                   Data obtained from semaphore operation (SESSION INFO).
 * @return boolean                 Returns false when get_var is called on non-existent entity.
 */

class Semaphore
{
	private $key, $ekey;
	
	public function __construct($key, $sharedkey)
	{
		$this->key = $key;
		$this->ekey = sha1($key + $sharedkey);
	}
	
	private function encrypt($input)
	{
		$iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$encrypted_string = mcrypt_encrypt(MCRYPT_BLOWFISH, $this->ekey, utf8_encode($input), MCRYPT_MODE_ECB, $iv);
		return $encrypted_string;
	}
	
	private function decrypt($input)
	{
		$iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$decrypted_string = mcrypt_decrypt(MCRYPT_BLOWFISH, $this->ekey, $input, MCRYPT_MODE_ECB, $iv);
		return $decrypted_string;
	}
	
	public function get_var($id)
	{
		$connection = shm_attach($this->key);
		if(shm_has_var($connection, $id))
		{
			$ret = unserialize($this->decrypt(base64_decode(shm_get_var($connection, $id))));
		}
		else
		{
			$ret = false;
		}
		shm_detach($connection);
		return $ret;
	}
	
	public function has_var($id)
	{
		$connection = shm_attach($this->key);
		$ret = shm_has_var($connection, $id);
		shm_detach($connection);
		return $ret;
	}
	
	public function put_var($id, $data)
	{
		$connection = shm_attach($this->key);
		$ret = shm_put_var($connection, $id, base64_encode($this->encrypt(serialize($data))));
		shm_detach($connection);
		return $ret;
	}
	
	public function remove_var($id)
	{
		$connection = shm_attach($this->key);
		$ret = shm_remove_var($connection, $id);
		shm_detach($connection);
		return $ret;
	}
	
	public function remove()
	{
		$connection = shm_attach($this->key);
		$ret = shm_remove($connection);
		shm_detach($connection);
		return $ret;
	}
}

/**
 * Execute a persistent process that communicates with a shared memory block using semaphore.
 *
 * @param string $cmd            Command to execute.
 * @param integer $timeout       Timeout in seconds.
 * @param integer $utv           Stream select timeout in microseconds.
 * @param integer $id            Semaphore token.
 * @param string $sharekey       The shared key incorporated during encryption/decryption.
 * @throws Exception             On error.
 */
 
class Persistent
{
	public $cmd, $timeout, $pid, $utv;
	private $descriptors, $process, $pipes, $id, $session_info, $shm;
	
	public function __construct($cmd, $timeout, $utv, $id, $sharedkey)
	{
		//Use stdbuf to make the output and error streams unbuffered.
		$this->cmd = /*"stdbuf -o0 -e0 " . */ "unbuffer -p " . $cmd . " 2>&1";
		$this->timeout = $timeout * 1000000;
		$this->descriptors = array(
			0 => array('pipe', 'r'),  // STDIN
			1 => array('pipe', 'w'),  // STDOUT
			2 => array('pipe', 'w')   // STDERR
		);
		$this->utv = $utv;
		$this->id = $id;
		$this->shm = new Semaphore($id, $sharedkey);
	}
	
	//Clears semaphore block.
	public function clear_shm()
	{	
		$this->shm->put_var(1, ""); //STDIN
		$this->shm->put_var(2, ""); //STDOUT
		$this->shm->put_var(3, array("pid" => -1, "running" => false, "cmd" => "", "error" => "")); //INFO
	}
	
	public function get_pid()
	{
		return $this->pid;
	}
	
	public function get_cmd()
	{
		return htmlspecialchars($this->cmd);
	}
	
	private function is_running()
	{
		$status = proc_get_status($this->process);
		return $status['running'];
	}
	
	private function update_info()
	{
		$this->shm->put_var(3, $this->session_info);
	}
	
	//Reads data from STDOUT and STDERR into semaphore block.
	private function read_pipe()
	{
		foreach(array(1, 2) as $pipeid)
		{
			$read = array($this->pipes[$pipeid]);
			$other = NULL;
				
			$change = stream_select($read, $other, $other, 0, $this->utv);
				
			if($change > 0)
			{
				$stdout = $this->shm->get_var(2);
				do
				{
					$data = fread($this->pipes[$pipeid], 8092);
					$stdout .= $data;
				}
				while
				(
					strlen($data) > 0
				);
				$this->shm->put_var(2, $stdout);
			}
		}
	}
	
	//Writes data from semaphore block to STDIN.
	private function write_pipe()
	{
		$stdin = $this->shm->get_var(1);
		if(strlen($stdin) > 0)
		{
			$this->shm->put_var(1, "");
			fwrite($this->pipes[0], $stdin);
		}
	}
	
	//Clears semaphore block information when process ends.
	private function process_ended()
	{
		$this->pid = -1;
		$this->session_info['pid'] = -1;
		$this->session_info['running'] = false;
		$this->update_info();
		
		//Wait before purging the semaphore block.
		usleep(5000000);
		
		//Don't purge if user has tried to spawned another process.
		$status = $this->shm->get_var(3);
		if($status['pid'] < 0 && $status['running'] === false)
		{
			$this->shm->remove();
		}
		exit();
	}
	
	//Initialize a process. Process status is written to semaphore block. Throws exception on error.
	public function start()
	{
		$this->session_info = $this->shm->get_var(3);
		
		if(!isset($this->session_info['pid']) || $this->session_info['pid'] < 0)
		{
			//Clear, reset, and create.
			$this->clear_shm();
			
			$this->process = proc_open($this->cmd, $this->descriptors, $this->pipes);
			
			$this->status = proc_get_status($this->process);
			$this->pid = $this->status['pid'];
			
			//Initial update of child process information.
			$this->session_info['cmd'] = $this->cmd;
			$this->session_info['pid'] = $this->status['pid'];
			$this->update_info();
			
			//Set all streams to non-blocking mode.
			stream_set_blocking($this->pipes[0], 0);
			stream_set_blocking($this->pipes[1], 0);
			stream_set_blocking($this->pipes[2], 0);
		}
		else
		{
			throw new Exception("Your session already has a process.");
		}
		
		//If PHP cannot attach to process, try to kill it.
		if($this->process === false)
		{
			posix_kill($this->session_info['pid'], 9);
			$this->shm->remove();
			throw new Exception("Could not successfully attach to child process.");
		}

		//Update child process status.
		$this->session_info['running'] = true;
		$this->update_info();
	}
	
	//Start child process main function. Executes until timeout or child process ends. 
	public function main()
	{
		while($this->timeout > 0)
		{
			$start = microtime(true);
			
			if($this->is_running() === false)
			{
				//Read STDOUT and STDERR one last time.
				$this->read_pipe();
				
				$this->process_ended();
			}
			
			$this->read_pipe();
			$this->write_pipe();
			
			$this->timeout -= (microtime(true) - $start) * 1000000;
		}
		
		//Read STDOUT and STDERR one last time.
		$this->read_pipe();
		
		proc_terminate($this->process, 9);
		
		//Close all pipes.
		fclose($this->pipes[0]);
		fclose($this->pipes[1]);
		fclose($this->pipes[2]);

		//Close resource handle.
		proc_close($this->process);
		
		$this->process_ended();
		
		//Execute in background.
		//exec('bash -c "(sleep ' . $this->timeout . ' && kill -9 ' . $this->status['pid'] . ') > /dev/null 2>&1 &"');
	}
}

/**
 * Interacts with a child process started by Persistent using semaphore. Outputs JSON data for interpretation by client-side.
 *
 * @param integer $id            Semaphore token.
 * @param string $sharekey       The shared key incorporated during encryption/decryption.
 * @return string                JSON data containing program running status and message.
 */

class Interact
{
	private $shm, $id, $session_info;
	
	public function __construct($id, $sharedkey)
	{
		$this->shm = new Semaphore($id, $sharedkey);
		$this->refresh_info();
		
		//Check if there is a zombie semaphore (for whatever reason).
		if($this->session_info['running'] && !file_exists("/proc/{$this->session_info['pid']}"))
		{
			$this->shm->remove();
		}
	}
	
	public function get_info()
	{
		$this->refresh_info();
		return $this->session_info;
	}
	
	//Clears semaphore block.
	public function clear_block()
	{
		$this->shm->put_var(1, ""); //STDIN
		$this->shm->put_var(2, ""); //STDOUT
		$this->shm->put_var(3, array("pid" => -1, "running" => false, "cmd" => "", "error" => "")); //INFO
	}

	private function refresh_info()
	{
		$this->session_info = $this->shm->get_var(3);
	}
	
	private function is_running()
	{
		$this->refresh_info();
		
		//In case running is not set is deleted.
		return ($this->session_info['running'] === true);
	}
	
	//Read from semaphore block (STDOUT/STDERR). Returns child process data and/or system message with status.
	public function read()
	{
		$stdout = $this->shm->get_var(2);
		$this->shm->put_var(2, "");

		if($this->is_running() === false && strlen($stdout) > 0)
		{
			return json_encode(array('data' => $stdout . "{s}System message: The child process has ended.{/s}\n", 'status' => 0));
		}
		else if($this->is_running() === false && strlen($stdout) === 0)
		{
			return json_encode(array('data' => "{s}System message: No child process with an output has been detected.{/s}\n", 'status' => 0));
		}
		else
		{
			return json_encode(array('data' => $stdout, 'status' => 1));
		}
	}
	
	//Writes into semaphore block (STDIN). Returns child process status and/or system message.
	public function write($stdin)
	{
		if($this->is_running() === false)
		{
			return json_encode(array('data' => "{s}System message: No child process available to pass input to.{/s}\n", 'status' => 0));
		}
		else
		{
			$input = $this->shm->get_var(1);
			$this->shm->put_var(1, $input . $stdin);
			return json_encode(array('status' => 1));
		}
	}
	
	//Force kills a child process. Returns system message.
	public function kill()
	{
		//Verify that process exists and is still running.
		if(file_exists("/proc/{$this->session_info['pid']}") && $this->session_info['running'])
		{
			if(posix_kill($this->session_info['pid'], 9))
			{
				return json_encode(array('data' => "{s}System message: Successfully killed PID {$this->session_info['pid']}.{/s}\n", 'status' => 0));
			}
			else
			{
				return json_encode(array('data' => "{s}System message: Unable to kill PID {$this->session_info['pid']}.{/s}\n", 'status' => 0));
			}
		}
		else
		{
			return json_encode(array('data' => "{s}System message: Process has already ended.{/s}\n", 'status' => 0));
		}
	}
}
?>