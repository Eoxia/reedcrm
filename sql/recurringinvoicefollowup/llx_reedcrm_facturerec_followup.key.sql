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

ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_rowid (rowid);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_ref (ref);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_status (status);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_fk_soc (fk_soc);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_fk_facture_rec (fk_facture_rec);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_period (period);
ALTER TABLE llx_reedcrm_facturerec_followup ADD INDEX idx_reedcrm_facturerec_followup_next_maj_du (next_maj_du);
ALTER TABLE llx_reedcrm_facturerec_followup ADD UNIQUE INDEX uk_reedcrm_facturerec_followup_ref (ref, entity);
ALTER TABLE llx_reedcrm_facturerec_followup ADD CONSTRAINT llx_reedcrm_facturerec_followup_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
