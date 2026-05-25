-- Rollback SQL for destructive decoupling migration:
-- 2026_05_25_205400_drop_legacy_document_foreign_keys.php
--
-- This script re-links legacy foreign keys to the documents table
-- without deleting any rows.

ALTER TABLE weekly_plans
  ADD CONSTRAINT weekly_plans_legacy_document_id_foreign
  FOREIGN KEY (legacy_document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE weekly_reports
  ADD CONSTRAINT weekly_reports_legacy_document_id_foreign
  FOREIGN KEY (legacy_document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE monthly_reports
  ADD CONSTRAINT monthly_reports_legacy_document_id_foreign
  FOREIGN KEY (legacy_document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE action_logs
  ADD CONSTRAINT action_logs_legacy_document_id_foreign
  FOREIGN KEY (legacy_document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE weekly_plan_activities
  ADD CONSTRAINT weekly_plan_activities_carry_over_source_report_id_foreign
  FOREIGN KEY (carry_over_source_report_id) REFERENCES documents(id)
  ON DELETE SET NULL;

ALTER TABLE weekly_activity_assignments
  ADD CONSTRAINT weekly_activity_assignments_source_document_id_foreign
  FOREIGN KEY (source_document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE document_user_shares
  ADD CONSTRAINT document_user_shares_document_id_foreign
  FOREIGN KEY (document_id) REFERENCES documents(id)
  ON DELETE CASCADE;

ALTER TABLE document_share_events
  ADD CONSTRAINT document_share_events_document_id_foreign
  FOREIGN KEY (document_id) REFERENCES documents(id)
  ON DELETE CASCADE;
