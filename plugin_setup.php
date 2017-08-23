<?php


include_once "/opt/fpp/www/common.php";
include_once 'functions.inc.php';
include_once 'commonFunctions.inc.php';
$pluginName = "Thermometer";

$pluginVersion ="1.0";
$PLAYLIST_NAME="";
$MAJOR = "98";
$MINOR = "01";
$eventExtension = ".fevt";

//$Plugin_DBName = "/tmp/FPP.".$pluginName.".db";
$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//1.0 - first deployment



$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED=false;


$logFile = $settings['logDirectory']."/".$pluginName.".log";



$messageQueuePluginPath = $settings['pluginDirectory']."/".$messageQueue_Plugin."/";

$messageQueueFile = urldecode(ReadSettingFromFile("MESSAGE_FILE",$messageQueue_Plugin));

if(file_exists($messageQueuePluginPath."functions.inc.php"))
{
	include $messageQueuePluginPath."functions.inc.php";
	$MESSAGE_QUEUE_PLUGIN_ENABLED=true;

} else {
	logEntry("Message Queue Plugin not installed, some features will be disabled");
}


$gitURL = "https://github.com/LightsOnHudson/FPP-Plugin-Thermometer.git";


$pluginUpdateFile = $settings['pluginDirectory']."/".$pluginName."/"."pluginUpdate.inc";





logEntry("plugin update file: ".$pluginUpdateFile);


if(isset($_POST['updatePlugin']))
{
	$updateResult = updatePluginFromGitHub($gitURL, $branch="master", $pluginName);

	echo $updateResult."<br/> \n";
}


if(isset($_POST['submit']))
{
	


//	echo "Writring config fie <br/> \n";
	
	
	WriteSettingToFile("TEMPERATURE_OUTPUT",urlencode($_POST["TEMPERATURE_OUTPUT"]),$pluginName);
	WriteSettingToFile("MATRIX_LOCATION",urlencode($_POST["MATRIX_LOCATION"]),$pluginName);
}
sleep(1);
$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
if (file_exists($pluginConfigFile))
	$pluginSettings = parse_ini_file($pluginConfigFile);


$LAST_READ = $pluginSettings['LAST_READ'];
$TEMPERATURE_OUTPUT = $pluginSettings['TEMPERATURE_OUTPUT'];


$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//echo "PLUGIN DB:NAME: ".$Plugin_DBName;

$db = new SQLite3($Plugin_DBName) or die('Unable to open database');

//create the default tables if they do not exist!
createThermometerTables($db);

//modprobe to ensure that it is there
//also add to /boot/config.txt  dtoverlay=w1-gpio and reboot

$MOD_PROBE_CMD_1 = "/sbin/modprobe w1-gpio";

exec($MOD_PROBE_CMD_1);
sleep(1);

$MOD_PROBE_CMD_2 = "/sbin/modprobe w1-therm";
exec($MOD_PROBE_CMD_2);

//now read in the file
//Temp file can be found in /sys/bus/w1/devices/28-*
$TEMPERATURE_DEVICE_PATH = "/sys/bus/w1/devices/";
$paths = glob($TEMPERATURE_DEVICE_PATH.'28-*');

//print_r($paths);

$TEMP_PROBES = array();

if(count($paths) >0) {
	//we have some probes!!!

echo "<table border=\"1\" cellspacing=\"3\" cellpadding=\"3\"> \n";
echo "<th colspan=\"3\"> \n";
echo "Temperature probes \n";
echo "</th> \n";
//get the files in each - because apparently you can have more than one on the string!!!
	foreach ($paths as $temp_probe) {
		
		echo "<tr> \n";
		echo "<td> \n";
		//read the contents of the file w1_slave in each of the folders
		$TEMPERATURE_FILE_PATH = $temp_probe . "/w1_slave";
		
		$temperature_file_contents = file_get_contents($TEMPERATURE_FILE_PATH);
		
		$temperature_file_parts = explode("\n",$temperature_file_contents);
		
		//the temperature information is on the second line t= in celcius
		//example:
		//d5 01 4b 46 7f ff 0c 10 2c : crc=2c YES
		//d5 01 4b 46 7f ff 0c 10 2c t=29312
		
		$TEMP_IN_CELCIUS =  get_string_between ($temperature_file_parts[1],"=","\n");
		echo "Probe: ".basename($temp_probe).PHP_EOL;
		echo "</td> \n";
		echo "<td> \n";
		
		switch($TEMPERATURE_OUTPUT) {
			
			case "FARENHEIGHT":
				$FARENHEIGHT = (floatval($TEMP_IN_CELCIUS) /1000.0) * 9.0 / 5.0 + 32.0;
				echo $FARENHEIGHT."&deg F";
				$message = $FARENHEIGHT."&deg F";
				break;
				
				
			case "CELCIUS":
				$CELCIUS = floatval($TEMP_IN_CELCIUS) /1000.0;
				echo $CELCIUS."&deg C";
				$message = $CELCIUS."&deg C";
				break;
				
		}
		echo "</td> \n";
		echo "</tr> \n";
		//echo "Temp in celcisu: ".$TEMP_IN_CELCIUS;
		logEntry("Temp in celcius for probe: ".$temp_probe." ".$CELCIUS);
		logEntry("Temp in farenheight for probe: ".$temp_probe." ".$FARENHEIGHT);
		$table="messages";
		$pluginData = $temp_probe;
		
		insertMessage($Plugin_DBName, $table, $message, $pluginName, $pluginData);
		WriteSettingToFile("LAST_READ",urlencode(time ()),$pluginName);
	}
	echo "</table> \n";
	
} else {
	echo "There are no probes currently detected! <br/>";

}
?>

