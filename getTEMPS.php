<?php
// error_reporting(0);
//
//
// Version 1 for release
// TwilioVersion 2.0 = Dec 27 2016 - SQLLite messages
$CONSOLE_DEBUG = false;
$pluginName = "Thermometer";

$myPid = getmypid ();

$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED = false;

// MATRIX ACTIVE - true / false to catch more messages if they arrive
$MATRIX_ACTIVE = false;

$skipJSsettings = 1;
include_once ("/opt/fpp/www/config.php");
include_once ("/opt/fpp/www/common.php");
include_once ("functions.inc.php");
include_once ("commonFunctions.inc.php");


$logFile = $settings ['logDirectory'] . "/" . $pluginName . ".log";

$messageQueuePluginPath = $pluginDirectory . "/" . $messageQueue_Plugin . "/";

$messageQueueFile = urldecode ( ReadSettingFromFile ( "MESSAGE_FILE", $messageQueue_Plugin ) );

$profanityMessageQueueFile = $settings ['configDirectory'] . "/plugin." . $pluginName . ".ProfanityQueue";

$blacklistFile = $settings ['configDirectory'] . "/plugin." . $pluginName . ".Blacklist";

if (file_exists ( $messageQueuePluginPath . "functions.inc.php" )) {
	include $messageQueuePluginPath . "functions.inc.php";
	$MESSAGE_QUEUE_PLUGIN_ENABLED = true;
} else {
	logEntry ( "Message Queue Plugin not installed, some features will be disabled" );
}

// set up DB connection
$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//echo "PLUGIN DB:NAME: ".$Plugin_DBName;

$db = new SQLite3($Plugin_DBName) or die('Unable to open database');

// logEntry("DB: ".$db);

if ($db != null) {
	//create the tables if this is the first time!!!! this is also done in the plugin-setup !
	createThermometerTables($db);
}

require ("lock.helper.php");

define ( 'LOCK_DIR', '/tmp/' );
define ( 'LOCK_SUFFIX', $pluginName . '.lock' );

$pluginConfigFile = $settings ['configDirectory'] . "/plugin." . $pluginName;
if (file_exists ( $pluginConfigFile ))
	$pluginSettings = parse_ini_file ( $pluginConfigFile );


$DEBUG = urldecode ( $pluginSettings ['DEBUG'] );

logEntry("DEBUG mode: ".$DEBUG);

$ENABLED = urldecode ( $pluginSettings ['ENABLED'] );

$IMMEDIATE_OUTPUT = urldecode ( $pluginSettings ['IMMEDIATE_OUTPUT'] );
$MATRIX_LOCATION = urldecode ( $pluginSettings ['MATRIX_LOCATION'] );
$TEMPERATURE_OUTPUT = $pluginSettings['TEMPERATURE_OUTPUT'];
//$CONSOLE_DEBUG = urldecode ( $pluginSettings ['CONSOLE_DEBUG'] );

// $CONSOLE_DEBUG = true;

$MATRIX_MESSAGE_PLUGIN_NAME = "MatrixMessage";
// page name to run the matrix code to output to matrix (remote or local);
$MATRIX_EXEC_PAGE_NAME = "matrix.php";

if (strtoupper ( $ENABLED ) != "ON") {
	
	logEntry ( "Plugin Status: DISABLED Please enable in Plugin Setup to use" );
	lockHelper::unlock ();
	exit ( 0 );
}


$TEMPERATURE_DEVICE_PATH = "/sys/bus/w1/devices/";
$paths = glob($TEMPERATURE_DEVICE_PATH.'28-*');

//print_r($paths);

$TEMP_PROBES = array();

