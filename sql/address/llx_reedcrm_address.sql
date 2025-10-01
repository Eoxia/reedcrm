-- Copyright (C) 2022-2025 EVARISK <technique@evarisk.com>
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

CREATE TABLE llx_reedcrm_address(
  rowid         integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  ref           varchar(128) DEFAULT '(PROV)' NOT NULL,
  ref_ext       varchar(128),
  entity        integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms           timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  import_key    varchar(14),
  status        integer NOT NULL,
  element_type  varchar(255),
  element_id    integer NOT NULL,
  name          varchar(255),
  type          varchar(255),
  fk_country    integer,
  fk_region     integer,
  fk_department integer,
  town          varchar(255),
  zip           varchar(255),
  address       varchar(255),
  latitude      double(24,8) DEFAULT 0 NOT NULL,
  longitude     double(24,8) DEFAULT 0 NOT NULL,
  osm_id        bigint(20),
  osm_category  varchar(255),
  osm_type      varchar(255),
  fk_user_creat integer NOT NULL,
  fk_user_modif integer
) ENGINE=innodb;
