-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

-- One line per mail of a newsletter: occurrence = planned date of the sending it belongs to (a recurring
-- reminder has one per period), dedupe_key = md5 of the recipient, so that a recipient is mailed once per
-- occurrence even when the sending is split over several runs or two runs overlap
CREATE TABLE llx_reedcrm_newsletter_send(
    rowid         integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity        integer DEFAULT 1 NOT NULL,
    fk_newsletter integer NOT NULL,
    occurrence    datetime NOT NULL,
    dedupe_key    varchar(32) NOT NULL,
    date_send     datetime,
    email         varchar(255) NOT NULL,
    fk_contact    integer,
    fk_project    integer,
    fk_soc        integer,
    status        smallint DEFAULT 0 NOT NULL,
    error_msg     varchar(255),
    -- Set when the recipient unsubscribed through the link of this mail: the unsubscribes of each edition
    date_unsubscribe datetime
) ENGINE=innodb;
