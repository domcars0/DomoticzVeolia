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

# See water.inc  for configuration
require(dirname(__FILE__).'/water.inc');

if ( $identifier == '1234567' && $password =='654321' )
	exit("Vous n'avez pas configuré le script? Merci d'éditer et renseigner le fichier \"water.inc\".\n");

# Veolia web Page
# login page
$loginUrl="https://www.eau-services.com/default.aspx"; 
# Consommations
$dataUrl="https://www.eau-services.com/mon-espace-suivi-personnalise.aspx";

$doday = false;
$pgm = array_shift($argv);
if ( empty($argv[2]) === false && $argv[2] === "debug" ) 
	$debug = true;
else if (  empty($argv[0]) === false && $argv[0] === "debug" ) {
	array_shift($argv);
	$debug = true;
}


if ( empty($argv[0]) === false && is_numeric($argv[0])
                &&  empty($argv[1]) === false && is_numeric($argv[1]) )  {
        if ( $argv[0] > 12 || $argv[0] < 1 )
                exit( " Erreur : Le premier argument numérique (mois) doit etre compris entre 01 et 12\n");
        if ( $argv[1] < 2010 || $argv[1] > 2030 )
                exit( " Erreur : Le second argument numérique (année) doit etre compris entre 2010 et 2030\n");
	$SQLyearMonth =  $argv[1].'-'.$argv[0];
        $dataMonthUrl = $dataUrl . "?ex=".$argv[0]."/".$argv[1]."&mm=".$argv[0]."/".$argv[1];
} else if ( empty($argv[0]) === false ) {
        exit (" Syntaxe : ". $pgm ." [mm yyyy] [debug] \n");
} else if ( date ('d') < 4 )  {
// On doit importer le mois précédent à cause du J-3
        $month_year = date("m/Y",mktime(0, 0, 0, date("m")  , date("d")-3, date("Y")));
	$dataMonthUrl = $dataUrl . "?ex=".$month_year.'&mm='.$month_year;
	$SQLyearMonth =  date("Y-m",mktime(0, 0, 0, date("m")  , date("d")-3, date("Y")));
	// On va tenter le csv par jour
	$doday = true;
}  else {
	$dataMonthUrl = $dataUrl . "?ex=".date('m')."/".date('Y')."&mm=".date('m')."/".date('Y');
	$SQLyearMonth =  date('Y').'-'.date('m');
	// On va tenter le csv par jour
	$doday = true;
}


//login form action url
$post = "login=".$identifier."&pass=".$password;

$ch = curl_init();
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_URL, $loginUrl);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

curl_setopt($ch, CURLOPT_COOKIEJAR, null);
//set the cookie 
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

if ( $debug )
	print("login ok\n");

//page with the content I want to grab
curl_setopt($ch, CURLOPT_URL, $dataMonthUrl );

$csv = curl_exec($ch);
$table = explode("\n",$csv);
// Vire les commentaires en haut du fichier
unset ($table[0]);
// Prepare to parse the CSV
if ( $debug )  {
	print "Consommations journalières du fichier Veolia Med pour le mois ".$SQLyearMonth." : \n";
	foreach ( $table as $key => $info ) {
		if ( empty($info) === false ) {
			$c = split(';',$info);
			print "Le ".$c[0]." : ".$c[1] ." litre(s)\n";
		} else 
			unset($table[$key]);
	}
	// print_r ($table) ;
}

// Open domoticz database
if ( ! file_exists($sqlite))
	exit("Fichier $sqlite introuvable?\n");

$db = new SQLite3($sqlite);
$db->busyTimeout(10000);

// WAL mode has better control over concurrency.
// Source: https://www.sqlite.org/wal.html
// $db->exec('PRAGMA journal_mode = wal;');

// CHeck if Db is OK
$results = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='DeviceStatus';");
$check =  $results->fetchArray(SQLITE3_ASSOC);
if ( empty($check['name']) ) 
	exit("La base de donnée $sqlite semble corrompue ou n'est pas une BdD domoticz?");

# Indique si de nouvelles donnees ont été importées
$new_data = false;

// Transaction
$update = '';
  // On recupere la valeur d'ajustement du DeviceStatus et la date du lastUpdate
