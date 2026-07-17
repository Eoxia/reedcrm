# Design : Liste d'appel (CallList) — ReedCRM

**Date :** 2026-05-27  
**Statut :** Approuvé  

---

## Contexte

ReedCRM a besoin d'un nouvel objet autonome "Liste d'appel" permettant de constituer une liste de contacts à appeler, enrichie depuis des devis ou opportunités existants. Cet objet remplace la "Liste des opportunités importées" dans le menu sous Opportunités.

---

## Architecture

### Pattern

Suit exactement le pattern Saturne/ReedCRM existant (identique à `Address`) :
- Deux classes PHP étendant `SaturneObject`
- Deux tables SQL dédiées
- Vue card + vue liste
- Module PDF avec classe abstraite
- Permissions déclarées dans `modReedCRM.class.php`

---

## Modèle de données

### Table `llx_reedcrm_call_list`

| Champ | Type | Description |
|---|---|---|
| `rowid` | integer AUTO_INCREMENT PK | ID technique |
| `ref` | varchar(128) | Référence (ex. `LA-0001`) |
| `ref_ext` | varchar(128) | Référence externe |
| `entity` | integer | Entité multicompany |
| `date_creation` | datetime | Date de création |
| `tms` | timestamp | Date de modification |
| `import_key` | varchar(14) | Clé d'import |
| `status` | smallint | `-1` supprimé, `0` brouillon, `1` actif, `2` archivé |
| `label` | varchar(255) | Titre de la liste d'appel |
| `note_public` | text | Note publique |
| `note_private` | text | Note privée |
| `fk_user_assign` | integer | Responsable (FK llx_user) |
| `date_start` | datetime | Date de début |
| `date_end` | datetime | Date de fin |
| `fk_user_creat` | integer | Créateur |
| `fk_user_modif` | integer | Dernier modificateur |

### Table `llx_reedcrm_call_list_line`

