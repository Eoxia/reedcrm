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

-- Position of the calls of a call list: the order they are called in (card of the list, PWA, PDF), set
-- by the up / down arrows of the card, a new call going to the end, the order they were added in
-- breaking the ties.
--
-- llx_reedcrm_call_list_line.position is declared in the $fields of CallListLine, so fetch() selects
-- it: an installation without the column fails on every call of a list until the module is activated
-- again. The existing calls get 0, which keeps them in the order they were added in, the only order
-- they had so far.
--
-- Re-running the module activation is harmless: run_sql accepts DB_ERROR_COLUMN_ALREADY_EXISTS, the
-- table created by sql/call_list/ on a fresh install already holding the column.

ALTER TABLE llx_reedcrm_call_list_line ADD COLUMN position integer DEFAULT 0 NOT NULL AFTER fk_contact;
