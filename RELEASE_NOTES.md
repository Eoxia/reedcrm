# [ReedCRM] [23.0.0] - PWA enrichie - Carte interactive - Suivi des opportunités

Description : Cette version enrichit la PWA (filtre par personne, sélecteur de semaine via Flatpickr, géolocalisation dans la création rapide, sélecteur de catégories), ajoute la carte aux opportunités avec routes et badges, refond l'analyse des opportunités avec graphes et widget hebdomadaire, et étend le bloc de relance aux fiches tiers et propositions.

## Nouvelles fonctionnalités et innovations

### PWA — gestion des semaines et filtres

* Sélecteur de semaine **Flatpickr** en remplacement du picker natif (UX cohérente cross-browser).
* Navigation par `week_start` absolu au lieu d'un offset relatif → URL partageable.
* Filtre par **personne** disponible sur toute l'application.
* Position GPS courante affichée dans la création rapide (avec fallback gracieux si pas de nom/prénom).
* Sélecteur de **catégories** dans la création rapide ; champ Gravityform retiré du formulaire.
* Colonnes `lat` / `lon` ajoutées à la vue d'adresse pour faciliter la géolocalisation.

<!-- 📸 Ajouter une screenshot ici -->

### Carte interactive

* Carte ajoutée à la PWA avec pré-filtres en boutons.
* Affichage des **routes entre points**.
* Badge avec compteur sur les épingles qui se chevauchent.
* Corrections sur le rendu des points (point sur point, JS).

<!-- 📸 Ajouter une screenshot ici -->

### Statistiques opportunités

* Nouveau widget « Infos globales de la semaine » sur la home.
* Graphes opportunités sur la home (origine, % de probabilité, etc.).
* Logo d'origine d'opportunité affiché sur la fiche projet.
* Total compté correctement comme opportunités avec probabilité ≤ 50.
* Bornes des plages d'opportunités fixées entre 50-80 et 80+.
* Nouveau champ extra `reedcrm_field_opp_percent` sur les projets.
* Samedi et dimanche retirés des graphes home (semaine de travail uniquement).

<!-- 📸 Ajouter une screenshot ici -->

### Bloc de relance

* Bloc de relance étendu à `thirdpartycard` et `propalcard` (avant : seulement la liste).
* Pop-up d'historique au survol du badge de relance commerciale.
* Couleurs des boutons de relance refondues en colorblind-friendly.

### Liste des propositions et tiers

* Nouvelle liste de propositions générique (basée sur la liste Saturne).
* Lien direct pour rechercher un tiers par SIRET à la création de tiers.

### EventPro

* Assignation utilisateur pour les rappels (reminder).
* Case à cocher « Reminder » retirée des EventPro (gérée différemment maintenant).

---

## Améliorations & corrections

### Menu

* Réorganisation du menu gauche (noms, ordre, refonte complète).
* Ordre des entrées « opportunités importées » corrigé.
* Filtre `search_status` corrigé pour les propals ouvertes.
* Redirection du menu « facture récurrente » vers la bonne page.

### Extrafields

* Suppression des extrafields obsolètes (`vocal`, `contact_informations`, `description`).
* Nettoyage rétro-compatible des extrafields obsolètes à l'init du module (avec garde via conf).
* Nouveau champ extra `reedcrm_gravityform` (URL Gravityform) + exposition dans l'API.

### Cartes / SQL

* `SALESREPINTERNAL` ajouté pour tous les modules.
* Mises à jour SQL déplacées dans `update.sql`.
* JS de la carte corrigé.

### Module / configuration

* Renommage cohérent « PWA » → « App » dans les traductions.
* Backward compatibility pour les projets sans nom/prénom.
* Trad manquante `call_notifications` ajoutée.

### Actions / hooks

* `printFieldListWhere` corrigé.
* JS qui disparaissait dans la pop-up de liste — corrigé.

### Build / CI

* `cross-env` requis dans `package.json` (compat Windows).
* Dart SCSS v3 supporté.
* Recompilation des assets min CSS/JS.
* Migration vers `saturne_list` au lieu de la liste Dolibarr.
* `.gitattributes` corrigé pour le build des assets.

## Comparaison des versions [22.1.0](https://github.com/Eoxia/easycrm/compare/22.1.0...23.0.0) et 23.0.0

