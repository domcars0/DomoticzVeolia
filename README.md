Script pour alimenter un compteur d'eau virtuel Domoticz avec les infos extraites de son espace client **![#1589F0](https://placehold.it/15/1589F0/000000?text=+) `Veolia Méditerrannée`** ou **`Veolia Eau du Grand Lyon`**.

ATTENTION : Ce script n'est pas un 'script Domoticz' au sens propre du terme. Les données n'etant pas disponibles en temps réel, ce script écrit directement dans la base de données de Domoticz, ce que ne permettent pas les 'scripts Domoticz' (ni les plugins). Il n'est donc pas lancé automatiquement par Domoticz, mais par la cron.

## Prérequis:
 * Domoticz (domoticz.com)
 * Être abonné Veolia Méditerrannée (ou 'Eau du Grand Lyon') pour l'eau et avoir ouvert son compte sur le site https://www.eau-services.com (ou https://agence.eaudugrandlyon.com/)
 * PHP et les modules : PHP cli , PHP curl , PHP-sqlite3

Les commandes d'install de PHP et des modules varient selon l'OS, sa version, et la version de PHP (ex: sudo apt install php-cli php-curl php-sqlite3)

## Mise en place :
  * Domoticz :
	- Créer un matériel de type "Dummy" (virtuel) appelé "Veolia"
	- Depuis ce matériel, créer un capteur virtuel de type "Compteur" appelé "Eau" et l'activer. Retenir l'Idx de ce capteur.
	- Dans l'onglet "Mesures", le périphérique "Eau" apparait, cliquer sur "Editer" pour changer le "Type" d'énergie, choisir "Water".
	- Dans "Réglages"=>"Paramètres"=>"Mètres/Compteurs", modifier le Compteur Diviseur de l'Eau et indiquer '1000' (la valeur par défaut est '100').
  * Placer les deux fichiers water.php et water.inc (fichier de configuration) dans un même répertoire (exemple /home/pi/Veolia)
  * Rendre executable le fichier water.php (chmod +x water.php)
  * Editer le fichier water.inc et renseigner les variables:
    - $identifier : votre identifiant sur le site Veolia,
    - $password : votre mot de passe sur le site Veolia,
    - $sqlite : chemin complet vers la base de données Domoticz,
    - $device_idx : Idx du capteur virtuel d'eau,
    - $server_name : nom complet du serveur Veolia.
 * Créer une crontab qui lancera le script water.php une fois par jour. Exemple :

~~~~
30 06 * * * /home/pi/Veolia/water.php
~~~~

## Notes
  * Veolia Méditerrannée (ou Grand Lyon) ne fournit les informations de consommation horaire du jour J qu'à J+1.
  * Les informations de conso du jour en cours ne sont pas disponibles.
  * La mise à disposition des données de consommation horaire de la veille sur le site Veolia semblent se faire vers 6h00.
  * Pour importer un mois particulier d'une année particulière, lancer le script water.php avec les arguments MM et AAAA où MM est le mois (compris entre 01 et 12) et AAAA l'année (entre 2010 et 2030). Ex: la commande 'water.php 07 2020' importera les données de juillet 2020 (si elles existent sur le site).
