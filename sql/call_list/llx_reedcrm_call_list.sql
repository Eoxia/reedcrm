-- Copyright (C) 2025 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_reedcrm_call_list(
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
