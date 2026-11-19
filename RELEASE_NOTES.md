# [ReedCRM] [23.3.2] - Correctif : plus de journaux de débogage en production

Description : Cette version corrective retire trois **journaux de débogage** livrés depuis la 23.1.0, qui écrivaient sur le serveur à chaque appel de la liste d'appels de l'application mobile, à chaque envoi d'un enregistrement audio et à chaque création rapide. Elle ne contient aucune nouveauté.

## Améliorations & corrections

### Liste d'appels et création rapide de l'application mobile

* La liste d'appels écrivait les paramètres de **chaque requête** dans `documents/ecm/debug_upload_top.log`, et ceux de **chaque envoi audio** dans `documents/ecm/debug_upload.log`. Ces écritures sont retirées ; l'enregistrement des fichiers audio est inchangé.
* La création rapide écrivait une ligne dans `debug_update.log`, **dans le dossier du module lui-même**. Elle passe désormais par le journal de Dolibarr, en niveau débogage.

Après la mise à jour, ces trois fichiers peuvent être supprimés : plus rien ne les écrit.

## Comparaison des versions [23.3.1](https://github.com/Eoxia/reedcrm/compare/23.3.1...23.3.2) et 23.3.2

* [#1238] [PWA] fix: plus de journaux de débogage écrits en production [`08d6df1`](https://github.com/Eoxia/reedcrm/commit/08d6df1)