$results = $db->query("SELECT date(LastUpdate) as Date, sValue as Counter, AddjValue from DeviceStatus where  ID=".$device_idx." ;");
$deviceStatus = $results->fetchArray(SQLITE3_ASSOC);
$AddjValue = 1000 * $deviceStatus['AddjValue'];
$compteur = empty($deviceStatus['Counter']) ? 0 : $deviceStatus['Counter'];
$deviceLastUpdate = empty($deviceStatus['Date']) ? "2000-01-01" : $deviceStatus['Date'];

if ( $debug) 
	echo "Dernier update du compteur  le ". $deviceLastUpdate ." : " . $compteur . " litres (valeur d'ajustement = ".$AddjValue." litres ).\n";

// Va nous servir si $import_day = true
$lastUpdate = new DateTime($deviceLastUpdate);
$lastUpdate->setTime(23,55,00);

// Schema de la table [Meter_Calendar] :
// [DeviceRowID] BIGINT NOT NULL, [Value] BIGINT NOT NULL, [Counter] BIGINT DEFAULT 0, [Date] DATETIME DEFAULT (datetime('now','localtime')

  // Domotics a pu modifier la valeur du compteur de la dernière entrée on va la remettre à la bonne valeur
$db->exec("UPDATE Meter_Calendar SET Counter=".$compteur." WHERE DeviceRowID=".$device_idx." AND Counter>".$compteur." ;");

// Combien de jour d'historique pour les compteurs à 5mns (Eau)
$results = $db->query("SELECT nValue FROM Preferences WHERE Key=\"5MinuteHistoryDays\" ;");
$Prefs = $results->fetchArray(SQLITE3_ASSOC);
$historyDays = $Prefs['nValue'];
if ( $debug) 
	print "Historique des compteurs = ".$historyDays." jour(s).\n";


// Quelles sont les entrées de la table Meter_Calendar qui sont déjà en BdD 
$calendarEntries = array(); // tableau : Date=>Counter des 31 dernieres entrees (1mois)
if ( $doday ) 
	$query = "SELECT Date,Counter FROM Meter_Calendar WHERE DeviceRowID=".$device_idx." ORDER By Date DESC LIMIT 31 ;";
else 
	$query = "SELECT Date,Counter FROM Meter_Calendar WHERE DeviceRowID=".$device_idx." AND Date LIKE '".$SQLyearMonth."-%' ;";
$results = $db->query($query);
while ( $e = $results->fetchArray(SQLITE3_ASSOC) ) {
	$calendarEntries[$e['Date']] = $e['Counter'];
}

$csv_counter = 0; // lorsque la valeur de conso du jour est negative dans le csv, alors c'est celle du compteur!
$liters = null;
$updateDeviceStatus = false;
$sql_calendar = "";
// ON va récupérer le compteur depuis la table Meter_Calandar
$compteur = 0;
// On construite une requete SQL multiple
foreach ( $table as $entry ) {
	if ( empty($entry) )
		continue;
	$values = explode(';',$entry);
	// Date au format US
	$tdate = explode('/',$values[0] );
	if ( count($tdate) != 3 || empty($tdate[2]) || empty($tdate[1]) || empty($tdate[0]) ) {
		$db->close();
 		exit("Bad data detected in veolia page ?\n " . $values[0] . "\n");
	}
	$sql_date = $tdate[2].'-'.$tdate[1].'-'.$tdate[0];
	$date = new DateTime($sql_date);
	$date->setTime(23,55,00);

	if ( empty($compteur)) { // Récupere la valeur du compteur de la veille;
		$prev_date = new DateTime($sql_date);
		$prev_date->sub(new DateInterval('P1D'));
		$result = $db->query("SELECT Counter FROM Meter_Calendar WHERE DeviceRowID=".$device_idx." and Date='".$prev_date->format('Y-m-d')."' ;");
		$c =  $result->fetchArray(SQLITE3_ASSOC);
		$compteur = $c['Counter'];
		if ( $debug )  {
			$prev_count = $compteur + $AddjValue;
			print (">>> Valeur du compteur à la date du " . $prev_date->format('d/m/Y') . " : " . $prev_count . "\n");
		}
	}


	$liters = $values[1];

	if ( $liters < 0  ) {
		$csv_counter = -$liters;
		$compteur  =  $csv_counter - $AddjValue ;
		print (">>> Valeur du Compteur , selon Veolia, le ".$date->format('d/m/Y')." = ".$csv_counter." \n");
		continue ;
	} else if ( $csv_counter > 0 ) {
		$liters -= $csv_counter;
		if ( $debug ) 
			print (">>> Consommation effective du ".$date->format('d/m/Y')." = ".$liters." litre(s)\n");
  		$csv_counter = 0; 
	}

	if (empty($compteur) === false )
               	$compteur  +=  $liters;
	
	if ( array_key_exists($sql_date, $calendarEntries) ) {
        	$requete = " UPDATE Meter_Calendar SET Counter=".$compteur.", Value=".$liters." WHERE Date='".$sql_date."' AND DeviceRowID=".$device_idx.";";
	} else {
                $requete = " INSERT INTO Meter_Calendar VALUES($device_idx,".$liters.",". $compteur .",'".$sql_date."'); ";
	}
	$sql_calendar .= $requete ;
	if ( $debug ) {
		echo ">" . $requete ."\n";
		usleep(200000);
	}

}

