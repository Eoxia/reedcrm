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

-- Event of a recording: each recording Pocket processed gets an in progress phone call event,
-- created by the synchronisation. llx_reedcrm_pocket_recording.fk_actioncomm remembers it, so that
-- an event deleted by its owner is not created again on the next run. The column is declared in the
-- $fields of PocketRecording, so an installation without it answers 'Record not found' on every
-- card until the module is activated again. Its index is set by the key file of the table, which
-- the activation loads after this folder.
--
-- Re-running the module activation is harmless: run_sql accepts DB_ERROR_COLUMN_ALREADY_EXISTS, and
-- DB_ERROR_NOSUCHTABLE on a fresh install where sql/migration/ is loaded before sql/pocket/.

ALTER TABLE llx_reedcrm_pocket_recording ADD COLUMN fk_actioncomm integer AFTER fk_soc;
