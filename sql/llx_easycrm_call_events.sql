-- Copyright (C) 2025 EVARISK <technique@evarisk.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see https://www.gnu.org/licenses/.

CREATE TABLE IF NOT EXISTS llx_easycrm_call_events (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    fk_user int(11) NOT NULL,
    fk_contact int(11) NOT NULL,
    caller varchar(50) NOT NULL,
    callee varchar(50) NOT NULL,
    call_date datetime NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'new',
    date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    KEY idx_fk_user (fk_user),
    KEY idx_fk_contact (fk_contact),
    KEY idx_status (status),
    KEY idx_call_date (call_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
