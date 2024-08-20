-- Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
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

-- 1.1.0

INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(1, 0, 'Received', 'Received', '', 'propal', 1, 1);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(2, 0, 'Sent', 'Sent', '', 'propal', 1, 10);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(3, 0, 'OnHold', 'OnHold', '', 'propal', 1, 20);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(4, 0, 'Reminder1', 'Reminder1', '', 'propal', 1, 30);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(5, 0, 'Reminder2', 'Reminder2', '', 'propal', 1, 40);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(6, 0, 'Reminder3', 'Reminder3', '', 'propal', 1, 50);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(7, 0, 'Abandoned', 'Abandoned', '', 'propal', 1, 60);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(8, 0, 'Unreachable', 'Unreachable', '', 'propal', 1, 70);
INSERT INTO `llx_c_commercial_status` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(9, 0, 'ToCallBack', 'ToCallBack', '', 'propal', 1, 80);

INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(1, 0, 'TooHard', 'TooHard', '', 'propal', 1, 1);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(2, 0, 'Interface', 'Interface', '', 'propal', 1, 10);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(3, 0, 'TooCheap', 'TooCheap', '', 'propal', 1, 20);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(4, 0, 'TooExpensive', 'TooExpensive', '', 'propal', 1, 30);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(5, 0, 'ToSignElsewhere', 'ToSignElsewhere', '', 'propal', 1, 40);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(6, 0, 'NotAProjectAnymore', 'NotAProjectAnymore', '', 'propal', 1, 50);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(7, 0, 'GoesBackOnExcel', 'GoesBackOnExcel', '', 'propal', 1, 60);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(8, 0, 'DontWantToSay', 'DontWantToSay', '', 'propal', 1, 70);
INSERT INTO `llx_c_refusal_reason` (`rowid`, `entity`, `ref`, `label`, `description`, `element_type`, `active`, `position`) VALUES(9, 0, 'Other', 'Other', '', 'propal', 1, 80);

INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (1, 0, 'Workplace', 'Workplace', '', 1, 1);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (2, 0, 'Home', 'Home', '', 1, 10);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (3, 0, 'PrincipalResidence', 'PrincipalResidence', '', 1, 20);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (4, 0, 'SecondaryResidence', 'SecondaryResidence', '', 1, 30);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (5, 0, 'Office', 'Office', '', 1, 40);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (6, 0, 'BranchOffice', 'BranchOffice', '', 1, 50);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (7, 0, 'WorkSite', 'WorkSite', '', 1, 60);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (8, 0, 'Factory', 'Factory', '', 1, 70);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (9, 0, 'Warehouse', 'Warehouse', '', 1, 80);
INSERT INTO llx_c_address_type (rowid, entity, ref, label, description, active, position) VALUES (10, 0, 'Headquarters', 'Headquarters', '', 1, 90);

-- 1.2.0
ALTER TABLE llx_notify_def ADD UNIQUE INDEX uk_notify_def_asct (fk_action, fk_soc, fk_contact, type);

-- 1.4.0
DELETE FROM llx_c_actioncomm WHERE code = 'AC_PHYSICAL_SALES_RELAUCH';
INSERT INTO llx_overwrite_trans (entity, lang, transkey, transvalue) VALUES (0, 'fr_FR', 'ActionAC_EMAIL_IN', 'Email entrant');
INSERT INTO llx_overwrite_trans (entity, lang, transkey, transvalue) VALUES (0, 'fr_FR', 'ActionAC_EMAIL', 'Email sortant');
INSERT INTO llx_overwrite_trans (entity, lang, transkey, transvalue) VALUES (0, 'fr_FR', 'ActionAC_RDV', 'Rendez-vous physique ou visioconférence');
