<?php

/**
 * Basic server information retriever for ZAndronum servers
 * Based on code from Doomseeker by Blzut3, Zalewa and others	[http://doomseeker.drdteam.org]
 *
 * @author Const
 *
 * for use only by Const & Long]ASTS[
 */

require 'huffman.php';

class ZandronumProcessor
{
	private $huffman;

	protected function unpack2($format, $data) {
		return unpack($format, $data)[1];
	}
	protected function processString($challenge, &$flags, $flag, &$pos) {
		if (($flags & $flag) != $flag)	return null;
		$data = substr($challenge, $pos, strpos($challenge, chr(0), $pos) - $pos);
		$pos += strlen($data) + 1;
		$flags ^= $flag;
		if ($pos >= strlen($challenge) && $flags != 0)	throw new Exception('incorrect or corrupted answer');
		return $data;
	}
	protected function processByte($challenge, &$flags, $flag, &$pos) {
		if (($flags & $flag) != $flag)	return null;
		$data = ord(substr($challenge, $pos, 1));
		$pos ++;
		$flags ^= $flag;
		return $data;
	}
	protected function processWord($challenge, &$flags, $flag, &$pos) {
		if (($flags & $flag) != $flag)	return null;
		$data = $this->unpack2('v', substr($challenge, $pos, 2));
		$pos += 2;
		$flags ^= $flag;
		return $data;
	}
	protected function processDWord($challenge, &$flags, $flag, &$pos) {
		if (($flags & $flag) != $flag)	return null;

		$bin = substr($challenge, $pos, 4);	//workaround PHP bug
		if (PHP_INT_SIZE <= 4){
			list(,$h,$l) = unpack('n*', $bin); 
			$data = $l + ($h*0x010000);
		} else {
			list(,$int) = unpack('N', $bin); 
			$data = $int;
		}
		
		$pos += 4;
		$flags ^= $flag;
		return $data;
	}
	protected function processFloat($challenge, &$flags, $flag, &$pos) {
		if (($flags & $flag) != $flag)	return null;
		$data = $this->unpack2('f', substr($challenge, $pos, 4));
		$pos += 4;
		$flags ^= $flag;
		return $data;
	}
	protected function check_game($datafile){
		$datafile = strtolower($datafile);
		$games = array(
			'doom1.wad' => 'Shareware Doom',
			'doom.wad' => 'Doom I',
			'doom2.wad' => 'Doom II',
			'tnt.wad' => 'Final Doom - TNT: Evilution',
			'plutonia.wad' => 'Final Doom - The Plutonia Experiment',
			'heretic1.wad' => 'Shareware Heretic',
			'heretic.wad' => 'Heretic',
			'hexen.wad' => 'Hexen',
			'hexdd.wad' => 'Hexen: Deathkings of the Dark Citadel',
			'strife0.wad' => 'Strife Teaser',
			'strife1.wad' => 'Strife',
			);
		if (isset($games[$datafile])) {
			return $games[$datafile];
		}
		return $datafile;
	}
	protected function processZandronumAnswer($challenge) {
		$data = array();
		$size = strlen($challenge);
		if ($size < 8 || $size >= 6000)	return false;
		if (5660023 != $this->unpack2('V', substr($challenge, 0, 4)))	return false;	//check if response is RESPONSE_GOOD
		$data['engine'] = 'Zandronum';
		$data['ping'] = round((microtime(true) * 1000) & 0xffffffff) - $this->unpack2('V', substr($challenge, 4, 4));
		$data['version'] = substr($challenge, 8, strpos($challenge, chr(0), 8) - 8);
		$pos = 8 + strlen($data['version']) + 1;

		$flags = $this->unpack2('V', substr($challenge, $pos, 4));
		$pos += 4;
		
		$gamemodes = array(0 => 'Cooperative',	/*be sure to change SQF_LIMITS processing when rename*/
						  'Survival',
						  'Invasion',
						  'Deathmatch',
						  'Team DM',
						  'Duel',
						  'Terminator',
						  'Last Man Standing',
						  'Team LMS',
						  'Possession',
						  'Team Possession',
						  'Team Game',
						  'Capture The Flag',
						  'One Flag CTF',
						  'Skulltag',
						  'Domination',
						  'Unknown',
						  );
		$isteamgame = array('Cooperative' => false,	/*be sure to change SQF_LIMITS processing when rename*/
						  'Survival' => false,
						  'Invasion' => false,
						  'Deathmatch' => false,
						  'Team DM' => true,
						  'Duel' => false,
						  'Terminator' => false,
						  'Last Man Standing' => false,
						  'Team LMS' => true,
						  'Possession' => false,
						  'Team Possession' => true,
						  'Team Game' => true,
						  'Capture The Flag' => true,
						  'One Flag CTF' => true,
						  'Skulltag' => true,
						  'Domination' => true,
						  'Unknown' => false,
						  );
		try {
			$data['name'] = $this->processString($challenge, $flags, 0x00000001, $pos);	//SQF_NAME
			$data['url'] = $this->processString($challenge, $flags, 0x00000002, $pos);	//SQF_URL
			$data['email'] = $this->processString($challenge, $flags, 0x00000004, $pos);	//SQF_EMAIL
			$data['map'] = $this->processString($challenge, $flags, 0x00000008, $pos);	//SQF_MAPNAME

			$data['maxclients'] = $this->processByte($challenge, $flags, 0x00000010, $pos);	//SQF_MAXCLIENTS
			if (null === $data['maxclients'])	$data['maxclients'] = 0;
			
			$data['maxplayers'] = $this->processByte($challenge, $flags, 0x00000020, $pos);	//SQF_MAXPLAYERS
			if (null === $data['maxplayers'])	$data['maxplayers'] = 0;
			
			$data['datafiles'] = array();
			$wads = $this->processByte($challenge, $flags, 0x00000040, $pos);	//SQF_PWADS
			if (null !== $wads) {
				for ($i = 0; $i < $wads; $i++)
					$data['datafiles'][] = $this->processString($challenge, $flags, 0, $pos);
			}
			
			$data['gamemode'] = $this->processByte($challenge, $flags, 0x00000080, $pos);	//SQF_GAMETYPE
			if (null !== $data['gamemode']) {
				if ($data['gamemode'] >= count($gamemodes))	$data['gamemode'] = count($gamemodes);
				$data['gamemode'] = $gamemodes[$data['gamemode']];
				$data['gamemodex'] = array();
				if ((bool)$this->processByte($challenge, $flags, 0, $pos))
				{
					$data['gamemodex'][] = 'instagib';
				}
				if ((bool)$this->processByte($challenge, $flags, 0, $pos))
				{
					$data['gamemodex'][] = 'buckshot';
				}
			}
			
			$data['gamename'] = $this->processString($challenge, $flags, 0x00000100, $pos);	//SQF_GAMENAME
			$data['gamedata'] = $this->processString($challenge, $flags, 0x00000200, $pos);	//SQF_IWAD
			$data['game'] = $this->check_game($data['gamedata']);
			
			$data['needpassword'] = (bool)$this->processByte($challenge, $flags, 0x00000400, $pos);	//SQF_FORCEPASSWORD
			$data['needjoinpassword'] = (bool)$this->processByte($challenge, $flags, 0x00000800, $pos);	//SQF_FORCEJOINPASSWORD
			$data['skill'] = $this->processByte($challenge, $flags, 0x00001000, $pos);	//SQF_GAMESKILL
			$data['botskill'] = $this->processByte($challenge, $flags, 0x00002000, $pos);	//SQF_BOTSKILL
			
			if (($flags & 0x00004000) == 0x00004000) {	//SQF_DMFLAGS
				$flags ^= 0x00004000;
				for ($i = 0; $i < 3; $i++) {	//Zandronum has 3 dmflags sectons
					$dmflags = $this->processDWord($challenge, $flags, 0, $pos);
					//TODO $data['dmflags'] []= processZandronumDMFlags($dmflags);
					$data['flags'] [] = $dmflags;
				}
			}
			
			if (($flags & 0x00010000) == 0x00010000) {	//SQF_LIMITS
				$flags ^= 0x00010000;
				$fraglimit = $this->processWord($challenge, $flags, 0, $pos);
				$data['timelimit'] = $this->processWord($challenge, $flags, 0, $pos);
				if ($data['timelimit'])
					$data['timeleft'] = $this->processWord($challenge, $flags, 0, $pos);
				$duellimit = $this->processWord($challenge, $flags, 0, $pos);
				$pointlimit = $this->processWord($challenge, $flags, 0, $pos);
				$winlimit = $this->processWord($challenge, $flags, 0, $pos);
				switch($data['gamemode']) {
					case 'Last Man Standing':
					case 'Team LMS':
						$data['scorelimit'] = $winlimit;
						break;
					case 'Possession':
					case 'Team Possession':
					case 'Team Game':
					case 'Capture The Flag':
					case 'One Flag CTF':
					case 'Skulltag':
					case 'Domination':
						$data['scorelimit'] = $pointlimit;
						break;
					default:
						$data['scorelimit'] = $fraglimit;
						break;
				}
			}
			
			$data['teamdamage'] = $this->processFloat($challenge, $flags, 0x00020000, $pos);	//SQF_TEAMDAMAGE

			if (($flags & 0x00040000) == 0x00040000) {	//SQF_TEAMSCORES (deprecated) 
				$flags ^= 0x00040000;
				for ($i = 0; $i < 2; $i++)
					$data['teamscore'][] = $this->processWord($challenge, $flags, 0, $pos);
			}

			if (($flags & 0x00080000) == 0x00080000) {	//SQF_NUMPLAYERS
				$flags ^= 0x00080000;
				$data['numplayers'] = $this->processByte($challenge, $flags, 0, $pos);
				if ($data['numplayers'] > $data['maxclients'])	return false;
				
				if (($flags & 0x00100000) == 0x00100000) {	//SQF_PLAYERDATA
					$flags ^= 0x00100000;
					$isteam = $isteamgame[$data['gamemode']];
					
					for ($i = 0; $i < $data['numplayers']; $i++) {
						$player = array();
						$player['name'] = $this->processString($challenge, $flags, 0, $pos);
						$player['score'] = $this->processWord($challenge, $flags, 0, $pos);
						$player['ping'] = $this->processWord($challenge, $flags, 0, $pos);
						$player['spectator'] = (bool)$this->processByte($challenge, $flags, 0, $pos);
						$player['bot'] = (bool)$this->processByte($challenge, $flags, 0, $pos);
						if ($isteam)	$player['team'] = $this->processByte($challenge, $flags, 0, $pos);
						$data['players'][] = $player;
					}
				}
			}
			
			$data['numteams'] = $this->processByte($challenge, $flags, 0x00200000, $pos);	//SQF_TEAMINFO_NUMBER
			if (($flags & 0x00400000) == 0x00400000) {	//SQF_TEAMINFO_NAME
				$flags ^= 0x00400000;
				for ($i = 0; $i < $data['numteams'] && $i < 4; $i++) {
					$data['teams'][$i]['name'] = $this->processString($challenge, $flags, 0, $pos);
				}
			}
			if (($flags & 0x00800000) == 0x00800000) {	//SQF_TEAMINFO_COLOR
				$flags ^= 0x00800000;
				$forlimit = min($data['numteams'], 4);
				for ($i = 0; $i < $forlimit; $i++) {
					$data['teams'][$i]['color'] = $this->processDWord($challenge, $flags, 0, $pos);
					if ($pos >= $size && $i < $forlimit)	return false;
				}
			}
			if (($flags & 0x01000000) == 0x01000000) {	//SQF_TEAMINFO_SCORE
				$flags ^= 0x01000000;
				$forlimit = min($data['numteams'], 4);
				for ($i = 0; $i < $forlimit; $i++) {
					$data['teams'][$i]['score'] = $this->processWord($challenge, $flags, 0, $pos);
					if ($pos >= $size && $i < $forlimit)	return false;
				}
			}
			if (($flags & 0x02000000) == 0x02000000) {	//SQF_TESTING_SERVER
				$flags ^= 0x02000000;
				$data['testing'] = (bool)$this->processByte($challenge, $flags, 0, $pos);
				$data['testingdata'] = $this->processString($challenge, $flags, 0, $pos);
			}
			return $data;
		} catch (Exception $e) {
			echo "Error: $e<br>\n";
			echo "Flags left unprocessed: $flags<br>\n";
			return false;
		}
	}

