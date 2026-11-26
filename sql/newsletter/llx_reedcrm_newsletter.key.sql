-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_reedcrm_newsletter ADD INDEX idx_reedcrm_newsletter_rowid (rowid);
ALTER TABLE llx_reedcrm_newsletter ADD INDEX idx_reedcrm_newsletter_ref (ref);
-- The cron looks for the scheduled newsletters whose date has come
ALTER TABLE llx_reedcrm_newsletter ADD INDEX idx_reedcrm_newsletter_status_date (entity, status, date_send);
-- "Prepare next month" looks for the edition made from a newsletter before making another one
ALTER TABLE llx_reedcrm_newsletter ADD INDEX idx_reedcrm_newsletter_fk_previous (fk_previous);
ALTER TABLE llx_reedcrm_newsletter ADD CONSTRAINT fk_reedcrm_newsletter_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
