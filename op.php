<?php
require_once('config.php');
include('main.php');

if(!isset($_GET['type']))
{
	die("No operation specified.");
}

session_start();

//Generate a new token for semaphore operations (using session ID).
try
{
	$stoken = new Stoken(session_id());
}
catch(Exception $e)
{
	die(json_encode(array('data' => $e . "\n", 'status' => 0)));
}
$token = $stoken->token;

//Create new Interact object. Must be created before child process is executed.
$interact = new Interact($token, SHARED_KEY);

switch($_GET['type'])
{
	case 'start':
		//Check if user already has a process.
		$info = $interact->get_info();
		if($info['pid'] > 0 && $info['running'])
		{
			echo json_encode(array('data' => "{s}Process has already started.{/s}\n", 'status' => 1));
			exit();
		}
		
		//Use curl to execute main application and child process in background.
		$url = S_URL . "?cmd=" . urlencode(CMD) . "&timeout=" . TIMEOUT . "&utv=" . UTV . "&token=" . $token . "&sharedkey=" . urlencode(SHARED_KEY);
		exec('nohup curl -m 1 "' . $url . '" > /dev/null 2>&1 &');

		//Check for program initialization.
		for($i = 0; $i < 5; $i++)
		{
			$info = $interact->get_info();
			if($info['pid'] > 0 && $info['running'])
			{
				echo json_encode(array('data' => "{s}System message: Process started.{/s}\n", 'status' => 1));
				exit();
			}
			else
			{
				usleep(500000);
			}
		}
		echo json_encode(array('data' => "{s}Failed to start process.{/s}\n", 'status' => 0));
		break;
		
	case 'read':
		echo $interact->read();
		break;
		
	case 'write':
		if(!isset($_POST['data']))
		{
			exit();
		}
		else
		{
			echo $interact->write($_POST['data']);
		}
		break;
		
	case 'kill':
		echo $interact->kill();
		break;
		
	default:
		echo "Invalid operation.";
}
?>