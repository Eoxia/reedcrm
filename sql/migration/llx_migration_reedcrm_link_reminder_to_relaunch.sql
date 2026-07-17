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

-- Backfill the link between a relaunch event and the reminder it generated.
--
-- The pair is created in one request by view/procard.php, so both rows share the exact same datec
-- and the same fk_project. That signature is what identifies past pairs, since nothing recorded the
-- link before. The two categories tell them apart: the relaunch carries
-- REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG, the reminder REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG.
--
-- Deliberately conservative:
--   - only reminders whose fk_parent is still unset are touched, so a link set by the module wins;
--   - the pair must be unique on both sides (one relaunch <-> one reminder for a given datec and
--     project). Anything ambiguous is left alone rather than guessed: a wrong link would be worse
--     than no link.
--   - the tag ids are read from llx_const, they differ per install.
--
-- Idempotent: re-running matches nothing once fk_parent is set.

UPDATE llx_actioncomm AS rem
INNER JOIN (
    SELECT rap.id AS reminder_id, MIN(rel.id) AS relaunch_id, COUNT(DISTINCT rel.id) AS nb_relaunch
    FROM llx_actioncomm AS rap
    INNER JOIN llx_categorie_actioncomm AS crap ON crap.fk_actioncomm = rap.id
    INNER JOIN llx_const AS kr ON kr.name = 'REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG' AND crap.fk_categorie = kr.value
    INNER JOIN llx_actioncomm AS rel ON rel.datec = rap.datec AND rel.id <> rap.id AND rel.entity = rap.entity AND (rel.fk_project <=> rap.fk_project)
    INNER JOIN llx_categorie_actioncomm AS crel ON crel.fk_actioncomm = rel.id
    INNER JOIN llx_const AS kl ON kl.name = 'REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG' AND crel.fk_categorie = kl.value
    WHERE rap.fk_parent = 0 OR rap.fk_parent IS NULL
    GROUP BY rap.id
    HAVING nb_relaunch = 1
) AS pair ON pair.reminder_id = rem.id
SET rem.fk_parent = pair.relaunch_id;
