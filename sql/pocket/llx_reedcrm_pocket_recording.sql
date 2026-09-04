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

CREATE TABLE llx_reedcrm_pocket_recording(
  rowid               integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  ref                 varchar(128) DEFAULT '(PROV)' NOT NULL,
  ref_ext             varchar(128),
  entity              integer DEFAULT 1 NOT NULL,
  date_creation       datetime NOT NULL,
  tms                 timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key          varchar(14),
  status              smallint DEFAULT 0 NOT NULL,
  label               varchar(255),
  pocket_id           varchar(64) NOT NULL,
  pocket_folder_id    varchar(64),
  pocket_folder_label varchar(255),
  pocket_state        varchar(32),
  pocket_tags         varchar(255),
  language            varchar(8),
  duration            integer DEFAULT 0,
  recording_date      datetime,
  last_sync_date      datetime,
  summary             mediumtext,
  transcript          longtext,
  action_items        mediumtext,
  note_public         text,
  note_private        text,
  fk_soc              integer,
  fk_user_creat       integer NOT NULL,
  fk_user_modif       integer
) ENGINE=innodb;
