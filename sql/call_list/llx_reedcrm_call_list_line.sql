-- Copyright (C) 2025 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_reedcrm_call_list_line(
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
