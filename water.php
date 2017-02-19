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

######### Configuration ###################
# identifier and password of your Veolia account
$identifier = "1234567";
$password = "654321";
# Path to the domoticz sqlite database
$sqlite = "/home/pi/domoticz.db";
# Virtual counter Idx
$device_idx = 15;
# Mois a importer (utiliser uniquement lors de l'initialisation)
# Importe le mois courant si null
# Format "MM/AAAA" . Exemple :
#$month = "11/2016";
$month = null;

############## End Configuration ###########################

$debug = false;

# Veolia web Page
# login page
$loginUrl="https://www.eau-services.com/default.aspx"; 
# Consommations
$dataUrl="https://www.eau-services.com/mon-espace-suivi-personnalise.aspx";
if ( $month ) {
	$dataUrl .= "?mm=".$month;
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
$last_tr = $table->find('tr',-1);
$liters = $date = null;

// Open domoticz database
$db = new SQLite3($sqlite);
// Transaction
$db->exec("BEGIN IMMEDIATE TRANSACTION");
try {
  // ON cherche la dernière valeur du compteur (em m3!)
  $add_counter = false;
  $results = $db->query("SELECT Counter,Date from Meter_Calendar WHERE Counter != 0 ORDER By Date DESC LIMIT 1; ");
  if ( $results ) {
  	$counter = $results->fetchArray(SQLITE3_ASSOC);
	if ( empty($counter['Counter'])) {
		// On initialise avec la valeur d'offset de la table DeviceStatus
		$results = $db->query("SELECT date(LastUpdate) as Date ,AddjValue as Counter from DeviceStatus where  ID=".$device_idx." ;");
		$counter = $results->fetchArray(SQLITE3_ASSOC);
	}
  }

  $last_date = $counter['Date'];
  $compteur = $counter['Counter'];

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
			if ( empty($exist) === false ) {
				if ( empty($exist['Counter']) === false || empty($add_counter) ) { // Deja en BdD avec compteur
					if ( $debug ) echo "Rien à faire pour la date du ".$date.", compteur = ".$exist['Counter']." (".$add_counter.")\n";
					continue; 
				}
				// Faut ajouter la conso au  compteur
				$compteur +=  $liters/100;
				// On supprime l'entrée qui va être mise à 
                        	$sql_query = "UPDATE Meter_Calendar SET Counter=".$compteur." WHERE Date='".$exist['Date']."' AND DeviceRowID=".$device_idx.";";
			} else {
				$compteur = $add_counter ? $compteur + $liters/100 : 0 ;
				$sql_query = "INSERT INTO Meter_Calendar VALUES($device_idx,".$liters.",". $compteur .",'".$date."'); ";
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
			if ( $date === $last_date )
				$add_counter = true;
			$conso = true;
		}
	}
  }
} catch ( Exception $e) {
  $db->exec("ROLLBACK TRANSACTION");
  exit($e->message());
}

$db->exec("COMMIT TRANSACTION");
