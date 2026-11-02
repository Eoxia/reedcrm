# [ReedCRM] [23.3.1] - Conformité du paquet Dolistore - Dolibarr 24 - Contrôles qualité

Description : Cette version corrige les deux motifs qui faisaient **refuser le paquet par le Dolistore** et empêchaient la mise à jour du module sur la boutique. Elle élargit la **compatibilité à Dolibarr 24**, ajoute une **chaîne de contrôles qualité** sur les pull requests — analyse statique, lint PHP et parité des fichiers de langue — et supprime cinq clés de traduction mortes qui laissaient des libellés en anglais.

## Améliorations & corrections

### Conformité du paquet Dolistore

* Le contrôle de paquet du Dolistore refusait le zip : quatre fichiers chargeaient l'environnement Dolibarr par un `require` unique, alors que la règle demande **au moins deux tentatives** — une pour le module à la racine de Dolibarr, une pour le module dans `custom`. Les trois points d'entrée Pocket, le manifeste de l'application web et le script de clients de test sont alignés sur le bootstrap utilisé par les 39 autres fichiers du module.
* Les points d'entrée Pocket passaient directement par le socle Saturne, sans définir `$moduleNameLowerCase` au préalable. Ils entrent désormais par `reedcrm.main.inc.php` comme les autres.
* Second motif de refus : six **libs du socle** étaient incluses par un chemin `/custom` en dur, qui ne résout pas si les modules sont installés à la racine de Dolibarr. Elles passent par `dol_include_once`, qui cherche dans les deux racines de documents.

### Compatibilité

* Le module déclare **Dolibarr 23 au minimum et 24 au maximum**.

### Traductions

* Cinq clés mortes sont retirées du fichier anglais : elles n'avaient pas d'équivalent français, ce qui faisait basculer en anglais l'ensemble des libellés d'une même requête.

### Intégration continue

* Les pull requests passent désormais **PHPStan**, un **lint PHP** et un contrôle de **parité des fichiers de langue** français / anglais.
* La baseline PHPStan figeait le numéro de version du module dans un message d'erreur : toute release cassait la chaîne qualité. Le motif est désormais ignoré indépendamment du numéro.

## Comparaison des versions [23.3.0](https://github.com/Eoxia/reedcrm/compare/23.3.0...23.3.1) et 23.3.1

* [#1016] [CI] fix: ignorer par motif la version des triggers, figée dans la baseline [`cdfddf5`](https://github.com/Eoxia/reedcrm/commit/cdfddf5)
* [#1013] [Module] fix: inclure les libs de Saturne par dol_include_once [`f7ab1c4`](https://github.com/Eoxia/reedcrm/commit/f7ab1c4)
* [#1011] [Lang] fix: retirer cinq clés mortes de en_US [`1de64ff`](https://github.com/Eoxia/reedcrm/commit/1de64ff)
* [#1009] [Module] fix: bootstrap main.inc.php à deux tentatives, exigé par le Dolistore [`e2295b3`](https://github.com/Eoxia/reedcrm/commit/e2295b3)
* [CI] fix: compléter les dossiers du coeur vus par PHPStan [`e95d936`](https://github.com/Eoxia/reedcrm/commit/e95d936)
* [#1007] [CI] feat: PHPStan, lint PHP et parité des langues [`9d43ffc`](https://github.com/Eoxia/reedcrm/commit/9d43ffc)
* [#1005] [Module] rework: bornes de version Dolibarr 23 minimum, 24 maximum [`c87859b`](https://github.com/Eoxia/reedcrm/commit/c87859b)
