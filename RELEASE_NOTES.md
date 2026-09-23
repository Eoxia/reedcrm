# [ReedCRM] [23.3.0] - Suivi DU enrichi - Clôture rapide paramétrable - Calendrier chiffré

Description : Cette version étoffe le **suivi du document unique** — rendez-vous client dans le calendrier des interventions, audits faits visibles et facturables, lignes manuelles liées, saisie allégée des autres engagements. La **modale de clôture rapide** devient paramétrable : type de relance choisi sur des pastilles ou dans une liste, renommage de l'événement clôturé, délai de report saisi comme un nombre. Le **calendrier des interventions** affiche le montant apporté par chaque intervention, le **tableau Todo** signale les cartes à venir et le nombre de relances, et les relances d'une facture récurrente peuvent être confiées à un utilisateur choisi.

## Nouvelles fonctionnalités et innovations

### Suivi du document unique

* Les **rendez-vous client** apparaissent dans le calendrier des interventions, aux côtés du suivi des autres engagements.
* Les **audits déjà réalisés** sont visibles et peuvent être facturés depuis le suivi.
* Les lignes saisies à la main sont rattachées à leur objet, et la saisie des autres suivis est allégée.
* La section « Portefeuille service » est désormais masquée par défaut.

### Clôture rapide d'un événement

* Le **type de relance** se choisit directement dans la modale, sur quatre pastilles ou dans une liste déroulante — au choix dans la configuration du module.
* L'événement clôturé peut être **renommé** depuis la modale.
* Le délai de report en mois est saisi comme un nombre.

### Tableau Todo

* Badge **« à venir »** sur les cartes planifiées après aujourd'hui.
* **Compteur de relances** sur le badge d'objet des cartes.
* La barre de défilement horizontale reste accessible en bas de l'écran.

### Interventions

* Le **montant apporté** par chaque intervention s'affiche sur le calendrier.
* La configuration du calendrier accepte **plusieurs étiquettes de produit**.

### Facturation

* Les relances d'un **modèle de facture récurrente** se confient à un utilisateur choisi.
* Le graphique annuel du suivi FA est coloré par avancement de facturation.

### Fiches

* Les champs vides sont masqués sur les fiches.

## Améliorations & corrections

### Modale de clôture rapide

* **Trois écrans partaient en erreur fatale** — Todo, Opportunités et Outils : le template exigeait un fichier du socle qui n'existe pas. Les feuilles de style et le script de la modale sont désormais servis par un utilitaire local, avec une version prise sur le fichier pour ne plus être servis périmés depuis le cache.

### Suivi du document unique

* La facture d'un audit clôturé sans devis sur la ligne est retrouvée.
* Un audit facturé avant sa réalisation restait affiché « À facturer ».

### Pocket

* Carte d'enregistrement : titre lisible des objets liés, formulation des actions plus aérée, pictogrammes de type, et correction des éléments d'action et du résumé.
* La synchronisation des enregistrements tourne une fois par jour, le soir, au lieu de repasser en continu.

### Listes d'appel

* La bannière de la fiche navigue par identifiant et ignore les listes supprimées.

### Divers

* Formulation française du champ du formulaire web, et plus de point doublé sur « Réf. ».
* Le suivi FA ne parcourt plus la table des factures une fois par modèle récurrent.

### Intégration continue

* Les assets sont vérifiés sur les pull requests au lieu d'un commit automatique devenu impossible, et les scripts npm pointent sur la nouvelle chaîne de build du socle.

## Comparaison des versions [23.2.0](https://github.com/Eoxia/reedcrm/compare/23.2.0...23.3.0) et 23.3.0
