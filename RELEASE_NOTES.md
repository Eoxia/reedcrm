# [ReedCRM] [23.1.0] - Listes d'appel - Suivi de temps sur tickets - Chaîne d'opportunités

Description : Cette version introduit un système complet de **listes d'appel** (vue PWA mobile, PDF, widgets sur projets / propositions / factures, actions de masse, enregistrement audio, création automatique d'événements), un **suivi de temps natif sur les tickets**, l'analyse de la **chaîne d'opportunités** sur la fiche et l'onglet Vue d'ensemble du projet, une refonte de la **liste des projets** (cartes KPI, vues enregistrées, densité, édition inline), ainsi que de nombreuses améliorations PWA, expéditions et suivis récurrents.

## Nouvelles fonctionnalités et innovations

### Listes d'appel (Call List)

* Nouvel objet « liste d'appel » complet : tables SQL, classes, permissions, menu, numérotation, modèle PDF et endpoints AJAX.
* Vue **PWA mobile** dédiée avec mise à jour du statut en AJAX (à appeler / appelé / sans réponse / à rappeler) ; la carte passe en fin de liste au changement de statut.
* Gros **bouton d'appel vert** avec copie du numéro (clic = appel, appui long = copie), séparation appel / copie.
* **Enregistrement audio** Saturne sur chaque fiche d'appel.
* Widget « ajouter à une liste d'appel » injecté sur les fiches projet / proposition / facture, plus **action de masse** sur les listes.
* Liste d'appel **par défaut** provisionnée pour chaque utilisateur, étoile en un clic sur le widget.
* Routage via la **liste générique Saturne**, note publique et contacts en bas du PDF.
* Création automatique d'un **événement + tâche commerciale** au changement de statut depuis la PWA.
* Refus d'ajout d'un contact sans numéro de téléphone ; repli sur les coordonnées ReedCRM du projet si aucun contact n'est lié.

<!-- 📸 Ajouter une screenshot ici -->

### Suivi de temps sur les tickets

* Bloc de **suivi de temps rapide** natif sur la fiche ticket + page de configuration.
* Case « enregistrement automatique du temps » sur le formulaire d'envoi de message ; création d'un `actioncomm` au log du temps.
* Dernière saisie de temps affichée sous le bloc et mise à jour dynamiquement à l'enregistrement.
* En-tête avec référence de tâche, temps passé / prévu en infobulle, compteur d'entrées, icône tâche cliquable vers `time.php`.
* Blocs **gravité** et **assignation** en édition inline (Select2) sur la fiche ticket ; zone de note redimensionnable.
* Réglages : longueur max du titre d'événement, suffixe du libellé de tâche.

<!-- 📸 Ajouter une screenshot ici -->

### Chaîne d'opportunités

* Barre de **chaîne d'opportunités** sur la fiche projet (`procard.php`) et sur l'onglet Vue d'ensemble (hook `projectOverview`).
* `reedcrm_compute_opportunity_chain` : état de progression + 4 règles d'incohérence, progression colorée, badges d'incohérence, mode icônes seules.
* Exposition des statuts de pièces et des totaux facturé / payé du projet pour l'analyse.

<!-- 📸 Ajouter une screenshot ici -->

### Refonte de la liste des projets

* **Cartes KPI** d'opportunités en tête de liste.
* **Vues enregistrées** (presets) par filtres, avec mise en avant de la vue active.
* Bascule de **densité** compacte / confortable par utilisateur.
* Édition inline enrichie : contact, téléphone (recherche pays type-ahead), `opp_percent`, statut avec infobulle native.

<!-- 📸 Ajouter une screenshot ici -->

### PWA

* **Menu burger** et **favoris personnels** dans la barre de navigation basse.
* **Kanban** des tickets avec filtre par assigné, recherche et glisser-déposer.
* Barre de **statut des documents** + page de configuration.
* Ajout d'un **tiers** directement depuis la PWA.
* Pourcentage d'opportunité repositionné près du montant, référence réduite.

<!-- 📸 Ajouter une screenshot ici -->

### Expéditions

* Nouvelle **liste des expéditions** avec colonne des factures liées et montant total.
* Colonne de **contrôle OK/KO** (expédition vs factures) ; factures brouillon ignorées, PROV réintégrées à l'affichage mais exclues du contrôle.
* Marquage « facturé » réversible en AJAX + `actioncomm`.
* Option **date d'expédition = date de création** (déclencheur `SHIPPING_CREATE`, bascule admin `REEDCRM_EXPEDITION_SHIPPING_DATE_AS_CREATION_DATE`).
* Entrée de menu « Expéditions ».

