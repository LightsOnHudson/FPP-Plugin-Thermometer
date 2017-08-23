<?php
//create database
function createThermometerTables($db) {
	//global $db;


	
	$createQuery = "CREATE TABLE IF NOT EXISTS messages (messageID INTEGER PRIMARY KEY AUTOINCREMENT, timestamp int(16) NOT NULL, message varchar(255), pluginName varchar(64), pluginData varchar(64));";
	
	logEntry("Thermometer: CREATING Messages Table for Thermometer: ".$createQuery);
	
	$db->exec($createQuery) or die('Create Table Failed');

}
function insertTwilioMessage($message, $pluginName, $pluginData) {
	global $db;
	$messagesTable = "messages";
	//$db = new SQLite3($DBName) or die('Unable to open database');

	$insertQuery = "INSERT INTO ".$messagesTable." (timestamp, message, pluginName, pluginData) VALUES ('".time()."','".urlencode($message)."','".$pluginName."','".$pluginData."');";

	logEntry("TWILIO: INSERT query string: ".$insertQuery);
	$db->exec($insertQuery) or die('could not insert into database');


}
function stripHexChars($line) {
 return preg_replace('/([0-9#][\x{20E3}])|[\x{00ae}\x{00a9}\x{203C}\x{2047}\x{2048}\x{2049}\x{3030}\x{303D}\x{2139}\x{2122}\x{3297}\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F6FF}][\x{FE00}-\x{FEFF}]?/u', '', $line);
	
}

//get the fpp log level
function getFPPLogLevel() {
	
	$FPP_LOG_LEVEL_FILE = "/home/fpp/media/settings";
	if (file_exists($FPP_LOG_LEVEL_FILE)) {
		$FPP_SETTINGS_DATA = parse_ini_file($FPP_LOG_LEVEL_FILE);
	} else {
		//return log level 0
		return 0;
	}
	
		logEntry("FPP Settings file: ".$FPP_LOG_LEVEL_FILE);
		
		$logLevelString = trim($FPP_SETTINGS_DATA['LogLevel']);
		logEntry("Log level in fpp settings file: ".$logLevelString);
		
		switch($logLevelString) {
			
			
			case "info":
				$logLevel=0;
				
			//	break;
				
			case "warn":
				$logLevel=1;
				
			//	break;
				
			case "debug":
				
				$logLevel=2;
				
			//	break;
				
			case "excess":
				
				$logLevel=3;
				
				//break;
				
			 default:
				$logLevel = 0;
				
				
		}
		
		
	
	return $logLevel;
	
}
//is fppd running?????
function isFPPDRunning() {
	$FPPDStatus=null;
	logEntry("Checking to see if fpp is running...");
        exec("if ps cax | grep -i fppd; then echo \"True\"; else echo \"False\"; fi",$output);

        if($output[1] == "True" || $output[1] == 1 || $output[1] == "1") {
                $FPPDStatus = "RUNNING";
        }
	//print_r($output);

	return $FPPDStatus;
        //interate over the results and see if avahi is running?

}
//get current running playlist
function getRunningPlaylist() {

	global $sequenceDirectory;
	$playlistName = null;
	$i=0;
	//can we sleep here????

	//sleep(10);
	//FPPD is running and we shoud expect something back from it with the -s status query
	// #,#,#,Playlist name
	// #,1,# = running

	$currentFPP = file_get_contents("/tmp/FPP.playlist");
	logEntry("Reading /tmp/FPP.playlist : ".$currentFPP);
	if($currentFPP == "false") {
		logEntry("We got a FALSE status from fpp -s status file.. we should not really get this, the daemon is locked??");
	}
	$fppParts="";
	$fppParts = explode(",",$currentFPP);
//	logEntry("FPP Parts 1 = ".$fppParts[1]);

	//check to see the second variable is 1 - meaning playing
	if($fppParts[1] == 1 || $fppParts[1] == "1") {
		//we are playing

		$playlistParts = pathinfo($fppParts[3]);
		$playlistName = $playlistParts['basename'];
		logEntry("We are playing a playlist...: ".$playlistName);
		
	} else {

		logEntry("FPPD Daemon is starting up or no active playlist.. please try again");
	}
	
	
	//now we should have had something
	return $playlistName;
}