// On va mettre à jour la table DeviceStatus
if ( $date > $lastUpdate  && empty($compteur) === false )  {
	$lastUpdate = $date ;
	$sql_date = $lastUpdate->format('Y-m-d H:i:s');
	$requete = " UPDATE DeviceStatus SET LastUpdate='".$sql_date."' , sValue=".$compteur." WHERE ID=".$device_idx." ;";
	if ( $debug ) 
		echo ">" . $requete . "\n";
	$sql_calendar .= $requete;
}

// On nettoie la table Meter  des entres d'aujourd'hui (Domoticz ??)
$requete = " DELETE FROM Meter WHERE DeviceRowID=".$device_idx." AND Date LIKE '".date('Y')."-".date('m')."-".date('d')." %' ; ";
if ( $debug ) 
        echo ">". $requete . "\n";
$sql_calendar .= $requete;
$db->exec($sql_calendar); 

if ( $doday !== true ) { // Pas de conso horaire
	exit("Mise à jour des données de consommation journalières effectuée avec succès.\nPour les données de consommations horaires, ne pas passer de paramètres.\n");
}
	
// On ramène au moins les $historyDays derniers jours de conso horaire
$Hday = new DateTime();
$Hday->sub(new DateInterval('P'.$historyDays.'D'))->setTime(23,55,00);
$date->add(new DateInterval('P1D'));

if ( $date < $Hday )
	$Hday = $date;

$yesterday = new DateTime();
$yesterday->add(DateInterval::createFromDateString('yesterday'))->setTime(23,55,00);


if ( $debug ) {
        print "Start Day = ". $Hday->format("d-m-Y H:i:s")."\n";
        print "LastUpdate = ".$lastUpdate->format("d-m-Y H:i:s")."\n";
        print "Yesterday = ".$yesterday->format("d-m-Y H:i:s ")."\n";
}

// Compteur 'virtuel' pour remplir la table Meter
$virt_counter = 0 ;

$sql_meter = "";

