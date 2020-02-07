Script pour alimenter un compteur d'eau virtuel Domoticz avec les infos extraites de son espace client **![#1589F0](https://placehold.it/15/1589F0/000000?text=+) `Veolia Méditerrannée`**.

__ Prérequis:
 * Domoticz (domoticz.com)
 * Etre abonné Veolia Méditerrannée pour l'eau et avoir ouvert son compte sur le site https://www.eau-services.com
 * PHP cli + PHP curl + PHP-sqlite (apt-get install php5-cli php5-curl php5-sqlite)

__ Mise en place :
  * Se créer un compte sur le site veolia https://www.eau-services.com
  * Domoticz :
	- Créer un matériel de type "Dummy" (virtuel) appelé "Veolia"
	- Depuis ce matériel, créer un capteur virtuel de type "Compteur" appelé "Eau" et l'activer. Retenir l'Idx de ce capteur.
	- Dans l'onglet "Mesures", le périphérique "Eau" apparait, cliquer sur "Editer" pour changer le "Type" d'énergie, choisir "Water". 
	- Attention , Dans "Réglages"=>"Paramètres"=>"Mètres/Compteurs", vérifier que le Compteur Diviseur de l'Eau est bien à '1000'.
  * Placer les deux fichiers water.php et water.inc (fichier de configuration) dans un même répertoire (exemple /home/pi/Veolia)
  * Rendre executable le fichier water.php (chmod +x water.php)
  * Editer le fichier water.inc et renseigner les variables $identifier (votre identifiant veolia),  $password (votre mot de passe sur le site veolia), $sqlite (chemin complet vers la base de données domoticz), $device_idx (Idx du capteur virtuel d'eau).
 * Créer un crontab qui lancera le script water.php une fois par jour.  Exemple:

30 06 * * * sudo /home/pi/Veolia/water.php



__ Notes
  * Veolia Méditerrannée ne fournit les informations de consommation horaire du jour J qu'à J+1.
  * Les informations de conso du jour en cours ne sont pas disponibles
  * La mise à disposition des données de consommation horaire de la veille sur le site Veolia semblent se faire vers 6h00
  * Pour importer un mois particulier d'un année particulière, lancer le script water.php avec les arguments mm et aaaa ou mm est le mois (compris entre 01 et 12) et aaaa l'année (entre 2010 et 2030). Ex: la commande 'water.php 07 2015' importera les données de juillet 2015 (si elles existent sur le site)
