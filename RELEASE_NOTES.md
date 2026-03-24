# ReedCRM 22.1.0 - Interface publique retravaillée - Carte et filtres avancés

Description : Cette version apporte une refonte majeure de l'interface publique (PWA), des améliorations significatives de la carte interactive avec filtres, l'enrichissement des formulaires EventPro, ainsi que plusieurs corrections sur l'API et le menu.

## Nouvelles fonctionnalités et innovations

### Interface publique (PWA)

* Refonte complète de l'interface publique avec de nombreuses fonctionnalités supplémentaires
* Nouvelle interface publique retravaillée, plus fluide et ergonomique

<!-- 📸 Ajouter une screenshot ici -->

### Carte interactive (Map)

* Ajout d'un filtre par date sur la carte des projets
* Affichage des fiches EventPro directement depuis la carte
* Corrections de l'en-tête et des informations affichées sur les fiches projet

<!-- 📸 Ajouter une screenshot ici -->

### EventPro

* Affichage de la modale EventPro depuis la page des événements de projet
* Ajout du champ statut d'opportunité par défaut lors de la création d'un EventPro
* Ajout du champ pourcentage d'opportunité à la création d'un EventPro
* Remplacement de l'iframe par une modale pour une meilleure intégration

### Opportunités

* Ajout du site web comme champ sur les opportunités

### Menu

* Ajout des entrées de menu : opportunités, factures récurrentes et propositions commerciales ouvertes
* Séparation dans le menu entre « Importer un projet » et « Projets importés »

### Événements rapides (QuickEvent)

* Ajout d'un second événement de rappel
* Ajout des traductions manquantes

### API

* Ajout de la catégorie lors de la création d'un projet via l'API
* Correction des droits d'accès à l'API

### Liste des projets

* Ajout de nouvelles colonnes sur la liste des projets avec mise en forme

<!-- 📸 Ajouter une screenshot ici -->

## Améliorations & corrections

### EventPro

* Correction de la fermeture de la modale EventPro lors d'un clic en dehors
* Correction de la position du calendrier pour le sélecteur de date
* Correction de la longueur maximale du champ titre
* Correction du tiers sélectionné dans la modale
* Correction de la création de ticket depuis la modale EventPro

### Interface & CSS

* Effet grisé sur le compteur d'EventPro lorsqu'il est à zéro
* Correction d'un excès de gris dans l'interface

### Modèle de données

* Suppression d'un champ inutile dans le formulaire de création/édition de projet
* Correction du numéro pour `reedcrm_website`

### Navigation

* Correction d'une erreur fatale sur l'entrée de menu

### Fiche action (ProCard)

* Correction : les actions sont maintenant marquées comme effectuées

### Modèles de création rapide (QuickCreationTpl)

* Correction : le bouton IA n'apparaissait plus partout

## Comparaison des versions [22.0.0](https://github.com/Eoxia/easycrm/compare/22.0.0...22.1.0) et 22.1.0

* [#544] [PWA] add: nouvelles fonctionnalités frontend [`#545`](https://github.com/Eoxia/easycrm/pull/545)
* [#541] [CI] add: Build assets [`#542`](https://github.com/Eoxia/easycrm/pull/542)
* [#534] [PWA] ui/ux: ajout de nombreuses fonctions PWA [`#535`](https://github.com/Eoxia/easycrm/pull/535)
* [#536] [API] add: description opportunité [`#537`](https://github.com/Eoxia/easycrm/pull/537)
* [#531] [Map] add: fiche EventPro sur la carte projet [`#539`](https://github.com/Eoxia/easycrm/pull/539)
* [#531] [Map] fix: informations fiche projet
* [#530] [Map] add: filtre par date [`#538`](https://github.com/Eoxia/easycrm/pull/538)
* [#532] [Map] fix: en-tête carte [`#533`](https://github.com/Eoxia/easycrm/pull/533)
* [#513] [CSS] add: effet gris compteur EventPro à zéro [`#527`](https://github.com/Eoxia/easycrm/pull/527)
* [#513] [CSS] fix: trop de gris dans l'interface
* [#518] [Mod] remove: champ inutile création/édition projet [`#526`](https://github.com/Eoxia/easycrm/pull/526)
* [#521] [EventPro] add: champ statut opportunité par défaut [`#525`](https://github.com/Eoxia/easycrm/pull/525)
* [#523] [Mod] fix: numéro reedcrm_website [`#524`](https://github.com/Eoxia/easycrm/pull/524)
* [#523] [EventPro] add: site web sur les opportunités
* [#521] [EventPro] add: opp_percent à la création [`#522`](https://github.com/Eoxia/easycrm/pull/522)
* [#517] [EventPro] fix: fermeture modale au clic extérieur [`#520`](https://github.com/Eoxia/easycrm/pull/520)
* [#516] [EventPro] fix: position calendrier datepicker [`#519`](https://github.com/Eoxia/easycrm/pull/519)
* [#516] [EventPro] fix: longueur max champ titre
* [#515] [Map] fix: géolocalisation
* [#438] [Project] add: nouvelles colonnes liste & styles [`#497`](https://github.com/Eoxia/easycrm/pull/497)
* [#499] [API] fix: droits API [`#501`](https://github.com/Eoxia/easycrm/pull/501)
* [#463] [API] fix: catégorie lors de createProject
* [#483] [EventPro] fix: passage iframe → modale [`#488`](https://github.com/Eoxia/easycrm/pull/488)
* [#486] [ProCard] fix: action marquée comme effectuée [`#487`](https://github.com/Eoxia/easycrm/pull/487)
* [#336] [QuickEvent] add: traductions [`#485`](https://github.com/Eoxia/easycrm/pull/485)
* [#336] [QuickEvent] add: second événement de rappel
* [#483] [EventPro] fix: tiers sélectionné [`#484`](https://github.com/Eoxia/easycrm/pull/484)
* [#476] [Menu] add: opportunités, factures récurrentes, propals ouvertes
* [#478] [Menu] add: séparation import projet / projets importés
* [#476] [EventPro] add: modale sur page événements projet
* [#448] [EventPro] fix: création ticket depuis modale [`#480`](https://github.com/Eoxia/easycrm/pull/480)
* [#469] [QuickCreationTpl] fix: bouton IA partout [`#470`](https://github.com/Eoxia/easycrm/pull/470)
* [#508] [CI] fix: editorconfig & release
