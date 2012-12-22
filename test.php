<?php
require 'main.php';

$servers = array(
	'127.0.0.1:10701',
	'46.4.93.42:10701',
	'46.4.93.42:10702',
	'46.4.93.42:10703',
	'46.4.93.42:10704',
	);
	
for ($attempt = 0; $attempt < 3; ++$attempt)
{
	$last_data = getServerData('Zandronum', getRandomServer($servers));
	if ($last_data != null)	break;
}
displayServerData($last_data);

?>