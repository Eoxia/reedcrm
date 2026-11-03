-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_reedcrm_intervention_date ADD INDEX idx_reedcrm_intervention_date_rowid (rowid);
ALTER TABLE llx_reedcrm_intervention_date ADD INDEX idx_reedcrm_intervention_date_element (element_type, element_id);
ALTER TABLE llx_reedcrm_intervention_date ADD INDEX idx_reedcrm_intervention_date_date (date_intervention);
ALTER TABLE llx_reedcrm_intervention_date ADD INDEX idx_reedcrm_intervention_date_fk_user_intervenant (fk_user_intervenant);
ALTER TABLE llx_reedcrm_intervention_date ADD UNIQUE INDEX uk_reedcrm_intervention_date_line_position (element_type, fk_element_line, position);
