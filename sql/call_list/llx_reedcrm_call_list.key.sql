ALTER TABLE llx_reedcrm_call_list ADD INDEX idx_reedcrm_call_list_rowid (rowid);
ALTER TABLE llx_reedcrm_call_list ADD INDEX idx_reedcrm_call_list_ref (ref);
ALTER TABLE llx_reedcrm_call_list ADD INDEX idx_reedcrm_call_list_status (status);
ALTER TABLE llx_reedcrm_call_list ADD CONSTRAINT fk_reedcrm_call_list_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
