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

CREATE TABLE llx_reedcrm_pocket_action_item(
  rowid                integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  entity               integer DEFAULT 1 NOT NULL,
  date_creation        datetime NOT NULL,
  tms                  timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key           varchar(14),
  status               smallint DEFAULT 0 NOT NULL,
  fk_pocket_recording  integer NOT NULL,
  pocket_action_id     varchar(128) NOT NULL,
  label                varchar(255),
  description          text,
  due_date             datetime,
  priority             varchar(16),
  pocket_assignee      varchar(128),
  pocket_status        varchar(32),
  fk_user_assign       integer,
  fk_actioncomm        integer,
  fk_user_creat        integer NOT NULL,
  fk_user_modif        integer
) ENGINE=innodb;