<html>
<head>
</head>

<div id="<?php echo $pluginName;?>" class="settings">
<fieldset>
<legend><?php echo $pluginName." Version: ".$pluginVersion;?> Support Instructions</legend>

<p>Known Issues:
<ul>
<li>None
</ul>

<p>Configuration:
<ul>
<li>Connect the temperature probe in accordance with the documentation on the HELP page (press F1 for help)</li>
</ul>

<ul>

<li>Configure how often you want the temperature read</li>




<form method="post" action="http://<? echo $_SERVER['SERVER_ADDR'];?>/plugin.php?plugin=<?echo $pluginName;?>&page=plugin_setup.php">


<?
//will add a 'reset' to this later

echo "<input type=\"hidden\" name=\"LAST_READ\" value=\"".$LAST_READ."\"> \n";


$restart=0;
$reboot=0;

echo "ENABLE PLUGIN: ";

//if($ENABLED== 1 || $ENABLED == "on") {
	//	echo "<input type=\"checkbox\" checked name=\"ENABLED\"> \n";
PrintSettingCheckbox("Plugin: ".$pluginName." ", "ENABLED", $restart = 0, $reboot = 0, "ON", "OFF", $pluginName = $pluginName, $callbackName = "");
//	} else {
//		echo "<input type=\"checkbox\"  name=\"ENABLED\"> \n";
//}

echo "<p/> \n";
echo "Immediately output to Matrix (Run MATRIX plugin): ";

//if($IMMEDIATE_OUTPUT == "on" || $IMMEDIATE_OUTPUT == 1) {
//	echo "<input type=\"checkbox\" checked name=\"IMMEDIATE_OUTPUT\"> \n";
	PrintSettingCheckbox("Immediate output to Matrix", "IMMEDIATE_OUTPUT", $restart = 0, $reboot = 0, "ON", "OFF", $pluginName = $pluginName, $callbackName = "");
//} else {
	//echo "<input type=\"checkbox\"  name=\"IMMEDIATE_OUTPUT\"> \n";
//}
echo "<p/> \n";
?>
MATRIX Message Plugin Location: (IP Address. default 127.0.0.1);
<input type="text" size="15" value="<? if($MATRIX_LOCATION !="" ) { echo $MATRIX_LOCATION; } else { echo "127.0.0.1";}?>" name="MATRIX_LOCATION" id="MATRIX_LOCATION"></input>
<p/>
<?
echo "<p/> \n";

echo "Temperature output (F / C): \n";
echo "<select name=\"TEMPERATURE_OUTPUT\"> \n";
	if($TEMPERATURE_OUTPUT !="" ) {
		switch ($TEMPERATURE_OUTPUT)
				{
					case "FARENHEIGHT":
						echo "<option selected value=\"".$TEMPERATURE_OUTPUT."\">".$TEMPERATURE_OUTPUT."</option> \n";
                                		echo "<option value=\"CELCIUS\">CELCIUS</option> \n";
                                		break;
                                		
					case "CELCIUS":
						echo "<option selected value=\"".$TEMPERATURE_OUTPUT."\">".$TEMPERATURE_OUTPUT."</option> \n";
                                		echo "<option value=\"FARENHEIGHT\">FARENHEIGHT</option> \n";
                        			break;
			
					default:
						echo "<option value=\"FARENHEIGHT\">FARENHEIGHT</option> \n";
						echo "<option value=\"CELCIUS\">CELCIUS</option> \n";
							break;
	
				}
	
			} else {

                                echo "<option value=\"FARENHEIGHT\">FARENHEIGHT</option> \n";
                                echo "<option value=\"CELCIUS\">CELCIUS</option> \n";
			}
               
			echo "</select> \n";
echo "<p/> \n";
?>
<p/>
<input id="submit_button" name="submit" type="submit" class="buttons" value="Save Config">
<?
 if(file_exists($pluginUpdateFile))
 {
 	//echo "updating plugin included";
	include $pluginUpdateFile;
}
?>
</form>


<p>To report a bug, please file it against the sms Control plugin project on Git:<? echo $gitURL;?> 
</fieldset>
</div>
<br />
</html>
