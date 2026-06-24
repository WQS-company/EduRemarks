-- Migration 006: More Staff Permissions
-- Adds can_manage_academics to staff_details for feature-level access control

ALTER TABLE staff_details 
ADD COLUMN can_manage_academics TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Allow this staff member to add/edit classes, subjects, and sessions';
