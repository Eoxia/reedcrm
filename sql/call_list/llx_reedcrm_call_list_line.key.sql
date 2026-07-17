ALTER TABLE llx_reedcrm_call_list_line ADD INDEX idx_reedcrm_call_list_line_rowid (rowid);
ALTER TABLE llx_reedcrm_call_list_line ADD INDEX idx_reedcrm_call_list_line_fk_call_list (fk_call_list);
ALTER TABLE llx_reedcrm_call_list_line ADD CONSTRAINT fk_reedcrm_call_list_line_fk_call_list FOREIGN KEY (fk_call_list) REFERENCES llx_reedcrm_call_list(rowid);
