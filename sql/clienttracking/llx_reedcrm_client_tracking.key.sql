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

ALTER TABLE llx_reedcrm_client_tracking ADD INDEX idx_reedcrm_client_tracking_rowid (rowid);
ALTER TABLE llx_reedcrm_client_tracking ADD INDEX idx_reedcrm_client_tracking_fk_soc (fk_soc);
ALTER TABLE llx_reedcrm_client_tracking ADD INDEX idx_reedcrm_client_tracking_date_planned (date_planned);
ALTER TABLE llx_reedcrm_client_tracking ADD INDEX idx_reedcrm_client_tracking_type (type);
ALTER TABLE llx_reedcrm_client_tracking ADD UNIQUE INDEX uk_reedcrm_client_tracking_ref (ref, entity);