	public function __construct()
	{
		$cdict = array(	//Huffman tree compatible with ZAndronum
			-128, 4, 0, -38, 8, 16, -34, 8, 17, -80, 9, 36, -110, 10, 74, -144, 10, 75, -67, 8, 19, -74, 9, 40, -243, 10, 82, -142, 10, 83, -37, 8, 21, -124, 9, 44, -58, 9, 45, -182, 8, 23, -36, 8, 24, -221, 10, 100, -131, 10, 101, -245, 10, 102, -163, 10, 103, -35, 8, 26, -113, 9, 54, -85, 9, 55, -41, 8, 28, -77, 9, 58, -199, 10, 118, -130, 10, 119, -206, 9, 60, -185, 10, 122, -153, 10, 123, -70, 9, 62, -118, 9, 63, -3, 5, 4, -5, 5, 5, -24, 7, 24, -198, 10, 200, -190, 10, 201, -63, 9, 101, -139, 10, 204, -186, 10, 205, -75, 9, 103, -44, 8, 52, -240, 10, 212, -218, 10, 213, -56, 9, 107, -40, 8, 54, -39, 8, 55, -244, 10, 224, -247, 10, 225, -81, 9, 113, -65, 8, 57, -9, 9, 116, -125, 9, 117, -68, 9, 118, -60, 9, 119, -25, 9, 120, -191, 10, 242, -138, 10, 243, -86, 9, 122, -17, 9, 123, -23, 9, 124, -220, 10, 250, -178, 10, 251, -165, 10, 252, -194, 10, 253, -14, 9, 127, 0, 3, 2, -208, 9, 192, -150, 10, 386, -157, 10, 387, -181, 8, 97, -222, 8, 98, -216, 10, 396, -230, 10, 397, -211, 9, 199, -252, 10, 400, -141, 10, 401, -10, 9, 201, -42, 8, 101, -134, 10, 408, -135, 10, 409, -104, 9, 205, -103, 9, 206, -187, 10, 414, -225, 10, 415, -95, 5, 13, -32, 4, 7, -57, 8, 128, -61, 9, 258, -183, 10, 518, -237, 10, 519, -233, 10, 520, -234, 10, 521, -246, 10, 522, -203, 10, 523, -250, 10, 524, -147, 10, 525, -79, 9, 263, -129, 7, 66, -7, 9, 268, -143, 10, 538, -136, 10, 539, -20, 9, 270, -179, 10, 542, -148, 10, 543, -28, 9, 272, -106, 9, 273, -101, 9, 274, -87, 9, 275, -66, 8, 138, -180, 10, 556, -219, 10, 557, -227, 10, 558, -241, 10, 559, -26, 8, 140, -251, 9, 282, -229, 10, 566, -214, 10, 567, -54, 8, 142, -69, 8, 143, -231, 10, 576, -212, 10, 577, -156, 10, 578, -176, 10, 579, -93, 9, 290, -83, 9, 291, -96, 9, 292, -253, 9, 293, -30, 9, 294, -13, 9, 295, -175, 10, 592, -254, 10, 593, -94, 9, 297, -159, 9, 298, -27, 9, 299, -8, 9, 300, -204, 10, 602, -226, 10, 603, -78, 8, 151, -107, 9, 304, -88, 9, 305, -31, 9, 306, -137, 10, 614, -169, 10, 615, -215, 10, 616, -145, 10, 617, -6, 9, 309, -4, 8, 155, -127, 7, 78, -99, 9, 316, -209, 10, 634, -217, 10, 635, -213, 10, 636, -238, 10, 637, -177, 10, 638, -170, 10, 639, -132, 4, 10, -22, 9, 352, -12, 9, 353, -114, 8, 177, -158, 10, 712, -197, 10, 713, -97, 9, 357, -45, 8, 179, -46, 8, 180, -112, 9, 362, -174, 10, 726, -249, 10, 727, -224, 9, 364, -102, 9, 365, -171, 10, 732, -151, 10, 733, -193, 9, 367, -15, 9, 368, -16, 9, 369, -2, 9, 370, -168, 9, 371, -49, 8, 186, -91, 9, 374, -146, 9, 375, -48, 8, 188, -173, 9, 378, -29, 9, 379, -19, 9, 380, -126, 9, 381, -92, 9, 382, -242, 9, 383, -205, 9, 384, -192, 9, 385, -235, 10, 772, -149, 10, 773, -255, 9, 387, -223, 9, 388, -184, 9, 389, -248, 8, 195, -108, 9, 392, -236, 9, 393, -111, 9, 394, -90, 9, 395, -117, 9, 396, -115, 9, 397, -71, 8, 199, -11, 8, 200, -50, 8, 201, -188, 9, 404, -119, 9, 405, -122, 9, 406, -167, 10, 814, -162, 10, 815, -160, 7, 102, -133, 8, 206, -123, 9, 414, -21, 9, 415, -59, 8, 208, -155, 10, 836, -154, 10, 837, -98, 9, 419, -43, 7, 105, -76, 8, 212, -51, 8, 213, -201, 9, 428, -116, 9, 429, -72, 8, 215, -109, 9, 432, -100, 9, 433, -121, 8, 217, -195, 9, 436, -232, 9, 437, -18, 8, 219, -1, 6, 55, -164, 7, 112, -120, 9, 452, -189, 9, 453, -73, 8, 227, -196, 8, 228, -239, 9, 458, -210, 9, 459, -64, 8, 230, -62, 8, 231, -89, 5, 29, -33, 7, 120, -228, 9, 484, -161, 9, 485, -55, 8, 243, -84, 8, 244, -152, 8, 245, -47, 7, 123, -207, 9, 496, -172, 9, 497, -140, 8, 249, -82, 8, 250, -166, 8, 251, -53, 8, 252, -105, 8, 253, -52, 8, 254, -202, 9, 510, -200, 9, 511
			);	
		$this->huffman = new Huffman($cdict);
	}
	
