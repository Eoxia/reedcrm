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

-- Add the two edition flags of the Pocket mirror to the installations created before them.
--
-- llx_reedcrm_pocket_recording.summary_edited and llx_reedcrm_pocket_action_item.user_edited
-- are declared in the $fields of PocketRecording and PocketActionItem, so fetch() selects them
-- on every card: an installation whose tables were created without the columns answers with an
-- unknown column error, fetch() returns -1 and the card shows 'Record not found'. The lists,
-- which select their columns one by one, keep working and hide the drift.
--
-- Both columns hold the same meaning: 1 once the value was rewritten in Dolibarr, which freezes
-- it against the next Pocket synchronisation. The existing rows were never rewritten by hand,
-- so the default 0 is the right value for them.
--
-- Re-running the module activation is harmless: run_sql accepts DB_ERROR_COLUMN_ALREADY_EXISTS,
-- and DB_ERROR_NOSUCHTABLE on a fresh install where sql/migration/ is loaded before sql/pocket/.

ALTER TABLE llx_reedcrm_pocket_recording ADD COLUMN summary_edited smallint DEFAULT 0 NOT NULL AFTER summary;
ALTER TABLE llx_reedcrm_pocket_action_item ADD COLUMN user_edited smallint DEFAULT 0 NOT NULL AFTER priority;