function processSequenceName($sequenceName,$sequenceAction="NONE RECEIVED") {

	global $CONTROL_NUMBER_ARRAY,$PLAYLIST_NAME,$EMAIL,$PASSWORD,$pluginDirectory,$pluginName;
        logEntry("Sequence name: ".$sequenceName);

        $sequenceName = strtoupper($sequenceName);
	//$PLAYLIST_NAME= getRunningPlaylist();

	if($PLAYLIST_NAME == null) {
		$PLAYLIST_NAME = "FPPD Did not return a playlist name in time, please try again later";
	}
//        switch ($sequenceName) {

 //               case "SMS-STATUS-SEND.FSEQ":

                $messageToSend="";
	//	$gv = new GoogleVoice($EMAIL, $PASSWORD);

		//send a message to all numbers in control array and then delete them from new messages
		for($i=0;$i<=count($CONTROL_NUMBER_ARRAY)-1;$i++) {
			logEntry("Sending message to : ".$CONTROL_NUMBER_ARRAY[$i]. " that playlist: ".$PLAYLIST_NAME." is ACTION:".$sequenceAction);
			//get the current running playlist name! :)	

				//$gv->sendSMS($CONTROL_NUMBER_ARRAY[$i], "PLAYLIST EVENT: ".$PLAYLIST_NAME." Action: ".$sequenceAction);
			//	$gv->sendSMS($CONTROL_NUMBER_ARRAY[$i], "PLAYLIST EVENT: Action: ".$sequenceAction);
		
		}		
		logEntry("Plugin Directory: ".$pluginDirectory);
		//run the sms processor outside of cron
		$cmd = $pluginDirectory."/".$pluginName."/getSMS.php";

		exec($cmd,$output); 



}


function logEntry($data,$logLevel=1,$sourceFile, $sourceLine) {

	global $logFile,$myPid, $LOG_LEVEL;

	
	if($logLevel <= $LOG_LEVEL) 
		//return
		
		if($sourceFile == "") {
			$sourceFile = $_SERVER['PHP_SELF'];
		}
		$data = $sourceFile." : [".$myPid."] ".$data;
		
		if($sourceLine !="") {
			$data .= " (Line: ".$sourceLine.")";
		}
		
		$logWrite= fopen($logFile, "a") or die("Unable to open file!");
		fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
		fclose($logWrite);


}



function processCallback($argv) {
	global $DEBUG,$pluginName;
	
	
	if($DEBUG)
		print_r($argv);
	//argv0 = program
	
	//argv2 should equal our registration // need to process all the rgistrations we may have, array??
	//argv3 should be --data
	//argv4 should be json data
	
	$registrationType = $argv[2];
	$data =  $argv[4];
	
	logEntry("PROCESSING CALLBACK: ".$registrationType);
	$clearMessage=FALSE;
	
	switch ($registrationType)
	{
		case "media":
			if($argv[3] == "--data")
			{
				$data=trim($data);
				logEntry("DATA: ".$data);
				$obj = json_decode($data);
	
				$type = $obj->{'type'};
				logEntry("Type: ".$type);	
				switch ($type) {
						
					case "sequence":
						logEntry("media sequence name received: ");	
						processSequenceName($obj->{'Sequence'},"STATUS");
							
						break;
					case "media":
							
						logEntry("We do not support type media at this time");
							
						//$songTitle = $obj->{'title'};
						//$songArtist = $obj->{'artist'};
	
	
						//sendMessage($songTitle, $songArtist);
						//exit(0);
	
						break;
						
						case "both":
								
						logEntry("We do not support type media/both at this time");
						//	logEntry("MEDIA ENTRY: EXTRACTING TITLE AND ARTIST");
								
						//	$songTitle = $obj->{'title'};
						//	$songArtist = $obj->{'artist'};
							//	if($songArtist != "") {
						
						
						//	sendMessage($songTitle, $songArtist);
							//exit(0);
						
							break;
	
					default:
						logEntry("We do not understand: type: ".$obj->{'type'}. " at this time");
						exit(0);
						break;
	
				}
	
	
			}
	
			break;
			exit(0);
	
		case "playlist":

			logEntry("playlist type received");
			if($argv[3] == "--data")
                        {
                                $data=trim($data);
                                logEntry("DATA: ".$data);
                                $obj = json_decode($data);
				$sequenceName = $obj->{'sequence0'}->{'Sequence'};	
				$sequenceAction = $obj->{'Action'};	
                                                processSequenceName($sequenceName,$sequenceAction);
                                                //logEntry("We do not understand: type: ".$obj->{'type'}. " at this time");
                                        //      logEntry("We do not understand: type: ".$obj->{'type'}. " at this time");
			}

			break;
			exit(0);			
		default:
			exit(0);
	
	}
	

}
?>