	protected function compress_data($data)
	{
		return $this->huffman->compressData($data);
	}
	
	protected function decompress_data($data)
	{
		return $this->huffman->decompressData($data);
	}
	
	public function cook_challenge()
	{
		/*
		The challenge consists of:
	
		SERVER_CHALLENGE        0xC7,0x00,0x00,0x00
		SQF_STANDARDQUERY = 0x03795eff = SQF_NAME|SQF_URL|SQF_EMAIL|SQF_MAPNAME|SQF_MAXCLIENTS
			|SQF_MAXPLAYERS|SQF_PWADS|SQF_GAMETYPE|SQF_IWAD|SQF_FORCEPASSWORD
			|SQF_FORCEJOINPASSWORD|SQF_DMFLAGS|SQF_LIMITS|SQF_NUMPLAYERS|SQF_PLAYERDATA
			|SQF_TEAMINFO_NUMBER|SQF_TEAMINFO_NAME|SQF_TEAMINFO_SCORE|SQF_GAMESKILL
			|SQF_TESTING_SERVER
		millisecondTime()
		*/

		$challengeOut = pack('CxxxVV', 0xc7,	0x03795eff, round(microtime(true) * 1000) & 0xffffffff);
		$challengeOut = $this->compress_data($challengeOut);

		return $challengeOut;
	}

	public function process_answer($challengeIn, &$serverData)
	{
		$challengeIn = $this->decompress_data($challengeIn);
		if ($challengeIn === '' || 5660024 !== $this->unpack2('V', substr($challengeIn, 0, 4)))	//wait if needed
		{
			$serverData = $this->processZandronumAnswer($challengeIn);
			if ($serverData === false)	return 'BAD';
			return 'GOOD';
		}
		return 'WAIT';
	}	
}

?>