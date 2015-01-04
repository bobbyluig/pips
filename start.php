<?php
require_once('config.php');
include('main.php');

//Verify all components are recieved.
if(!isset($_GET['cmd']) || !isset($_GET['timeout']) || !isset($_GET['utv']) || !isset($_GET['token']) || !isset($_GET['sharedkey']))
{
	exit();
}

//Check that the call is internal.
if($_GET['sharedkey'] !== SHARED_KEY)
{
	exit();
}

//Spawn a Persistent object.
$program = new Persistent($_GET['cmd'], $_GET['timeout'], $_GET['utv'], $_GET['token'], SHARED_KEY);

//Start the program. Ignore any exceptions (exceptions processed by another ajax process reading semaphore block).
try
{
	$program->start();
}
catch(Exception $e) {}
	
//Run main communication loop in background.
$program->main();
?>