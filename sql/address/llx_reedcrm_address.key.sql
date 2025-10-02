-- Copyright (C) 2022-2025 EVARISK <technique@evarisk.com>
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

ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_rowid (rowid);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_ref (ref);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_status (status);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_element_id (element_id);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_fk_country (fk_country);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_fk_region (fk_region);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_fk_department (fk_department);
ALTER TABLE llx_reedcrm_address ADD INDEX idx_reedcrm_address_osm_id (osm_id);
ALTER TABLE llx_reedcrm_address ADD UNIQUE INDEX uk_reedcrm_address_ref (ref, entity);
ALTER TABLE llx_reedcrm_address ADD CONSTRAINT llx_reedcrm_address_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
