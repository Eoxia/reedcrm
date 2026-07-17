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

-- Restore the em dash in the labels of the auto-created call lists.
--
-- The fr_FR DefaultCallListLabel key held a corrupted em dash: the bytes EF BF BD (U+FFFD,
-- the replacement character) followed by two literal '?'. Every call list created for a user
-- baked that sequence into llx_reedcrm_call_list.label, and it surfaced everywhere the label
-- is shown, up to the titles of the agenda events created from a call list.
--
-- Bytes are addressed through UNHEX so this file stays pure ASCII and cannot be corrupted again
-- the same way. EFBFBD3F3F = U+FFFD + '??', E28094 = the em dash the en_US key always had.
--
-- Idempotent: only rows still carrying the corrupted sequence match, so re-running the module
-- activation is harmless.

UPDATE llx_reedcrm_call_list SET label = REPLACE(label, UNHEX('EFBFBD3F3F'), UNHEX('E28094')) WHERE label LIKE CONCAT('%', UNHEX('EFBFBD3F3F'), '%');