### Suivis récurrents & audit DU

* **Suivi des factures récurrentes** et **audit DU** : nouvelles tables, listes et fiche.
* État automatique suivant le devis et la facture, date d'audit réelle capturée au passage « Fait », ancrage du cycle suivant.

### Divers

* **Colonnes de tableau redimensionnables** avec persistance serveur + colonne message.
* **Ligne du jour** (rouge) matérialisée dans les listes d'événements de l'agenda.
* Carte « **rappels d'appel à venir** » sur le tableau de bord.
* Description produit injectée sous les lignes de réception.
* Sélecteur inline `SALESREPINTERNAL` dans l'en-tête de la fiche projet.
* Documentation agent IA & architecture.

---

## Améliorations & corrections

### Menu & navigation

* Préfixe `/custom` ajouté aux URLs du menu Saturne.
* Logos des modules externes affichés dans le menu « Plus », icône PNG ReedCRM restaurée, alignement des entrées de la barre supérieure.

### Compatibilité modules

* Pages fonctionnelles sans le module **Projet** (`hasRight()`), page blanche de la création rapide corrigée.
* « Class FormTicket not found » évitée quand le module **Ticket** est désactivé.

### PWA & carte

* Filtres de la carte réparés, preset actif, retrait Type / icône.
* Modale vCard et double scrollbar corrigés, chevauchement de la navigation basse sur la pagination.
* Positionnement de l'en-tête PWA et geoloc, spinner infini corrigé.
* Double init du module Saturne causant une création d'opportunité en double corrigé.

### Traductions & outils

* Traductions manquantes ajoutées (PropalList, RelauchCommercial, ContactDetails, parité en_US).
* Accès `conf->global` déprécié remplacé par `getDolGlobalString` dans `reedcrmtools.php`.
* Import : BOM UTF-8, multiselect natif des tags, confirmation de tag en double.
* Include `reedcrm.main.inc.php` en 2 tentatives pour Dolistore.

### Divers

* EventPro : rappel créé « à faire » et non « fait », titre de projet complet dans le select.
* Bloc de relance : infobulle au survol restaurée.
* PDF : téléphone et e-mail du projet injectés dans le bloc adressé.

## Comparaison des versions [23.0.0](https://github.com/Eoxia/easycrm/compare/23.0.0...23.1.0) et 23.1.0

