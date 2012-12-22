<?php

require 'ZandronumProcessor.php';

class Zandronum
{
	private $processor;

	public function __construct()
	{
		$this->processor = new ZandronumProcessor();
	}
	
	public function cook_challenge()
	{
		return $this->processor->cook_challenge();
	}
	
	public function process_answer($challengeIn, &$serverData)
	{
		return $this->processor->process_answer($challengeIn, $serverData);
	}
	
	public function get_protocol()
	{
		return 'udp';
	}

}

?>