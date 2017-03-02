#!/usr/bin/php
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
date_default_timezone_set('Europe/Paris');

######### Configuration ###################
# identifier and password of your Veolia account
#$identifier = "1234567";
$identifier = "3921758";
#$password = "654321";
$password = "135137";
# Path to the domoticz sqlite database
$sqlite = "/home/pi/domoticz.db";
$sqlite = "/tmp/domoticz.db";
#$sqlite = "./domoticz.db";
# Virtual counter Idx
$device_idx = 15;
# Mois a importer (utiliser plutot les arguments! )
# Importe le mois courant si null
# Format "MM/AAAA" . Exemple :
# $month = "11/2016";
$month = null;

############## End Configuration ###########################

$debug = false;

# Veolia web Page
# login page
$loginUrl="https://www.eau-services.com/default.aspx"; 
# Consommations
$dataUrl="https://www.eau-services.com/mon-espace-suivi-personnalise.aspx";

// On doit importer le mois précédent à cause du J-3
if ( !$month && date ('d') < 3 )  {
        $month = date("m/Y",mktime(0, 0, 0, date("m")  , date("d")-3, date("Y")));
        $dataUrl .= "?mm=".$month;
} else if ( $month ) {
	# Un mois particulier ?
	$dataUrl .= "?mm=".$month;
	$debug = true;
} else if ( empty($argv[1]) === false && is_numeric($argv[1]) 
		&&  empty($argv[2]) === false && is_numeric($argv[2]) )  {
	if ( $argv[1] > 12 || $argv[1] < 1 )
		exit( " Erreur : Le premier argument doit etre compris entre 1 et 12\n");
	if ( $argv[2] < 2010 || $argv[2] > 2030 )
		exit( " Erreur : Le second argument doit etre compris entre 2010 et 2030\n");
	$dataUrl .= "?mm=".$argv[1].'/'.$argv[2] ;
        $debug = true;
}

require('simple_html_dom.php');

//login form action url
$post = "login=".$identifier."&pass=".$password;

$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

curl_setopt($ch, CURLOPT_COOKIEJAR, null);
//set the cookie the site has for certain features, this is optional
curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");
curl_setopt($ch, CURLOPT_USERAGENT,
    "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_exec($ch);

//page with the content I want to grab
curl_setopt($ch, CURLOPT_URL, $dataUrl );

//Yes! DomDocument()!! 
$string = curl_exec($ch);
curl_close($ch);

// Prepare to parse the Dom
$html=str_get_html($string);

$table = $html->find('table[class=responsive]',0);
if ( !is_object($table))
	exit("Le code ne semble pas provenir du site Veolia Mediterranée ou Veolia Mediterranee a modifié son site,  désolé.\n");

$liters = $date = null;

// Open domoticz database
if ( ! file_exists($sqlite))
	exit("Fichier $sqlite introuvable?\n");
$db = new SQLite3($sqlite);
// CHeck if Db is OK
$results = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='DeviceStatus';");
$check =  $results->fetchArray(SQLITE3_ASSOC);
if ( empty($check['name']) ) 
	exit("La base de donnée $sqlite semble corrompue ou n'est pas une BdD domoticz?");

// Transaction
$db->exec("BEGIN IMMEDIATE TRANSACTION");
try {
  // ON cherche la dernière valeur du compteur (em m3!)
  $add_counter = false;
  $update = '';
  // On initialise le compteur avec la valeur sValue de la table DeviceStatus
  $results = $db->query("SELECT date(LastUpdate) as Date ,sValue as Counter from DeviceStatus where  ID=".$device_idx." ;");
  $deviceStatus = $results->fetchArray(SQLITE3_ASSOC);
  $last_update = $update_date = $deviceStatus['Date'];
  $compteur = $deviceStatus['Counter'];
  if ( $debug) echo "Dernier update du compteur  le $last_update : ".$deviceStatus['Counter']." m3 \n";

  // ON supprime l'entrée correspondante à hier (faites par domoticz  à 0h00 ?)
  $yesterday = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")));
 $db->exec("DELETE FROM Meter_Calendar WHERE Date='".date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")))."' AND DeviceRowID=".$device_idx." ;");
  if ( $debug ) print "Hier = $yesterday \n";

  
  foreach ( $table->find('tr') as $tr ) {
	$conso = false;
	foreach ( $tr->find('td') as $td ) {
		if ( $conso ) { 
			// Faut diviser par 10 ?
			$liters = $td->innertext /10;
			// [Meter_Calendar] ([DeviceRowID] BIGINT NOT NULL, [Value] BIGINT NOT NULL, [Counter] BIGINT DEFAULT 0, [Date] DATETIME DEFAULT (datetime('now','localtime')));

			// l'entrée existe déjà ?
			$exists = $db->query("SELECT Date,Counter FROM Meter_Calendar WHERE Date = '" . $date ."' ;");
			$exist = $exists->fetchArray(SQLITE3_ASSOC);
			if ($add_counter)
				$compteur +=  $liters;
			if ( empty($exist) === false ) {
				if (empty($add_counter) ) { // Deja en BdD 
					if ( $debug ) echo "Rien à faire pour la date du ".$date.", ($liters L), compteur = ".$exist['Counter']." (".$add_counter.")\n";
					continue; 
				}
				//  Mise à jour avec Counter
                        	$sql_query = "UPDATE Meter_Calendar SET Counter=".$compteur." WHERE Date='".$exist['Date']."' AND DeviceRowID=".$device_idx.";";
				$update = $exist['Date'] . ' ' . date("H:i:s");
			} else {
				$this_counter = $add_counter ? $compteur : 0 ;
				$sql_query = "INSERT INTO Meter_Calendar VALUES($device_idx,".$liters.",". $this_counter .",'".$date."'); ";
				$update = $date . ' ' . date("H:i:s");
			}

			if ( $debug ) echo "requete SQL : ".$sql_query ."\n";
			// Et on insert.
			$db->exec($sql_query); 

		} else {
			// Date au format US
			$date = explode('/',$td->innertext );
			if ( count($date) != 3 || empty($date[2]) || empty($date[1]) || empty($date[0]) )
				exit('Bad date detected in veolia web page ? ' . $td->innertext);
			$date = $date[2].'-'.$date[1].'-'.$date[0];
			if ( $date === $last_update )
				$add_counter = true;
			$conso = true;
		}
	}

  }
  if (  $add_counter && $update ) { // On va mettre à jour la table DeviceStatus
	$sql_query = "UPDATE DeviceStatus SET LastUpdate='".$update."' , sValue=".$compteur." WHERE ID=".$device_idx." AND LastUpdate<'".$update."';";
	if ( $debug ) echo $sql_query . "\n";
	$db->query($sql_query);
  }
} catch ( Exception $e) {
  $db->exec("ROLLBACK TRANSACTION");
  exit($e->message());
}

$db->exec("COMMIT TRANSACTION");