* [#642] [RelaunchBlock] feat: extend header relaunch block to thirdpartycard and propalcard [`8b79eee`](https://github.com/Eoxia/easycrm/commit/8b79eee)
* [#629] [PWA] feat: categories selector and remove gravityform field in quickcreation [`c027a80`](https://github.com/Eoxia/easycrm/commit/c027a80)
* [#625] [PWA/Address] feat: current location display in quickcreation, lat/lon columns [`eb58f7a`](https://github.com/Eoxia/easycrm/commit/eb58f7a) [`aca3ad3`](https://github.com/Eoxia/easycrm/commit/aca3ad3)
* [#622] [Menu] fix: reorder imported opportunities left menu entries [`a152961`](https://github.com/Eoxia/easycrm/commit/a152961)
* [#621] [PWA] feat: replace week picker with Flatpickr, person filter, week_start [`5ec1dcf`](https://github.com/Eoxia/easycrm/commit/5ec1dcf) [`2eaff6f`](https://github.com/Eoxia/easycrm/commit/2eaff6f) [`789d04b`](https://github.com/Eoxia/easycrm/commit/789d04b)
* [#619] [PWA] fix: opportunity ranges, total count [`59dc974`](https://github.com/Eoxia/easycrm/commit/59dc974) [`5ec5f2a`](https://github.com/Eoxia/easycrm/commit/5ec5f2a)
* [#616] [Extrafields] remove: deprecated extrafields with conf guard [`bcc4dfb`](https://github.com/Eoxia/easycrm/commit/bcc4dfb) [`50a0489`](https://github.com/Eoxia/easycrm/commit/50a0489)
* [#614] [Menu] fix: search_status filter not working for opened propals [`14b0d51`](https://github.com/Eoxia/easycrm/commit/14b0d51)
* [#610] [PWA] remove: saturday from home stats graphs [`dbba031`](https://github.com/Eoxia/easycrm/commit/dbba031)
* [#606] [EventPro] remove: checkbox for reminder from eventpro [`e812807`](https://github.com/Eoxia/easycrm/commit/e812807)
* [#604] [PWA] fix: no geoloc when no name and lastname [`36e7a12`](https://github.com/Eoxia/easycrm/commit/36e7a12)
* [#602] [Mod] fix: backward for project without name and lastname [`71e794a`](https://github.com/Eoxia/easycrm/commit/71e794a)
* [#596] [PWA] add: widget for week global infos, graphs for opportunities [`bc6ba86`](https://github.com/Eoxia/easycrm/commit/bc6ba86) [`1a00e43`](https://github.com/Eoxia/easycrm/commit/1a00e43)
* [#595] [EventPro] add: user assign for reminder [`d846a36`](https://github.com/Eoxia/easycrm/commit/d846a36)
* [#592] [Map] add: badge count pin [`856f4ab`](https://github.com/Eoxia/easycrm/commit/856f4ab)
* [#590] [PWA] add: map to pwa [`dc19efb`](https://github.com/Eoxia/easycrm/commit/dc19efb)
* [#586] [SQL] add: SALESREPINTERNAL, update.sql refactor [`a7336a4`](https://github.com/Eoxia/easycrm/commit/a7336a4)
* [#531] [Map] add: pre filter buttons, routes between points [`33cb9a7`](https://github.com/Eoxia/easycrm/commit/33cb9a7) [`7be912c`](https://github.com/Eoxia/easycrm/commit/7be912c)
* [#514] [Graph] add: opportunity origin graph and logo [`eccfd3c`](https://github.com/Eoxia/easycrm/commit/eccfd3c)
* [#509] [Mod/Actions] fix: PWA → App, link search thirdparty siret [`84ffd98`](https://github.com/Eoxia/easycrm/commit/84ffd98) [`1179a9c`](https://github.com/Eoxia/easycrm/commit/1179a9c)
* [#508] [Project/Propal] add: reedcrm_field_opp_percent, propal list [`98a3dbb`](https://github.com/Eoxia/easycrm/commit/98a3dbb) [`6a46fe8`](https://github.com/Eoxia/easycrm/commit/6a46fe8)
* [#498] [front] rework: colorblind-friendly relaunch buttons [`633e37a`](https://github.com/Eoxia/easycrm/commit/633e37a)
* [#490] [Extrafield/API] add: reedcrm_gravityform [`d542932`](https://github.com/Eoxia/easycrm/commit/d542932) [`aaed41d`](https://github.com/Eoxia/easycrm/commit/aaed41d) [`de19f7d`](https://github.com/Eoxia/easycrm/commit/de19f7d)
* [#481] [menu] fix: redirect recurring invoice menu to correct page [`bf00705`](https://github.com/Eoxia/easycrm/commit/bf00705)
* [#556] [Mod] fix: rework left menu, names of menu [`94f3ab4`](https://github.com/Eoxia/easycrm/commit/94f3ab4) [`64040f6`](https://github.com/Eoxia/easycrm/commit/64040f6)
* [#552/550/546] various commit-only fixes [`b65cacc`](https://github.com/Eoxia/easycrm/commit/b65cacc) [`b35e8f0`](https://github.com/Eoxia/easycrm/commit/b35e8f0) [`7027d46`](https://github.com/Eoxia/easycrm/commit/7027d46)
* [#540] [JS/Actions] fix: list popup, hover history popup on relaunch badge [`3f7e493`](https://github.com/Eoxia/easycrm/commit/3f7e493) [`cbaa90c`](https://github.com/Eoxia/easycrm/commit/cbaa90c)
* [Mod/CI] fix: cross-env, dart scss v3, build assets min, saturne_list migration [`b94ad63`](https://github.com/Eoxia/easycrm/commit/b94ad63) [`9a757d0`](https://github.com/Eoxia/easycrm/commit/9a757d0) [`b86d96b`](https://github.com/Eoxia/easycrm/commit/b86d96b) [`c954e03`](https://github.com/Eoxia/easycrm/commit/c954e03)
