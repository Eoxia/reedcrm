# [ReedCRM] [23.4.0] - Thème moderne - Accueil et tableaux de bord de pilotage - Tickets orientés argent

Description : ReedCRM adopte le **thème moderne** des modules Evarisk et s'ouvre sur un **accueil** qui donne d'un coup d'œil le chiffre d'affaires, le pipe et les encaissements, complété par deux **tableaux de bord de pilotage** (commercial et Direction) avec objectifs. Les **tickets se lisent par l'argent** qu'ils rapportent, une **frise du cycle de vie** accompagne devis, commandes, factures et projets, et la Todo, la fiche pro, les listes d'appel, l'application mobile, Pocket et le temps des projets gagnent chacun leurs nouveautés.

**Cette version demande Saturne 23.10.0 ou supérieur.**

## 🎬 Les nouveautés en vidéo

<a href="https://github.com/Eoxia/reedcrm/releases/download/23.4.0/reedcrm-23.4.0-theme.mp4"><img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/video_poster_theme.jpeg" width="32%" alt="Vidéo : le thème moderne de ReedCRM"></a> <a href="https://github.com/Eoxia/reedcrm/releases/download/23.4.0/reedcrm-23.4.0-pilotage.mp4"><img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/video_poster_pilotage.jpeg" width="32%" alt="Vidéo : l'accueil et les tableaux de bord de pilotage"></a> <a href="https://github.com/Eoxia/reedcrm/releases/download/23.4.0/reedcrm-23.4.0-tickets.mp4"><img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/video_poster_tickets.jpeg" width="32%" alt="Vidéo : les tickets orientés argent"></a>

*Cliquez sur une image pour voir la vidéo.*

## Nouvelles fonctionnalités et innovations

### Thème moderne

* Les écrans ReedCRM passent au **thème moderne** partagé par les modules Evarisk à travers Saturne : blocs en cartes arrondies, tuiles de chiffres, listes aérées. Il s'applique d'office, sans réglage ; l'application mobile garde son propre design.
* **Tableau Todo** : barre de critères en carte, colonnes bordées, cartes arrondies, pastilles « En retard » et « À venir ».
* **Menu de gauche** : chaque section (Accès rapides, Commerce, Facturation, Pilotage…) prend un bandeau vert à micro-libellé en capitales, lisible aussi en menu réduit.
* Également restylés : l'accueil et les tableaux de bord, les opportunités par mois, les pages de facturation (suivi facturation, PR signées non facturées, suivi des factures récurrentes), le calendrier des interventions, la fiche Pocket, la fiche pro, la liste des expéditions, les appels Keyyo d'un tiers et la liste des modèles de proposition.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/theme-moderne-todo.png" alt="Thème moderne : menu de gauche avec les sections Accès rapides, Commerce, Portefeuille service, ADV / Technique et Facturation sur bandeaux verts, et tableau Todo avec sa barre de critères en carte et ses colonnes Devis à relancer, Factures à relancer, À faire et En cours">

### Accueil et tableaux de bord de pilotage

