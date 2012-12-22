<?php

require 'Zandronum/Zandronum.php';

define('REQUEST_INTERVAL', 10);	//request interval in seconds
define('REQUEST_TIMEOUT', 200);	//request timeout in milliseconds

class LockSentry
{
	private $filepath;
	
	public function __construct($path)
	{
		$this->filepath = $path;
		file_put_contents($path, time());
	}
	public function __destruct()
	{
		unlink($this->filepath);
	}
}

function doCheckTimeOut($stream)
{
	$status = stream_get_meta_data($stream);
	return $status['timed_out'];
}

function doRequest($game, $server)
{

	$filename = str_replace(':', '_', $server);
	$lockfilename = $filename.'.lock';
	
	$olddata = unserialize(@file_get_contents($filename));
	
	if (@$olddata['request_time'] + REQUEST_INTERVAL > time())	return $olddata;
	
	if (file_exists($lockfilename))	return $olddata;
	$sentry = new LockSentry($lockfilename);
	
	$protocol = $game->get_protocol();
	
	$fp = stream_socket_client($protocol.'://'.$server, $errno, $errstr);
	if ($fp === false)	return false;
	
	stream_set_blocking($fp, 0);
		
	$challengeOut = $game->cook_challenge();	
	fwrite($fp, $challengeOut);

	for ($attempt = 0; $attempt < 5; ++$attempt)
	{
		$challengeIn = stream_get_contents($fp);
		if ($challengeIn != '')	break;
		usleep(REQUEST_TIMEOUT*1000/5);
	}
	if ($challengeIn == '')	return null;
	
	$status = $game->process_answer($challengeIn, $serverInfo);

	if ($status != 'GOOD')	return $olddata;
	
	$serverInfo['ip'] = $server;
	$serverInfo['request_time'] = time();
	file_put_contents($filename, serialize($serverInfo));
		
	fclose($fp);
	
	return $serverInfo;
}

function goGetWadScreenshot($server)
{
	$wads_to_check = $server['datafiles'];
	array_unshift($wads_to_check, $server['gamedata']);
	print_r($wads_to_check);
}

function displayServerData($server)
{
	if ($server == null)
	{
		$server = array(
			'datafiles' => array('ERROR'),
			'map' => 'NOSERVER',
			);
	}
	goGetWadScreenshot($server);
	
	print_r($server);
	
	echo <<<HTML
	{$server['engine']}
	TODO: write something here
HTML;
}

function getRandomServer($servers)
{
	return $servers[mt_rand(0, count($servers)-1)];
}
function getServerData($engine, $server)
{
	static $engines = false;
	if ($engines === false)
	{
		$engines['Zandronum'] = new Zandronum;
	}
	return doRequest($engines[$engine], $server);
}


?>