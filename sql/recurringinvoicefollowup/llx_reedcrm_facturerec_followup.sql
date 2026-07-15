-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_reedcrm_facturerec_followup(
  rowid                 integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  ref                   varchar(128) DEFAULT '(PROV)' NOT NULL,
  ref_ext               varchar(128),
  entity                integer DEFAULT 1 NOT NULL,
  date_creation         datetime NOT NULL,
  tms                   timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key            varchar(14),
  status                smallint DEFAULT 1 NOT NULL,
  fk_soc                integer NOT NULL,
  fk_facture_rec        integer,
  period                date NOT NULL,
  prestation            varchar(32),
  montant_ttc           double(24,8),
  date_derniere_facture date,
  facture_creee         integer DEFAULT 0,
  facture_envoyee       integer DEFAULT 0,
  facture_payee         integer DEFAULT 0,
  paiement_ok           integer DEFAULT 0,
  date_relance          date,
  nb_relances           integer DEFAULT 0,
  client_contacte       integer DEFAULT 0,
  date_contact          date,
  temps_sav             integer,
  digirisk_existant     integer DEFAULT 0,
  digirisk_ajour        integer DEFAULT 0,
  acces_ok              integer DEFAULT 0,
  version_dolibarr      varchar(32),
  version_digirisk      varchar(32),
  date_maj_du           date,
  next_maj_du           date,
  dernier_audit_du      date,
  besoin                varchar(255),
  proposition           varchar(32),
  reaction              text,
  montant_pr            double(24,8),
  commentaire           text,
  fk_user_creat         integer NOT NULL,
  fk_user_modif         integer
) ENGINE=innodb;
