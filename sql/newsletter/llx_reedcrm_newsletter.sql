-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_reedcrm_newsletter(
    rowid             integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    ref               varchar(128) DEFAULT '(PROV)' NOT NULL,
    ref_ext           varchar(128),
    entity            integer DEFAULT 1 NOT NULL,
    date_creation     datetime NOT NULL,
    tms               timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    import_key        varchar(14),
    status            smallint DEFAULT 0 NOT NULL,
    label             varchar(255) NOT NULL,
    mail_type         varchar(16) DEFAULT 'newsletter' NOT NULL,
    target_type       varchar(16) DEFAULT 'contact' NOT NULL,
    target_tags       varchar(255),
    exclude_tags      varchar(255),
    thirdparty_type   varchar(16) DEFAULT '' NOT NULL,
    fk_sales_rep      integer,
    content_mode      varchar(16) DEFAULT 'html' NOT NULL,
    fk_email_template integer,
    email_from        varchar(255),
    subject           varchar(255),
    body_html         mediumtext,
    body_blocks       mediumtext,
    date_send         datetime,
    send_trigger      varchar(16) DEFAULT 'date' NOT NULL,
    send_delay        integer DEFAULT 30 NOT NULL,
    send_delay_unit   varchar(1) DEFAULT 'd' NOT NULL,
    frequency         integer DEFAULT 0 NOT NULL,
    frequency_unit    varchar(1) DEFAULT 'm' NOT NULL,
    auto_next         smallint DEFAULT 0 NOT NULL,
    fk_previous       integer,
    date_last_send    datetime,
    nb_sent           integer DEFAULT 0 NOT NULL,
    nb_errors         integer DEFAULT 0 NOT NULL,
    note_private      text,
    fk_user_creat     integer NOT NULL,
    fk_user_modif     integer
) ENGINE=innodb;
