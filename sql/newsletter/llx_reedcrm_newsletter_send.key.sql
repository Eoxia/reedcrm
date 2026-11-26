-- Copyright (C) 2026 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

-- The insert of a line claims the recipient: a second run of the same occurrence fails on it and skips him
ALTER TABLE llx_reedcrm_newsletter_send ADD UNIQUE INDEX uk_reedcrm_newsletter_send_recipient (fk_newsletter, occurrence, dedupe_key);
ALTER TABLE llx_reedcrm_newsletter_send ADD INDEX idx_reedcrm_newsletter_send_email (email);
-- The "Newsletters" tabs of the contacts and of the third parties read their mails
ALTER TABLE llx_reedcrm_newsletter_send ADD INDEX idx_reedcrm_newsletter_send_fk_contact (fk_contact);
ALTER TABLE llx_reedcrm_newsletter_send ADD INDEX idx_reedcrm_newsletter_send_fk_soc (fk_soc);
ALTER TABLE llx_reedcrm_newsletter_send ADD CONSTRAINT fk_reedcrm_newsletter_send_fk_newsletter FOREIGN KEY (fk_newsletter) REFERENCES llx_reedcrm_newsletter(rowid);
