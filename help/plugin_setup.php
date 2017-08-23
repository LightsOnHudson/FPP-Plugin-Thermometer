<b>
Thermometer Help Page</b>

<p>You will need to connect the Temperature probe to the PI like the picture below.
<p>You will need to attach at a minimum a 4.7k resister between VCC and data on the probe

<ul>
<li>DATA of probe to PI Physical pin 7</li>
<li>VCC of probe to +3.3V on Pi Physical pin 1</li>
<li>GND of probe to GND on Pi Physical Pin 6 (there are other GNDs on PI as well, 6 is what we have proven to work)</li>
<li>Currently you will need to make a modification to the /boot/config.txt file. The plugin will eventually make this for you</li>
<li>You need to visit the Thermometer page at least ONCE to have the libraries registered for the probe to be probed for data</li>
</ul>
<img src="/plugin.php?plugin=Thermometer&page=TempWiring.png">

