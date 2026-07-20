-- Copyright (C) 2024 EVARISK <technique@evarisk.com>
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

-- 1.5.0
ALTER TABLE `llx_element_geolocation` ADD `gis` varchar(255) DEFAULT 'osm' AFTER `rowid`;
ALTER TABLE `llx_element_geolocation` ADD `status` integer NOT NULL AFTER `rowid`;
ALTER TABLE `llx_element_geolocation` ADD `tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `rowid`;
ALTER TABLE `llx_element_geolocation` ADD `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `rowid`;

-- Fix opporigin extrafield configuration to enable native dictionary translations on existing installations
UPDATE llx_extrafields SET param = 'c_input_reason:code:rowid' WHERE name = 'opporigin' AND elementtype = 'projet' AND param = 'c_input_reason:label:rowid';

-- Add contact types for sales representatives
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('propal',  'internal', 'SALESREPINTERNAL', 'Commercial qui a vendu la proposition', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('facture', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la facture', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('commande', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la commande', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('shipping', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''expédition', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('project', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au projet', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('societe', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au tiers', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('payment', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au paiement', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('contrat', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au contrat', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('fichinter', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''intervention', 1);
insert into llx_c_type_contact (element, source, code, libelle, active, module ) values ('ticket', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au ticket', 1, NULL);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('product', 'internal', 'SALESREPINTERNAL', 'Commercial affecté au produit', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('conferenceorbooth', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à l''événement', 1);
insert into llx_c_type_contact (element, source, code, libelle, active ) values ('project_task', 'internal', 'SALESREPINTERNAL', 'Commercial affecté à la tâche', 1);

-- v23.0.0 CallList
CREATE TABLE IF NOT EXISTS llx_reedcrm_call_list(
    rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    ref            varchar(128) DEFAULT '(PROV)' NOT NULL,
    ref_ext        varchar(128),
    entity         integer DEFAULT 1 NOT NULL,
    date_creation  datetime NOT NULL,
    tms            timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    import_key     varchar(14),
    status         smallint DEFAULT 0 NOT NULL,
    label          varchar(255) NOT NULL,
    note_public    text,
    note_private   text,
    fk_user_assign integer,
    date_start     datetime,
    date_end       datetime,
    fk_user_creat  integer NOT NULL,
    fk_user_modif  integer
) ENGINE=innodb;

CREATE TABLE IF NOT EXISTS llx_reedcrm_call_list_line(
    rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity         integer DEFAULT 1 NOT NULL,
    date_creation  datetime NOT NULL,
    tms            timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_call_list   integer NOT NULL,
    element_type   varchar(255) NOT NULL,
    element_id     integer NOT NULL,
    fk_contact     integer,
    status         smallint DEFAULT 0 NOT NULL,
    note           text,
    fk_user_creat  integer NOT NULL,
    fk_user_modif  integer
) ENGINE=innodb;
-- 23.0.1 - Recurring invoice & DU audit follow-up: proposal link, assignee, real audit date
ALTER TABLE `llx_reedcrm_du_audit` ADD `proposal_sent_date` date DEFAULT NULL AFTER `source`;
ALTER TABLE `llx_reedcrm_du_audit` ADD `fk_propal` integer DEFAULT NULL AFTER `proposal_sent_date`;
ALTER TABLE `llx_reedcrm_du_audit` ADD `fk_user_assign` integer DEFAULT NULL AFTER `fk_propal`;
ALTER TABLE `llx_reedcrm_du_audit` ADD `date_done` date DEFAULT NULL AFTER `next_audit_date`;
