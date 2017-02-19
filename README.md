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
	- Dans l'onglet "Mesures", le périphérique Eau apparait, cliquer sur "Editer" pour changer le "Type" d'émergie, choisir "Water". 
  * Placer les deux fichiers water.php et simple_html_dom.php dans un même répertoir (exemple /home/pi/Veolia)
  * Editer le fichier water.php et rensigner les variables $identifier (votre identifiant veolia),  $password (votre mot de passe sur le site veolia), $sqlite (chemin complet vers la base de données domoticz), $device_idx (Idx du capteur virtuel d'eau). Pour importer un mois antérieur au mois cournat, ajuster la variable $month en conséquence (remettre à null en usage normal)
 * Créer un crontab qui lancera le script water.php une fois par jour.

__ Notes
  * Veolia ne fournit les informations de consommation du jour J qu'à J+3.
  * Les informations de conso du jour en cours ne sont pas disponibles
