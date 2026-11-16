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

-- Position of the call lists: the order they are listed and offered in (list, selectors of the cards
-- and of the PWA), set by hand like the position of a bookmark, the label breaking the ties.
--
-- llx_reedcrm_call_list.position is declared in the $fields of CallList, so fetch() and fetchAll()
-- select it: an installation without the column fails on every call list until the module is
-- activated again. The existing lists get 0, which keeps them in the order of their label, the order
-- the users numbered them in so far ("1 - Hot", "2 - Negotiation"...).
--
-- Re-running the module activation is harmless: run_sql accepts DB_ERROR_COLUMN_ALREADY_EXISTS, the
-- table created by sql/call_list/ on a fresh install already holding the column.

ALTER TABLE llx_reedcrm_call_list ADD COLUMN position integer DEFAULT 0 NOT NULL AFTER label;