| Champ | Type | Description |
|---|---|---|
| `rowid` | integer AUTO_INCREMENT PK | ID technique |
| `ref` | varchar(128) | Référence ligne |
| `entity` | integer | Entité multicompany |
| `date_creation` | datetime | Date de création |
| `tms` | timestamp | Date de modification |
| `fk_call_list` | integer | FK vers `llx_reedcrm_call_list` |
| `element_type` | varchar(255) | `'propal'` ou `'project'` |
| `element_id` | integer | ID du devis ou de l'opportunité |
| `fk_contact` | integer | Contact principal (résolu à l'ajout, FK llx_socpeople) |
| `status` | smallint | `0` à appeler, `1` appelé, `2` pas de réponse, `3` à rappeler |
| `note` | text | Note libre sur la ligne |
| `fk_user_creat` | integer | Créateur |
| `fk_user_modif` | integer | Dernier modificateur |

---

## Classes PHP

### `class/calllist.class.php`
- Étend `SaturneObject`
- `$module = 'reedcrm'`
- `$element = 'call_list'`
- `$table_element = 'reedcrm_call_list'`
- Statuts : `STATUS_DELETED = -1`, `STATUS_DRAFT = 0`, `STATUS_ACTIVE = 1`, `STATUS_ARCHIVED = 2`
- Champs mappés depuis la table ci-dessus

### `class/calllistline.class.php`
- Étend `SaturneObject`
- `$module = 'reedcrm'`
- `$element = 'call_list_line'`
- `$table_element = 'reedcrm_call_list_line'`
- Statuts : `STATUS_TO_CALL = 0`, `STATUS_CALLED = 1`, `STATUS_NO_ANSWER = 2`, `STATUS_CALLBACK = 3`

---

## Fichiers à créer

```
htdocs/custom/reedcrm/
├── class/
│   ├── calllist.class.php
│   └── calllistline.class.php
├── sql/
│   ├── call_list/
│   │   ├── llx_reedcrm_call_list.sql
│   │   └── llx_reedcrm_call_list.key.sql
│   └── call_list_line/
│       ├── llx_reedcrm_call_list_line.sql
│       └── llx_reedcrm_call_list_line.key.sql
├── view/
│   ├── call_list_card.php
│   └── call_list_list.php
└── core/
    └── modules/
        └── reedcrm/
            └── call_list/
                ├── modules_calllist.php          (classe abstraite ModelePDFCallList)
                ├── mod_call_list_standard.php    (module de numérotation)
                └── doc/
                    └── pdf_calllist_standard.modules.php
```

---

## Layout de la card (`view/call_list_card.php`)

Structure de haut en bas :

1. **Bandeau titre** — ref (`LA-0001`), label, badge statut, boutons Modifier / Valider / Archiver / Supprimer
2. **Bloc principal** — responsable (`fk_user_assign`), date début, date fin
3. **Notes** — note publique + note privée (éditeur DolEditor standard)
4. **Tableau liste d'appel**
   - Formulaire d'ajout : sélecteur `element_type` (devis ou opportunité) + sélecteur `element_id` → résolution automatique du contact principal → pré-remplissage Nom/Prénom/Téléphone
   - Colonnes du tableau : Nom, Prénom, Téléphone, Source (lien vers devis/opportunité), Statut (dropdown modifiable en ligne), Note, Supprimer
5. **Documents PDF** — bouton de génération + liste des PDFs existants via `saturne_show_documents`
6. **Actions comm** — liste des événements liés via le pattern `ActionComm` de Dolibarr

---

## Module PDF (`pdf_calllist_standard.modules.php`)

Contenu du PDF :
- **En-tête** : ref, label, dates début/fin, responsable
- **Tableau** : Nom | Prénom | Téléphone | Source | Statut
- Utilise TCPDF via les helpers Dolibarr standard (`pdf_getInstance`, `pdf_pagefoot`, etc.)

---

## Menu & Navigation

### Remplacement dans `modReedCRM.class.php`
- Supprimer l'entrée menu `'leftmenu' => 'importedopportunities'` (lien vers `reedcrm_imported_projects.php`)
- Ajouter une entrée `'leftmenu' => 'call_list'` → `view/call_list_list.php` sous `fk_mainmenu=reedcrm,fk_leftmenu=opportunities`

### Permissions (dans `modReedCRM.class.php`)
Trois entrées ajoutées à la suite des permissions existantes (`address`, `eventpro`) :
- `call_list` / `read`
- `call_list` / `write`
- `call_list` / `delete`

### Constantes
- `REEDCRM_CALL_LIST_ADDON` — module de numérotation (défaut : `mod_call_list_standard`)
- `REEDCRM_CALL_LIST_GENERATE_DOCUMENTS_ADDON` — modèle PDF par défaut (`pdf_calllist_standard`)

---

## Résolution du contact principal

Lors de l'ajout d'une ligne :
1. L'utilisateur sélectionne `element_type` = `'propal'` ou `'project'`
2. L'utilisateur sélectionne l'objet via un select AJAX
3. Le système résout le contact principal :
   - Pour un devis (`Propal`) : premier contact lié via `llx_element_contact` avec `source = 'external'`
   - Pour une opportunité (`Project`) : premier contact lié via `llx_element_contact`
4. `fk_contact`, nom, prénom, téléphone sont pré-remplis et enregistrés dans la ligne

---

## Contraintes & Points d'attention

- Suivre les conventions de nommage Saturne/Evarisk (camelCase pour les méthodes, snake_case pour les champs SQL)
- Pas de JS/CSS inline — JS dans `.js`, CSS dans `.scss`
- Pas de margin intrinsèque sur les composants réutilisables (espacement au parent)
- Utiliser `saturne_load_langs()` pour les traductions
- Ajouter les clés de traduction dans `langs/fr_FR/reedcrm.lang`
