<?php
/*
* Parse Veolia customer account web page to fill Domoticz 
*
* Contributions by  : 
* 		domcars0
*
*
* Licensed under The GPL V3 License
* Redistributions of files must retain the above copyright notice.
*
* @author domcars0
*
*/

######### Configuration ###################
# identifier and password of your Veolia account
$identifier = "1234567";
$password = "654321";

# Path to the domoticz sqlite database
$sqlite = "/home/pi/domoticz/domoticz.db";

# Water Virtual counter Idx
$device_idx = 12;

# Nom (domainname) du serveur veolia
# $server_name = "agence.eaudugrandlyon.com";
$server_server = "www.eau-services.com";

# Pour infos de debugage passer à true.
$debug = false;

############## End Configuration ###########################
?>
