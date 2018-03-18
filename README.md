Script pour alimenter un compteur d'eau virtuel Domoticz avec les infos extraites de son espace client Veolia.
Pour parser le site veolia, ce code utilise la librairie simple_html_dom.php (@see http://sourceforge.net/projects/simplehtmldom/)

__ Prérequis:
 * Domoticz (domoticz.com)
 * Etre abonné Veolia pour l'eau et avoir ouvert son compte sur le site https://www.eau-services.com
 * PHP cli + PHP curl + PHP-sqlite (apt-get install php5-cli php5-curl php5-sqlite)

__ Mise en place :
  * Se créer un compte sur le site veolia https://www.eau-services.com
  * Domoticz :
	- Créer un matériel de type "Dummy" (virtuel) appelé "Veolia"
	- Depuis ce matériel, créer un capteur virtuel de type "Compteur" appelé "Eau" et l'activé. Retenir l'Idx de ce capteur.
	- Dans l'onglet "Mesures", le périphérique "Eau" apparait, cliquer sur "Editer" pour changer le "Type" d'énergie, choisir "Water". 
	- Attention , Dans "Réglages"=>"Paramètres"=>"Mètres/Compteurs", vérifier que le Compteur Diviseur de l'Eau est bien à '1000'.
  * Placer les trois fichiers water.php, water.inc (fichier de configuration) et simple_html_dom.php dans un même répertoire (exemple /home/pi/Veolia)
  * Rendre executable le fichier water.php (chmod +x water.php)
  * Editer le fichier water.inc et renseigner les variables $identifier (votre identifiant veolia),  $password (votre mot de passe sur le site veolia), $sqlite (chemin complet vers la base de données domoticz), $device_idx (Idx du capteur virtuel d'eau).
 * Créer un crontab qui lancera le script water.php une fois par jour. 
Ex: 
# Eau Veolia
30 00 * * * sudo /home/pi/Veolia/water.php


__ Notes
  * Veolia ne fournit les informations de consommation du jour J qu'à J+3.
  * Les informations de conso du jour en cours ne sont pas disponibles
  * Pour importer un mois particulier d'un année particulière, lancer le script water.php avec les arguments M et A ou M est le mois (compris entre 1 et 12) et A l'année (entre 2010 et 2030). Ex: la commande 'water.php 7 2015' importera les données de juillet 2015 (si elles existent sur le site)
