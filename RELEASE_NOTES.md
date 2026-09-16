# [ReedCRM] [23.2.0] - Todo & relances automatiques - Pocket - Tableau de bord des tickets - Dates d'intervention - Suivi de facturation

Description : Version majeure. Elle ajoute un **tableau Todo** (kanban des événements d'agenda) accompagné de deux **relances automatiques** quotidiennes sur les devis et les factures, le **miroir Pocket** des enregistrements et de leurs actions rattachables aux objets métier, un **tableau de bord des tickets** centré sur le temps et les personnes (également exposé par l'API), les **dates d'intervention** par unité de ligne de service avec leur événement d'agenda et leur calendrier, ainsi qu'un ensemble complet de **suivi de facturation** (factures récurrentes en live, audits DU, devis signés non facturés, clients Digirisk sans abonnement). Le **menu de gauche** est réorganisé en sections, la **création rapide** gagne la recherche SIREN et les tags de contact, et la PWA permet désormais de consulter une opportunité et de saisir une relance depuis un mobile.

> **Mise à jour** : le module doit être **désactivé puis réactivé** — cette version apporte de nouvelles tables (Pocket, dates d'intervention, liste Digirisk masquée), une entrée de dictionnaire, des constantes, des entrées de menu et deux tâches planifiées.

## Nouvelles fonctionnalités et innovations

### Todo : le kanban des événements d'agenda

* Nouvelle page **Todo** : un kanban dont les colonnes sont les statuts de l'événement — `Devis à relancer` · `Factures à relancer` · `À faire` · `En cours` · `Réalisé` · `Non applicable`.
* Glisser une carte d'une colonne à l'autre écrit le pourcentage de l'événement ; la barre de progression est elle aussi déplaçable.
* **Édition en place** sur la carte : libellé, dates de début et de fin, propriétaire et utilisateurs affectés.
* La carte porte le type d'événement, la référence, le tiers, le projet, le devis / la facture d'origine, un badge « En retard », le lieu et la note.
* **Filtres** : utilisateur affecté (soi-même par défaut), période, type d'événement, recherche texte, masquage des événements automatiques — critères conservés d'une visite à l'autre.
* **Menu de colonne** pour trier et masquer les colonnes, chargement progressif par pages et réglage de la largeur / de l'espacement.
* **Clôture rapide** : depuis le pourcentage d'une carte, à une date choisie, avec datation de la fin de l'événement.

<!-- 📸 Ajouter une screenshot ici -->

### Relances automatiques des devis et des factures

* `ReedcrmTodoCron::createProposalRelaunchEvents` : un événement à faire pour chaque **devis validé depuis plus de 30 jours** qui n'est ni signé ni refusé.
* `ReedcrmTodoCron::createInvoiceRelaunchEvents` : un événement à faire pour chaque **facture validée et non payée** plus de 30 jours après son échéance (avoirs exclus).
* Délais réglables (`REEDCRM_TODO_PROPAL_RELAUNCH_DAYS`, `REEDCRM_TODO_INVOICE_RELAUNCH_DAYS`), exécution quotidienne.
* L'événement est créé **sans date** — c'est une chose à faire, pas un rendez-vous — affecté à celui qui a validé l'objet, et rattaché au devis / à la facture : il apparaît donc aussi dans l'onglet Agenda de l'objet.
* Anti-doublon : une relance déjà ouverte, ou créée depuis moins que le délai configuré, bloque la suivante.

<!-- 📸 Ajouter une screenshot ici -->

### Pocket : miroir des enregistrements et rattachement aux objets

* Nouvel objet **enregistrement Pocket** reflétant les enregistrements d'un dossier configurable, avec les **actions** que Pocket en extrait.
* Configuration dans `admin/pocket.php` : clé d'API avec **test de connexion**, dossier importé alimenté en direct par l'API, et objets auxquels un enregistrement peut être rattaché (mécanisme d'objets liés Saturne).
* Synchronisation **idempotente** : elle n'écrase jamais ce qui appartient à l'utilisateur (statut, tiers, note, liens, utilisateur affecté, événement créé).
* **Rattachement depuis l'onglet de l'objet métier**, là où l'utilisateur a le contexte ; recherche de n'importe quel objet par type et par référence, au-delà du seul tiers.
* Édition en place du **libellé d'action**, du **tiers**, du **statut** (sur le badge du bandeau) et réécriture de la **synthèse**, dont les blocs graphiques sont rendus tels quels.

<!-- 📸 Ajouter une screenshot ici -->

### Tableau de bord des tickets

* Nouvelle page de pilotage branchée sur le **renderer de tableau de bord Saturne** : graphiques masquables, filtrables et exportables en CSV comme tous les tableaux de bord Evarisk.
* **4 widgets** : flux des tickets, délais (prise en charge, première réponse, résolution — moyenne **et** médiane), temps loggé, personnes.
* **13 graphiques** : charge et délais par affecté, temps loggé par affecté, messages publics contre notes privées, créés contre clôturés par mois, temps loggé par mois, tickets ouverts par statut, âge du backlog, répartition des temps de résolution, gravité, type, créations par jour de semaine et par heure, top tiers.
* **3 listes** : charge détaillée par affecté, tickets ouverts les plus anciens, tickets dormants. **2 filtres** : période analysée et affecté.
* Les indicateurs de **flux** suivent la période choisie, ceux de **stock** décrivent toujours les tickets ouverts à l'instant ; chaque infobulle dit à quelle famille appartient le compteur.
* Le temps loggé est lu à travers les **tâches ticket** créées par ReedCRM, donc conforme au préfixe et au suffixe réglés dans la configuration.
* Tout est calculé par **quatre requêtes groupées** au lieu d'un fetch par ticket : 473 tickets et 2 736 événements agrégés en ~50 ms.
* Le tableau de bord est également **exposé par l'API**.
* Les utilisateurs désactivés sont sortis des affectés et les tickets clôturés peuvent être ignorés.

<!-- 📸 Ajouter une screenshot ici -->

### Dates d'intervention sur les lignes de service

* Nouvel objet **date d'intervention** : une ligne par intervention attendue, avec sa date, sa durée, son intervenant, son lieu, sa note et son statut.
* Le nombre d'interventions attendues est la **quantité arrondie au supérieur** (qty 2,5 → 3 dates), plafonné par une constante.
* Une **pastille compteur** (`2/3`) sous chaque ligne de service ouvre une modale de saisie ; vider une date supprime l'intervention, baisser la quantité retire celles qui n'ont plus d'unité sur laquelle tenir.
* Un **événement d'agenda** par intervention (nouveau type `AC_REEDCRM_INTERVENTION`), affecté à l'intervenant, lié au devis et au tiers, passé à 100 % quand l'intervention est réalisée.
* Nouveau **calendrier des interventions** : vue mois ou liste, navigation par mois, filtres intervenants / tiers / statut d'intervention / statut de devis, raccourci « Mes interventions », et sous le calendrier les **interventions à planifier**.
* Périmètre réglé dans la configuration : tag des services à planifier, date plancher des devis pris en compte, durée par défaut et plafond de dates par ligne.

<!-- 📸 Ajouter une screenshot ici -->

### Suivi de facturation

* Nouvelle page **« Suivi facturation »** consolidant ce qui n'a pas encore été facturé : devis signés sans facture, commandes validées sans facture, factures modèles au-delà de leur date de génération, plus les factures brouillon et les impayés échus — chaque ligne pointe vers le document et vers sa facturation.
* Le **suivi des factures récurrentes** est désormais piloté **en live par les factures modèles** : un modèle appartient au mois parcouru soit parce que sa prochaine génération y tombe, soit parce qu'une facture y a réellement été générée, même si le modèle a été suspendu depuis. Badge de traitement explicite (Fait / À faire / En retard), facture générée liée à côté, tuiles cliquables.
* **Mouvements de portefeuille** demandés par le commerce : entrées (création de modèle) et sorties (modèle suspendu, daté sur sa dernière génération réelle), graphique entrées / sorties / solde cumulé sur 12 mois et détail du mois.
* **Devis signés jamais facturés** listés sur une période glissante et **recoupés avec les factures réellement émises** (même chaîne de facturation, même montant, mêmes produits facturés après la signature) ; une ligne peut être classée « facturé » à la main.
* **Audit DU** : devis Document Unique signés non facturés, audits amorcés aussi depuis les services de mise en place.
* **Clients Digirisk sans abonnement récurrent**, détectés aussi par leurs projets `*.digirisk.com` actifs, avec masquage d'un client de la liste.

<!-- 📸 Ajouter une screenshot ici -->

### Menu de gauche par sections

* Entrées **regroupées en sections**, avec un picto par en-tête et les couleurs d'en-tête Dolibarr, puis la couleur de marque ReedCRM.
* Cible de clic sur **toute la ligne**, sous-entrées alignées et tenues à la largeur du menu.
* Page des outils déplacée dans l'administration, picto de la carte unifié.

<!-- 📸 Ajouter une screenshot ici -->

### Création rapide

* **Recherche SIREN** via le module Sirene dans la création rapide de tiers.
* Cases **« identique à »** pour l'adresse et pour les contacts du projet.
* **Tags de contact** à la création, bascules de configuration du projet corrigées.
* **Héritage des commerciaux** lors de l'ajout rapide d'un projet.
* Configuration des **tags / catégories des modèles de propositions commerciales**.

<!-- 📸 Ajouter une screenshot ici -->

### PWA

* **Consultation de l'opportunité et saisie d'une relance depuis un mobile**.
* **Lien de sortie vers Dolibarr** dans le tiroir de navigation de l'App.
* Sélecteur de **liste d'appel recherchable**, restreint aux employés.

### Listes

* Colonne **tags / catégories** sur la liste des opportunités.
* Liste des projets : retrait de l'icône œil, **filtre de date**, **validation en masse**, libellés de colonnes clarifiés.
* Liste des expéditions : **commande liée** avec sa référence et son total HT — le total de la commande est affiché quand le montant de l'expédition est à 0.
* Clôture rapide des événements « à faire » depuis une liste ou depuis la fiche événement.

---

## Améliorations & corrections

### Hooks & intégration Saturne

* Contextes de hook comparés **à l'identique** au lieu d'une recherche de sous-chaîne (`invoicelist` matchait `supplierinvoicelist`), puis prise en compte des **vues génériques Saturne** suffixées `_saturne`.
* Notation du contact tenue **hors de la liste des factures fournisseur**.
* Assets CSS/JS et UI de relance rétablis sur `saturne_list.php` : icônes FontAwesome isolées des boutons ReedCRM, `tdoverflowmax` neutralisé, garde d'objet nul dans `printCommonFooter` (erreur fatale).

### Agenda & infobulles

* **Double décalage de fuseau horaire** à la création d'un événement rapide corrigé (`dol_now()` + `tzuserrel` explicite).
* Rappel affecté à **l'utilisateur choisi** et non au créateur de l'événement.
* Infobulles : doublons dus à un double branchement d'écouteurs, position hors écran lors d'un défilement horizontal, `data-dialog-url` manquant sur la liste de projets native, « Class Form not found » au rendu d'un avatar.
* Infobulle de relance : avatars des utilisateurs, colonnes alignées et lien vers l'événement.

### Listes d'appel

* **Numéro de téléphone obligatoire** à l'ajout d'un élément dans une liste d'appel, avec des messages d'erreur explicites.
* Logo du widget dimensionné par ses attributs `width` / `height`.
* Libellé configurable des événements créés au changement de statut, tiret cadratin restauré dans le libellé par défaut.

### Divers

* Onglets de **facture récurrente** réparés (boucle infinie et onglet « Factures générées » écrasé).
* Clé `options_notation_societe_contact` indéfinie dans `actions_reedcrm`.
* Fiche ticket : traductions du hook chargées et blocs inline qui ne s'étirent plus verticalement.
* Bloc contact : **nom avant prénom** ; traduction des libellés des extrafields projet.
* Table de relance : colonnes nommées, 404 de l'infobulle quand Dolibarr tourne dans un sous-répertoire, fichier de langue chargé dans l'endpoint.
* Statut du projet respectant `PROJECT_CREATE_NO_DRAFT`.
* Portée du **manifest PWA** restreinte aux pages de l'App.
* Bouton d'événement rapide retiré des barres d'action des fiches.
* Suivi FA : un warning PHP par ligne affichée, la ligne brute de la requête live ne portant pas de `rowid`.
* Artefacts de travail internes retirés du dépôt.

## Comparaison des versions [23.1.1](https://github.com/Eoxia/reedcrm/compare/23.1.1...23.2.0) et 23.2.0

* [#954] [Facturation] fix: `rowid` alimenté avant `setVarsFromFetchObj` sur le Suivi FA [`fb29248`](https://github.com/Eoxia/reedcrm/commit/fb29248)
* [#952] [Pocket] fix: réparation des tables des installs existantes et blocs graphiques d'une synthèse éditée [`017a265`](https://github.com/Eoxia/reedcrm/commit/017a265)
* [#950] [Hook] fix: prise en compte des contextes génériques Saturne [`7025280`](https://github.com/Eoxia/reedcrm/commit/7025280)
* [#946] [Pocket] feat: édition du tiers, du statut et de la synthèse, rattachement de n'importe quel objet [`80b6f6c`](https://github.com/Eoxia/reedcrm/commit/80b6f6c)
* [#944] [Intervention] feat: une date par unité de ligne de service, son événement d'agenda et le calendrier des interventions [`b951c5f`](https://github.com/Eoxia/reedcrm/commit/b951c5f)
* [#942] [Todo] feat: conservation des critères du tableau, clôture à une date choisie et datation de la fin [`2b4db41`](https://github.com/Eoxia/reedcrm/commit/2b4db41)
* [#940] [Todo] feat: clôture rapide d'un événement depuis le pourcentage d'une carte [`d079ab2`](https://github.com/Eoxia/reedcrm/commit/d079ab2)
* [#937] [Menu] fix: sous-entrées tenues à la largeur du menu de gauche [`a16632e`](https://github.com/Eoxia/reedcrm/commit/a16632e)
* [#935] [Menu] fix: cible de clic sur toute la ligne du menu de gauche [`85518a7`](https://github.com/Eoxia/reedcrm/commit/85518a7)
* [#933] [Pocket] feat: édition en place du libellé d'action et rendu des blocs de la synthèse [`9c05533`](https://github.com/Eoxia/reedcrm/commit/9c05533)
* [#926] [Menu] rework: regroupement des entrées du menu de gauche en sections, pictos et couleurs [`38efe44`](https://github.com/Eoxia/reedcrm/commit/38efe44) [`239278f`](https://github.com/Eoxia/reedcrm/commit/239278f) [`000d8e9`](https://github.com/Eoxia/reedcrm/commit/000d8e9) [`2cfedca`](https://github.com/Eoxia/reedcrm/commit/2cfedca) [`e5051f1`](https://github.com/Eoxia/reedcrm/commit/e5051f1) [`2138ed1`](https://github.com/Eoxia/reedcrm/commit/2138ed1)
* [#924] [Hook] fix: contextes de hook comparés à l'identique au lieu d'une sous-chaîne [`b36713d`](https://github.com/Eoxia/reedcrm/commit/b36713d)
* [#922] [Invoice] fix: notation du contact tenue hors de la liste des factures fournisseur [`e39b336`](https://github.com/Eoxia/reedcrm/commit/e39b336)
* [#912] [Pocket] feat: miroir des enregistrements Pocket et rattachement depuis les objets métier [`d78ed5a`](https://github.com/Eoxia/reedcrm/commit/d78ed5a)
* [#904] [FA] feat: statistiques mensuelles d'entrées / sorties et traçabilité des générations [`4a8bbbf`](https://github.com/Eoxia/reedcrm/commit/4a8bbbf)
* [#899] [Ticket] feat: utilisateurs désactivés exclus et tickets clôturés ignorables [`a82ddbd`](https://github.com/Eoxia/reedcrm/commit/a82ddbd)
* [#897] [Todo] fix: load more vide, fermeture des popovers au clic extérieur, recherche des utilisateurs [`a36c4b0`](https://github.com/Eoxia/reedcrm/commit/a36c4b0)
* [#895] [Todo] fix: toutes les tâches en attente affichées, colonnes lues par pages [`45633fe`](https://github.com/Eoxia/reedcrm/commit/45633fe)
* [#893] [Todo] fix: utilisateurs internes listés sur `fk_soc` plutôt que sur l'indicateur employé [`722b10d`](https://github.com/Eoxia/reedcrm/commit/722b10d)
* [#891] [Todo] feat: menu de colonne pour trier et masquer les colonnes du kanban [`e0f355d`](https://github.com/Eoxia/reedcrm/commit/e0f355d)
* [#888] [Todo] feat: kanban des événements d'agenda par statut et crons de relance devis / factures [`6196bd0`](https://github.com/Eoxia/reedcrm/commit/6196bd0)
* [#886] [PWA] fix: portée du manifest restreinte aux pages de l'App [`6721ee9`](https://github.com/Eoxia/reedcrm/commit/6721ee9)
* [#884] [Ticket] feat: exposition du tableau de bord des tickets par l'API [`73f75b4`](https://github.com/Eoxia/reedcrm/commit/73f75b4)
* [#882] [Ticket] feat: tableau de bord des tickets centré sur le temps et les personnes [`8f40be3`](https://github.com/Eoxia/reedcrm/commit/8f40be3)
* [#875] [Project] feat: colonne tags / catégories sur la liste des opportunités [`9e49570`](https://github.com/Eoxia/reedcrm/commit/9e49570)
* [#874] [Agenda] feat: clôture rapide des événements à faire, depuis une liste ou la fiche [`dd3532c`](https://github.com/Eoxia/reedcrm/commit/dd3532c)
* [#872] [QuickCreation] feat: tags de contact et bascules de configuration du projet [`f3530e2`](https://github.com/Eoxia/reedcrm/commit/f3530e2)
* [#871] [QuickEvent] remove: bouton d'événement rapide des barres d'action des fiches [`032c817`](https://github.com/Eoxia/reedcrm/commit/032c817)
* [#856] [PWA] feat: consultation de l'opportunité et saisie de relance sur mobile [`d48b8fb`](https://github.com/Eoxia/reedcrm/commit/d48b8fb)
* [#865] [Agenda] fix: double décalage de fuseau horaire à la création d'un événement [`939792c`](https://github.com/Eoxia/reedcrm/commit/939792c)
* [#864] [Tooltip] fix: infobulles multiples, position hors écran et `data-dialog-url` manquant [`1e2ef80`](https://github.com/Eoxia/reedcrm/commit/1e2ef80) [`d521a5b`](https://github.com/Eoxia/reedcrm/commit/d521a5b) [`b57b910`](https://github.com/Eoxia/reedcrm/commit/b57b910) [`6a74c5a`](https://github.com/Eoxia/reedcrm/commit/6a74c5a)
* [#860] [Relaunch] rework: avatars, colonnes alignées et lien vers l'événement dans l'infobulle [`785f2a3`](https://github.com/Eoxia/reedcrm/commit/785f2a3) [`75fc770`](https://github.com/Eoxia/reedcrm/commit/75fc770) [`3fd4eee`](https://github.com/Eoxia/reedcrm/commit/3fd4eee) [`ad84763`](https://github.com/Eoxia/reedcrm/commit/ad84763)
* [#857] [Saturne] fix: assets CSS/JS et UI de relance rétablis sur `saturne_list.php` [`690b97b`](https://github.com/Eoxia/reedcrm/commit/690b97b) [`d56fd75`](https://github.com/Eoxia/reedcrm/commit/d56fd75) [`75bf5d4`](https://github.com/Eoxia/reedcrm/commit/75bf5d4) [`23f2d1d`](https://github.com/Eoxia/reedcrm/commit/23f2d1d) [`f037b43`](https://github.com/Eoxia/reedcrm/commit/f037b43) [`187a238`](https://github.com/Eoxia/reedcrm/commit/187a238)
* [#854] [Agenda] fix: rappel affecté à l'utilisateur choisi et non au créateur [`218ee13`](https://github.com/Eoxia/reedcrm/commit/218ee13)
* [#843] [Ticket] fix: traductions du hook et blocs inline qui ne s'étirent plus [`0ad9e8b`](https://github.com/Eoxia/reedcrm/commit/0ad9e8b) [`0653d8a`](https://github.com/Eoxia/reedcrm/commit/0653d8a)
* [#837] [FA] fix: calendrier annuel, jointure dédoublonnée, cron à une annotation par modèle, filtre identique au natif [`d1ed0f6`](https://github.com/Eoxia/reedcrm/commit/d1ed0f6) [`ac92d4c`](https://github.com/Eoxia/reedcrm/commit/ac92d4c) [`a4845f7`](https://github.com/Eoxia/reedcrm/commit/a4845f7) [`f6d31f7`](https://github.com/Eoxia/reedcrm/commit/f6d31f7) [`1c923f7`](https://github.com/Eoxia/reedcrm/commit/1c923f7)
* [#835] [FA] refactor: suivi des factures récurrentes piloté en live par les factures modèles [`a791b54`](https://github.com/Eoxia/reedcrm/commit/a791b54) [`2bc0b58`](https://github.com/Eoxia/reedcrm/commit/2bc0b58) [`79c4d64`](https://github.com/Eoxia/reedcrm/commit/79c4d64) [`c014288`](https://github.com/Eoxia/reedcrm/commit/c014288) [`529eccc`](https://github.com/Eoxia/reedcrm/commit/529eccc) [`3f9e8e1`](https://github.com/Eoxia/reedcrm/commit/3f9e8e1)
* [#833] [Facturation] feat: page « Suivi facturation » des manquements de facturation [`146a3e3`](https://github.com/Eoxia/reedcrm/commit/146a3e3)
* [#832] [DU] feat: devis Document Unique signés non facturés [`83da21d`](https://github.com/Eoxia/reedcrm/commit/83da21d) [`8fa3203`](https://github.com/Eoxia/reedcrm/commit/8fa3203) [`0a90a87`](https://github.com/Eoxia/reedcrm/commit/0a90a87) [`58fa38f`](https://github.com/Eoxia/reedcrm/commit/58fa38f)
* [#829] [CallList] fix: logo du widget dimensionné par `width` / `height` [`cf38c13`](https://github.com/Eoxia/reedcrm/commit/cf38c13)
* [#827] [QuickCreation] feat: cases « identique à » pour l'adresse et les contacts du projet [`11b3f49`](https://github.com/Eoxia/reedcrm/commit/11b3f49)
* [#826] [QuickCreation] feat: recherche SIREN du module Sirene dans la création rapide de tiers [`065ee3a`](https://github.com/Eoxia/reedcrm/commit/065ee3a)
* [#825] [Propale] feat: configuration des tags / catégories des modèles de propositions [`1ae00fe`](https://github.com/Eoxia/reedcrm/commit/1ae00fe)
* [#822] [PWA] feat: lien de sortie vers Dolibarr dans le tiroir de navigation [`09cfd9b`](https://github.com/Eoxia/reedcrm/commit/09cfd9b)
* [#821] [QuickCreation] fix: statut du projet respectant `PROJECT_CREATE_NO_DRAFT` [`6a3550a`](https://github.com/Eoxia/reedcrm/commit/6a3550a)
* [#820] [ReedCRM] fix: clé `options_notation_societe_contact` indéfinie dans `actions_reedcrm` [`572eb98`](https://github.com/Eoxia/reedcrm/commit/572eb98)
* [#817] [Facture] fix: onglets de facture récurrente (boucle infinie et onglet « Factures générées » écrasé) [`fdfc598`](https://github.com/Eoxia/reedcrm/commit/fdfc598)
* [#816] [Digirisk] feat: clients Digirisk sans abonnement récurrent, détection par projets et masquage [`7e68ce9`](https://github.com/Eoxia/reedcrm/commit/7e68ce9) [`e0ace76`](https://github.com/Eoxia/reedcrm/commit/e0ace76) [`5f46fd9`](https://github.com/Eoxia/reedcrm/commit/5f46fd9) [`a789c43`](https://github.com/Eoxia/reedcrm/commit/a789c43) [`c6029ea`](https://github.com/Eoxia/reedcrm/commit/c6029ea) [`a7e4a0b`](https://github.com/Eoxia/reedcrm/commit/a7e4a0b) [`b72cdb1`](https://github.com/Eoxia/reedcrm/commit/b72cdb1)
* [#815] [CallList] fix: numéro de téléphone obligatoire à l'ajout dans une liste d'appel [`f6e7e37`](https://github.com/Eoxia/reedcrm/commit/f6e7e37) [`29cef57`](https://github.com/Eoxia/reedcrm/commit/29cef57) [`da832c1`](https://github.com/Eoxia/reedcrm/commit/da832c1)
* [#814] [Project] feat: héritage des commerciaux à l'ajout rapide d'un projet [`71d67e3`](https://github.com/Eoxia/reedcrm/commit/71d67e3)
* [#813] [Project] feat: filtre de date, validation en masse et libellés de la liste des projets [`1e73f20`](https://github.com/Eoxia/reedcrm/commit/1e73f20) [`003172e`](https://github.com/Eoxia/reedcrm/commit/003172e) [`2759522`](https://github.com/Eoxia/reedcrm/commit/2759522) [`ca8f98c`](https://github.com/Eoxia/reedcrm/commit/ca8f98c) [`e25a317`](https://github.com/Eoxia/reedcrm/commit/e25a317)
* [#810] [DU] chore: migration des colonnes de suivi d'audit DU et montant DU du mois parcouru [`bf3e766`](https://github.com/Eoxia/reedcrm/commit/bf3e766) [`8942074`](https://github.com/Eoxia/reedcrm/commit/8942074)
* [#790] [Projet] fix: nom avant prénom dans le bloc contact [`7cc1359`](https://github.com/Eoxia/reedcrm/commit/7cc1359)
* [#790] [Relaunch] add: colonnes nommées, 404 de l'infobulle en sous-répertoire, fichier de langue de l'endpoint [`907c4ed`](https://github.com/Eoxia/reedcrm/commit/907c4ed) [`781bbfc`](https://github.com/Eoxia/reedcrm/commit/781bbfc) [`c64fb68`](https://github.com/Eoxia/reedcrm/commit/c64fb68)
* [#790] [CallList] add: libellé configurable des événements de changement de statut et tiret cadratin [`5d9ada3`](https://github.com/Eoxia/reedcrm/commit/5d9ada3) [`b479268`](https://github.com/Eoxia/reedcrm/commit/b479268)
* [#790] [Lang] fix: traduction des libellés des extrafields projet [`e44aac8`](https://github.com/Eoxia/reedcrm/commit/e44aac8)
* [#767] [Expedition] feat: commande liée avec sa référence et son total HT sur la liste [`71a7ac2`](https://github.com/Eoxia/reedcrm/commit/71a7ac2) [`3b7c3d7`](https://github.com/Eoxia/reedcrm/commit/3b7c3d7)
* [#866] [Repo] chore: retrait des artefacts de travail internes [`441f2da`](https://github.com/Eoxia/reedcrm/commit/441f2da)
