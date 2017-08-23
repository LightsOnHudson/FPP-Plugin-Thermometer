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

$ENABLED = urldecode ( $pluginSettings ['ENABLED'] );

$IMMEDIATE_OUTPUT = urldecode ( $pluginSettings ['IMMEDIATE_OUTPUT'] );
$MATRIX_LOCATION = urldecode ( $pluginSettings ['MATRIX_LOCATION'] );
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
if ($IMMEDIATE_OUTPUT != "ON") {
	logEntry ( "TWILIO: NOT immediately outputting to matrix" );
	// } elseif(!$MATRIX_ACTIVE) {
	} else {
	// add the message pre text to the names before sending it to the matrix!
	switch ($MATRIX_MODE) {
		
		case "NAMES" :
			
			$messageText = $NAMES_PRE_TEXT . " " . $messageText;
			break;
	}
	
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