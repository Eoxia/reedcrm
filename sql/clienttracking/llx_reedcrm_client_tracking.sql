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

CREATE TABLE llx_reedcrm_client_tracking(
  rowid                integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  ref                  varchar(128) DEFAULT '(PROV)' NOT NULL,
  entity               integer DEFAULT 1 NOT NULL,
  date_creation        datetime NOT NULL,
  tms                  timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status               smallint DEFAULT 0 NOT NULL,
  type                 varchar(24) DEFAULT 'assistance' NOT NULL,
  fk_soc               integer NOT NULL,
  date_planned         date NOT NULL,
  date_rdv             date DEFAULT NULL,
  date_done            date DEFAULT NULL,
  label                text,
  montant              double(24,8) DEFAULT NULL,
  fk_user_assign       integer DEFAULT NULL,
  fk_propal            integer DEFAULT NULL,
  fk_facture           integer DEFAULT NULL,
  fk_intervention_date integer DEFAULT NULL,
  fk_user_creat        integer NOT NULL,
  fk_user_modif        integer
) ENGINE=innodb;