* **Un accueil de travail** remplace les camemberts : période (mois, trimestre ou exercice en cours) et portée (toute l'entreprise ou mes chiffres), puis quatre chiffres clés qui ouvrent chacun leur liste Dolibarr filtrée : chiffre d'affaires HT, devis signés HT (avec ceux en attente), pipe ouvert brut et pondéré, encaissements TTC avec le reste à encaisser et le retard.
* Dessous : le chiffre d'affaires des 12 derniers mois face aux mêmes mois un an plus tôt, le pipe par étape (nombre, brut, pondéré), les derniers devis signés et encaissements, les prochains rappels, les dernières actions faites et la dernière vente de chaque produit, avec un onglet du stock sans vente depuis 6 mois (réglable). Sans droit sur toute l'entreprise, l'accueil montre les chiffres de l'utilisateur.
* **Tableau de bord commercial** (nouvelle section Pilotage du menu) : appels du jour face à l'objectif, objectifs de la période et effort restant, pipeline, taux de transformation, graphes et tableau de l'équipe, actions du jour et en retard, opportunités à relancer, devis en attente.
* **Tableau de bord Direction** : chiffre d'affaires facturé et signé avec son évolution, revenu récurrent mensuel et annuel (MRR, ARR), encours et ancienneté des impayés, performance de l'équipe, graphes mois par mois sur trois exercices, entonnoir brut, pondéré, signé et motifs de perte.
* Les deux tableaux de bord se filtrent par personne et par période (de la semaine en cours à l'exercice précédent). Nouvel onglet de configuration **Objectifs commerciaux** : groupe de l'équipe commerciale, objectifs de l'équipe (30 appels par jour ouvré et 3 devis signés par mois par défaut) et objectifs individuels ; trois nouveaux droits « Tableau de bord ».
* **Opportunités par mois** : nouvelle page sous Opportunités qui compte les opportunités d'un tag mois par mois, sur 2 à 10 ans, avec total, moyenne sur les mois écoulés, meilleur mois et badge d'évolution comparé à la même période de l'année précédente.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/accueil-tableau-de-bord.png" alt="Accueil de ReedCRM : filtres Mois en cours, Trimestre, Exercice et Toute l'entreprise ou Mes chiffres, tuiles Chiffre d'affaires HT, Devis signés HT, Pipe ouvert et Encaissements TTC avec le retard, graphe du chiffre d'affaires sur 12 mois face à l'année précédente et pipe par étape, brut et pondéré">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/pilotage-tableau-de-bord-direction.png" alt="Tableau de bord Direction : blocs Chiffre d'affaires de l'exercice en cours, Facturation récurrente avec MRR, ARR et abonnements actifs, Pipeline avec opportunités ouvertes et pipeline pondéré, Encaissements et impayés avec encours, échu et délai moyen de paiement">

### Tickets orientés argent

* **Tickets à traiter (€)** devient la liste du menu Tickets (Liste et Mes tickets ; la liste native reste sous « Liste complète ») : tuiles à facturer, échu chez ces clients, devis à faire, non facturables et dormants, puis les tickets groupés par **action suivante**, dans l'ordre de l'argent : relancer les impayés, facturer le temps, faire les devis, rattacher un client, rattacher un projet, saisir le temps, clore les dormants. Un seul bouton par ligne ; facturation groupée, une facture brouillon par client.
* **Crédit d'heures** : une facture d'abonnement ouvre un crédit égal à la durée du produit, valable un an, que le temps des tickets du client consomme dans l'ordre chronologique. Il est gelé tant que le client a des factures échues. Le reste est facturé au produit de facturation, au prix du client, arrondi au quart d'heure ; factures et devis sont créés en brouillon, liés au ticket.
* **Bandeau argent sur la fiche ticket** : état du client, couverture, temps du ticket et boutons +15 min, +30 min, +1 h, Facturer, Faire le devis, Offrir (motif obligatoire) et Facturable.
* **Traiter sous réserve** le ticket d'un client en retard de paiement : la réserve, datée et valable 30 jours (réglable), le range dans sa propre section avec sa vraie action suivante, puis il revient dans « Relancer ». Elle se lève depuis la liste ou la fiche.
* **Vue tech** : les tickets à faire, du plus prioritaire au plus ancien, avec **Clore** et **Abandonner** sur place (et le temps à consigner avant la clôture), puis ce qui est **à facturer en fin de mois**, par technicien et par client, avec « Facturer au … » et « Tout facturer » : seul le temps saisi jusqu'à la fin du mois choisi part sur la facture. Le projet s'affiche sur chaque ticket.
* Réglages dans l'onglet Ticket de la configuration : produits d'abonnement, produit de facturation, arrondi, durée de la réserve.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/tickets-a-traiter.png" alt="Liste Tickets à traiter, vue Mes tickets : tuiles À facturer 150 €, Échu, Devis à faire, Non facturables et Dormants, pastilles par action suivante, section Facturer le temps avec deux tickets hors abonnement et leurs boutons Facturer 100 € et Facturer 50 €, puis section Faire les devis">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/tickets-bandeau-fiche.png" alt="Bandeau argent de la fiche ticket d'un client en retard de paiement : montant TTC échu, crédit gelé, boutons Relancer et Traiter sous réserve, puis +15 min, +30 min, +1 h, Faire le devis, Offrir et Facturable">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/tickets-vue-tech.png" alt="Vue tech, Mes tickets de novembre : tuiles À faire, À facturer au 30/11/2026 et Saisi en novembre, puis les tickets à faire par priorité, du ticket Élevé aux tickets Normal, chacun avec son action suivante (Rattacher un projet, Facturer 100 €, Faire le devis, Saisir le temps) et les boutons Clore et Abandonner">

### Frise du cycle de vie et liste des opportunités

* **Frise du cycle de vie** dans la bannière des fiches devis, commande, facture et projet : chaque étape avec sa date (création, validation, envoi, relances, fin de validité, signature, facturation… selon l'objet), en quatre états : faite, à venir, en retard ou en échec. L'infobulle détaille l'étape : auteur, envois, relances, documents liés, règlements.
* Sur un projet, l'infobulle des étapes Devis, Signature, Commande et Facture liste les pièces (référence, date, montant HT, état).
* **Liste des opportunités** : colonne **Étape** (la frise en version compacte, triée sur l'étape atteinte et filtrée sur les étapes franchies), colonne **Tiers** triable et cherchable, colonnes **Photos** et **Vocal** (lecture du dernier enregistrement), et tri des relances commerciales sur la date de la prochaine relance ou sur le nombre de relances en retard.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/cycle-de-vie-devis.png" alt="Bannière d'un devis facturé : frise Création, Validation, Réception, Envoi, Relances, Fin de validité, Signature et Facturation, chaque étape avec sa date, et widget de relance sous la référence">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/cycle-de-vie-liste-opportunites.png" alt="Liste des opportunités : colonnes Tiers, Statut, Étape avec la frise compacte, probabilité, montant et Relances commerciales, et infobulle de l'étape Devis qui liste la proposition avec sa référence, sa date, son montant HT et son état">

### Tableau Todo

* Les cartes de devis et de factures affichent le **montant HT** de l'objet, arrondi (exact au survol), et une relance sans date montre en grisé la date qui compte : validation du devis ou échéance de la facture.
* Au survol : les **lignes du devis ou de la facture**, les **infos du projet** (étape, montant, probabilité, tiers, contact, dates, origine, relances) et la **liste des relances** du casque.
* Le badge du projet est suivi de son budget, de sa probabilité et de son nombre de relances. Une colonne se **trie sur la probabilité** et le tableau se **filtre** sur elle (`>= 50`, `20|90`…).
* Un bouton lien sur chaque carte choisit à la volée le **tiers, le contact et le projet** de l'événement.
* La carte d'un événement issu de Pocket montre le début du compte rendu.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/todo-infobulle-projet.png" width="35%" alt="Carte de la Todo À venir avec le badge du projet suivi de 2,4 k€, 60 % et une relance, et l'infobulle du projet : référence, statut Ouvert, intitulé, étape Proposition, montant, probabilité, tiers, date de début et nombre de relances">

### Fiche pro et widget de relance

* **Fiche pro (CardPro) d'un tiers** : tuiles de montants en cartes, bouton « + » sur chaque bloc (proposition, projet, ticket, note), nouveaux blocs **Tickets en cours** et **Notes en cours**, formulaire en carte et bandeau compact. Un « + » à côté des sélecteurs de projet crée un projet (libellé, montant) sans quitter la page.
* **Widget de relance à deux boutons** : à gauche le passé (nombre, jour de la dernière relance, alerte ambre puis rouge sur les relances en retard), à droite l'à venir (nombre, jour de la prochaine). Le « + » de gauche ouvre l'événement complet, celui de droite un **rappel direct** : objet, type, quand (demain, dans 3 jours, dans une semaine), qui.
* Au survol, le panneau liste les relances (date, type, qui, quoi, état) et un **sélecteur d'état** (À faire, En cours, Effectué, Non applicable) les met à jour sans recharger la page, compteurs compris.
* Le widget, avec le logo ReedCRM, est sur les fiches tiers, client, projet et devis, en bas du bandeau de la fiche pro, dans les listes des projets et des opportunités et dans l'application.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/cardpro-tiers.png" alt="Fiche pro d'un tiers : bandeau compact avec adresse, téléphone, e-mail et widget de relance, formulaire d'événement en carte, tuiles Propositions, Commandes, Factures et Montant encours, blocs Dernières propositions, Tickets en cours, Notes en cours et Derniers projets, chacun avec son bouton +">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/relances-panneau-etat.png" alt="Widget de relance d'une fiche pro : bouton des relances passées (1, il y a 3 j) et des relances à venir (1, vendredi 27/11), panneau Relances à venir avec date, type, qui et quoi, et sélecteur d'état ouvert sur À faire, En cours, Effectué et Non applicable">

### Listes d'appel et application mobile

* **Fiche de la liste d'appel** alignée sur la liste des projets : origine, tiers, contact, téléphone, statut, probabilité et montant de l'opportunité modifiables sur place, étape, relances commerciales, état et **dernier appel** ; tri, filtres et choix des colonnes. Les appels s'ordonnent par **flèches ↑ / ↓** ou par glisser-déposer, et l'application comme le PDF suivent cet ordre ; « Délier » remplace la poubelle.
* À l'ajout d'un élément, le **téléphone est cherché en cascade** (opportunité, contact lié, tiers, contacts du tiers) : tous les numéros trouvés sont proposés, et un numéro peut être saisi, vérifié à la frappe.
* Les listes d'appel ont une **position**, modifiable dans la liste, qui les ordonne partout (liste et sélecteurs).
* **Liste des opportunités** : une colonne **Listes d'appel** montre une pastille par liste où figure l'opportunité. Le crayon ouvre les listes en brouillon ou actives : cocher ajoute l'opportunité (le téléphone est demandé s'il manque), décocher la retire, sans quitter la liste.
* **Application** : l'onglet Appel porte le nombre de personnes à appeler et rouvre la dernière liste consultée (la liste par défaut la première fois), et le sélecteur reste sur la liste affichée après un retour arrière. Chaque carte montre l'objet avec son montant et ses relances, le badge En retard ou À venir, la date du prochain événement (en rouge une fois dépassée), la date d'ajout et le dernier appel, avec les deux boutons ReedCRM, les flèches et « Délier ». Le formulaire d'événement prend le style du rappel, avec un vocal à côté de la description.
* **Ajout rapide d'opportunité** : case « Me rappeler plus tard » (demain, dans 3 jours, 1 semaine, 1 mois ou date choisie) et, en option désactivée par défaut, **description rédigée par l'IA** à partir du vocal (module IA de Dolibarr, service ChatGPT).

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/listes-appel-fiche.png" alt="Fiche d'une liste d'appel : lignes avec poignée et flèches, origine, tiers, contact, téléphone, statut, probabilité et montant de l'opportunité, étape, relances commerciales, état À appeler, dernier appel le 22/11/2026 et bouton Délier">

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/app-liste-appel.png" width="32%" alt="Liste d'appel sur téléphone : carte d'un contact À venir, dans la liste depuis le 25/11/26, dernier appel le 22/11/26, gros bouton du numéro, opportunité à 2,4 k€ avec une relance, prochain rappel, boutons ReedCRM des relances, flèche et bouton Délier">

### Pocket

* Puce **« Je viens de raccrocher »** sur les fiches : elle rattache à la fiche le dernier enregistrement rangé dans le dossier Pocket de l'utilisateur.
* **Un compte Pocket par utilisateur**, sur un onglet Pocket de sa fiche ; le compte de la configuration devient le compte partagé de ceux qui n'en ont pas.
* Chaque enregistrement traité crée un événement **Appel téléphonique en cours**, avec le tiers, le projet et les contacts de l'enregistrement ; la fiche de l'événement indique l'enregistrement dont il vient.
* **Rappel quotidien**, à l'heure choisie, pour ranger les appels Pocket qui ne sont dans aucun dossier.

### Temps du projet et durée des événements

* La fiche projet reçoit le **bloc de saisie rapide du temps** de la fiche ticket, avec le total passé sur le prévu, et une carte **Temps** sous la frise : une barre par mois (prévu, passé, dépassement), les totaux de l'année et la navigation d'une année à l'autre, encadrée en rouge quand l'année dépasse le prévu.
* Le temps prévu vient des services des **devis signés**, sur le mois du service, sinon de la charge des tâches.
* L'événement rapide prend une **durée** à côté de sa date (par défaut selon son type, réglable) : terminé et lié à un projet, il pointe cette durée sur la tâche de suivi commercial.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/projet-temps.png" alt="Fiche projet : frise du cycle de vie, puis carte Temps 2026 avec 70 h prévues, 28 h 45 consommées et 14 h 03 de dépassement, une barre par mois de janvier à décembre, le prévu en bleu clair, le passé en bleu et le dépassement en rouge">

### Propositions commerciales et contrats

* **Proposition depuis un modèle** : une entrée « Créer une proposition à partir d'un modèle » dans le menu Créer de la fiche projet et une ligne « Modèle de proposition » en tête du formulaire de nouvelle proposition reprennent les lignes et les notes du modèle. La liste des modèles passe sur la liste Saturne : colonnes redimensionnables et déplaçables, filtres, pagination.
* **Devis non facturés** : un tag « Non facturé » est posé sur les devis signés sans facture, mis à jour quatre fois par jour par une tâche planifiée et par un bouton de la configuration, avec un bandeau rouge sur la fiche du devis. La fiche d'un contrat validé sans facture a le même bandeau.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/propositions-depuis-modele.png" alt="Formulaire de nouvelle proposition : ligne Modèle de proposition en tête, liste ouverte sur le modèle Document unique PME à 2 400 € HT">

### Import CSV d'opportunités

* Nouvelles colonnes à choisir à la confirmation : les **personnes assignées** (reconnues par login, e-mail, nom complet, prénom…), une **note** de 0 à 10 convertie en probabilité avec l'étape correspondante, et le **texte et la date d'un rappel**, qui créent les rappels de la Todo (la date du fichier devient l'échéance).
* Les CSV enregistrés par Excel (Windows-1252) et les en-têtes accentués sont acceptés.

### Événements et clôture rapide

* Formulaire « événement complet » : le type se choisit sur des pastilles (Appel, Email, RDV, Autre).
* Clôture rapide : **avancement** réglable (sous 100 %, seul l'avancement change), jour du rappel affiché avec une heure au choix, compteur de caractères du nouveau nom (128 au plus, au-delà la clôture avec reprogrammation est refusée au lieu de couper le nom).

### Documents : aperçu des e-mails .msg et .eml

* Une loupe après chaque fichier .msg ou .eml, sur les fiches et les onglets Fichiers joints, ouvre l'e-mail dans une fenêtre : objet, expéditeur, destinataires, date, corps avec ses images et pièces jointes à télécharger. Un e-mail joint s'ouvre à son tour.

<img src="https://raw.githubusercontent.com/nicolas-eoxia/easycrm/refs/heads/assets/release-23.4.0/documents-apercu-email.png" alt="Aperçu d'un e-mail .eml joint à un tiers : objet Demande de devis, expéditeur, destinataire, copie et date, corps du message avec sa liste à puces, et pièce jointe postes-atelier.csv à télécharger">

### Contrôle des mandats SEPA

* Nouvelle page **Facturation > Contrôle des mandats SEPA**, en lecture seule : RIB par RIB, ce qui empêchera un prélèvement de partir correctement (date de signature absente ou future, RUM absente ou en double, IBAN ou BIC invalide, RIB désigné désactivé ou supprimé, plusieurs RIB par défaut, demande sans RIB…), avec sa gravité, la date qui sera écrite dans le fichier et un bouton « Corriger ». Les IBAN n'y apparaissent que masqués.

## Améliorations & corrections

### Tableau Todo

* Une relance déposée dans une colonne qui ne peut pas la recevoir revenait à sa place **sans explication** : pendant le glisser, les colonnes possibles sont mises en avant, et un message donne la règle.
* Les relances d'une **facture payée** ou d'un **devis signé, refusé ou facturé** restaient dans leur colonne jusqu'à un déplacement à la main : elles sont closes automatiquement, et les tâches planifiées rattrapent les anciennes.
* Tableau plus rapide : une colonne vide n'est plus lue (2,2 s ramenées à 0,8 s sur une copie de base client).
* Une relance planifiée quitte sa colonne de relances pour « À faire » ; deux événements à la même date gardent leur ordre ; les relances levées en anglais sont traduites et celles enregistrées avec une clé brute réparées ; plus d'icône d'onglet ni de barre de défilement en double.

### Relances, clôture rapide et événements

* Le rappel créé à la clôture rapide durait aussi longtemps que l'événement clôturé (parfois des semaines) : il commence et finit au même moment, et revient à la personne qui le pose.
* La fenêtre de clôture ne se ferme plus quand une sélection de texte se relâche sur le fond ; une heure tapée sans ses minutes est acceptée.
* Un rappel coché est créé même sans titre, avec son propre type, et l'état de l'événement se choisit.
* Opportunité perdue : le motif choisi dans le dictionnaire suffit (le commentaire devient facultatif) et sert de titre à l'événement de clôture.
* Le formulaire d'e-mail du panneau latéral s'envoie de nouveau ; une liste de la fenêtre ne se bloque plus quand la page porte un champ du même nom ; l'onglet Ticket du formulaire n'affiche plus d'avertissement sur une opportunité sans tiers.
* Boutons de relance alignés d'une ligne à l'autre dans les listes, badge « En cours » en bleu, événements sans titre traduits.
* Fiche pro : la date du formulaire d'événement était coupée par le champ durée sur un écran de 1680 px (la durée passe dessous) ; dans le panneau des relances, la colonne « Quoi » était écrasée par les autres colonnes.

### Opportunités et projets

* La probabilité suit l'étape choisie, même à la baisse ; la liste des étapes de la cellule Opportunité restait vide.
* Supprimer un projet dont les tâches portent du temps était refusé sans message : le temps est supprimé avec le projet.
* Le formulaire de création de projet garde ce qui était saisi quand on crée un tiers par son « + ».
* Bannière du projet : la référence reste en haut à côté du statut, les widgets restent dans le flux, le picto du tiers reste un lien après un changement sur place ; libellé des tags au survol ; badges des motifs de refus conservés ; champ e-mail au format e-mail.
* Le lecteur de vocal de la liste des opportunités mettait en pause ce qu'il venait de lancer.
* Liste des opportunités : la colonne des coordonnées se trie sur le nom du contact et se filtre sur les valeurs de la cellule ; les feuilles de style des listes des projets et des propositions sont chargées dans l'en-tête de la page.

### Listes d'appel et application mobile

* L'appel passé depuis la liste d'appel était enregistré « à faire » et sans tiers : il est terminé et rattaché au tiers, au projet ou à la facture de la ligne.
* Le modèle PDF de la liste d'appel est traduit, et le dernier tri de la liste des listes d'appel est conservé.
* Application : la recherche de la liste des opportunités s'écrivait à l'envers sur téléphone, le champ commercial de l'ajout rapide se réduisait à un carré, une opportunité créée sur mobile recevait une seconde tâche commerciale ; les pages d'erreur retrouvent leur en-tête, la baguette IA des photos est rétablie, les miniatures de l'ajout rapide sont créées au bon endroit et le rappel « Me rappeler plus tard » revient à son propriétaire.
* L'entrée « Ajout rapide » mobile n'apparaît plus en double dans le menu.

### Keyyo

* Un appel entrant créait un événement à chaque changement d'état : il n'en crée plus qu'un, et aucun quand personne ne correspond au numéro appelé.
* La fenêtre d'appel entrant reprenait les relances « Appel téléphonique » saisies à la main, puis les close comme faites : elle s'en tient aux appels du webhook. Les appels entrants sont comptés à part dans les tableaux de bord, et les libellés enregistrés par le webhook et les tâches planifiées sont écrits dans la langue de leur destinataire.

### Pocket

* La puce « Je viens de raccrocher » n'apparaît qu'une fois Pocket configuré (clé et dossier), et son lien est protégé contre les requêtes forgées.
* Le bouton de synchronisation reste sur la liste et lit tous les comptes ; les liens d'un enregistrement passent à l'événement d'une action ; renommer un enregistrement dans Pocket renomme son événement ; une fiche ouverte sans identifiant affiche « enregistrement introuvable » au lieu d'une erreur fatale ; les onglets Pocket sont bien déclarés à l'activation.

### Tickets et propositions commerciales

* Vue tech : la tuile « À facturer » montre ce que les boutons facturent. Le sujet du ticket n'est plus échappé deux fois dans la confirmation de clôture ; montant et bouton restent sur une ligne dans la liste argent.
* Le bandeau « non facturé » d'un devis disparaît dès qu'une facture le facture, sans attendre la tâche planifiée.
* Sur une installation neuve, le statut commercial des devis n'était pas créé et la frise faisait échouer chaque fiche devis : il est de nouveau déclaré.
* Fiche pro : le lien « Toutes les propales » est filtré sur le tiers.

### Frise du cycle de vie et pilotage

* Les envois de factures et de commandes sont lus sous le type que l'agenda enregistre, et la feuille de style de la frise est chargée sur tous les onglets.
* Objectifs commerciaux : les jours ouvrés sont comptés juste autour d'un changement d'heure (un avertissement PHP s'affichait sur chaque tableau de bord et les jours fériés étaient lus la veille).

### Menus, affichage et performances

* Bandeaux de section lisibles en menu réduit et rétablis en thème moderne ; entrée « Réduire le menu » retirée du menu Projets ; entrées du menu de gauche replacées sous le bloc d'administration de Saturne.
* Des règles CSS mortes occupaient le navigateur environ 4 secondes après le chargement de certaines pages : elles sont retirées. Les feuilles de style et scripts écrits à la main sont versionnés, pour ne plus être servis périmés par le cache.
* L'infobulle des projets ne déforme plus celles des autres objets.

### Sécurité

* Jeton CSRF ajouté aux liens et appels AJAX ; contrôle renforcé du type des médias envoyés.
* Droits lus par `hasRight()` dans la configuration et trois pages d'outils : plus d'avertissement PHP pour un utilisateur qui n'a pas ces droits.

### Installation et compatibilité

* **Version minimale de Saturne** déclarée : l'activation est refusée avec un message sur un Saturne trop ancien ou absent, au lieu d'une erreur fatale.
* Les fichiers des objets validés restés sous `(PROVn)` sont repris à l'activation depuis Accueil > Modules.
* Module utilisable hors de `custom/` (racine alternative, lien symbolique) : chemins PHP, adresses JS et registre d'objets passent par Dolibarr, et le registre reste lisible par les Saturne antérieurs à 23.3.0.
* L'API REST des expéditions répondait en erreur 500 dès qu'un filtre était donné ; les 13 hooks acceptent une action nulle (erreur fatale à l'annulation d'un formulaire, par exemple dans DoliLetter) ; plus d'avertissement sur les déclencheurs d'ExternalAccess ; picto des déclencheurs retrouvé.
* Carte et fiche adresse sans type : plus d'erreur fatale ; classe d'adresse renommée pour ne plus entrer en conflit avec infraspackplus.
* La tâche des notations de contacts traite tous les objets et liste les refus au lieu de s'arrêter au premier ; plus d'avertissement quand un champ de création rapide est masqué.
* Fichiers de langue fr_FR et en_US à parité ; changelog renommé `ChangeLog.md`, le nom qu'attend Dolibarr sur un serveur Linux.

### Qualité et intégration continue

* PHPStan, lint et parité des langues alignés sur le gabarit commun des modules, contrôle de l'API Saturne publiée dans la CI qualité, actions GitHub en Node 24.

## Comparaison des versions [23.3.2](https://github.com/Eoxia/reedcrm/compare/23.3.2...23.4.0) et 23.4.0

* [#1330] [EventPro] fix: date entière dans le formulaire d'événement de la fiche pro et colonne « Quoi » lisible dans le panneau des relances [`0fe29970`](https://github.com/Eoxia/reedcrm/commit/0fe29970)
* [#1327] [CSS] fix: print the stylesheets of the project and proposal lists in the page header [`af606250`](https://github.com/Eoxia/reedcrm/commit/af606250)
* [#1325] [Project] feat: call lists column on the opportunity list, to add the opportunity to a list or take it off one [`16cae8f6`](https://github.com/Eoxia/reedcrm/commit/16cae8f6)
* [#1323] [CallList] feat: the App reopens the last call list opened unless one is asked for, and its selector stays on the list displayed after a back navigation [`f7e0c4be`](https://github.com/Eoxia/reedcrm/commit/f7e0c4be)
* [#1319] [Mod] fix: ReedCRM picto on the commercial status of the proposal card [`97cbc740`](https://github.com/Eoxia/reedcrm/commit/97cbc740)
* [#1319] [Mod] fix: declare the commstatus extrafield of proposals again [`d9b1f761`](https://github.com/Eoxia/reedcrm/commit/d9b1f761)
* [#1317] [Trigger] fix: plus d'avertissement sur un événement dont l'objet n'est pas un CommonObject [`56bb00ae`](https://github.com/Eoxia/reedcrm/commit/56bb00ae)
* [#1315] [Outils] fix: droits lus par hasRight() dans trois pages de view/, sans avertissement pour un utilisateur qui ne les a pas [`11a90a10`](https://github.com/Eoxia/reedcrm/commit/11a90a10)
* [#1313] [Admin] fix: droit de lecture de la configuration lu par hasRight(), sans avertissement pour un utilisateur qui ne l'a pas [`a183fb43`](https://github.com/Eoxia/reedcrm/commit/a183fb43)
* [#1309] [Mod] fix: fichiers des objets validés restés sous (PROVn) repris à l'activation depuis Accueil > Modules [`7fb7d4c4`](https://github.com/Eoxia/reedcrm/commit/7fb7d4c4)
* [#1310] [Trigger] fix: picto reedcrm_color@reedcrm, l'image reedcrm@reedcrm n'existe pas [`b3e56779`](https://github.com/Eoxia/reedcrm/commit/b3e56779)
* [#1307] [Hook] fix: les 13 hooks typés string acceptent $action nul [`3de135f4`](https://github.com/Eoxia/reedcrm/commit/3de135f4)
* [#1305] [CallList] fix: satisfy PHPStan checks on opp badge and arrayfields [`b7fadfca`](https://github.com/Eoxia/reedcrm/commit/b7fadfca)
* [#1305] [CallList] feat: align call list lines table with ReedCRM project list standard [`59b0e6c1`](https://github.com/Eoxia/reedcrm/commit/59b0e6c1)
* [#1303] [CallList] feat: phone fallback cascade, inline phone choices and live regex validation [`dfcda5d7`](https://github.com/Eoxia/reedcrm/commit/dfcda5d7)
* [#1301] [CallList] feat: unlink instead of the bin on the call list card, last call of each line on the card and in the App [`6baa5052`](https://github.com/Eoxia/reedcrm/commit/6baa5052)
* [#1299] [CallList] feat: ReedCRM buttons and up / down arrows on each call of the App call list, voice note beside the description and new style of the App event form [`eede3e89`](https://github.com/Eoxia/reedcrm/commit/eede3e89)
* [#1297] [Project] fix: opportunity status select read from a column the dictionary does not have [`b2ed9cc5`](https://github.com/Eoxia/reedcrm/commit/b2ed9cc5)
* [#1295] [CallList] fix: the call made from the call list is done and on its third party [`b2df7703`](https://github.com/Eoxia/reedcrm/commit/b2df7703)
* [#1293] [Pilotage] fix: count the Keyyo incoming calls apart, the quoted caller pattern never matched [`c00b2bc4`](https://github.com/Eoxia/reedcrm/commit/c00b2bc4)
* [#1291] [Keyyo] fix: keep the incoming call popup to the calls of the webhook, relaunches no longer closed as done [`762931bc`](https://github.com/Eoxia/reedcrm/commit/762931bc)
* [#1286] [Propal] fix: not billed banner of the proposal card hidden as soon as an invoice bills the proposal, without waiting for the cron job to take the tag off [`bd95a658`](https://github.com/Eoxia/reedcrm/commit/bd95a658)
* [#1283] [CallList] fix: return values of moveInCallList() readable by PHPStan, rebuilt .min assets [`678b1b2e`](https://github.com/Eoxia/reedcrm/commit/678b1b2e)
* [#1283] [CallList] feat: up / down arrows ordering the calls of a call list on its card, without reloading the page, the PWA and the PDF following the same order [`3ef1f746`](https://github.com/Eoxia/reedcrm/commit/3ef1f746)
* [#1260] [Project] fix: type of the contact details column, required by the shape of the fields [`13bb6b7d`](https://github.com/Eoxia/reedcrm/commit/13bb6b7d)
* [#1260] [Project] fix: sort the contact details column of the opportunity list on the name of the contact and filter it on the values of the cell [`638fcc5c`](https://github.com/Eoxia/reedcrm/commit/638fcc5c)
* [#1260] [QuickClose] feat: description field of the close modal labelled as the description of the action [`09343d1f`](https://github.com/Eoxia/reedcrm/commit/09343d1f)
* [#1280] [Documents] feat: preview .msg / .eml email attachments in a modal [`1121d40f`](https://github.com/Eoxia/reedcrm/commit/1121d40f)
* [#1130] [Project] feat: planned time of the project from the services of its signed proposals, on the month of the service, the workload of the tasks for a year without any [`c22b09e4`](https://github.com/Eoxia/reedcrm/commit/c22b09e4)
* [#1130] [Project] fix: time card in the status column of the banner, under the lifecycle timeline, framed in red when the year went beyond the planned time [`f1eefab6`](https://github.com/Eoxia/reedcrm/commit/f1eefab6)
* [#1130] [Project] rework: time card of the project, one bar per month (planned, spent, over the planned time) and the totals of the year beside the title [`d2099316`](https://github.com/Eoxia/reedcrm/commit/d2099316)
* [#1278] [Admin] fix: no "Undefined property" warning when a quick creation field is hidden [`b30cc790`](https://github.com/Eoxia/reedcrm/commit/b30cc790)
* [#1263] [CallList] feat: position of the call lists, ordering the list and the selectors, editable in place [`7905be15`](https://github.com/Eoxia/reedcrm/commit/7905be15)
* [#1260] [Lifecycle] fix: filter the opportunity list on the milestones reached, not only the current one [`a89a99c8`](https://github.com/Eoxia/reedcrm/commit/a89a99c8)
* [#1260] [Relaunch] feat: sort the opportunity list on the date of the next relaunch or the number of overdue ones [`cb643d24`](https://github.com/Eoxia/reedcrm/commit/cb643d24)
* [#1260] [Projet] feat: third party column in the opportunity list, sorted on its name and searchable [`55f0a633`](https://github.com/Eoxia/reedcrm/commit/55f0a633)
* [#1272] [Relaunch] fix: running status badge in blue, apart from the to do one [`fc0133df`](https://github.com/Eoxia/reedcrm/commit/fc0133df)
* [#1270] [Media] fix: renforcement de la validation des types de médias à l'upload [`8e375754`](https://github.com/Eoxia/reedcrm/commit/8e375754)
* [#1268] [Assets] chore: rebuild the css bundle [`e45d4120`](https://github.com/Eoxia/reedcrm/commit/e45d4120)
* [#1268] [Lifecycle] feat: list the proposals, orders and invoices behind a milestone in the tooltip of the project timeline [`87b3125f`](https://github.com/Eoxia/reedcrm/commit/87b3125f)
* [#1266] [Projet] fix: show the tag label on hover in the opportunity list [`caee24ce`](https://github.com/Eoxia/reedcrm/commit/caee24ce)
* [#1261] [Relaunch] feat: status selector in the panel of the relaunch widget [`0790bb0f`](https://github.com/Eoxia/reedcrm/commit/0790bb0f)
* [#1258] [Ticket] fix: sujet du ticket échappé une seule fois dans la confirmation de clôture [`7d7bf7ac`](https://github.com/Eoxia/reedcrm/commit/7d7bf7ac)
* [#1256] [Ticket] feat: actions de clôture sur la vue tech (clore, abandonner, temps à consigner) [`bee64fea`](https://github.com/Eoxia/reedcrm/commit/bee64fea)
* [#1254] [Todo] fix: close the relaunches of a paid invoice or an answered proposal [`c7beb2bc`](https://github.com/Eoxia/reedcrm/commit/c7beb2bc)
* [#1252] [Ticket] fix: vue tech, la tuile « À facturer » montre ce que les boutons facturent [`5fbf17b4`](https://github.com/Eoxia/reedcrm/commit/5fbf17b4)
* [#1250] [Ticket] feat: vue tech, à faire par priorité et à facturer en fin de mois, projet sur chaque ticket [`6772da6d`](https://github.com/Eoxia/reedcrm/commit/6772da6d)
* [#1248] [Ticket] feat: action « Traiter sous réserve » pour les tickets d'un client en retard de paiement [`f56297a2`](https://github.com/Eoxia/reedcrm/commit/f56297a2)
* [#1246] [Ticket] feat: liste « Tickets à traiter » groupée par action suivante [`7cfc2293`](https://github.com/Eoxia/reedcrm/commit/7cfc2293)
* [#1244] [SCSS] fix: montant et bouton d'action sur une seule ligne dans la liste argent [`cf020ab5`](https://github.com/Eoxia/reedcrm/commit/cf020ab5)
* [#1242] [Ticket] feat: liste et fiche ticket orientées argent [`58f5cce1`](https://github.com/Eoxia/reedcrm/commit/58f5cce1)
* [#1240] [EventPro] feat: type chips instead of the type select in the complete event form [`439bcce8`](https://github.com/Eoxia/reedcrm/commit/439bcce8)
* [#1238] [PWA] fix: plus de journal de débogage à l'envoi d'un enregistrement audio [`515e9957`](https://github.com/Eoxia/reedcrm/commit/515e9957)
* [#1236] [Todo] feat: pick the third party, contact and project of a card on the fly [`9c801289`](https://github.com/Eoxia/reedcrm/commit/9c801289)
* [#1234] [Pocket] fix: rename the event of a recording renamed in pocket [`01d29a6b`](https://github.com/Eoxia/reedcrm/commit/01d29a6b)
* [#1232] [Menu] fix: remove the minimize menu entry from the projects menu [`82cb450f`](https://github.com/Eoxia/reedcrm/commit/82cb450f)
* [Medias] fix: plus de journal de débogage des requêtes, pas de galerie sur le suivi des factures récurrentes [`b66510fc`](https://github.com/Eoxia/reedcrm/commit/b66510fc)
* [#1229] [Cron] fix: notations contact, traiter tous les objets et remonter les refus [`4c208f8d`](https://github.com/Eoxia/reedcrm/commit/4c208f8d)
* [#1227] [Menu] fix: restore the green section bands in the modern theme [`268a73ce`](https://github.com/Eoxia/reedcrm/commit/268a73ce)
* [#1225] [Assets] chore: rebuild reedcrm.min.css [`50e195fc`](https://github.com/Eoxia/reedcrm/commit/50e195fc)
* [#1225] [RelaunchWidget] feat: reedcrm logo and frame on every surface [`f5518096`](https://github.com/Eoxia/reedcrm/commit/f5518096)
* [#1221] [Assets] chore: rebuild reedcrm.min.css and reedcrm.min.js [`a0659e49`](https://github.com/Eoxia/reedcrm/commit/a0659e49)
* [#1221] [JS] fix: select2 of a modal select sharing its id with the page [`d477b803`](https://github.com/Eoxia/reedcrm/commit/d477b803)
* [#1221] [CardPro] feat: relaunch widget at the bottom of the banner [`d13a1f4c`](https://github.com/Eoxia/reedcrm/commit/d13a1f4c)
* [#1222] [Todo] feat: show the beginning of the pocket summary on the event card [`d07071bf`](https://github.com/Eoxia/reedcrm/commit/d07071bf)
* [#1222] [Agenda] feat: show the pocket recording an event comes from on its card [`32fd4772`](https://github.com/Eoxia/reedcrm/commit/32fd4772)
* [#1222] [Pocket] feat: create an in progress call event for each processed recording [`18284e0f`](https://github.com/Eoxia/reedcrm/commit/18284e0f)
* [#1219] [EventQuickClose] fix: accept an hour typed without its minutes [`912662d8`](https://github.com/Eoxia/reedcrm/commit/912662d8)
* [#1217] [Pocket] fix: sync button stays on the recordings list and fetches every account [`4b8ca452`](https://github.com/Eoxia/reedcrm/commit/4b8ca452)
* [#1215] [JS] fix: let the email form of the side modal post itself [`6d00fa57`](https://github.com/Eoxia/reedcrm/commit/6d00fa57)
* [#1215] [EventPro] fix: unnest the email form and handle its send action [`c78cab1e`](https://github.com/Eoxia/reedcrm/commit/c78cab1e)
* [#1213] [CardPro] feat: amount tiles as cards and an add button on every block [`e559076a`](https://github.com/Eoxia/reedcrm/commit/e559076a)
* [#909] [Todo] fix: two events sharing a start date keep the order of their IDs [`f274eb27`](https://github.com/Eoxia/reedcrm/commit/f274eb27)
* [#1211] [PWA] fix: build quick creation thumbnails with saturne_vignette [`2b41bf68`](https://github.com/Eoxia/reedcrm/commit/2b41bf68)
* [#997] [CardPro] fix: type the assigned user of the open notes as ActionComm expects it [`322dfc80`](https://github.com/Eoxia/reedcrm/commit/322dfc80)
* [#997] [CardPro] fix: page layout, compact thirdparty banner and reminder box that did not unfold [`52687425`](https://github.com/Eoxia/reedcrm/commit/52687425)
* [#997] [CardPro] feat: open tickets and open notes under the latest proposals [`336f9f04`](https://github.com/Eoxia/reedcrm/commit/336f9f04)
* [#1208] [CI] ci: actions GitHub en Node 24 (checkout@v7, cache@v6) [`e61bbc1f`](https://github.com/Eoxia/reedcrm/commit/e61bbc1f)
* [#1206] [CI] ci: contrôle « API Saturne publiée » dans la CI qualité [`7caca3f4`](https://github.com/Eoxia/reedcrm/commit/7caca3f4)
* [#1204] [CI] ci: retrait du workflow Generate Auto Changelog [`2de4a636`](https://github.com/Eoxia/reedcrm/commit/2de4a636)
* [#601] [EventPro] fix: PHPStan, globales déclarées et identifiant de projet en entier [`14bc20df`](https://github.com/Eoxia/reedcrm/commit/14bc20df)
* [#1202] [Mod] feat: version minimale de Saturne, activation refusee sur un Saturne trop ancien [`cf76247c`](https://github.com/Eoxia/reedcrm/commit/cf76247c)
* [#1200] [PocketRecording] fix: fiche ouverte sans id, enregistrement introuvable au lieu d'une fatale [`c955a6cc`](https://github.com/Eoxia/reedcrm/commit/c955a6cc)
* [#1198] [Registre] fix: chemins du registre publies tels qu'installes, lisibles par Saturne < 23.3.0 [`049f9154`](https://github.com/Eoxia/reedcrm/commit/049f9154)
* [#1056] [JS] chore: supprimer reedcrm_opportunities.js, charge par aucune page [`ee4dd131`](https://github.com/Eoxia/reedcrm/commit/ee4dd131)
* [#1194] [Relaunch] fix: align relaunch buttons across rows and translate untitled events [`1fad4b7c`](https://github.com/Eoxia/reedcrm/commit/1fad4b7c)
* [#1192] [Relaunch] rework: two-storey relaunch buttons with counter and day [`00a30ee6`](https://github.com/Eoxia/reedcrm/commit/00a30ee6)
* [#1190] [Pocket] fix: carry the links of the recording over to the event of an action [`c1eaa37e`](https://github.com/Eoxia/reedcrm/commit/c1eaa37e)
* [#1180] [Propal] feat: create a proposal from a template, from the project card or the creation form [`ac6f6e63`](https://github.com/Eoxia/reedcrm/commit/ac6f6e63)
* [#1180] [Propal] rework: list the proposal templates on the generic saturne list [`1a202509`](https://github.com/Eoxia/reedcrm/commit/1a202509)
* [#1182] [Assets] chore: rebuild the css bundle [`4fb53844`](https://github.com/Eoxia/reedcrm/commit/4fb53844)
* [#1182] [Lifecycle] fix: keep the ref of every banner carrying the timeline at the top, next to its status [`dbbd5f7e`](https://github.com/Eoxia/reedcrm/commit/dbbd5f7e)
* [#1185] [Assets] fix: version every asset url printed by hand through reedcrm_asset_full_url [`e87a155f`](https://github.com/Eoxia/reedcrm/commit/e87a155f)
* [#1181] [Assets] chore: rebuild the css and js bundles [`f0390687`](https://github.com/Eoxia/reedcrm/commit/f0390687)
* [#1181] [Project] fix: keep the ref of the banner at the top, next to its status [`b1ff5c0c`](https://github.com/Eoxia/reedcrm/commit/b1ff5c0c)
* [#1181] [Project] fix: keep the third party picto a link to its card after changing it inline [`a88c64d8`](https://github.com/Eoxia/reedcrm/commit/a88c64d8)
* [#1183] [CSS] delete: dead body:has() rules of the compact list density [`b02df563`](https://github.com/Eoxia/reedcrm/commit/b02df563)
* [#1178] [CI] ci: refuser les inclusions relatives de Saturne [`18940060`](https://github.com/Eoxia/reedcrm/commit/18940060)
* [#1178] [Saturne] fix: inclure Saturne par dol_include_once / dol_buildpath [`0365096b`](https://github.com/Eoxia/reedcrm/commit/0365096b)
* [#1176] [Keyyo] fix: restore the contact join of pending call events [`32f45dcf`](https://github.com/Eoxia/reedcrm/commit/32f45dcf)
* [#1173] [PWA] feat: unlink a line from the call list and show its added date [`b74edf83`](https://github.com/Eoxia/reedcrm/commit/b74edf83)
* [#873] [Relaunch] fix: restore the two-button widget layout inside list cells [`585e0eca`](https://github.com/Eoxia/reedcrm/commit/585e0eca)
* [#873] [Relaunch] fix: restyle the direct reminder form and align its pictos [`827d173a`](https://github.com/Eoxia/reedcrm/commit/827d173a)
* [#873] [Relaunch] rework: two-button relaunch widget and direct reminder form [`9e0caaa9`](https://github.com/Eoxia/reedcrm/commit/9e0caaa9)
* [#1170] [Module] fix: refuse the activation instead of a fatal error when Saturne is missing [`63182403`](https://github.com/Eoxia/reedcrm/commit/63182403)
* [#1168] [Project] fix: use email extrafield type for reedcrm_email [`600b68da`](https://github.com/Eoxia/reedcrm/commit/600b68da)
* [#1166] [Pocket] fix: activate dependencies before init so Pocket tabs are registered [`5888b83f`](https://github.com/Eoxia/reedcrm/commit/5888b83f)
* [#1164] [Admin] feat: set the time of the Pocket filing reminder [`e8f6b68b`](https://github.com/Eoxia/reedcrm/commit/e8f6b68b)
* [#1164] [Pocket] feat: daily reminder event to file the unfiled Pocket calls [`049e9080`](https://github.com/Eoxia/reedcrm/commit/049e9080)
* [#1162] [Assets] chore: rebuild the css and js bundles [`59aa38bc`](https://github.com/Eoxia/reedcrm/commit/59aa38bc)
* [#1162] [Project] fix: keep the banner widgets in the flow and each contact field on one line [`4da73001`](https://github.com/Eoxia/reedcrm/commit/4da73001)
* [#1162] [EventQuickClose] fix: keep the modal open when a selection is released on its backdrop [`16135957`](https://github.com/Eoxia/reedcrm/commit/16135957)
* [#1160] [Todo] feat: count the characters of the new event name in the quick close modal [`228c0f67`](https://github.com/Eoxia/reedcrm/commit/228c0f67)
* [#1158] [Todo] feat: list the relaunches on hovering the headset of a card [`2851f5c6`](https://github.com/Eoxia/reedcrm/commit/2851f5c6)
* [#1156] [Object] rework: retirer le prefixe custom/ des entrees du registre d'objets [`f05ca123`](https://github.com/Eoxia/reedcrm/commit/f05ca123)
* [#1154] [API] fix: list hooks accept any object, the shipments REST API sends a stdClass [`76e8530d`](https://github.com/Eoxia/reedcrm/commit/76e8530d)
* [#1152] [Pocket] feat: a Pocket account per user, and "I just hung up" picks the last recording of the folder [`f1ffdae8`](https://github.com/Eoxia/reedcrm/commit/f1ffdae8)
* [#1149] [Todo] feat: show the infos of the project on hovering its badge [`7a91783e`](https://github.com/Eoxia/reedcrm/commit/7a91783e)
* [#1148] [Todo] fix: show the icon of the board tab only once [`aea68baa`](https://github.com/Eoxia/reedcrm/commit/aea68baa)
* [#1146] [Lifecycle] fix: load the module stylesheet with the timeline on the tabs that skip it in the header [`cfabe592`](https://github.com/Eoxia/reedcrm/commit/cfabe592)
* [#1144] [QuickClose] fix: give the reminder to the assignee who sets it [`7c7f48b4`](https://github.com/Eoxia/reedcrm/commit/7c7f48b4)
* [#1142] [QuickClose] feat: show the day of the reminder and let its hour be picked [`7a2d9d6e`](https://github.com/Eoxia/reedcrm/commit/7a2d9d6e)
* [#1142] [QuickClose] fix: end the reminder when it starts [`c2e2d961`](https://github.com/Eoxia/reedcrm/commit/c2e2d961)
* [#1140] [Lifecycle] fix: read the sendings of invoices and orders under the element type the agenda stores [`4626b261`](https://github.com/Eoxia/reedcrm/commit/4626b261)
* [#1138] [Accueil] feat: usable home dashboard with revenue, pipeline, signed proposals, payments, last actions and last sale per product [`8ab3a67e`](https://github.com/Eoxia/reedcrm/commit/8ab3a67e)
* [#981] [Pocket] fix: show the hang up chip only once Pocket is configured [`12b2f0e8`](https://github.com/Eoxia/reedcrm/commit/12b2f0e8)
* [#1136] [Propal] feat: tag the not billed proposals 4 times a day with a scheduled job [`26a88108`](https://github.com/Eoxia/reedcrm/commit/26a88108)
* [#1134] [Header] feat: thème partagé sur les pages en llxHeader [`73705991`](https://github.com/Eoxia/reedcrm/commit/73705991)
* [#1134] [SCSS] feat: thème moderne des écrans ReedCRM [`a734734c`](https://github.com/Eoxia/reedcrm/commit/a734734c)
* [#1134] [SCSS] rework: blocs style des listes de facturation déplacés en SCSS [`ca61c1af`](https://github.com/Eoxia/reedcrm/commit/ca61c1af)
* [#1132] [SEPA] feat: SEPA mandate check page [`6661a032`](https://github.com/Eoxia/reedcrm/commit/6661a032)
* [#1130] [EventPro] feat: duration beside the date of the quick event, setting its end and logged on the project [`8a7c39af`](https://github.com/Eoxia/reedcrm/commit/8a7c39af)
* [#1130] [Project] feat: quick time block, total recap and monthly table of the time spent on the project [`03de3d13`](https://github.com/Eoxia/reedcrm/commit/03de3d13)
* [#1128] [Todo] fix: hide the board's own scrollbar, keep the one following the screen [`41269ce1`](https://github.com/Eoxia/reedcrm/commit/41269ce1)
* [#1126] [Admin] feat: option to write the description from the voice note [`ca2071e5`](https://github.com/Eoxia/reedcrm/commit/ca2071e5)
* [#1126] [QuickCreation] feat: description written by the AI from the voice note [`ee36f256`](https://github.com/Eoxia/reedcrm/commit/ee36f256)
* [#1119] [Project] feat: lifecycle timeline of the opportunities in the list, sorted and filtered on the step reached [`c8bae7fb`](https://github.com/Eoxia/reedcrm/commit/c8bae7fb)
* [#1075] [CSRF] fix: ajouter le token CSRF aux liens et appels AJAX [`012a1802`](https://github.com/Eoxia/reedcrm/commit/012a1802)
* [#1123] [Todo] feat: filter on the probability of the opportunity, read like the numeric fields of Dolibarr [`f65d83f2`](https://github.com/Eoxia/reedcrm/commit/f65d83f2)
* [#1121] [Todo] feat: sort a column on the probability of the opportunity [`012ec60a`](https://github.com/Eoxia/reedcrm/commit/012ec60a)
* [#1117] [Menu] fix: keep the section bands and pictos readable in the minimized menu [`41aeddc3`](https://github.com/Eoxia/reedcrm/commit/41aeddc3)
* [#1115] [Todo] feat: budget, probability and relaunch count next to the project of the cards [`ee646344`](https://github.com/Eoxia/reedcrm/commit/ee646344)
* [#1113] [Project] style: photo and vocal of the opportunity list sized and aligned like the contact block [`44d958a4`](https://github.com/Eoxia/reedcrm/commit/44d958a4)
* [#1113] [Project] fix: legacy title script laid the vocal column out inline [`3968e074`](https://github.com/Eoxia/reedcrm/commit/3968e074)
* [#1111] [Import] feat: create reminders without text or date, the date of the file becomes the due date [`105f4fc0`](https://github.com/Eoxia/reedcrm/commit/105f4fc0)
* [#1109] [Project] fix: give the photo and vocal columns a type, as CommonObject::$fields requires [`c3fa9552`](https://github.com/Eoxia/reedcrm/commit/c3fa9552)
* [#1109] [JS] fix: vocal player bound twice, a click paused what it had just started [`1a8c59e1`](https://github.com/Eoxia/reedcrm/commit/1a8c59e1)
* [#1109] [Project] feat: photo and vocal columns on the opportunity list [`a5473318`](https://github.com/Eoxia/reedcrm/commit/a5473318)
* [#1107] [Import] feat: opportunity probability from a note column of the CSV [`18dd19f5`](https://github.com/Eoxia/reedcrm/commit/18dd19f5)
* [#1105] [Import] feat: create todo reminders from a text column and a date column of the CSV [`875a5c01`](https://github.com/Eoxia/reedcrm/commit/875a5c01)
* [#1103] [Carte] fix: no fatal error on map and address card without a known type [`3f840278`](https://github.com/Eoxia/reedcrm/commit/3f840278)
* [#1101] [Todo] fix: skip the query of an empty column [`d2406c47`](https://github.com/Eoxia/reedcrm/commit/d2406c47)
* [#1099] [Pilotage] fix: count working days on GMT dates [`4091ee81`](https://github.com/Eoxia/reedcrm/commit/4091ee81)
* [#1097] [Todo] fix: say why a relaunch card goes back instead of a silent refusal [`9304e38f`](https://github.com/Eoxia/reedcrm/commit/9304e38f)
* [#1095] [Pilotage] feat: sales and direction dashboards with goals, person and period filters [`cee63159`](https://github.com/Eoxia/reedcrm/commit/cee63159)
* [#1093] [Import] feat: assign users to imported opportunities from a CSV column [`7182be6f`](https://github.com/Eoxia/reedcrm/commit/7182be6f)
* [#1091] [Address] fix: prefix Address class to avoid collision with infraspackplus [`2be8dc6a`](https://github.com/Eoxia/reedcrm/commit/2be8dc6a)
* [#1089] [PWA] fix: imprimer l'en-tete des pages d'erreur de l'App [`47d7cb7d`](https://github.com/Eoxia/reedcrm/commit/47d7cb7d)
* [#790] [PWA] fix: the search of the opportunity list typed backwards on a phone, its field had no room left for the text [`36bb2083`](https://github.com/Eoxia/reedcrm/commit/36bb2083)
* [#790] [Project] fix: keep what was typed in the project creation form when a thirdparty is created with its + [`1190fda4`](https://github.com/Eoxia/reedcrm/commit/1190fda4)
* [#1078] [Menu] fix: remove the duplicate mobile quick creation entry [`647d4c0f`](https://github.com/Eoxia/reedcrm/commit/647d4c0f)
* [#790] [Project] fix: delete the time spent on the tasks of a confirmed project deletion, which Dolibarr refused without a message [`06d81643`](https://github.com/Eoxia/reedcrm/commit/06d81643)
* [#790] [CallList] fix: keep the last sort of the call lists list (needs Saturne remember_sort) [`32970802`](https://github.com/Eoxia/reedcrm/commit/32970802)
* [#790] [PWA] fix: the sales representative field of the opportunity quick creation shrank to a square on a phone, the tags opened misaligned [`095a9820`](https://github.com/Eoxia/reedcrm/commit/095a9820)
* [#790] [PWA] fix: link the opportunity created on mobile to its commercial task, a second one was created later [`b0815260`](https://github.com/Eoxia/reedcrm/commit/b0815260)
* [#790] [Project] fix: keep the refusal reason badges on the refusal reason lists [`310b9c43`](https://github.com/Eoxia/reedcrm/commit/310b9c43)
* [#790] [EventPro] fix: a ticked reminder is created even without a title, with its own type, and the event status can be chosen [`1d22108b`](https://github.com/Eoxia/reedcrm/commit/1d22108b)
* [#869] [Project] fix: the opportunity probability follows the chosen status, even downwards [`735bce63`](https://github.com/Eoxia/reedcrm/commit/735bce63)
* [#1076] [Lifecycle] feat: lifecycle timeline in the banner of the proposal, order, invoice and project cards [`648dabc0`](https://github.com/Eoxia/reedcrm/commit/648dabc0)
* [#1071] [QuickCreation] fix: restore the AI photo wand and read only the quick creation photos [`7d8a15fd`](https://github.com/Eoxia/reedcrm/commit/7d8a15fd)
* [#1069] [Contract] feat: not billed red banner on the contract card [`35058266`](https://github.com/Eoxia/reedcrm/commit/35058266)
* [#1067] [PWA] fix: let ActionComm assign the reminder to its owner [`8fe97e01`](https://github.com/Eoxia/reedcrm/commit/8fe97e01)
* [#1067] [PWA] feat: remind me later from the opportunity quick creation [`29731e0a`](https://github.com/Eoxia/reedcrm/commit/29731e0a)
* [#914] [PWA] feat: late next event date on the call cards and bigger back button [`2195fe5c`](https://github.com/Eoxia/reedcrm/commit/2195fe5c)
* [#919] [Keyyo] fix: one call event per incoming call [`24ce7600`](https://github.com/Eoxia/reedcrm/commit/24ce7600)
* [#995] [EventPro] feat: project creation button next to the project selects [`b519861b`](https://github.com/Eoxia/reedcrm/commit/b519861b)
* [#1052] [PWA] feat: call list badge, default call list and todo indicators on the call cards [`e5f6b380`](https://github.com/Eoxia/reedcrm/commit/e5f6b380)
* [#1061] [Event] feat: change the progress of an event from the quick close modal [`6bbe3194`](https://github.com/Eoxia/reedcrm/commit/6bbe3194)
* [#1059] [Todo] feat: show the lines of the proposal on hovering its reference [`6643fd32`](https://github.com/Eoxia/reedcrm/commit/6643fd32)
* [#1057] [Propal] feat: not billed tag with a sync button and a red banner on the card [`6378eecc`](https://github.com/Eoxia/reedcrm/commit/6378eecc)
* [#1053] [JS] rework: lire l'URL du module au lieu de la deviner autour de /custom/ [`b49e5e61`](https://github.com/Eoxia/reedcrm/commit/b49e5e61)
* [#1053] [Module] rework: ne plus reprefixer $cardProUrl par DOL_URL_ROOT [`c0e1cf72`](https://github.com/Eoxia/reedcrm/commit/c0e1cf72)
* [#1053] [Module] rework: ne plus ecrire /custom/ en dur dans les chemins PHP [`fef4dff0`](https://github.com/Eoxia/reedcrm/commit/fef4dff0)
* [#1049] [Mod] fix: renommer le changelog en ChangeLog.md [`e6c9bc1d`](https://github.com/Eoxia/reedcrm/commit/e6c9bc1d)
* [#1047] [CardPro] fix: filter the all proposals link on the third party of the card [`c932102c`](https://github.com/Eoxia/reedcrm/commit/c932102c)
* [#468] [Dashboard] feat: move the new third party button to the first column of the latest opportunities list [`80f4572a`](https://github.com/Eoxia/reedcrm/commit/80f4572a)
* [#962] [Tooltip] fix: keep the project tooltip layout off the tooltips of other objects [`111c708b`](https://github.com/Eoxia/reedcrm/commit/111c708b)
* [#918] [CallList] fix: store the translation key of the call list PDF model instead of its translation [`b80101ab`](https://github.com/Eoxia/reedcrm/commit/b80101ab)
* [#916] [Todo] fix: translate the relaunch events raised in English and repair the ones saved with a raw key [`6a19034c`](https://github.com/Eoxia/reedcrm/commit/6a19034c)
* [#918] [CallList] fix: translate the label of the call list PDF model [`cb5b797a`](https://github.com/Eoxia/reedcrm/commit/cb5b797a)
* [#1040] [Todo] chore: rebuild min assets [`e669a5f3`](https://github.com/Eoxia/reedcrm/commit/e669a5f3)
* [#1040] [Todo] feat: HT amount and reference date on proposal and invoice cards [`2e6b2c2c`](https://github.com/Eoxia/reedcrm/commit/2e6b2c2c)
* [#1038] [Opportunity] feat: monthly opportunity stats page with evolution badge [`81de229a`](https://github.com/Eoxia/reedcrm/commit/81de229a)
* [#1038] [Menu] fix: keep left menu positions under the saturne admin block [`444c3720`](https://github.com/Eoxia/reedcrm/commit/444c3720)
* [#1035] [CI] rework: elaguer les entrees mortes de la baseline [`77421c22`](https://github.com/Eoxia/reedcrm/commit/77421c22)
* [#1032] [CI] rework: scanner le socle par dossier plutot que l exclure par morceaux [`3e6dd7c0`](https://github.com/Eoxia/reedcrm/commit/3e6dd7c0)
* fix(#1028): update menu positions to use module numero [`58352735`](https://github.com/Eoxia/reedcrm/commit/58352735)
* [#1026] [CI] fix: exclure les bouchons phan de saturne de l'analyse [`4b0a65b9`](https://github.com/Eoxia/reedcrm/commit/4b0a65b9)
* [#1024] [CI] fix: sortir les stubs de test de saturne du perimetre scanne [`1327f077`](https://github.com/Eoxia/reedcrm/commit/1327f077)
* [#1022] [CI] fix: declarer le type de la version du trigger [`3033e295`](https://github.com/Eoxia/reedcrm/commit/3033e295)
* [#1019] [CI] rework: aligner phpstan.neon sur le gabarit commun [`4b2380da`](https://github.com/Eoxia/reedcrm/commit/4b2380da)
* [#981] [Pocket] feat: "I just hung up" chip on the cards to attach the last recording [`3fc34679`](https://github.com/Eoxia/reedcrm/commit/3fc34679)
* [#942] [Todo] fix: a planned relaunch leaves its backlog for the to-do column [`ace28a6b`](https://github.com/Eoxia/reedcrm/commit/ace28a6b)
* [#920] [Agenda] fix: write the labels saved by the crons and the webhook in the language of their owner [`cabf291b`](https://github.com/Eoxia/reedcrm/commit/cabf291b)
* [#920] [Lang] fix: fill the missing keys and put the fr_FR and en_US files at parity [`cfd5b426`](https://github.com/Eoxia/reedcrm/commit/cfd5b426)
* [#909] [Todo] rework: speed up the board reads and counters [`f8fab759`](https://github.com/Eoxia/reedcrm/commit/f8fab759)
* [#601] [EventPro] fix: use dictionary refusal reason as closure event title [`bc60c532`](https://github.com/Eoxia/reedcrm/commit/bc60c532)