while ( $Hday <= $yesterday ) {
        $insert = true;
        $jour = $Hday->format('d');
        $mois = $Hday->format('m');
        $an = $Hday->format('Y');
        $dataDayUrl = $dataUrl . "?ex=".$mois.'/'.$an.'&mm='.$mois.'/'.$an.'&d='.$jour;


        if ( $debug )
                print ("Data URL = $dataDayUrl \n");

//page with the content I want to grab
        curl_setopt($ch, CURLOPT_URL, $dataDayUrl );

// Fichier CSV 
        $csv = curl_exec($ch);
        $csvtable = explode("\n",$csv);
//Inutile
        unset($csvtable[0]) ;

        $date = $Hday->format('Y-m-d');

	// On supprime l'entrée de Meter_Calendar générée par Domoticz
	if ( $Hday == $yesterday )  {
		$requete = " DELETE From Meter_Calendar WHERE DeviceRowID=".$device_idx." AND Date = \"".$date."\" ; ";
		$sql_meter .= $requete;
		if ( $debug ) 
			print ">" . $requete."\n";
		if ( isset($calendarEntries[$date]) ) 
			unset ($calendarEntries[$date]);
	}

	
        // Tableau des conso (jour=>conso)
        $day_table = array();

	if ( $debug )  {
		print "Analyse du fichier CSV de consommations horaires du ".$date." : .";
		usleep(100000);
	}
        foreach ( $csvtable as $value )  {
                if ( empty($value) == false ) {
                        $vals = explode(';',$value);
                        if ( !isset($vals[1]) || !isset($vals[2]) || !is_numeric($vals[1]) || !is_numeric($vals[2]) ) {
                                 print "Erreur(s) dans le fichier csv :\n ---------- \n " . $csv ." \n ----------------\n";
                                 $Hday->add(new DateInterval('P1D'));
                                 $insert = false ;
                                 break;
                        }
                        $day_table[$vals[1]] = $vals[2]; // heure => conso
                }
		if ( $debug )  {
			print ".";
			usleep(100000);
		}
        }
        if ( ! $insert )
                continue;
	if ( $debug ) 
		print ": OK\n";
        // print_r($day_table); exit();
	// ON vide la Table Meter pour le jour $date
	$requete = " DELETE FROM Meter WHERE DeviceRowID=".$device_idx." AND Date LIKE \"".$date." %\" ;";
	$sql_meter .= $requete;
	if ( $debug ) 
		print ">".$requete."\n";

// Transaction
        // Calcule la conso totale du Jour pour la table Meter_Calendar
        $day_conso = 0 ;
        foreach ( $day_table as $cvs_hour => $liters ) {
        	$hour = str_pad($cvs_hour - 1 ,2, '0', STR_PAD_LEFT) ;
                $min = 0 ;
                while ( $min < 60 ) {
                	if ( $min == 55 ) {
                        	if ( $Hday > $lastUpdate )  // ON incremente le VRAI compteur qu'à partir du lastUpdate
                               		$compteur += $liters;
                                $day_conso += $liters ;
                                $virt_counter += $liters < 0 ? 0 : $liters;
                        }
                        $requete = " INSERT INTO Meter Values ('".$device_idx."',".$virt_counter.",0,'".$date." ".$hour.":".str_pad($min,2, '0', STR_PAD_LEFT)."') ;";
			$sql_meter .= $requete ;
                        if ( $debug && $min == 55 ) {
                        	print (">" . $requete . "\n");
				usleep(200000);
			}
                        $min += 5;
                }
        }

 // [Meter_Calendar] ([DeviceRowID] BIGINT NOT NULL, [Value] BIGINT NOT NULL, [Counter] BIGINT DEFAULT 0, [Date] DATETIME DEFAULT (datetime('now','localtime')));
        // Si besoin, on met à jour Meter_Calendar & DeviceStatus
        // Ce jour n'est pas dans la table Meter_Calendar
        if ( ! array_key_exists($date, $calendarEntries) ) {
        	$requete = " INSERT INTO Meter_Calendar Values (".$device_idx.",".$day_conso.",".$compteur.",'".$date."') ;";
	} else if ( $calendarEntries[$date] != $day_conso ) {
        	$requete = " UPDATE Meter_Calendar SET Value=".$day_conso." WHERE Date='".$date."' AND DeviceRowID=".$device_idx." ;";
	} else
		$requete = "";
	if ( $debug && empty($requete) === false )
		print (">" . $requete . "\n");
	$sql_meter .= $requete;

       // On met à jour DeviceStatus
       if ( $Hday >= $lastUpdate ) {
       		$requete = " UPDATE DeviceStatus SET LastUpdate='".$date." 23:59:59' , sValue=".$compteur." WHERE ID=".$device_idx." ;";
		$new_data = true;
		if ( $debug ) 
			print (">". $requete . "\n");
		$sql_meter .= $requete;
       }


        $Hday->add(new DateInterval('P1D'));
}

$db->exec($sql_meter);
curl_close($ch);

# TABLE [LightingLog] ([DeviceRowID] BIGINT(10) NOT NULL, [nValue] INTEGER DEFAULT 0, [sValue] VARCHAR(200), [Date] DATETIME DEFAULT (datetime('now','localtime')), [User] VARCHAR(100) DEFAULT (''));

if ( $new_data ) {
	if ( empty($switch_idx) === false && is_numeric($switch_idx) ) {
		$sql_exec = " INSERT INTO LightingLog ('DeviceRowID','sValue') Values ('".$switch_idx."','0');";
		$db->exec($sql_exec);
      		if ( $debug )
               		print (">".$sql_exec."\n");
	} else 
		echo "Nouvelles données importées. Les données, jusqu'à la date " . $date . " incluse, sont disponibles. \n";
} else 
	echo "Pas de nouvelles données importées. \n";
	
$db->close();