* [#796] [DU] feat: suivi des factures récurrentes et de l'audit DU [`74cee58`](https://github.com/Eoxia/easycrm/commit/74cee58) [`dd85810`](https://github.com/Eoxia/easycrm/commit/dd85810) [`1966cc4`](https://github.com/Eoxia/easycrm/commit/1966cc4) [`7f834ce`](https://github.com/Eoxia/easycrm/commit/7f834ce)
* [#790] [Agenda] feat: ligne du jour (rouge) dans les listes d'événements + rebuild css [`f8c5269`](https://github.com/Eoxia/easycrm/commit/f8c5269) [`9404f5a`](https://github.com/Eoxia/easycrm/commit/9404f5a) [`6c1e0c9`](https://github.com/Eoxia/easycrm/commit/6c1e0c9)
* [#791] [ProCard] fix: éviter « Class FormTicket not found » sans le module Ticket [`c568fb3`](https://github.com/Eoxia/easycrm/commit/c568fb3)
* [#788] [Frontend] fix: page blanche de la création rapide sans le module Projet [`82e6546`](https://github.com/Eoxia/easycrm/commit/82e6546)
* [#786] [Frontend] fix: `hasRight()` pour fonctionner sans le module Projet [`fc2a180`](https://github.com/Eoxia/easycrm/commit/fc2a180)
* [#782] [Dashboard] feat: carte des rappels d'appel à venir [`023658b`](https://github.com/Eoxia/easycrm/commit/023658b)
* [#779] [Import] fix: BOM UTF-8, historique inline, multiselect natif des tags, confirmation de doublon [`9351598`](https://github.com/Eoxia/easycrm/commit/9351598)
* [#777] [Tools] fix: `getDolGlobalString` dans `reedcrmtools.php` [`5086272`](https://github.com/Eoxia/easycrm/commit/5086272)
* [#775] [ProCard] feat: en-tête d'édition inline compact + titre dynamique + Select2 [`76e7330`](https://github.com/Eoxia/easycrm/commit/76e7330)
* [#773] [Traductions] fix: traductions manquantes PropalList, RelauchCommercial, ContactDetails [`873a855`](https://github.com/Eoxia/easycrm/commit/873a855)
* [#769] [Dolistore] fix: include `reedcrm.main.inc.php` en 2 tentatives [`1d4acb7`](https://github.com/Eoxia/easycrm/commit/1d4acb7)
* [#762] [CallList] feat: création auto d'événement et de tâche commerciale au changement de statut PWA [`4f52693`](https://github.com/Eoxia/easycrm/commit/4f52693)
* [#759] [PWA] fix: visibilité de la modale vCard et double scrollbar [`8010efb`](https://github.com/Eoxia/easycrm/commit/8010efb)
* [#757] [PWA] feat: gros bouton d'appel vert avec copie [`0becb06`](https://github.com/Eoxia/easycrm/commit/0becb06) [`c2a7763`](https://github.com/Eoxia/easycrm/commit/c2a7763)
* [#755] [CallList] fix: lecture des listes d'appel par les admins dans la PWA [`71f6519`](https://github.com/Eoxia/easycrm/commit/71f6519)
* [#753] [PWA] feat: menu burger et favoris perso dans la bottom nav [`9cf415f`](https://github.com/Eoxia/easycrm/commit/9cf415f)
* [#750] [PWA] feat: UX et audio de la liste d'appel PWA [`28a591e`](https://github.com/Eoxia/easycrm/commit/28a591e)
* [#748] [CallList] fix: valider les listes avec une ref définitive au lieu de PROV [`5090b7e`](https://github.com/Eoxia/easycrm/commit/5090b7e)
* [#745] [Ticket] feat: suivi de temps natif (bloc rapide, actioncomm, gravité/assignation inline) [`d0e01aa`](https://github.com/Eoxia/easycrm/commit/d0e01aa) [`cd03e63`](https://github.com/Eoxia/easycrm/commit/cd03e63) [`cfcd8db`](https://github.com/Eoxia/easycrm/commit/cfcd8db)
* [#729] [EventPro] fix: rappel créé « à faire » et non « fait » [`79cd567`](https://github.com/Eoxia/easycrm/commit/79cd567)
* [#728] [EventPro] fix: titre de projet complet dans le select (16 → 64) [`b6631a4`](https://github.com/Eoxia/easycrm/commit/b6631a4)
* [#726] [CallList] feat: routage de la liste d'appel via la liste générique Saturne [`e6d7285`](https://github.com/Eoxia/easycrm/commit/e6d7285)
* [#724] [CallList] fix: repli sur les coordonnées ReedCRM du projet sans contact [`5154746`](https://github.com/Eoxia/easycrm/commit/5154746)
* [#722] [Expedition] feat: option date d'expédition = date de création (`SHIPPING_CREATE`) [`63e77f3`](https://github.com/Eoxia/easycrm/commit/63e77f3) [`a062638`](https://github.com/Eoxia/easycrm/commit/a062638) [`2957965`](https://github.com/Eoxia/easycrm/commit/2957965)
* [#716] [ReedCRM] feat: barre de chaîne d'opportunités sur la fiche projet et l'onglet Vue d'ensemble [`e3717e6`](https://github.com/Eoxia/easycrm/commit/e3717e6) [`620211c`](https://github.com/Eoxia/easycrm/commit/620211c) [`54ec112`](https://github.com/Eoxia/easycrm/commit/54ec112)
* [#711] [ReedCRM] feat: description produit injectée sous les lignes de réception [`77ac840`](https://github.com/Eoxia/easycrm/commit/77ac840) [`d8d7dcd`](https://github.com/Eoxia/easycrm/commit/d8d7dcd) [`057bf8b`](https://github.com/Eoxia/easycrm/commit/057bf8b)
* [#710] [Project] feat: sélecteur inline `SALESREPINTERNAL` dans l'en-tête de la fiche projet [`c349561`](https://github.com/Eoxia/easycrm/commit/c349561) [`d87933f`](https://github.com/Eoxia/easycrm/commit/d87933f)
* [#709] [RelaunchBlock] fix: infobulle au survol restaurée sur les boutons de relance [`3f5abf2`](https://github.com/Eoxia/easycrm/commit/3f5abf2)
* [#707] [CallList] add: note publique en bas du PDF [`b1d88aa`](https://github.com/Eoxia/easycrm/commit/b1d88aa)
* [#700] [ReedCRM/PWA] feat: calcul de la chaîne d'opportunités + barre de statut des documents [`b54561f`](https://github.com/Eoxia/easycrm/commit/b54561f) [`e0f95cc`](https://github.com/Eoxia/easycrm/commit/e0f95cc) [`42a4b5c`](https://github.com/Eoxia/easycrm/commit/42a4b5c)
* [#696] [CallList] feat: système complet de listes d'appel (widgets, actions de masse, PDF, PWA) [`1382915`](https://github.com/Eoxia/easycrm/commit/1382915) [`3cd2346`](https://github.com/Eoxia/easycrm/commit/3cd2346) [`717baf7`](https://github.com/Eoxia/easycrm/commit/717baf7)
* [#693] [JS] fix: double init de Saturne provoquant une création d'opportunité en double [`baee3d7`](https://github.com/Eoxia/easycrm/commit/baee3d7)
* [#687] [Project] feat: cartes KPI, vues enregistrées, densité et édition inline sur la liste [`ae2ee38`](https://github.com/Eoxia/easycrm/commit/ae2ee38) [`2f43d53`](https://github.com/Eoxia/easycrm/commit/2f43d53) [`1a1e261`](https://github.com/Eoxia/easycrm/commit/1a1e261)
* [#686] [Map] fix: réparation des filtres + preset actif [`f947669`](https://github.com/Eoxia/easycrm/commit/f947669) [`782bba8`](https://github.com/Eoxia/easycrm/commit/782bba8) [`1d9cd75`](https://github.com/Eoxia/easycrm/commit/1d9cd75)
* [#683] [Project] feat: colonnes redimensionnables avec persistance serveur + colonne message [`5fa02aa`](https://github.com/Eoxia/easycrm/commit/5fa02aa)
* [#682] [PWA] feat: Kanban des tickets avec filtre par assigné et glisser-déposer [`a62ac34`](https://github.com/Eoxia/easycrm/commit/a62ac34) [`06a8625`](https://github.com/Eoxia/easycrm/commit/06a8625) [`8c91f7c`](https://github.com/Eoxia/easycrm/commit/8c91f7c)
* [#679] [JS] fix: positionnement de l'en-tête geoloc PWA + spinner infini [`33475f7`](https://github.com/Eoxia/easycrm/commit/33475f7) [`76fe0c5`](https://github.com/Eoxia/easycrm/commit/76fe0c5) [`6e4a5cc`](https://github.com/Eoxia/easycrm/commit/6e4a5cc)
* [#676] [PWA] fix: exclure le PROJECTLEADER interne des chips de contact [`ef041a4`](https://github.com/Eoxia/easycrm/commit/ef041a4)
* [#674] [Projet] rework: bloc de relance d'en-tête remplacé par des boutons typés [`669334f`](https://github.com/Eoxia/easycrm/commit/669334f)
* [#671] [PWA] rework: UI/UX des cartes projet [`11172d2`](https://github.com/Eoxia/easycrm/commit/11172d2)
* [#669] [Projet] fix: ajout de l'opportunité au dictionnaire de sources [`a48d584`](https://github.com/Eoxia/easycrm/commit/a48d584)
* [#667] [Docs] feat: guidelines agent IA et docs d'architecture [`d607998`](https://github.com/Eoxia/easycrm/commit/d607998) [`f09c10c`](https://github.com/Eoxia/easycrm/commit/f09c10c)
* [#665] [QuickCreation] fix: intégration du module media Saturne pour l'upload de photos [`03f1ae2`](https://github.com/Eoxia/easycrm/commit/03f1ae2)
* [#659] [PWA] feat: ajouter un tiers depuis la PWA [`8d14b5d`](https://github.com/Eoxia/easycrm/commit/8d14b5d)
* [#633] [Menu] fix: préfixe `/custom` sur les URLs du menu Saturne [`e6982da`](https://github.com/Eoxia/easycrm/commit/e6982da)
* [#597] [PDF] fix: injection du téléphone et de l'e-mail du projet dans le bloc adressé [`8a9406a`](https://github.com/Eoxia/easycrm/commit/8a9406a)
