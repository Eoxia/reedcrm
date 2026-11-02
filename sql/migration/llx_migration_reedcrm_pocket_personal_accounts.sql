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

-- Personal Pocket accounts: each user may set their own key and folder on the Pocket tab of their
-- user card, the key of the module setup becoming the shared account of the others.
--
-- llx_reedcrm_pocket_recording.fk_user_pocket remembers the account a recording was imported
-- from: Pocket only answers its detail and its audio to that account. NULL is the shared account,
-- which is where every recording imported so far comes from. The column is declared in the
-- $fields of PocketRecording, so an installation without it answers 'Record not found' on every
-- card until the module is activated again.
--
-- The synchronisation job used to be skipped by the cron manager when the shared key was empty,
-- its test is frozen in llx_cronjob at activation. A team where everyone set their own key leaves
-- the shared one empty, so the test drops the key and the job reports the accounts it walked.
--
-- Re-running the module activation is harmless: run_sql accepts DB_ERROR_COLUMN_ALREADY_EXISTS,
-- DB_ERROR_NOSUCHTABLE on a fresh install where sql/migration/ is loaded before sql/pocket/, and
-- the update only rewrites the test to the value the descriptor declares.

ALTER TABLE llx_reedcrm_pocket_recording ADD COLUMN fk_user_pocket integer AFTER pocket_folder_label;
UPDATE llx_cronjob SET test = 'isModEnabled(''saturne'') && isModEnabled(''reedcrm'')' WHERE classesname = '/reedcrm/class/pocketcron.class.php' AND methodename = 'syncPocketRecordings';