if(count($paths) >0) {
	//we have some probes!!!
	
	
	//get the files in each - because apparently you can have more than one on the string!!!
	foreach ($paths as $temp_probe) {
		
	
		//read the contents of the file w1_slave in each of the folders
		$TEMPERATURE_FILE_PATH = $temp_probe . "/w1_slave";
		
		$temperature_file_contents = file_get_contents($TEMPERATURE_FILE_PATH);
		
		$temperature_file_parts = explode("\n",$temperature_file_contents);
		
		//the temperature information is on the second line t= in celcius
		//example:
		//d5 01 4b 46 7f ff 0c 10 2c : crc=2c YES
		//d5 01 4b 46 7f ff 0c 10 2c t=29312
		
		$TEMP_IN_CELCIUS =  get_string_between ($temperature_file_parts[1],"=","\n");
		
		switch($TEMPERATURE_OUTPUT) {
			
			case "FARENHEIGHT":
				$FARENHEIGHT = (floatval($TEMP_IN_CELCIUS) /1000.0) * 9.0 / 5.0 + 32.0;
				$FARENHEIGHT = round($FARENHEIGHT,2);
				echo $FARENHEIGHT."&deg F";
				$message = $FARENHEIGHT."&deg F";
				break;
				
				
			case "CELCIUS":
				$CELCIUS = floatval($TEMP_IN_CELCIUS) /1000.0;
				$CELCIUS= round($CELCIUS,2);
				echo $CELCIUS."&deg C";
				$message = $CELCIUS."&deg C";
				break;
				
		}
		
		//echo "Temp in celcisu: ".$TEMP_IN_CELCIUS;
		logEntry("Temp in celcius for probe: ".$temp_probe." ".$CELCIUS);
		logEntry("Temp in farenheight for probe: ".$temp_probe." ".$FARENHEIGHT);
		$table="messages";
		$pluginData = $temp_probe;
		
		insertMessage($Plugin_DBName, $table, $message, $pluginName, $pluginData);
		
		$pluginLatest = time();
		//set it to one or 2 seconds back so that when it is sent it is read properly by the matrix plugin!!!
		
		WriteSettingToFile("LAST_READ",$pluginLatest - 5,$pluginName);
	}
	
	
} else {
	logEntry("There are no probes currently detected!");

	
}

if ($IMMEDIATE_OUTPUT != "ON") {
	logEntry ( "Temperature: NOT immediately outputting to matrix" );
	// } elseif(!$MATRIX_ACTIVE) {
	} else {
	// add the message pre text to the names before sending it to the matrix!
	
	logEntry ( "IMMEDIATE OUTPUT ENABLED" );
	
	// write high water mark, so that if run-matrix is run it will not re-run old messages
	
	$pluginLatest = time ();
	
	// logEntry("message queue latest: ".$pluginLatest);
	// logEntry("Writing high water mark for plugin: ".$pluginName." LAST_READ = ".$pluginLatest);
	
	// file_put_contents($messageQueuePluginPath.$pluginSubscriptions[$pluginIndex].".lastRead",$pluginLatest);
	// WriteSettingToFile("LAST_READ",urlencode($pluginLatest),$pluginName);
	
	// do{
	
	logEntry ( "Matrix location: " . $MATRIX_LOCATION );
	logEntry ( "Matrix Exec page: " . $MATRIX_EXEC_PAGE_NAME );
	$MATRIX_ACTIVE = true;
	WriteSettingToFile ( "MATRIX_ACTIVE", urlencode ( $MATRIX_ACTIVE ), $pluginName );
	logEntry ( "MATRIX ACTIVE: " . $MATRIX_ACTIVE );
	
	$curlURL = "http://" . $MATRIX_LOCATION . "/plugin.php?plugin=" . $MATRIX_MESSAGE_PLUGIN_NAME . "&page=" . $MATRIX_EXEC_PAGE_NAME . "&nopage=1&subscribedPlugin=" . $pluginName . "&onDemandMessage=" . urlencode ( $messageText );
	if ($DEBUG)
		logEntry ( "MATRIX TRIGGER: " . $curlURL );
	
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $curlURL );
	
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt ( $ch, CURLOPT_WRITEFUNCTION, 'do_nothing' );
	curl_setopt ( $ch, CURLOPT_VERBOSE, false );
	
	$result = curl_exec ( $ch );
	logEntry ( "Curl result: " . $result ); // $result;
	curl_close ( $ch );
	
	$MATRIX_ACTIVE = false;
	WriteSettingToFile ( "MATRIX_ACTIVE", urlencode ( $MATRIX_ACTIVE ), $pluginName );
	
	// } while (count(getNewPluginMessages($pluginName)) >0);
}


?>